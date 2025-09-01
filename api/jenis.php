<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

function table_exists_j(PDO $pdo, string $name): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) { return false; }
}

// Fungsi untuk memproses upload icon
function process_icon_upload($file, $jenisId) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Validasi tipe file
    $allowedTypes = ['image/png'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Hanya file PNG yang diperbolehkan');
    }
    
    // Validasi ukuran file (maksimal 100KB)
    if ($file['size'] > 100000) {
        throw new Exception('Ukuran file terlalu besar (maksimal 100KB)');
    }
    
    // Baca file
    $imageData = file_get_contents($file['tmp_name']);
    if ($imageData === false) {
        throw new Exception('Gagal membaca file icon');
    }
    
    // Validasi dimensi gambar (32x32)
    $imageInfo = getimagesizefromstring($imageData);
    if ($imageInfo === false) {
        throw new Exception('File bukan merupakan gambar yang valid');
    }
    
    if ($imageInfo[0] !== 32 || $imageInfo[1] !== 32) {
        throw new Exception('Ukuran icon harus 32px x 32px');
    }
    
    // Simpan sebagai blob di database
    return $imageData;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond([]);
    }
    $hasPivot = table_exists_j($pdo, 'sarana_jenis');
    if ($hasPivot) {
        // Cek apakah kolom icon ada
        $hasIconColumn = false;
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `jenis_sarana` LIKE 'icon'");
            $stmt->execute();
            $hasIconColumn = (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            $hasIconColumn = false;
        }
        
        if ($hasIconColumn) {
            $sql = "SELECT j.id, j.nama_jenis, COALESCE(x.cnt,0) AS count, 
                           (CASE WHEN j.icon IS NOT NULL THEN 1 ELSE 0 END) AS has_custom_icon,
                           j.icon
                    FROM jenis_sarana j
                    LEFT JOIN (
                      SELECT jenis_id, COUNT(DISTINCT sarana_id) AS cnt
                      FROM sarana_jenis
                      GROUP BY jenis_id
                    ) x ON x.jenis_id = j.id
                    ORDER BY j.nama_jenis ASC";
        } else {
            $sql = "SELECT j.id, j.nama_jenis, COALESCE(x.cnt,0) AS count, 0 AS has_custom_icon
                    FROM jenis_sarana j
                    LEFT JOIN (
                      SELECT jenis_id, COUNT(DISTINCT sarana_id) AS cnt
                      FROM sarana_jenis
                      GROUP BY jenis_id
                    ) x ON x.jenis_id = j.id
                    ORDER BY j.nama_jenis ASC";
        }
    } else {
        // Cek apakah kolom icon ada
        $hasIconColumn = false;
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `jenis_sarana` LIKE 'icon'");
            $stmt->execute();
            $hasIconColumn = (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            $hasIconColumn = false;
        }
        
        if ($hasIconColumn) {
            $sql = "SELECT j.id, j.nama_jenis, 0 AS count, 
                           (CASE WHEN j.icon IS NOT NULL THEN 1 ELSE 0 END) AS has_custom_icon,
                           j.icon
                    FROM jenis_sarana j ORDER BY j.nama_jenis ASC";
        } else {
            $sql = "SELECT j.id, j.nama_jenis, 0 AS count, 0 AS has_custom_icon 
                    FROM jenis_sarana j ORDER BY j.nama_jenis ASC";
        }
    }
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
    
    // Encode icon data sebagai base64 jika ada
    if (isset($hasIconColumn) && $hasIconColumn) {
        foreach ($results as &$row) {
            if (!empty($row['icon'])) {
                $row['icon_base64'] = base64_encode($row['icon']);
            }
            unset($row['icon']); // Hapus data blob mentah
        }
    }
    
    respond($results);
}

if ($method === 'POST') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond(['error' => "Tabel 'jenis_sarana' belum tersedia."], 500);
    }
    
    // Cek apakah ada file upload
    if (!empty($_FILES['icon'])) {
        // Handle upload dengan icon
        try {
            $namaJenis = $_POST['nama_jenis'] ?? '';
            if (empty($namaJenis)) {
                respond(['error' => 'Nama jenis wajib diisi'], 422);
            }
            
            // Proses icon
            $iconData = process_icon_upload($_FILES['icon'], null);
            
            // Cek apakah ini update atau create
            $updId = intval($_POST['id'] ?? 0);
            
            if ($updId > 0) {
                // Update
                if ($iconData !== null) {
                    // Update dengan icon
                    $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=?, icon=? WHERE id=?");
                    $stmt->execute([$namaJenis, $iconData, $updId]);
                } else {
                    // Update tanpa icon
                    $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=? WHERE id=?");
                    $stmt->execute([$namaJenis, $updId]);
                }
                respond(['message' => 'Jenis diperbarui']);
            } else {
                // Create
                if ($iconData !== null) {
                    // Create dengan icon
                    $stmt = $pdo->prepare("INSERT INTO jenis_sarana (nama_jenis, icon) VALUES (?, ?)");
                    $stmt->execute([$namaJenis, $iconData]);
                } else {
                    // Create tanpa icon
                    $stmt = $pdo->prepare("INSERT INTO jenis_sarana (nama_jenis) VALUES (?)");
                    $stmt->execute([$namaJenis]);
                }
                respond(['message' => 'Jenis dibuat', 'id' => $pdo->lastInsertId()], 201);
            }
        } catch (Exception $e) {
            respond(['error' => $e->getMessage()], 422);
        }
        exit;
    }
    
    // Handle JSON request (seperti sebelumnya)
    $data = get_json_body();

    // Delete via POST
    if (($data['_action'] ?? '') === 'delete') {
        $delId = intval($data['delete_id'] ?? $data['id'] ?? 0);
        if (!$delId) respond(['error' => 'id wajib'], 422);
        try {
            $pdo->beginTransaction();
            try { $pdo->prepare("DELETE FROM sarana_jenis WHERE jenis_id=?")->execute([$delId]); } catch (Exception $e) {}
            $stmt = $pdo->prepare("DELETE FROM jenis_sarana WHERE id=?");
            $stmt->execute([$delId]);
            $pdo->commit();
            respond(['message' => 'Jenis dihapus']);
        } catch (Exception $e) {
            $pdo->rollBack();
            respond(['error' => 'Gagal menghapus jenis', 'detail' => $e->getMessage()], 500);
        }
    }

    // Update via POST (id present)
    $updId = intval($data['id'] ?? 0);
    if ($updId > 0) {
        require_fields($data, ['nama_jenis']);
        // Cek apakah ada permintaan untuk menghapus icon
        if (isset($data['remove_icon']) && $data['remove_icon']) {
            $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=?, icon=NULL WHERE id=?");
            $stmt->execute([$data['nama_jenis'], $updId]);
        } else {
            $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=? WHERE id=?");
            $stmt->execute([$data['nama_jenis'], $updId]);
        }
        respond(['message' => 'Jenis diperbarui']);
        exit;
    }

    // Create
    require_fields($data, ['nama_jenis']);
    $stmt = $pdo->prepare("INSERT INTO jenis_sarana (nama_jenis) VALUES (?)");
    $stmt->execute([$data['nama_jenis']]);
    respond(['message' => 'Jenis dibuat', 'id' => $pdo->lastInsertId()], 201);
}

if ($method === 'PUT') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond(['error' => "Tabel 'jenis_sarana' belum tersedia."], 500);
    }
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $id = $qs['id'] ?? null;
    if (!$id) respond(['error' => 'id wajib'], 422);
    $data = get_json_body();
    require_fields($data, ['nama_jenis']);
    $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=? WHERE id=?");
    $stmt->execute([$data['nama_jenis'], $id]);
    respond(['message' => 'Jenis diperbarui']);
}

if ($method === 'DELETE') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond(['error' => "Tabel 'jenis_sarana' belum tersedia."], 500);
    }
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $id = $qs['id'] ?? null;
    if (!$id) respond(['error' => 'id wajib'], 422);
    $stmt = $pdo->prepare("DELETE FROM jenis_sarana WHERE id=?");
    $stmt->execute([$id]);
    respond(['message' => 'Jenis dihapus']);
}

respond(['error' => 'Metode tidak didukung'], 405);
