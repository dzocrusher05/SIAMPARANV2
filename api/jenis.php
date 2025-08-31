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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond([]);
    }
    $hasPivot = table_exists_j($pdo, 'sarana_jenis');
    if ($hasPivot) {
        $sql = "SELECT j.id, j.nama_jenis, COALESCE(x.cnt,0) AS count
                FROM jenis_sarana j
                LEFT JOIN (
                  SELECT jenis_id, COUNT(DISTINCT sarana_id) AS cnt
                  FROM sarana_jenis
                  GROUP BY jenis_id
                ) x ON x.jenis_id = j.id
                ORDER BY j.nama_jenis ASC";
    } else {
        $sql = "SELECT j.id, j.nama_jenis, 0 AS count FROM jenis_sarana j ORDER BY j.nama_jenis ASC";
    }
    $stmt = $pdo->query($sql);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    if (!table_exists_j($pdo, 'jenis_sarana')) {
        respond(['error' => "Tabel 'jenis_sarana' belum tersedia."], 500);
    }
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
        $stmt = $pdo->prepare("UPDATE jenis_sarana SET nama_jenis=? WHERE id=?");
        $stmt->execute([$data['nama_jenis'], $updId]);
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
