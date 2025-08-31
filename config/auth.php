<?php
// config/auth.php - Fungsi autentikasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserData() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'level' => $_SESSION['level']
    ];
}

function isAdmin() {
    $userData = getUserData();
    return $userData && $userData['level'] === 'admin';
}

function login($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password, nama_lengkap, level FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Logging untuk debugging
        error_log("Login attempt for username: " . $username);
        error_log("User found: " . ($user ? "yes" : "no"));
        
        if ($user) {
            $passwordVerified = password_verify($password, $user['password']);
            error_log("Password verified: " . ($passwordVerified ? "yes" : "no"));
            
            if ($passwordVerified) {
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['level'] = $user['level'];
                
                error_log("Login successful for user: " . $username . " with level: " . $user['level']);
                return true;
            }
        }
        
        error_log("Login failed for username: " . $username);
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function registerUser($username, $password, $namaLengkap, $level = 'user', $email = null) {
    global $pdo;
    
    try {
        // Cek apakah username sudah ada
        $checkStmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $checkStmt->execute([$username]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Username sudah digunakan'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, nama_lengkap, level, email) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $namaLengkap, $level, $email]);
        
        return ['success' => true, 'message' => 'User berhasil dibuat', 'user_id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat membuat user'];
    }
}

function changePassword($userId, $oldPassword, $newPassword) {
    global $pdo;
    
    try {
        // Verifikasi password lama
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Password lama tidak sesuai'];
        }
        
        // Hash password baru
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $updateStmt->execute([$hashedNewPassword, $userId]);
        
        return ['success' => true, 'message' => 'Password berhasil diubah'];
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat mengubah password'];
    }
}