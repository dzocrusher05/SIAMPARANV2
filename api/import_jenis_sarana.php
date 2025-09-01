<?php
// api/import_jenis_sarana.php - API untuk mengimpor perubahan jenis sarana dari CSV
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
    error_log("import_jenis_sarana.php request received");
    // Periksa apakah ada file yang diupload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        error_log("import_jenis_sarana.php no file uploaded or upload error. File isset: " . (isset($_FILES['file']) ? "yes" : "no") . 
                  ", error code: " . ($_FILES['file']['error'] ?? "not set"));
        respond(['error' => 'File tidak ditemukan atau terjadi kesalahan saat upload'], 422);
    }
    
    $file = $_FILES['file'];
    $filePath = $file['tmp_name'];
    error_log("import_jenis_sarana.php file uploaded: " . $file['name'] . " tmp path: " . $filePath);
    
    // Validasi ekstensi file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        error_log("import_jenis_sarana.php invalid file extension: " . $extension);
        respond(['error' => 'Hanya file CSV yang diperbolehkan'], 422);
    }
    
    // Buka file CSV
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        error_log("import_jenis_sarana.php failed to open CSV file");
        respond(['error' => 'Gagal membaca file CSV'], 500);
    }
    
    // Baca header
    $header = fgetcsv($handle);
    error_log("import_jenis_sarana.php CSV header: " . json_encode($header));
    if (!$header || count($header) < 2) {
        fclose($handle);
        error_log("import_jenis_sarana.php invalid CSV header format");
        respond(['error' => 'Format file CSV tidak valid. Harus memiliki kolom: jenis_lama,jenis_baru'], 422);
    }
    
    // Validasi header
    if ($header[0] !== 'jenis_lama' || $header[1] !== 'jenis_baru') {
        fclose($handle);
        error_log("import_jenis_sarana.php CSV header mismatch. Expected: jenis_lama,jenis_baru. Got: " . implode(',', $header));
        respond(['error' => 'Header file CSV tidak sesuai. Harus: jenis_lama,jenis_baru'], 422);
    }
    
    // Proses baris data
    $changes = [];
    $errors = [];
    $lineNumber = 1;
    
    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        error_log("import_jenis_sarana.php processing line $lineNumber: " . json_encode($data));
        
        // Validasi jumlah kolom
        if (count($data) < 2) {
            $errors[] = "Baris $lineNumber: Jumlah kolom tidak sesuai";
            continue;
        }
        
        $oldJenis = trim($data[0]);
        $newJenis = trim($data[1]);
        
        // Validasi data
        if (empty($oldJenis) || empty($newJenis)) {
            $errors[] = "Baris $lineNumber: Semua kolom wajib diisi";
            continue;
        }
        
        if ($oldJenis === $newJenis) {
            $errors[] = "Baris $lineNumber: Jenis lama dan baru tidak boleh sama";
            continue;
        }
        
        $changes[] = [
            'old_jenis' => $oldJenis,
            'new_jenis' => $newJenis
        ];
    }
    
    fclose($handle);
    error_log("import_jenis_sarana.php finished reading CSV. Changes count: " . count($changes) . ", errors count: " . count($errors));
    
    if (empty($changes)) {
        respond(['error' => 'Tidak ada data valid untuk diproses', 'errors' => $errors], 422);
    }
    
    // Proses perubahan
    $totalUpdated = 0;
    $processedChanges = [];
    
    foreach ($changes as $change) {
        error_log("import_jenis_sarana.php processing change: " . json_encode($change));
        
        // Cari ID jenis lama
        $oldSql = "SELECT id FROM jenis_sarana WHERE nama_jenis = ?";
        $oldStmt = $pdo->prepare($oldSql);
        $oldStmt->execute([$change['old_jenis']]);
        $oldRow = $oldStmt->fetch();
        
        if (!$oldRow) {
            $errors[] = "Jenis lama '{$change['old_jenis']}' tidak ditemukan";
            continue;
        }
        
        $oldId = $oldRow['id'];
        
        // Cari ID jenis baru
        $newSql = "SELECT id FROM jenis_sarana WHERE nama_jenis = ?";
        $newStmt = $pdo->prepare($newSql);
        $newStmt->execute([$change['new_jenis']]);
        $newRow = $newStmt->fetch();
        
        if (!$newRow) {
            $errors[] = "Jenis baru '{$change['new_jenis']}' tidak ditemukan";
            continue;
        }
        
        $newId = $newRow['id'];
        
        // Hitung jumlah data yang akan diubah
        $countSql = "SELECT COUNT(*) FROM sarana_jenis WHERE jenis_id = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$oldId]);
        $count = $countStmt->fetchColumn();
        error_log("import_jenis_sarana.php count query result for jenis_id=$oldId: " . $count);
        
        if ($count > 0) {
            // Update jenis sarana
            $sql = "UPDATE sarana_jenis SET jenis_id = ? WHERE jenis_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newId, $oldId]);
            $updated = $stmt->rowCount();
            error_log("import_jenis_sarana.php update query result - rows updated: " . $updated);
            
            $totalUpdated += $updated;
            
            $processedChanges[] = [
                'old_jenis' => $change['old_jenis'],
                'new_jenis' => $change['new_jenis'],
                'updated' => $updated,
                'count_before_update' => $count
            ];
        } else {
            $processedChanges[] = [
                'old_jenis' => $change['old_jenis'],
                'new_jenis' => $change['new_jenis'],
                'updated' => 0,
                'count_before_update' => 0
            ];
        }
    }
    
    error_log("import_jenis_sarana.php finished processing. Total updated: " . $totalUpdated);
    respond([
        'message' => 'Berhasil memproses import perubahan jenis sarana',
        'total_updated' => $totalUpdated,
        'processed_changes' => $processedChanges,
        'errors' => $errors
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in import_jenis_sarana.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan database', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in import_jenis_sarana.php: " . $e->getMessage());
    respond(['error' => 'Terjadi kesalahan server', 'detail' => $e->getMessage()], 500);
}