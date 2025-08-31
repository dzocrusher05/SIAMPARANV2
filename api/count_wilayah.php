<?php
// api/count_wilayah.php - API untuk menghitung jumlah data wilayah
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Metode tidak didukung. Gunakan POST'], 405);
}

try {
    $rawData = file_get_contents('php://input');
    
    if (empty($rawData)) {
        respond(['error' => 'Data JSON tidak ditemukan dalam request body'], 400);
    }
    
    $data = json_decode($rawData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(['error' => 'Format JSON tidak valid: ' . json_last_error_msg()], 400);
    }
    
    $type = $data['type'] ?? '';
    $name = $data['name'] ?? '';
    
    // Validasi input
    if (empty($type)) {
        respond(['error' => 'Parameter "type" wajib diisi'], 400);
    }
    
    if (empty($name)) {
        respond(['error' => 'Parameter "name" wajib diisi'], 400);
    }
    
    if (!in_array($type, ['kabupaten', 'kecamatan', 'kelurahan'])) {
        respond(['error' => 'Jenis wilayah tidak valid. Gunakan: kabupaten, kecamatan, atau kelurahan'], 400);
    }
    
    // Hitung jumlah data
    $sql = "SELECT COUNT(*) FROM data_sarana WHERE `$type` = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name]);
    $count = $stmt->fetchColumn();
    
    respond([
        'type' => $type,
        'name' => $name,
        'count' => (int)$count
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in count_wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in count_wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}