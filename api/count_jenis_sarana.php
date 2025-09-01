<?php
// api/count_jenis_sarana.php - API untuk menghitung jumlah data jenis sarana
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');

// CORS dev-friendly
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Metode tidak didukung. Gunakan POST'], 405);
}

try {
    $rawData = file_get_contents('php://input');
    error_log("count_jenis_sarana.php request received. Raw data: " . $rawData);
    
    if (empty($rawData)) {
        respond(['error' => 'Data JSON tidak ditemukan dalam request body'], 400);
    }
    
    $data = json_decode($rawData, true);
    error_log("count_jenis_sarana.php parsed data: " . json_encode($data));
    
    $jenisId = $data['jenis_id'] ?? '';
    
    // Validasi input
    if (empty($jenisId)) {
        respond(['error' => 'Parameter "jenis_id" wajib diisi'], 400);
    }
    
    // Hitung jumlah data
    $sql = "SELECT COUNT(*) FROM sarana_jenis WHERE jenis_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$jenisId]);
    $count = $stmt->fetchColumn();
    error_log("count_jenis_sarana.php query result: " . $count);
    
    respond([
        'jenis_id' => $jenisId,
        'count' => (int)$count
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in count_jenis_sarana.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in count_jenis_sarana.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}