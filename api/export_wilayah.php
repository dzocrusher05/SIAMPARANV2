<?php
// api/export_wilayah.php - API untuk mengekspor daftar wilayah ke CSV
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Metode tidak didukung. Gunakan GET']);
    exit;
}

try {
    $type = $_GET['type'] ?? 'kabupaten';
    
    // Validasi jenis wilayah
    if (!in_array($type, ['kabupaten', 'kecamatan', 'kelurahan'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Jenis wilayah tidak valid. Gunakan: kabupaten, kecamatan, atau kelurahan']);
        exit;
    }
    
    // Set header untuk file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wilayah_' . $type . '.csv"');
    
    // Output buffer
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, ['Nama ' . ucfirst($type)]);
    
    // Ambil data wilayah
    $sql = "SELECT DISTINCT `$type` FROM data_sarana ORDER BY `$type`";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Tulis data ke CSV
    foreach ($results as $wilayah) {
        fputcsv($output, [$wilayah]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in export_wilayah.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Error in export_wilayah.php: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()]);
    exit;
}