<?php
// Logging untuk debugging
error_log("sarana.php called with method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];

// Logging method dan data
error_log("Method: $method");
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    error_log("Raw input: " . $rawInput);
    $data = json_decode($rawInput, true);
    error_log("Parsed data: " . json_encode($data));
}

// Helper: cek tabel ada
function table_exists(PDO $pdo, string $name): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) { 
        error_log("Error checking table existence: " . $e->getMessage());
        return false; 
    }
}
$hasColumn = function(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) { 
        error_log("Error checking column existence: " . $e->getMessage());
        return false; 
    }
};
$jenisAvail = table_exists($pdo, 'jenis_sarana') && table_exists($pdo, 'sarana_jenis');

// Pastikan tabel utama tersedia agar tidak fatal error saat query
if (!table_exists($pdo, 'data_sarana')) {
    respond([
        'error' => "Tabel 'data_sarana' belum tersedia",
        'hint'  => "Import sql/schema_core.sql di database Anda, lalu coba lagi.",
    ], 500);
}

// Logging method request
error_log("API sarana.php - Request method: $method");

if ($method === 'GET') {
    // ===== Filters umum =====
    $q     = $_GET['q'] ?? null;
    $kab   = $_GET['kabupaten'] ?? null;
    $kec   = $_GET['kecamatan'] ?? null;
    $kel   = $_GET['kelurahan'] ?? null;
    $jenis = $_GET['jenis'] ?? null; // comma-separated (id atau nama)
    $bbox  = $_GET['bbox'] ?? null;  // minLng,minLat,maxLng,maxLat

    // Pastikan parameter q tidak kosong
    if ($q !== null && trim($q) === '') {
        $q = null;
    }

    // ===== Mode paginasi (admin) =====
    $page     = intval($_GET['page'] ?? 0);           // jika >0 -> {data,total,...}
    $perPage  = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $offset   = max(0, ($page > 0 ? ($page - 1) * $perPage : 0));

    // ===== Mode non-paginasi (peta) =====
    $limit    = max(1, min(50000, intval($_GET['limit'] ?? 5000)));

    // ----- Parse jenis jadi 2 set: ids & names -----
    $jenisIds = [];
    $jenisNames = [];
    if ($jenis) {
        foreach (array_map('trim', explode(',', $jenis)) as $t) {
            if ($t === '') continue;
            if (ctype_digit($t)) $jenisIds[] = intval($t);
            else $jenisNames[] = $t;
        }
    }

    // ----- SQL base (dipakai select & count) -----
    // Agar kompatibel dengan ONLY_FULL_GROUP_BY, agregasi jenis dipisah dalam subquery.
    $base = " FROM data_sarana s ";
    $aggJoin = '';
    if ($jenisAvail) {
        $aggJoin = " LEFT JOIN (\n            SELECT sj.sarana_id,\n                   GROUP_CONCAT(DISTINCT j.nama_jenis ORDER BY j.nama_jenis SEPARATOR '|') AS jenis_list,\n                   GROUP_CONCAT(DISTINCT j.id ORDER BY j.nama_jenis SEPARATOR ',') AS jenis_ids\n            FROM sarana_jenis sj\n            JOIN jenis_sarana j ON j.id = sj.jenis_id\n            GROUP BY sj.sarana_id\n        ) gj ON gj.sarana_id = s.id ";
    }
    $base .= $aggJoin . " WHERE 1=1";
    $params = [];

    if ($q) {
        // Gunakan penilaian relevansi untuk pencarian
        $base .= " AND (s.nama_sarana LIKE ? OR s.kelurahan LIKE ? OR s.kecamatan LIKE ? OR s.kabupaten LIKE ?)";
        $likeContains = "%$q%";
        array_push($params, $likeContains, $likeContains, $likeContains, $likeContains);
    }
    if ($kab) {
        $base .= " AND s.kabupaten = ?";
        $params[] = $kab;
    }
    if ($kec) {
        $base .= " AND s.kecamatan = ?";
        $params[] = $kec;
    }
    if ($kel) {
        $base .= " AND s.kelurahan = ?";
        $params[] = $kel;
    }

    // bbox utk peta
    if ($bbox) {
        $parts = explode(',', $bbox);
        if (count($parts) === 4) {
            [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parts);
            $base .= " AND s.latitude BETWEEN ? AND ? AND s.longitude BETWEEN ? AND ?";
            array_push($params, $minLat, $maxLat, $minLng, $maxLng);
        }
    }

    // filter jenis pakai EXISTS hanya jika tabel jenis tersedia
    if ($jenisAvail && (!empty($jenisIds) || !empty($jenisNames))) {
        $exists = " AND EXISTS (
                  SELECT 1 FROM sarana_jenis sj2
                  JOIN jenis_sarana j2 ON j2.id = sj2.jenis_id
                  WHERE sj2.sarana_id = s.id ";
        if ($jenisIds) {
            $exists .= " AND j2.id IN (" . implode(',', array_fill(0, count($jenisIds), '?')) . ")";
            $params = array_merge($params, $jenisIds);
        }
        if ($jenisNames) {
            $exists .= " AND j2.nama_jenis IN (" . implode(',', array_fill(0, count($jenisNames), '?')) . ")";
            $params = array_merge($params, $jenisNames);
        }
        $exists .= " )";
        $base .= $exists;
    }

    // ----- SELECT utama (ambil agregat jenis dari subquery jika tersedia) -----
    if ($jenisAvail) {
        $select = "SELECT s.*, gj.jenis_list, gj.jenis_ids";
    } else {
        $select = "SELECT s.*";
    }

    // ===== Jika mode paginasi (admin) =====
    if ($page > 0) {
        try {
            // total rows (distinct s.id)
            $countSql = "SELECT COUNT(DISTINCT s.id) " . $base;
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Count SQL Error: " . $e->getMessage());
            error_log("Count SQL Query: " . $countSql);
            respond(['error' => 'Database count query error', 'detail' => $e->getMessage()], 500);
        }

        // Tambahkan ORDER BY relevansi jika ada pencarian
        $orderBy = ($hasColumn)($pdo, 'data_sarana', 'updated_at') ? "s.updated_at DESC, s.id DESC" : "s.id DESC";
        $finalParams = $params; // Salin parameter filter dasar
        if ($q) {
            // Untuk pencarian, urutkan berdasarkan kesesuaian dengan query
            $orderBy = "CASE
                            WHEN s.nama_sarana LIKE ? THEN 1  -- Cocok di awal nama
                            WHEN s.nama_sarana LIKE ? THEN 2  -- Cocok di mana saja dalam nama
                            WHEN s.kelurahan LIKE ? THEN 3    -- Cocok dengan kelurahan
                            WHEN s.kecamatan LIKE ? THEN 4    -- Cocok dengan kecamatan
                            WHEN s.kabupaten LIKE ? THEN 5    -- Cocok dengan kabupaten
                            ELSE 6
                        END, " . $orderBy;
            // Tambahkan parameter untuk perhitungan relevansi di awal array
            $searchParams = ["$q%", "%$q%", "%$q%", "%$q%", "%$q%"];
            $finalParams = array_merge($searchParams, $params);
        }

        // IMPORTANT: jangan pakai placeholder utk LIMIT/OFFSET
        $sql = $select . $base . "
           ORDER BY " . $orderBy . "
           LIMIT {$perPage} OFFSET {$offset}";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($finalParams);
            $rows = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Paginated SQL Error: " . $e->getMessage());
            error_log("Paginated SQL Query: " . $sql);
            respond(['error' => 'Database paginated query error', 'detail' => $e->getMessage()], 500);
        }

        if ($jenisAvail) {
            foreach ($rows as &$r) {
                $r['jenis'] = $r['jenis_list'] ? explode('|', $r['jenis_list']) : [];
                unset($r['jenis_list']);
            }
        } else {
            foreach ($rows as &$r) { 
                $r['jenis'] = []; 
            }
        }

        respond([
            'data' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage))
        ]);
    }

    // ===== Mode non-paginasi (peta) =====
    // Untuk peta: abaikan record tanpa koordinat (NULL) atau titik 0,0 atau koordinat yang tidak masuk akal
    $mapBase = $base . " AND s.latitude IS NOT NULL AND s.longitude IS NOT NULL 
                         AND NOT (s.latitude=0 AND s.longitude=0)
                         AND s.latitude BETWEEN -15 AND 10 
                         AND s.longitude BETWEEN 90 AND 150";
    
    // Tambahkan ORDER BY relevansi jika ada pencarian
    $orderBy = ($hasColumn)($pdo, 'data_sarana', 'updated_at') ? "s.updated_at DESC" : "s.id DESC";
    $finalParams = $params;
    if ($q) {
        // Untuk pencarian, urutkan berdasarkan kesesuaian dengan query
        $orderBy = "CASE
                        WHEN s.nama_sarana LIKE ? THEN 1  -- Cocok di awal nama
                        WHEN s.nama_sarana LIKE ? THEN 2  -- Cocok di mana saja dalam nama
                        WHEN s.kelurahan LIKE ? THEN 3    -- Cocok dengan kelurahan
                        WHEN s.kecamatan LIKE ? THEN 4    -- Cocok dengan kecamatan
                        WHEN s.kabupaten LIKE ? THEN 5    -- Cocok dengan kabupaten
                        ELSE 6
                    END, " . $orderBy;
        // Tambahkan parameter untuk perhitungan relevansi
        $searchParams = ["$q%", "%$q%", "%$q%", "%$q%", "%$q%"];
        $finalParams = array_merge($searchParams, $params);
    }
    
    $sql = $select . $mapBase . "\n         ORDER BY " . $orderBy . "\n         LIMIT {$limit}";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($finalParams);
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("SQL Error: " . $e->getMessage());
        error_log("SQL Query: " . $sql);
        respond(['error' => 'Database query error', 'detail' => $e->getMessage()], 500);
    }

    if ($jenisAvail) {
        foreach ($rows as &$r) {
            $r['jenis'] = $r['jenis_list'] ? explode('|', $r['jenis_list']) : [];
            unset($r['jenis_list']);
        }
    } else {
        foreach ($rows as &$r) { 
            $r['jenis'] = []; 
        }
    }
    respond($rows);
}

// ===== POST / PUT / DELETE tetap =====
if ($method === 'POST') {
    $data = get_json_body();

    // Support delete via POST to avoid hosting blocking DELETE
    $delId = intval($data['delete_id'] ?? ($data['id'] ?? 0));
    if (($data['_action'] ?? '') === 'delete' && $delId > 0) {
        try {
            if ($jenisAvail) {
                $pdo->prepare("DELETE FROM sarana_jenis WHERE sarana_id=?")->execute([$delId]);
            }
            $pdo->prepare("DELETE FROM data_sarana WHERE id=?")->execute([$delId]);
            respond(['message' => 'Sarana dihapus', 'id' => $delId]);
        } catch (Exception $e) {
            respond(['error' => 'Gagal menghapus sarana', 'detail' => $e->getMessage()], 500);
        }
    }

    // Update mode fallback: jika body berisi id, perlakukan sebagai UPDATE
    $idUpdate = intval($data['id'] ?? 0);
    if ($idUpdate > 0) {
        // Logging untuk debugging
        error_log("Updating sarana with ID: " . $idUpdate);
        error_log("Data received: " . json_encode($data));
        error_log("Jenis IDs in data: " . (isset($data['jenis_ids']) ? json_encode($data['jenis_ids']) : "not set"));
        error_log("Is jenis_ids array: " . (is_array($data['jenis_ids'] ?? null) ? "yes" : "no"));
        error_log("Jenis tables available: " . ($jenisAvail ? "yes" : "no"));
        
        $pdo->beginTransaction();
        try {
            $fields = ['nama_sarana','latitude','longitude','kabupaten','kecamatan','kelurahan'];
            $set = [];$params=[];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) { 
                    $set[] = "{$f} = ?"; 
                    $params[] = $data[$f]; 
                    error_log("Setting field {$f} to " . $data[$f]);
                }
            }
            if ($set) {
                $sql = "UPDATE data_sarana SET ".implode(',', $set)." WHERE id = ?";
                $params[] = $idUpdate;
                $st = $pdo->prepare($sql); 
                $result = $st->execute($params);
                error_log("Update result: " . ($result ? "success" : "failed"));
            }
            // Update relasi jenis jika diberikan
            if (isset($data['jenis_ids']) && is_array($data['jenis_ids']) && $jenisAvail) {
                $jenisIds = array_map('intval', $data['jenis_ids']);
                error_log("Processing jenis IDs: " . json_encode($jenisIds));
                error_log("Jenis IDs type: " . gettype($data['jenis_ids']));
                
                // Delete existing relations
                $delStmt = $pdo->prepare("DELETE FROM sarana_jenis WHERE sarana_id=?");
                $delResult = $delStmt->execute([$idUpdate]);
                error_log("Delete existing jenis relations result: " . ($delResult ? "success" : "failed"));
                
                if (!empty($jenisIds)) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO sarana_jenis (sarana_id, jenis_id) VALUES (?, ?)");
                    foreach ($jenisIds as $jid) { 
                        $insResult = $ins->execute([$idUpdate, $jid]); 
                        error_log("Inserting jenis relation for sarana_id=$idUpdate, jenis_id=$jid: " . ($insResult ? "success" : "failed"));
                    }
                }
                error_log("Jenis IDs processed: " . json_encode($jenisIds));
            } else {
                error_log("Skipping jenis processing. isset jenis_ids: " . (isset($data['jenis_ids']) ? "yes" : "no") . 
                         ", is_array: " . (is_array($data['jenis_ids'] ?? null) ? "yes" : "no") . 
                         ", jenisAvail: " . ($jenisAvail ? "yes" : "no"));
            }
            $pdo->commit();
            respond(['message' => 'Sarana diperbarui', 'id' => $idUpdate]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error updating sarana: " . $e->getMessage());
            respond(['error' => 'Gagal memperbarui sarana', 'detail' => $e->getMessage()], 500);
        }
    }

    // Create mode (default)
    require_fields($data, ['nama_sarana', 'latitude', 'longitude', 'kabupaten', 'kecamatan', 'kelurahan']);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO data_sarana (nama_sarana, latitude, longitude, kabupaten, kecamatan, kelurahan)
                           VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['nama_sarana'],
            $data['latitude'],
            $data['longitude'],
            $data['kabupaten'],
            $data['kecamatan'],
            $data['kelurahan']
        ]);
        $sid = intval($pdo->lastInsertId());

        if (!empty($data['jenis_ids']) && is_array($data['jenis_ids']) && table_exists($pdo,'sarana_jenis')) {
            $ins = $pdo->prepare("INSERT IGNORE INTO sarana_jenis (sarana_id, jenis_id) VALUES (?, ?)");
            foreach ($data['jenis_ids'] as $jid) $ins->execute([$sid, intval($jid)]);
        }

        $pdo->commit();
        respond(['message' => 'Sarana dibuat', 'id' => $sid], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(['error' => 'Gagal membuat sarana', 'detail' => $e->getMessage()], 500);
    }
}

if ($method === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $id = intval($qs['id'] ?? 0);
    if (!$id) respond(['error' => 'id wajib'], 422);
    $data = get_json_body();

    $pdo->beginTransaction();
    try {
        $fields = ['nama_sarana', 'latitude', 'longitude', 'kabupaten', 'kecamatan', 'kelurahan'];
        $sets = [];
        $p = [];
        foreach ($fields as $f) if (isset($data[$f])) {
            $sets[] = "$f=?";
            $p[] = $data[$f];
        }
        if ($sets) {
            $sql = "UPDATE data_sarana SET " . implode(',', $sets) . " WHERE id=?";
            $p[] = $id;
            $pdo->prepare($sql)->execute($p);
        }

        // Update relasi jenis jika diberikan
        if (isset($data['jenis_ids']) && is_array($data['jenis_ids']) && $jenisAvail) {
            $jenisIds = array_map('intval', $data['jenis_ids']);
            error_log("PUT method - Processing jenis IDs: " . json_encode($jenisIds));
            error_log("PUT method - Jenis IDs type: " . gettype($data['jenis_ids']));
            
            // Delete existing relations
            $delStmt = $pdo->prepare("DELETE FROM sarana_jenis WHERE sarana_id=?");
            $delResult = $delStmt->execute([$id]);
            error_log("PUT method - Delete existing jenis relations result: " . ($delResult ? "success" : "failed"));
            
            if (!empty($jenisIds)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO sarana_jenis (sarana_id, jenis_id) VALUES (?, ?)");
                foreach ($jenisIds as $jid) { 
                    $insResult = $ins->execute([$id, $jid]); 
                    error_log("PUT method - Inserting jenis relation for sarana_id=$id, jenis_id=$jid: " . ($insResult ? "success" : "failed"));
                }
            }
            error_log("PUT method - Jenis IDs processed: " . json_encode($jenisIds));
        } else {
            error_log("PUT method - Skipping jenis processing. isset jenis_ids: " . (isset($data['jenis_ids']) ? "yes" : "no") . 
                     ", is_array: " . (is_array($data['jenis_ids'] ?? null) ? "yes" : "no") . 
                     ", jenisAvail: " . ($jenisAvail ? "yes" : "no"));
        }

        $pdo->commit();
        respond(['message' => 'Sarana diperbarui']);
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(['error' => 'Gagal update sarana', 'detail' => $e->getMessage()], 500);
    }
}

if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $id = intval($qs['id'] ?? 0);
    if (!$id) respond(['error' => 'id wajib'], 422);
    $pdo->prepare("DELETE FROM data_sarana WHERE id=?")->execute([$id]);
    respond(['message' => 'Sarana dihapus']);
}

respond(['error' => 'Metode tidak didukung'], 405);
