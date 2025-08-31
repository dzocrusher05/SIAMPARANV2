<?php
// admin/setup_required.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Diperlukan - Admin</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
</head>
<body class="bg-tulang text-abu-900">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-abu-100 shadow-soft p-6 w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-semibold text-red-600">Setup Diperlukan</h1>
                <p class="text-sm text-abu-700 mt-2">Tabel admin_users belum ada di database</p>
            </div>
            
            <div class="mb-4 p-3 rounded-xl border bg-red-50 text-red-700 text-sm">
                <p class="mb-2">Tabel <code>admin_users</code> belum ada di database. Silakan jalankan perintah berikut untuk membuat tabel tersebut:</p>
                <pre class="bg-white p-2 rounded text-xs overflow-x-auto">mysql -u root pemetaan_sarana_db &lt; create_admin_users_table.sql</pre>
            </div>
            
            <div class="text-sm text-abu-700">
                <p class="mb-2">Atau jika menggunakan XAMPP, jalankan perintah:</p>
                <pre class="bg-white p-2 rounded text-xs overflow-x-auto">C:\xampp\mysql\bin\mysql.exe -u root pemetaan_sarana_db &lt; create_admin_users_table.sql</pre>
            </div>
            
            <div class="mt-6 text-center">
                <a href="./login.php" class="text-sm underline">Coba Lagi</a>
            </div>
        </div>
    </div>
</body>
</html>