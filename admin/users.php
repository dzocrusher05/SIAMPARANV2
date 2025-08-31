<?php
// admin/users.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$userData = getUserData();

// Hanya admin yang bisa mengakses halaman ini
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Handle form submit untuk menambah user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $level = trim($_POST['level'] ?? 'user');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($password) || empty($namaLengkap)) {
        $error = 'Semua field wajib diisi';
    } else {
        $result = registerUser($username, $password, $namaLengkap, $level, $email);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Handle form submit untuk mengganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $userId = intval($_POST['user_id'] ?? 0);
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Semua field password wajib diisi';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password baru dan konfirmasi tidak cocok';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password baru minimal 6 karakter';
    } else {
        $result = changePassword($userId, $oldPassword, $newPassword);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Ambil daftar user
try {
    $stmt = $pdo->query("SELECT id, username, nama_lengkap, level, email, created_at, last_login FROM admin_users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Gagal memuat daftar user: ' . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Admin</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
</head>
<body class="bg-tulang text-abu-900">
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-[260px_1fr]">
        <!-- Sidebar -->
        <aside class="bg-white/90 border-b lg:border-b-0 lg:border-r border-abu-100 p-4 lg:sticky lg:top-0 lg:h-svh lg:overflow-auto">
            <div class="flex items-center gap-2 mb-6">
                <img src="../assets/logo.png" onerror="this.style.display='none'" class="w-8 h-8 rounded" alt="logo" />
                <div class="font-semibold">Admin Sarana</div>
            </div>
            <nav class="space-y-2">
                <a href="./index.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50">📍 Dashboard</a>
                <a href="./import.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50">⬆️ Import Sarana</a>
                <a href="./wilayah.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50">🌍 Manajemen Wilayah</a>
                <a href="./users.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50 bg-abu-50">👥 Manajemen User</a>
            </nav>
            <div class="mt-8 pt-4 border-t">
                <div class="text-xs text-abu-600 mb-2">
                    <?php if ($userData): ?>
                        Login sebagai: <b><?php echo htmlspecialchars($userData['nama_lengkap']); ?></b>
                    <?php endif; ?>
                </div>
                <a href="./logout.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50 text-sm">🚪 Logout</a>
            </div>
        </aside>

        <!-- Main -->
        <main class="min-w-0 p-4 md:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                <h1 class="text-xl font-semibold">Manajemen User</h1>
                <a href="./index.php" class="text-sm underline shrink-0">← Kembali ke Dashboard</a>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-3 rounded-xl border bg-green-50 text-green-700 text-sm">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-6 p-3 rounded-xl border bg-red-50 text-red-700 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Form Tambah User -->
            <section class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 mb-6">
                <h2 class="text-lg font-medium mb-4">Tambah User Baru</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="action" value="add_user">
                    <div>
                        <label class="block text-sm mb-1">Username</label>
                        <input type="text" name="username" required class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Password</label>
                        <input type="password" name="password" required class="w-full px-3 py-2 border rounded-xl" minlength="6">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" required class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Level</label>
                        <select name="level" class="w-full px-3 py-2 border rounded-xl">
                            <option value="user">User</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Email (Opsional)</label>
                        <input type="email" name="email" class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div class="md:col-span-2 pt-2">
                        <button type="submit" class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700">
                            Tambah User
                        </button>
                    </div>
                </form>
            </section>

            <!-- Daftar User -->
            <section class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5">
                <h2 class="text-lg font-medium mb-4">Daftar User</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-abu-50">
                            <tr>
                                <th class="p-3 text-left">Username</th>
                                <th class="p-3 text-left">Nama Lengkap</th>
                                <th class="p-3 text-left">Level</th>
                                <th class="p-3 text-left">Email</th>
                                <th class="p-3 text-left">Dibuat</th>
                                <th class="p-3 text-left">Terakhir Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="border-t">
                                <td class="p-3 font-medium"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                <td class="p-3">
                                    <?php if ($user['level'] === 'admin'): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Administrator</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">User</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td class="p-3 text-abu-700"><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                                <td class="p-3 text-abu-700">
                                    <?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>