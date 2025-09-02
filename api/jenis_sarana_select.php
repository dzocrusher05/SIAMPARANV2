<?php
// api/jenis_sarana_select.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $jenisId = intval($_GET['jenis_id'] ?? 0);
        $limit = max(1, min(10000, intval($_GET['limit'] ?? 5000)));
        if (!$jenisId) respond(['error' => 'jenis_id wajib'], 422);

        // Ambil data sarana yang memiliki jenis tersebut
        $sql = "SELECT s.id, s.nama_sarana, s.kabupaten, s.kecamatan, s.kelurahan
                FROM sarana_jenis sj
                JOIN data_sarana s ON s.id = sj.sarana_id
                WHERE sj.jenis_id = ?
                ORDER BY s.nama_sarana ASC
                LIMIT {$limit}";
        $st = $pdo->prepare($sql);
        $st->execute([$jenisId]);
        $rows = $st->fetchAll();
        respond($rows);
    }

    if ($method === 'POST') {
        $data = get_json_body();
        $oldJenis = intval($data['old_jenis_id'] ?? 0);
        $newJenis = intval($data['new_jenis_id'] ?? 0);
        $ids = $data['sarana_ids'] ?? [];
        if (!$newJenis || !is_array($ids) || empty($ids)) respond(['error' => 'new_jenis_id dan sarana_ids wajib'], 422);

        // Opsional: jika old_jenis_id diberikan, hapus mapping lama
        $pdo->beginTransaction();
        try {
            if ($oldJenis) {
                $del = $pdo->prepare("DELETE FROM sarana_jenis WHERE sarana_id=? AND jenis_id=?");
                foreach ($ids as $sid) { $del->execute([intval($sid), $oldJenis]); }
            }
            // Tambahkan mapping baru
            $ins = $pdo->prepare("INSERT IGNORE INTO sarana_jenis (sarana_id, jenis_id) VALUES (?, ?)");
            foreach ($ids as $sid) { $ins->execute([intval($sid), $newJenis]); }
            $pdo->commit();
            respond(['moved' => count($ids)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            respond(['error' => 'Gagal memperbarui data terpilih', 'detail' => $e->getMessage()], 500);
        }
    }

    respond(['error' => 'Metode tidak didukung'], 405);
} catch (Exception $e) {
    respond(['error' => 'Kesalahan server', 'detail' => $e->getMessage()], 500);
}

