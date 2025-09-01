<?php
// api/import_wilayah.php - API untuk mengimpor perubahan nama wilayah dari CSV
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
    error_log("import_wilayah.php request received");
    // Periksa apakah ada file yang diupload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        error_log("import_wilayah.php no file uploaded or upload error. File isset: " . (isset($_FILES['file']) ? "yes" : "no") . 
                  ", error code: " . ($_FILES['file']['error'] ?? "not set"));
        respond(['error' => 'File tidak ditemukan atau terjadi kesalahan saat upload'], 422);
    }
    
    $file = $_FILES['file'];
    $filePath = $file['tmp_name'];
    error_log("import_wilayah.php file uploaded: " . $file['name'] . " tmp path: " . $filePath);
    
    // Validasi ekstensi file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        error_log("import_wilayah.php invalid file extension: " . $extension);
        respond(['error' => 'Hanya file CSV yang diperbolehkan'], 422);
    }
    
    // Buka file CSV
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        error_log("import_wilayah.php failed to open CSV file");
        respond(['error' => 'Gagal membaca file CSV'], 500);
    }
    
    // Baca header
    $header = fgetcsv($handle);
    error_log("import_wilayah.php CSV header: " . json_encode($header));
    if (!$header || count($header) < 3) {
        fclose($handle);
        error_log("import_wilayah.php invalid CSV header format");
        respond(['error' => 'Format file CSV tidak valid. Harus memiliki kolom: jenis_wilayah,nama_lama,nama_baru'], 422);
    }
    
    // Validasi header
    if ($header[0] !== 'jenis_wilayah' || $header[1] !== 'nama_lama' || $header[2] !== 'nama_baru') {
        fclose($handle);
        error_log("import_wilayah.php CSV header mismatch. Expected: jenis_wilayah,nama_lama,nama_baru. Got: " . implode(',', $header));
        respond(['error' => 'Header file CSV tidak sesuai. Harus: jenis_wilayah,nama_lama,nama_baru'], 422);
    }
    
    // Proses baris data
    $changes = [];
    $errors = [];
    $lineNumber = 1;
    
    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        error_log("import_wilayah.php processing line $lineNumber: " . json_encode($data));
        
        // Validasi jumlah kolom
        if (count($data) < 3) {
            $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
            continue;
        }
        
        $type = trim($data[0]);
        $oldName = trim($data[1]);
        $newName = trim($data[2]);
        
        // Validasi data
        if (empty($type) || empty($oldName) || empty($newName)) {
            $errors[] = "Baris $lineNumber: Semua kolom wajib diisi";
            continue;
        }
        
        if (!in_array($type, ['kabupaten', 'kecamatan', 'kelurahan'])) {
            $errors[] = "Baris $lineNumber: Jenis wilayah tidak valid";
            continue;
        }
        
        if ($oldName === $newName) {
            $errors[] = "Baris $lineNumber: Nama lama dan baru tidak boleh sama";
            continue;
        }
        
        $changes[] = [
            'type' => $type,
            'old_name' => $oldName,
            'new_name' => $newName
        ];
    }
    
    fclose($handle);
    error_log("import_wilayah.php finished reading CSV. Changes count: " . count($changes) . ", errors count: " . count($errors));
    
    if (empty($changes)) {
        respond(['error' => 'Tidak ada data valid untuk diproses', 'errors' => $errors], 422);
    }
    
    // Proses perubahan
    $totalUpdated = 0;
    $processedChanges = [];
    
    foreach ($changes as $change) {
        error_log("import_wilayah.php processing change: " . json_encode($change));
        // Hitung jumlah data yang akan diubah
        $countSql = "SELECT COUNT(*) FROM data_sarana WHERE `{$change['type']}` = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$change['old_name']]);
        $count = $countStmt->fetchColumn();
        error_log("import_wilayah.php count query result for {$change['type']}='{$change['old_name']}': " . $count);
        
        if ($count > 0) {
            // Update nama wilayah
            $sql = "UPDATE data_sarana SET `{$change['type']}` = ? WHERE `{$change['type']}` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$change['new_name'], $change['old_name']]);
            $updated = $stmt->rowCount();
            error_log("import_wilayah.php update query result - rows updated: " . $updated);
            
            $totalUpdated += $updated;
            
            $processedChanges[] = [
                'type' => $change['type'],
                'old_name' => $change['old_name'],
                'new_name' => $change['new_name'],
                'updated' => $updated,
                'count_before_update' => $count
            ];
        } else {
            $processedChanges[] = [
                'type' => $change['type'],
                'old_name' => $change['old_name'],
                'new_name' => $change['new_name'],
                'updated' => 0,
                'count_before_update' => 0
            ];
        }
    }
    
    error_log("import_wilayah.php finished processing. Total updated: " . $totalUpdated);
    respond([
        'message' => 'Berhasil memproses import perubahan wilayah',
        'total_updated' => $totalUpdated,
        'processed_changes' => $processedChanges,
        'errors' => $errors
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in import_wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in import_wilayah.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}