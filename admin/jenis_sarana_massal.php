<?php // admin/jenis_sarana_massal.php ?>
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
    <title>Ubah Jenis Sarana Massal - Admin</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
</head>
<body class="bg-tulang text-abu-900">
    <div class="min-h-screen max-w-4xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold">Ubah Jenis Sarana Massal</h1>
            <a href="./index.php" class="underline text-sm">← Kembali ke Dashboard</a>
        </div>

        <div class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 space-y-6">
            <div class="border-b pb-4">
                <h2 class="text-lg font-medium mb-2">Ubah Jenis Sarana Secara Massal</h2>
                <p class="text-sm text-abu-700">
                    Gunakan fitur ini untuk mengganti jenis sarana yang salah secara sekaligus.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Jenis Sarana Saat Ini</label>
                    <select id="jenis_lama" class="w-full px-3 py-2 border rounded-xl">
                        <option value="">Pilih jenis sarana...</option>
                    </select>
                    <div id="count_lama" class="text-xs text-abu-600 mt-1"></div>
                </div>
                
                <div>
                    <label class="block text-sm mb-1">Jenis Sarana Baru</label>
                    <select id="jenis_baru" class="w-full px-3 py-2 border rounded-xl">
                        <option value="">Pilih jenis sarana...</option>
                    </select>
                </div>
                
                <div class="md:col-span-2 flex justify-end">
                    <button id="btn_ubah" class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700" disabled>Ubah Jenis Sarana</button>
                </div>
            </div>

            <div class="border-t pt-4">
                <h3 class="font-medium mb-2">Import Perubahan Jenis Sarana dari CSV</h3>
                <p class="text-sm text-abu-700 mb-3">
                    Unggah file CSV dengan format: jenis_lama,jenis_baru
                    <a href="../assets/template_jenis_sarana.csv" class="underline" download>Unduh template CSV</a>
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
                <h3 class="font-medium mb-2">Daftar Jenis Sarana</h3>
                <div id="list_jenis" class="text-sm max-h-60 overflow-auto nice-scroll bg-abu-50 p-2 rounded"></div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = "/api";
        
        // Load daftar jenis sarana saat halaman dimuat
        document.addEventListener('DOMContentLoaded', async () => {
            await loadJenisSarana();
            
            // Tambahkan event listener untuk field jenis_lama
            document.getElementById('jenis_lama').addEventListener('change', async function() {
                const jenisId = this.value;
                const countDiv = document.getElementById('count_lama');
                const btnUbah = document.getElementById('btn_ubah');
                
                if (jenisId) {
                    try {
                        const res = await fetchJSON(`${API_BASE}/count_jenis_sarana.php`, {
                            method: 'POST',
                            body: JSON.stringify({
                                jenis_id: jenisId
                            })
                        });
                        countDiv.textContent = `${res.count} data akan diubah`;
                        countDiv.className = "text-xs text-abu-600 mt-1";
                        
                        // Enable tombol ubah jika kedua jenis sudah dipilih
                        const jenisBaru = document.getElementById('jenis_baru').value;
                        btnUbah.disabled = !jenisBaru || jenisBaru === jenisId;
                    } catch (err) {
                        countDiv.textContent = "Gagal menghitung data";
                        countDiv.className = "text-xs text-red-600 mt-1";
                    }
                } else {
                    countDiv.textContent = "";
                    btnUbah.disabled = true;
                }
            });
            
            // Tambahkan event listener untuk field jenis_baru
            document.getElementById('jenis_baru').addEventListener('change', function() {
                const jenisLama = document.getElementById('jenis_lama').value;
                const jenisBaru = this.value;
                const btnUbah = document.getElementById('btn_ubah');
                
                // Enable tombol ubah jika kedua jenis sudah dipilih dan berbeda
                btnUbah.disabled = !jenisLama || !jenisBaru || jenisLama === jenisBaru;
            });
            
            // Tambahkan event listener untuk form import
            document.getElementById('import_form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const resultDiv = document.getElementById('import_result');
                
                console.log('Import form submitted');
                
                try {
                    resultDiv.innerHTML = '<div class="p-3 rounded-xl border bg-abu-50">Mengimport perubahan jenis sarana...</div>';
                    
                    const response = await fetch(`${API_BASE}/import_jenis_sarana.php`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    console.log('Import response status:', response.status);
                    const text = await response.text();
                    console.log('Import response text:', text);
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
                            <div>Berhasil mengimport perubahan jenis sarana!</div>
                            <div>Total data diubah: ${data.total_updated}</div>
                            ${data.errors && data.errors.length > 0 ? 
                                `<div class="mt-2 text-red-700">Error: ${data.errors.join(', ')}</div>` : ''}
                        </div>
                    `;
                    
                    // Reset form
                    this.reset();
                    
                    // Reload daftar jenis sarana
                    await loadJenisSarana();
                } catch (err) {
                    console.error('Error in import form:', err);
                    resultDiv.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal mengimport: ${err.message}</div>`;
                }
            });
        });
        
        async function loadJenisSarana() {
            try {
                const jenis = await fetchJSON(`${API_BASE}/jenis.php`);
                
                // Isi dropdown jenis_lama
                const jenisLamaSelect = document.getElementById('jenis_lama');
                jenisLamaSelect.innerHTML = '<option value="">Pilih jenis sarana...</option>';
                jenis.forEach(j => {
                    const option = document.createElement('option');
                    option.value = j.id;
                    option.textContent = `${j.nama_jenis} (${j.count})`;
                    jenisLamaSelect.appendChild(option);
                });
                
                // Isi dropdown jenis_baru
                const jenisBaruSelect = document.getElementById('jenis_baru');
                jenisBaruSelect.innerHTML = '<option value="">Pilih jenis sarana...</option>';
                jenis.forEach(j => {
                    const option = document.createElement('option');
                    option.value = j.id;
                    option.textContent = j.nama_jenis;
                    jenisBaruSelect.appendChild(option);
                });
                
                // Isi daftar jenis
                const listDiv = document.getElementById('list_jenis');
                listDiv.innerHTML = jenis.map(j => `<div class="py-1">${j.nama_jenis} (${j.count})</div>`).join('');
            } catch (err) {
                console.error('Error loading jenis sarana:', err);
                document.getElementById('result').innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal memuat daftar jenis sarana: ${err.message}</div>`;
            }
        }
        
        async function fetchJSON(url, opts = {}) {
            console.log('Fetching URL:', url, 'with options:', opts);
            
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(opts.method || 'GET', url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                
                xhr.onload = function() {
                    console.log('Response status:', xhr.status);
                    
                    // Handle redirect to error page
                    if (xhr.responseURL && xhr.responseURL.includes('errors.infinityfree.net')) {
                        reject(new Error('Server error: Access denied (403). Please check server configuration.'));
                        return;
                    }
                    
                    try {
                        const data = JSON.parse(xhr.responseText);
                        console.log('Response text:', xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(data);
                        } else {
                            reject(new Error(data.error || 'HTTP Error: ' + xhr.status));
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        reject(new Error('Invalid JSON response: ' + xhr.responseText));
                    }
                };
                
                xhr.onerror = function() {
                    reject(new Error('Network error'));
                };
                
                xhr.send(opts.body || null);
            });
        }
        
        document.getElementById('btn_ubah').addEventListener('click', async () => {
            const jenisLama = document.getElementById('jenis_lama').value;
            const jenisBaru = document.getElementById('jenis_baru').value;
            const result = document.getElementById('result');
            
            console.log('Ubah jenis sarana button clicked. Jenis lama:', jenisLama, 'Jenis baru:', jenisBaru);
            
            if (!jenisLama || !jenisBaru) {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Harap pilih jenis lama dan jenis baru.</div>`;
                return;
            }
            
            if (jenisLama === jenisBaru) {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Jenis lama dan jenis baru tidak boleh sama.</div>`;
                return;
            }
            
            try {
                result.innerHTML = `<div class="p-3 rounded-xl border bg-abu-50">Mengubah jenis sarana...</div>`;
                
                // Gunakan XMLHttpRequest dengan form data untuk menghindari masalah method PUT
                const res = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', `${API_BASE}/jenis_sarana_massal.php`, true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                resolve(data);
                            } catch (e) {
                                reject(new Error('Invalid JSON response: ' + xhr.responseText));
                            }
                        } else {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                reject(new Error(data.error || 'HTTP Error: ' + xhr.status));
                            } catch (e) {
                                reject(new Error('HTTP Error: ' + xhr.status));
                            }
                        }
                    };
                    
                    xhr.onerror = function() {
                        reject(new Error('Network error'));
                    };
                    
                    xhr.send(JSON.stringify({
                        _method: 'PUT',
                        old_jenis_id: jenisLama,
                        new_jenis_id: jenisBaru
                    }));
                });
                
                result.innerHTML = `<div class="p-3 rounded-xl border bg-green-50 text-green-700">
                    Berhasil mengubah ${res.updated} data dari jenis lama ke jenis baru.
                </div>`;
                
                // Reset form
                document.getElementById('jenis_lama').value = '';
                document.getElementById('jenis_baru').value = '';
                document.getElementById('count_lama').textContent = '';
                document.getElementById('btn_ubah').disabled = true;
                
                // Reload daftar jenis sarana
                await loadJenisSarana();
            } catch (err) {
                console.error('Error in ubah jenis sarana:', err);
                result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal mengubah jenis sarana: ${err.message}</div>`;
            }
        });
    </script>
</body>
</html>