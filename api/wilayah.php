<?php
// api/wilayah.php
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
    
    if ($method === 'GET' || ($method === 'POST' && !isset($_POST['_method']) && !isset(json_decode(file_get_contents('php://input'), true)['_method']))) {
        // GET: Mendapatkan daftar wilayah unik
        $type = $_GET['type'] ?? 'kabupaten';
        
        // Validasi jenis wilayah
        if (!in_array($type, ['kabupaten', 'kecamatan', 'kelurahan'])) {
            respond(['error' => 'Jenis wilayah tidak valid. Gunakan: kabupaten, kecamatan, atau kelurahan'], 400);
        }
        
        $sql = "SELECT DISTINCT `$type` FROM data_sarana ORDER BY `$type`";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        respond($results);
    }
    
    // Handle both PUT and POST with _method parameter for update
    if ($method === 'PUT' || ($method === 'POST' && (
        (isset($_POST['_method']) && $_POST['_method'] === 'PUT') || 
        (isset(json_decode(file_get_contents('php://input'), true)['_method']) && json_decode(file_get_contents('php://input'), true)['_method'] === 'PUT')
    ))) {
        // PUT: Mengubah nama wilayah secara massal
        $rawData = file_get_contents('php://input');
        error_log("wilayah.php PUT/POST request received. Raw data: " . $rawData);
        
        if (empty($rawData)) {
            respond(['error' => 'Data JSON tidak ditemukan dalam request body'], 400);
        }
        
        $data = json_decode($rawData, true);
        error_log("wilayah.php PUT/POST request parsed data: " . json_encode($data));
        
        $type = $data['type'] ?? '';
        $oldName = $data['old_name'] ?? '';
        $newName = $data['new_name'] ?? '';
        
        // Validasi input
        if (empty($type)) {
            respond(['error' => 'Parameter "type" wajib diisi'], 400);
        }
        
        if (empty($oldName)) {
            respond(['error' => 'Parameter "old_name" wajib diisi'], 400);
        }
        
        if (empty($newName)) {
            respond(['error' => 'Parameter "new_name" wajib diisi'], 400);
        }
        
        if (!in_array($type, ['kabupaten', 'kecamatan', 'kelurahan'])) {
            respond(['error' => 'Jenis wilayah tidak valid. Gunakan: kabupaten, kecamatan, atau kelurahan'], 400);
        }
        
        if ($oldName === $newName) {
            respond(['error' => 'Nama lama dan nama baru tidak boleh sama'], 400);
        }
        
        // Hitung jumlah data yang akan diubah
        $countSql = "SELECT COUNT(*) FROM data_sarana WHERE `$type` = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$oldName]);
        $count = $countStmt->fetchColumn();
        error_log("wilayah.php PUT/POST count query result: " . $count);
        
        if ($count == 0) {
            respond([
                'message' => 'Tidak ada data yang cocok untuk diubah',
                'updated' => 0,
                'type' => $type,
                'old_name' => $oldName,
                'new_name' => $newName
            ]);
        }
        
        // Update nama wilayah
        $sql = "UPDATE data_sarana SET `$type` = ? WHERE `$type` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newName, $oldName]);
        $updated = $stmt->rowCount();
        error_log("wilayah.php PUT/POST update query result - rows updated: " . $updated);
        
        respond([
            'message' => 'Berhasil mengubah nama wilayah',
            'updated' => $updated,
            'type' => $type,
            'old_name' => $oldName,
            'new_name' => $newName,
            'count_before_update' => $count
        ]);
    }
    
    // Method tidak didukung
    respond(['error' => 'Metode tidak didukung. Gunakan GET atau PUT'], 405);
    
} catch (PDOException $e) {
    error_log("Database error in wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}