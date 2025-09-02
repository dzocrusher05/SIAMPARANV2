<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$loggedIn = isLoggedIn();
$userData = $loggedIn ? getUserData() : null;

// Get statistics from database
try {
    // Total sarana
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_sarana");
    $stmt->execute();
    $totalSarana = $stmt->fetchColumn();
    
    // Total kabupaten
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kabupaten) FROM data_sarana WHERE kabupaten IS NOT NULL AND kabupaten != ''");
    $stmt->execute();
    $totalKabupaten = $stmt->fetchColumn();
    
    // Total kecamatan
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kecamatan) FROM data_sarana WHERE kecamatan IS NOT NULL AND kecamatan != ''");
    $stmt->execute();
    $totalKecamatan = $stmt->fetchColumn();
    
    // Total kelurahan
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT kelurahan) FROM data_sarana WHERE kelurahan IS NOT NULL AND kelurahan != ''");
    $stmt->execute();
    $totalKelurahan = $stmt->fetchColumn();
    
    // Jenis sarana unik
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT nama_jenis) FROM jenis_sarana");
    $stmt->execute();
    $totalJenisSarana = $stmt->fetchColumn();
    
    // Data terbaru (dalam 30 hari terakhir)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_sarana WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $dataTerbaru = $stmt->fetchColumn();
    
} catch (Exception $e) {
    // Default values if database error
    $totalSarana = 100;
    $totalKabupaten = 10;
    $totalKecamatan = 50;
    $totalKelurahan = 200;
    $totalJenisSarana = 15;
    $dataTerbaru = 25;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAMPARAN - Sistem Informasi Pemetaan Sarana</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Ccircle cx='8' cy='8' r='8' fill='%23111827'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="public/assets/app-public.css" />
    <style>
        :root {
            --primary: #111827;
            --primary-light: #1f2937;
            --secondary: #3b82f6;
            --accent: #10b981;
            --light: #f8fafc;
            --dark: #0f172a;
            --gray: #94a3b8;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--dark);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Minimap embed behaves like a static screenshot (no interaction) */
        .minimap-embed { width: 100%; height: 100%; border: 0; pointer-events: none; filter: saturate(0.95) contrast(1.05); }
        
        .hero-bg {
            background: url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.343-3 3 1.343 3 3 3zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z" fill="%233b82f6" fill-opacity="0.1" fill-rule="evenodd"/%3E%3C/svg%3E');
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(17, 24, 39, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(17, 24, 39, 0.25);
        }

        /* Clean button icons: hide garbled spans and inject crisp SVGs */
        .btn-primary span:nth-child(2){ display:none; }
        .btn-primary::after{
            content:""; display:inline-block; width:18px; height:18px; margin-left:8px;
            background: no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M5 12h14'/><path d='m12 5 7 7-7 7'/></svg>");
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        }
        
        .btn-secondary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.25);
        }

        .btn-secondary span:first-child{ display:none; }
        .btn-secondary::before{
            content:""; display:inline-block; width:18px; height:18px; margin-right:8px;
            background: no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z'/><circle cx='12' cy='10' r='3'/></svg>");
        }
        
        .btn-accent {
            background: var(--accent);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }
        
        .btn-accent:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.25);
        }

        .btn-accent span:first-child{ display:none; }
        .btn-accent::before{
            content:""; display:inline-block; width:18px; height:18px; margin-right:8px;
            background: no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='3' y='3' width='7' height='7'/><rect x='14' y='3' width='7' height='7'/><rect x='14' y='14' width='7' height='7'/><rect x='3' y='14' width='7' height='7'/></svg>");
        }
        
        .feature-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0; /* hide any stray glyphs */
            margin-bottom: 1rem;
            position: relative;
        }
        .feature-icon::after{ content:""; display:block; width:28px; height:28px; }
        .feature-1 {
            background: rgba(59, 130, 246, 0.1);
            color: var(--secondary);
        }
        .feature-1::after{ background:no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z'/><circle cx='12' cy='10' r='3'/></svg>"); }
        
        .feature-2 {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent);
        }
        .feature-2::after{ background:no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='%2310b981' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M3 3v18h18'/><rect x='7' y='12' width='3' height='6'/><rect x='12' y='9' width='3' height='9'/><rect x='17' y='6' width='3' height='12'/></svg>"); }
        
        .feature-3 {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        .feature-3::after{ background:no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='%238b5cf6' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='23 4 23 10 17 10'/><polyline points='1 20 1 14 7 14'/><path d='M3.51 9a9 9 0 0 1 14.85-3.36L23 10'/><path d='M1 14l4.64 4.36A9 9 0 0 0 20.49 15'/></svg>"); }

        /* Preview block polish: hide stray glyph and show subtle illustration */
        .border-dashed .text-5xl{ display:none; }
        .border-dashed::before{
            content:""; display:block; width:64px; height:64px; margin:0 auto 12px auto;
            background:no-repeat center/contain url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0Z'/><circle cx='12' cy='10' r='3'/></svg>");
        }
        
        /* Responsive fixes */
        @media (max-width: 768px) {
            .max-w-7xl {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .text-4xl {
                font-size: 2.25rem;
            }
            
            .text-5xl {
                font-size: 2.5rem;
            }
            
            .text-6xl {
                font-size: 2.75rem;
            }
        }
        
        @media (max-width: 640px) {
            .flex-col {
                gap: 1rem;
            }
            
            .sm\:text-5xl {
                font-size: 2.25rem;
            }
            
            .btn-primary, .btn-secondary, .btn-accent {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="hero-bg">
    <!-- Header -->
    <header class="py-6 px-4 sm:px-8">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-xl">S</div>
                <h1 class="text-2xl font-bold text-gray-900">SIAMPARAN</h1>
            </div>
            <?php if ($loggedIn): ?>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700 hidden sm:inline">Halo, <span class="font-semibold"><?= htmlspecialchars($userData['nama_lengkap']) ?></span></span>
                    <a href="admin/logout.php" class="text-gray-600 hover:text-gray-900 font-medium">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="py-12 sm:py-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-gray-900 mb-6">
                    Pemetaan Sarana <span class="text-indigo-600">Terintegrasi</span>
                </h1>
                <p class="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
                    Sistem informasi pemetaan sarana yang memudahkan Anda mengelola, memvisualisasikan, dan menganalisis data sarana dalam satu platform terpadu.
                </p>
                
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <?php if (!$loggedIn): ?>
                        <a href="admin/login.php" class="btn-primary text-lg py-3 px-8">
                            <span>Masuk ke Sistem</span>
                            <span>→</span>
                        </a>
                    <?php else: ?>
                        <a href="public/index.php" class="btn-secondary text-lg py-3 px-8">
                            <span>🗺️</span>
                            <span>Lihat Peta</span>
                        </a>
                        <?php if ($userData && $userData['level'] === 'admin'): ?>
                            <a href="admin/index.php" class="btn-accent text-lg py-3 px-8">
                                <span>⚙️</span>
                                <span>Halaman Admin</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-indigo-600 mb-2"><?= number_format($totalSarana) ?>+</div>
                    <div class="text-gray-600">Sarana Terdata</div>
                </div>
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-green-500 mb-2"><?= number_format($totalKabupaten) ?>+</div>
                    <div class="text-gray-600">Kabupaten</div>
                </div>
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-purple-500 mb-2"><?= number_format($totalKecamatan) ?>+</div>
                    <div class="text-gray-600">Kecamatan</div>
                </div>
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-blue-500 mb-2"><?= number_format($totalKelurahan) ?>+</div>
                    <div class="text-gray-600">Kelurahan</div>
                </div>
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-yellow-500 mb-2"><?= number_format($totalJenisSarana) ?>+</div>
                    <div class="text-gray-600">Jenis Sarana</div>
                </div>
                <div class="card p-6 text-center">
                    <div class="text-4xl font-bold text-red-500 mb-2"><?= number_format($dataTerbaru) ?>+</div>
                    <div class="text-gray-600">Data Baru (30 hari)</div>
                </div>
            </div>
            
            <!-- Features Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                <div class="card p-8 text-center">
                    <div class="feature-icon feature-1 mx-auto">
                        🗺️
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Visualisasi Peta</h3>
                    <p class="text-gray-600 mb-4">
                        Tampilkan data sarana dalam bentuk peta interaktif yang mudah dipahami dan dianalisis.
                    </p>
                </div>
                
                <div class="card p-8 text-center">
                    <div class="feature-icon feature-2 mx-auto">
                        📊
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Analisis Data</h3>
                    <p class="text-gray-600 mb-4">
                        Lakukan analisis mendalam terhadap distribusi dan kondisi sarana di berbagai lokasi.
                    </p>
                </div>
                
                <div class="card p-8 text-center">
                    <div class="feature-icon feature-3 mx-auto">
                        🔄
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Update Real-time</h3>
                    <p class="text-gray-600 mb-4">
                        Data selalu diperbarui secara real-time untuk memastikan informasi yang akurat.
                    </p>
                </div>
            </div>
            
            <!-- Preview Image -->
            <div class="card p-2 max-w-4xl mx-auto">
                <div class="bg-gray-200 border-2 border-dashed rounded-xl w-full h-64 md:h-96 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-5xl mb-4">🗺️</div>
                        <p class="text-gray-500 font-medium">Preview Peta Interaktif</p>
                        <p class="text-gray-400 text-sm mt-2">Login untuk melihat peta sarana secara lengkap</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-4 border-t border-gray-200">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-6 md:mb-0">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold">S</div>
                        <h2 class="text-xl font-bold text-gray-900">SIAMPARAN</h2>
                    </div>
                    <p class="mt-2 text-gray-600 max-w-md">
                        Sistem Informasi Pemetaan Sarana Terintegrasi untuk pengelolaan data yang efisien dan akurat.
                    </p>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-4">Navigasi</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-600 hover:text-gray-900">Beranda</a></li>
                            <?php if ($loggedIn): ?>
                                <li><a href="public/index.php" class="text-gray-600 hover:text-gray-900">Peta</a></li>
                                <?php if ($userData && $userData['level'] === 'admin'): ?>
                                    <li><a href="admin/index.php" class="text-gray-600 hover:text-gray-900">Admin</a></li>
                                <?php endif; ?>
                            <?php else: ?>
                                <li><a href="admin/login.php" class="text-gray-600 hover:text-gray-900">Masuk</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-4">Dukungan</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-600 hover:text-gray-900">Dokumentasi</a></li>
                            <li><a href="#" class="text-gray-600 hover:text-gray-900">Bantuan</a></li>
                            <li><a href="#" class="text-gray-600 hover:text-gray-900">Kontak</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="mt-12 pt-8 border-t border-gray-200 text-center text-gray-500">
                <p>© 2025 SIAMPARAN. Hak Cipta Dilindungi.</p>
            </div>
        </div>
    </footer>
</body>
</html>
