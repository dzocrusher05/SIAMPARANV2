<?php
// api/jenis_sarana_massal.php
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

try {
    // Support both GET and POST for compatibility
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Handle both PUT and POST with _method parameter for update
    if ($method === 'PUT' || ($method === 'POST' && (
        (isset($_POST['_method']) && $_POST['_method'] === 'PUT') || 
        (isset(json_decode(file_get_contents('php://input'), true)['_method']) && json_decode(file_get_contents('php://input'), true)['_method'] === 'PUT')
    ))) {
        // PUT: Mengubah jenis sarana secara massal
        $rawData = file_get_contents('php://input');
        error_log("jenis_sarana_massal.php PUT/POST request received. Raw data: " . $rawData);
        
        if (empty($rawData)) {
            respond(['error' => 'Data JSON tidak ditemukan dalam request body'], 400);
        }
        
        $data = json_decode($rawData, true);
        error_log("jenis_sarana_massal.php PUT/POST request parsed data: " . json_encode($data));
        
        $oldJenisId = $data['old_jenis_id'] ?? '';
        $newJenisId = $data['new_jenis_id'] ?? '';
        
        // Validasi input
        if (empty($oldJenisId)) {
            respond(['error' => 'Parameter "old_jenis_id" wajib diisi'], 400);
        }
        
        if (empty($newJenisId)) {
            respond(['error' => 'Parameter "new_jenis_id" wajib diisi'], 400);
        }
        
        if ($oldJenisId === $newJenisId) {
            respond(['error' => 'ID jenis lama dan jenis baru tidak boleh sama'], 400);
        }
        
        // Pastikan jenis sarana baru ada
        $checkSql = "SELECT id FROM jenis_sarana WHERE id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$newJenisId]);
        if (!$checkStmt->fetch()) {
            respond(['error' => 'Jenis sarana baru tidak ditemukan'], 400);
        }
        
        // Hitung jumlah data yang akan diubah
        $countSql = "SELECT COUNT(*) FROM sarana_jenis WHERE jenis_id = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$oldJenisId]);
        $count = $countStmt->fetchColumn();
        error_log("jenis_sarana_massal.php PUT/POST count query result: " . $count);
        
        if ($count == 0) {
            respond([
                'message' => 'Tidak ada data yang cocok untuk diubah',
                'updated' => 0,
                'old_jenis_id' => $oldJenisId,
                'new_jenis_id' => $newJenisId
            ]);
        }
        
        // Update jenis sarana
        $sql = "UPDATE sarana_jenis SET jenis_id = ? WHERE jenis_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newJenisId, $oldJenisId]);
        $updated = $stmt->rowCount();
        error_log("jenis_sarana_massal.php PUT/POST update query result - rows updated: " . $updated);
        
        respond([
            'message' => 'Berhasil mengubah jenis sarana',
            'updated' => $updated,
            'old_jenis_id' => $oldJenisId,
            'new_jenis_id' => $newJenisId,
            'count_before_update' => $count
        ]);
    }
    
    // Method tidak didukung
    respond(['error' => 'Metode tidak didukung. Gunakan PUT'], 405);
    
} catch (PDOException $e) {
    error_log("Database error in jenis_sarana_massal.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in jenis_sarana_massal.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}