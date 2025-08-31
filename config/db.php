<?php
// config/db.php — Koneksi database
// Default: lingkungan lokal (XAMPP: root tanpa password)
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Mulai session untuk autentikasi
}

// Nilai default (lokal)
$DB_HOST = '127.0.0.1';
$DB_NAME = 'pemetaan_sarana_db';
$DB_USER = 'root';
$DB_PASS = '';

// Override via environment (jika diset di hosting)
$envHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
$envName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? null);
$envUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null);
$envPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? null);
if ($envHost && $envName && $envUser !== null && $envPass !== null) {
    $DB_HOST = $envHost;
    $DB_NAME = $envName;
    $DB_USER = $envUser;
    $DB_PASS = $envPass;
}

// Override via file kustom (tidak wajib). File ini dapat berisi assignment
// seperti: $DB_HOST = '...'; $DB_NAME = '...'; dst.
// Jika ada, nilai di file akan menimpa yang di atas.
$customFile = __DIR__ . '/db.custom.php';
if (is_file($customFile)) {
    /** @noinspection PhpIncludeInspection */
    require $customFile;
}

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Gagal konek database',
        'detail' => $e->getMessage(),
        'host' => $DB_HOST,
        'db' => $DB_NAME,
        'user' => $DB_USER,
    ]);
    exit;
}
