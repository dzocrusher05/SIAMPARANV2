<?php
// Test suggest API
header('Content-Type: application/json; charset=utf-8');

// Include database connection
require_once __DIR__ . '/../config/db.php';

try {
    // Test sederhana untuk memastikan koneksi database berfungsi
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM data_sarana");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test query suggest sederhana
    $q = 'apotek';
    $stmt = $pdo->prepare("SELECT id, nama_sarana, latitude, longitude, kabupaten, kecamatan, kelurahan 
                          FROM data_sarana 
                          WHERE nama_sarana LIKE ? 
                          LIMIT 5");
    $stmt->execute(["%$q%"]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'database_count' => $count['count'],
        'sample_data' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>