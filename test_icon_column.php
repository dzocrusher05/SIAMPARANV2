<?php
require_once __DIR__ . '/config/db.php';

try {
    // Test apakah kolom icon ada
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `jenis_sarana` LIKE 'icon'");
    $stmt->execute();
    $hasIconColumn = (bool)$stmt->fetchColumn();
    
    if ($hasIconColumn) {
        echo "Kolom 'icon' ditemukan di tabel jenis_sarana\n";
        
        // Test apakah ada data dengan icon
        $stmt = $pdo->prepare("SELECT id, nama_jenis, icon FROM jenis_sarana WHERE icon IS NOT NULL LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        
        if ($row) {
            echo "Ditemukan data jenis sarana dengan icon\n";
            echo "ID: " . $row['id'] . "\n";
            echo "Nama: " . $row['nama_jenis'] . "\n";
            echo "Ukuran icon: " . strlen($row['icon']) . " bytes\n";
        } else {
            echo "Tidak ada data jenis sarana dengan icon\n";
        }
    } else {
        echo "Kolom 'icon' tidak ditemukan di tabel jenis_sarana\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
