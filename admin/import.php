<?php // admin/import.php 
require_once __DIR__ . '/../config/auth.php';
requireLogin(); // Memerlukan login untuk mengakses halaman ini
$userData = getUserData();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Sarana</title>
    <link rel="icon" href="../assets/favicon.svg">
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
</head>

<body class="bg-tulang text-abu-900">
    <div class="min-h-screen max-w-3xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold">Import Data Sarana (XLSX/CSV)</h1>
            <a href="./index.php" class="underline text-sm">← Kembali ke Dashboard</a>
        </div>

        <div class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 space-y-4">
            <p class="text-sm text-abu-700">
                Unggah file <b>XLSX</b> (disarankan) atau <b>CSV</b> dengan kolom:
                <code class="bg-abu-50 px-2 py-1 rounded">nama_sarana, latitude, longitude, kabupaten, kecamatan, kelurahan, jenis</code>.<br />
                - Hanya <b>nama_sarana</b> yang wajib diisi. Kolom lain boleh dikosongkan dan akan tetap tersimpan.<br />
                - Kolom <b>jenis</b> opsional; jika kosong akan otomatis menjadi <b>Lainnya</b>. Banyak nilai bisa dipisah koma (,) atau pipa (|).<br />
                - Untuk file XLSX, pastikan ekstensi file benar (.xlsx) dan bukan .xls.
            </p>

            <div class="flex gap-2 flex-wrap">
                <a class="inline-block px-3 py-2 rounded-xl border bg-abu-50 hover:bg-abu-100 text-sm" href="../api/template_xlsx.php">⬇️ Unduh Template XLSX</a>
                <a class="inline-block px-3 py-2 rounded-xl border bg-abu-50 hover:bg-abu-100 text-sm" href="../assets/template_sarana.csv" download>⬇️ Unduh Template CSV (opsional)</a>
            </div>

            <form id="frm" class="space-y-3">
                <div>
                    <label class="block text-sm mb-1">File XLSX atau CSV</label>
                    <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,.csv,text/csv" required class="block w-full" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm mb-1">Baris Pertama Header?</label>
                        <select name="has_header" class="w-full px-3 py-2 border rounded-xl">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </div>
                </div>
                <div class="pt-2">
                    <button class="bg-abu-900 text-white px-4 py-2 rounded-xl hover:bg-abu-700" type="submit">Import</button>
                </div>
            </form>

            <div id="result" class="text-sm"></div>
        </div>
    </div>

    <script>
        const API = "../api/import_sarana.php";
        const DIAG = "../api/diag.php";
        const frm = document.getElementById('frm');
        const result = document.getElementById('result');
        const banner = document.createElement('div');
        banner.className = 'mb-4';
        document.querySelector('.max-w-3xl > .bg-white').insertAdjacentElement('beforebegin', banner);

        // Show diagnostics on load
        fetch(DIAG).then(r=>r.json()).then(d=>{
            const warns = [];
            if (!d.zip) warns.push('Ekstensi PHP Zip (ZipArchive) tidak aktif — impor XLSX tidak bisa dijalankan.');
            if (!d.simplexml) warns.push('Ekstensi PHP SimpleXML tidak aktif.');
            if (d.limits_bytes && d.limits_bytes.post_max_size < 10*1024*1024) warns.push('post_max_size kecil. Tingkatkan jika impor gagal untuk file besar.');
            if (d.limits_bytes && d.limits_bytes.upload_max_filesize < 10*1024*1024) warns.push('upload_max_filesize kecil. Tingkatkan jika impor gagal untuk file besar.');
            if (warns.length){
                banner.innerHTML = `<div class="p-3 rounded-2xl border bg-yellow-50 text-yellow-800 text-sm">${warns.map(w=>`<div>• ${w}</div>`).join('')}</div>`;
            }
        }).catch(()=>{});

        frm.addEventListener('submit', async (e) => {
            e.preventDefault();
            result.innerHTML = 'Mengupload & memproses...';
            const fd = new FormData(frm);
            try {
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                const status = res.status;
                const text = await res.text();
                let json;
                try { json = JSON.parse(text || '{}'); } catch { json = null; }
                if (!res.ok || (json && json.error)) {
                    const msg = (json && json.error) ? json.error : (text || 'Gagal import');
                    const detail = (json && json.detail) ? `<div class=\"mt-1\">Detail: ${json.detail}</div>` : (json ? '' : (text ? `<div class=\"mt-1\">Detail: ${text}</div>` : ''));
                    result.innerHTML = `<div class=\"mt-3 p-3 rounded-xl border bg-red-50 text-red-700\">`+
                        `<div><b>Gagal (${status}):</b> ${msg}</div>${detail}</div>`;
                    return;
                }

                result.innerHTML = `
          <div class="mt-3 p-3 rounded-xl border bg-abu-50">
            <div><b>Berhasil:</b> ${json.inserted} baris</div>
            <div><b>Terlewati:</b> ${json.skipped} baris</div>
            ${json.errors_preview && json.errors_preview.length ? `
              <div class="mt-2">
                <div class="font-medium">Contoh Error:</div>
                <ul class="list-disc ml-5">${json.errors_preview.map(e=>`<li>${e}</li>`).join('')}</ul>
                <div class="text-abu-600 mt-1">Hanya menampilkan hingga 25 error pertama.</div>
              </div>` : ''
            }
          </div>
          <div class="mt-3">
            <a href="./index.php" class="underline">Lihat data di Dashboard</a>
          </div>
        `;
            } catch (err) {
                result.innerHTML = `<div class="mt-3 p-3 rounded-xl border bg-red-50 text-red-700">Error: ${err.message}</div>`;
            }
        });
    </script>
</body>

</html>
