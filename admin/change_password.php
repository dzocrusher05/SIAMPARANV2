<?php
// admin/change_password.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();
$userData = getUserData();

$message = '';
$error = '';

// Handle form submit untuk mengganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $result = changePassword($userData['id'], $oldPassword, $newPassword);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Admin</title>
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
                <?php if ($userData && $userData['username'] === 'admin'): ?>
                <a href="./users.php" class="block w-full text-left px-3 py-2 rounded-xl hover:bg-abu-50">👥 Manajemen User</a>
                <?php endif; ?>
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
                <h1 class="text-xl font-semibold">Ganti Password</h1>
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

            <!-- Form Ganti Password -->
            <section class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 max-w-2xl">
                <h2 class="text-lg font-medium mb-4">Ubah Password Anda</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm mb-1">Password Lama</label>
                        <input type="password" name="old_password" required class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Password Baru</label>
                        <input type="password" name="new_password" required class="w-full px-3 py-2 border rounded-xl" minlength="6">
                        <div class="text-xs text-abu-600 mt-1">Minimal 6 karakter</div>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" required class="w-full px-3 py-2 border rounded-xl">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700">
                            Ganti Password
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>