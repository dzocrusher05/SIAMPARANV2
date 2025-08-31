<?php // admin/wilayah.php ?>
<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin(); // Memerlukan login untuk mengakses halaman ini
$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Wilayah - Admin</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
</head>
<body class="bg-tulang text-abu-900">
    <div class="min-h-screen max-w-4xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold">Manajemen Wilayah</h1>
            <a href="./index.php" class="underline text-sm">← Kembali ke Dashboard</a>
        </div>

        <div class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 space-y-6">
            <div class="border-b pb-4">
                <h2 class="text-lg font-medium mb-2">Ubah Nama Wilayah Secara Massal</h2>
                <p class="text-sm text-abu-700">
                    Gunakan fitur ini untuk mengganti nama wilayah yang tidak sesuai secara sekaligus.
                    Contoh: mengubah "luwu timur" menjadi "Kab. Luwu Timur".
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-3">
                    <label class="block text-sm mb-1">Wilayah yang akan diubah</label>
                    <select id="jenis_wilayah" class="w-full px-3 py-2 border rounded-xl">
                        <option value="kabupaten">Kabupaten</option>
                        <option value="kecamatan">Kecamatan</option>
                        <option value="kelurahan">Kelurahan</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm mb-1">Nama Saat Ini</label>
                    <input type="text" id="nama_lama" class="w-full px-3 py-2 border rounded-xl" placeholder="Contoh: luwu timur">
                    <div id="count_lama" class="text-xs text-abu-600 mt-1"></div>
                </div>
                
                <div>
                    <label class="block text-sm mb-1">Nama Baru</label>
                    <input type="text" id="nama_baru" class="w-full px-3 py-2 border rounded-xl" placeholder="Contoh: Kab. Luwu Timur">
                </div>
                
                <div class="flex items-end">
                    <button id="btn_ubah" class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700 w-full">Ubah</button>
                </div>
            </div>

            <div class="border-t pt-4">
                <h3 class="font-medium mb-2">Import Perubahan Wilayah dari CSV</h3>
                <p class="text-sm text-abu-700 mb-3">
                    Unggah file CSV dengan format: jenis_wilayah,nama_lama,nama_baru
                    <a href="../assets/template_wilayah.csv" class="underline" download>Unduh template CSV</a>
                </p>
                <form id="import_form" class="space-y-3">
                    <div>
                        <label class="block text-sm mb-1">File CSV</label>
                        <input type="file" name="file" accept=".csv,text/csv" required class="block w-full">
                    </div>
                    <div>
                        <button type="submit" class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700">Import CSV</button>
                    </div>
                </form>
                <div id="import_result" class="text-sm mt-3"></div>
            </div>

            <div id="result" class="text-sm"></div>

            <div class="border-t pt-4">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-medium">Daftar Wilayah Saat Ini</h3>
                    <div class="flex gap-2">
                        <a href="../api/export_wilayah.php?type=kabupaten" class="text-xs underline">Ekspor Kabupaten</a>
                        <a href="../api/export_wilayah.php?type=kecamatan" class="text-xs underline">Ekspor Kecamatan</a>
                        <a href="../api/export_wilayah.php?type=kelurahan" class="text-xs underline">Ekspor Kelurahan</a>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <h4 class="font-medium text-sm mb-2">Kabupaten</h4>
                        <div id="list_kabupaten" class="text-sm max-h-40 overflow-auto nice-scroll bg-abu-50 p-2 rounded"></div>
                    </div>
                    <div>
                        <h4 class="font-medium text-sm mb-2">Kecamatan</h4>
                        <div id="list_kecamatan" class="text-sm max-h-40 overflow-auto nice-scroll bg-abu-50 p-2 rounded"></div>
                    </div>
                    <div>
                        <h4 class="font-medium text-sm mb-2">Kelurahan</h4>
                        <div id="list_kelurahan" class="text-sm max-h-40 overflow-auto nice-scroll bg-abu-50 p-2 rounded"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = "../api";
        
        // Load daftar wilayah saat halaman dimuat
        document.addEventListener('DOMContentLoaded', async () => {
            await loadWilayah();
            
            // Tambahkan event listener untuk field nama_lama
            document.getElementById('nama_lama').addEventListener('input', debounce(async function() {
                const nama = this.value.trim();
                const jenis = document.getElementById('jenis_wilayah').value;
                const countDiv = document.getElementById('count_lama');
                
                if (nama) {
                    try {
                        const res = await fetchJSON(`${API_BASE}/count_wilayah.php`, {
                            method: 'POST',
                            body: JSON.stringify({
                                type: jenis,
                                name: nama
                            })
                        });
                        countDiv.textContent = `${res.count} data akan diubah`;
                        countDiv.className = "text-xs text-abu-600 mt-1";
                    } catch (err) {
                        countDiv.textContent = "Gagal menghitung data";
                        countDiv.className = "text-xs text-red-600 mt-1";
                    }
                } else {
                    countDiv.textContent = "";
                }
            }, 300));
            
            // Tambahkan event listener untuk form import
            document.getElementById('import_form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const resultDiv = document.getElementById('import_result');
                
                try {
                    resultDiv.innerHTML = '<div class="p-3 rounded-xl border bg-abu-50">Mengimport perubahan wilayah...</div>';
                    
                    const response = await fetch(`${API_BASE}/import_wilayah.php`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const text = await response.text();
                    let data;
                    
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Response bukan JSON yang valid: ' + text);
                    }
                    
                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Terjadi kesalahan saat import');
                    }
                    
                    resultDiv.innerHTML = `
                        <div class="p-3 rounded-xl border bg-green-50 text-green-700">
                            <div>Berhasil mengimport perubahan wilayah!</div>
                            <div>Total data diubah: ${data.total_updated}</div>
                            ${data.errors && data.errors.length > 0 ? 
                                `<div class="mt-2 text-red-700">Error: ${data.errors.join(', ')}</div>` : ''}
                        </div>
                    `;
                    
                    // Reset form
                    this.reset();
                    
                    // Reload daftar wilayah
                    await loadWilayah();
                } catch (err) {
                    resultDiv.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal mengimport: ${err.message}</div>`;
                }
            });
        });
        
        async function loadWilayah() {
            try {
                const [kabupaten, kecamatan, kelurahan] = await Promise.all([
                    fetchJSON(`${API_BASE}/wilayah.php?type=kabupaten`),
                    fetchJSON(`${API_BASE}/wilayah.php?type=kecamatan`),
                    fetchJSON(`${API_BASE}/wilayah.php?type=kelurahan`)
                ]);
                
                document.getElementById('list_kabupaten').innerHTML = kabupaten.map(k => `<div class="py-1">${k}</div>`).join('');
                document.getElementById('list_kecamatan').innerHTML = kecamatan.map(k => `<div class="py-1">${k}</div>`).join('');
                document.getElementById('list_kelurahan').innerHTML = kelurahan.map(k => `<div class="py-1">${k}</div>`).join('');
            } catch (err) {
                console.error('Error loading wilayah:', err);
                document.getElementById('result').innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal memuat daftar wilayah: ${err.message}</div>`;
            }
        }
        
        async function fetchJSON(url, opts = {}) {
            const res = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json'
                },
                ...opts
            });
            const t = await res.text();
            let j;
            try {
                j = JSON.parse(t)
            } catch (e) {
                throw new Error(t)
            }
            if (!res.ok || j?.error) throw new Error(j?.error || t);
            return j;
        }
        
        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        document.getElementById('btn_ubah').addEventListener('click', async () => {
            const jenis = document.getElementById('jenis_wilayah').value;
            const namaLama = document.getElementById('nama_lama').value.trim();
            const namaBaru = document.getElementById('nama_baru').value.trim();
            const result = document.getElementById('result');
            
            if (!namaLama || !namaBaru) {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Harap isi nama lama dan nama baru.</div>`;
                return;
            }
            
            if (namaLama === namaBaru) {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Nama lama dan nama baru tidak boleh sama.</div>`;
                return;
            }
            
            try {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-abu-50">Mengubah nama wilayah...</div>`;
                
                const res = await fetchJSON(`${API_BASE}/wilayah.php`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        type: jenis,
                        old_name: namaLama,
                        new_name: namaBaru
                    })
                });
                
                result.innerHTML = `<div class="p-3 rounded-xl border bg-green-50 text-green-700">
                    Berhasil mengubah ${res.updated} data dari "${namaLama}" menjadi "${namaBaru}".
                    ${res.count_before_update ? ` (${res.count_before_update} data diubah)` : ''}
                </div>`;
                
                // Reset form
                document.getElementById('nama_lama').value = '';
                document.getElementById('nama_baru').value = '';
                document.getElementById('count_lama').textContent = '';
                
                // Reload daftar wilayah
                await loadWilayah();
            } catch (err) {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal mengubah wilayah: ${err.message}</div>`;
            }
        });
    </script>
</body>
</html>