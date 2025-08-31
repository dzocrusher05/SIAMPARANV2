<?php
// admin/login.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$setupRequired = false;

// Cek apakah tabel admin_users ada
try {
    $stmt = $pdo->prepare("SELECT 1 FROM admin_users LIMIT 1");
    $stmt->execute();
} catch (PDOException $e) {
    // Jika tabel tidak ada, arahkan ke halaman setup
    if (strpos($e->getMessage(), 'Base table or view not found') !== false || 
        strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        $setupRequired = true;
    } else {
        // Error lainnya
        $error = 'Terjadi kesalahan database: ' . $e->getMessage();
    }
}

if ($setupRequired) {
    header('Location: setup_required.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        if (login($username, $password)) {
            // Redirect ke halaman dashboard
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Pemetaan Sarana</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
    <style>
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 32px;
            margin: 20px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        
        .input-field {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #a0aec0;
            box-shadow: 0 0 0 3px rgba(160, 174, 192, 0.2);
        }
        
        .login-btn {
            width: 100%;
            background: #2d3748;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }
        
        .login-btn:hover {
            background: #1a202c;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #feb2b2;
        }
        
        .error-message svg {
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #718096;
            text-decoration: none;
            font-size: 14px;
            margin-top: 24px;
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            color: #2d3748;
        }
        
        .back-link svg {
            margin-right: 6px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo-circle {
            width: 60px;
            height: 60px;
            background: #f7fafc;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        
        .logo-circle svg {
            width: 28px;
            height: 28px;
            color: #4a5568;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: #718096;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h1 class="page-title">Login Admin</h1>
                <p class="page-subtitle">Masuk ke dashboard administrasi</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required 
                            class="input-field" 
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                            autocomplete="username" 
                            placeholder="Masukkan username Anda"
                        >
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <div class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            class="input-field" 
                            autocomplete="current-password" 
                            placeholder="Masukkan password Anda"
                        >
                    </div>
                </div>
                
                <button type="submit" class="login-btn">
                    Masuk ke Dashboard
                </button>
            </form>
            
            <a href="../public/index.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke halaman utama
            </a>
        </div>
    </div>
</body>
</html>