<?php
require_once __DIR__ . '/config/db.php';

try {
    // Test apakah fungsi TO_BASE64 tersedia
    $stmt = $pdo->prepare("SELECT TO_BASE64('test') as result");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "TO_BASE64 function available: " . ($result ? "Yes" : "No") . "\n";
    if ($result) {
        echo "Result: " . $result['result'] . "\n";
    }
} catch (Exception $e) {
    echo "TO_BASE64 function not available: " . $e->getMessage() . "\n";
}

try {
    // Test apakah fungsi BASE64_ENCODE tersedia
    $stmt = $pdo->prepare("SELECT BASE64_ENCODE('test') as result");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "BASE64_ENCODE function available: " . ($result ? "Yes" : "No") . "\n";
    if ($result) {
        echo "Result: " . $result['result'] . "\n";
    }
} catch (Exception $e) {
    echo "BASE64_ENCODE function not available: " . $e->getMessage() . "\n";
}
?>
