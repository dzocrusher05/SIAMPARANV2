<?php
// Fungsi penanganan error global
function handle_error($errno, $errstr, $errfile, $errline) {
    // Jangan tampilkan error untuk error notice/warning
    if ($errno === E_NOTICE || $errno === E_WARNING) {
        error_log("PHP Warning/Notice: $errstr in $errfile on line $errline");
        return true;
    }
    
    // Untuk error lainnya, kirim response JSON
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    $error = [
        'error' => 'Internal Server Error',
        'message' => 'Terjadi kesalahan pada server',
        'details' => ''
    ];
    
    // Tampilkan detail error hanya dalam mode development
    if (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'localhost') !== false) {
        $error['details'] = $errstr;
        $error['file'] = $errfile;
        $error['line'] = $errline;
    }
    
    echo json_encode($error);
    exit;
}

// Set error handler
set_error_handler("handle_error");

// Fungsi penanganan exception
function handle_exception($exception) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    $error = [
        'error' => 'Internal Server Error',
        'message' => 'Terjadi kesalahan pada server',
        'details' => ''
    ];
    
    // Tampilkan detail error hanya dalam mode development
    if (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'localhost') !== false) {
        $error['details'] = $exception->getMessage();
        $error['file'] = $exception->getFile();
        $error['line'] = $exception->getLine();
    }
    
    echo json_encode($error);
    exit;
}

// Set exception handler
set_exception_handler("handle_exception");

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

function get_json_body()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return $data;
    return $_POST ?: [];
}

function respond($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function require_fields($data, $fields)
{
    foreach ($fields as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            respond(['error' => "Field '$f' wajib diisi"], 422);
        }
    }
}
