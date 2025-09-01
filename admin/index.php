<?php
// Stable admin dashboard (minimal, POST-only for update/delete)
require_once __DIR__ . '/../config/auth.php';
requireLogin();
$user = getUserData();
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin • Pemetaan Sarana</title>
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
      body{background:#f6f7fb;color:#111827}
      .container{max-width:1100px;margin:0 auto;padding:16px}
      .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.04)}
      .table{width:100%;border-collapse:collapse}
      .table th,.table td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:14px;vertical-align:top}
      .table thead th{background:#f8fafc;font-weight:600}
      .btn{display:inline-block;border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
      .btn.primary{background:#111827;color:#fff;border-color:#111827}
      .btn.danger{border-color:#ef4444;color:#ef4444}
      .grid{display:grid;gap:10px}
      .grid.cols-2{grid-template-columns:1fr 1fr}
      .field label{display:block;font-size:12px;color:#6b7280;margin-bottom:4px}
      .field input{width:100%;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px}
      .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
      .pill{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:4px 8px;font-size:12px}
      /* Pagination styling */
      .pagination { display: flex; align-items: center; gap: 4px; }
      .pagination .btn { padding: 6px 12px; min-width: 36px; text-align: center; }
      .pagination .btn.primary { background: #111827; color: white; border-color: #111827; }
      .pagination .btn:hover:not(.primary) { background: #f8fafc; }
      .pagination .pill { padding: 4px 6px; }
      dialog{border:none;padding:0}
      dialog::backdrop{background:rgba(17,24,39,.45);backdrop-filter:blur(2px)}
      .modal-frame{width:min(720px,92vw);background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 30px 80px rgba(0,0,0,.18);display:flex;flex-direction:column;max-height:80vh}
      .modal-header{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #eef2f7;border-top-left-radius:16px;border-top-right-radius:16px;background:linear-gradient(180deg,#fafafa,#ffffff)}
      .modal-header #dlgTitle{font-weight:700}
      .modal-close{border:none;background:transparent;font-size:16px;cursor:pointer;color:#6b7280}
      .modal-close:hover{color:#111827}
      .modal-body{padding:14px;overflow:auto}
      .modal-footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 14px;border-top:1px solid #eef2f7;border-bottom-left-radius:16px;border-bottom-right-radius:16px;background:#fafafa}
      /* Modal form polish */
      .modal-form{display:grid;gap:12px}
      @media(min-width:720px){ .modal-form.grid-2{grid-template-columns:1fr 1fr} }
      .modal-form .field{display:flex;flex-direction:column}
      .modal-form .field label{font-size:12px;color:#6b7280;margin-bottom:4px}
      .modal-form .field input{height:38px;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;transition:border-color .2s, box-shadow .2s}
      .modal-form .field input:focus{outline:none;border-color:#a3b7ff;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
      .modal-form .section{padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fafafa}
      .modal-form .section .section-title{font-weight:600;margin-bottom:6px}
      .jenis-list{max-height:220px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:6px}
      .jenis-item{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;border-radius:10px;border:1px solid transparent}
      .jenis-item:hover{background:#f8fafc;border-color:#e5e7eb}
      .jenis-search{margin-bottom:8px}
      .jenis-search input{width:100%;height:38px;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;transition:border-color .2s, box-shadow .2s}
      .jenis-search input:focus{outline:none;border-color:#a3b7ff;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
      .jenis-item input{transform:translateY(1px)}
      .jenis-item .cnt{font-size:12px;color:#6b7280}
    </style>
  </head>
  <body>
    <div class="container">
      <div class="toolbar" style="justify-content:space-between;margin-bottom:12px">
        <div style="font-weight:700">Dashboard Admin</div>
        <div class="toolbar" style="gap:10px">
          <a class="btn" href="../public/index.php" target="_blank" title="Buka halaman peta">🗺️ Lihat Peta</a>
          <div class="pill">Login: <?php echo htmlspecialchars($user['nama_lengkap']??''); ?></div>
        </div>
      </div>

      <div class="grid cols-2" style="grid-template-columns: 260px 1fr; align-items:start">
        <aside class="card" style="padding:12px;position:sticky;top:12px">
          <div style="font-weight:600;margin-bottom:8px">Menu</div>
          <div class="grid" style="grid-template-columns:1fr">
            <button class="btn" id="tabBtnSarana">📍 Data Sarana</button>
            <button class="btn" id="tabBtnJenis">🏷️ Jenis Sarana</button>
            <a class="btn" href="../public/index.php" target="_blank">🗺️ Halaman Peta</a>
            <a class="btn" href="./import.php">⬆️ Import Sarana</a>
            <a class="btn" href="./wilayah.php">🌍 Manajemen Wilayah</a>
            <a class="btn" href="./jenis_sarana_massal.php">🔄 Ubah Jenis Sarana Massal</a>
            <a class="btn" href="./change_password.php">🔑 Ganti Password</a>
            <a class="btn" href="./logout.php">🚪 Logout</a>
          </div>
        </aside>

        <main class="grid" style="grid-template-columns:1fr; gap:12px">
          <section id="panelSarana" class="card" style="padding:12px">
            <div class="toolbar" style="justify-content:space-between;margin-bottom:8px">
              <div style="font-weight:600">Data Sarana</div>
              <div class="toolbar">
                <input id="q" placeholder="Cari nama/wilayah.." class="field" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:10px">
                <select id="perPage" class="field" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:10px">
                  <option value="20">20/hal</option>
                  <option value="50">50/hal</option>
                  <option value="100">100/hal</option>
                </select>
                <button id="btnAddSarana" class="btn primary">+ Tambah</button>
              </div>
            </div>
            <div style="overflow:auto">
              <table class="table">
                <thead><tr><th>Nama</th><th>Koordinat</th><th>Wilayah</th><th>Jenis</th><th style="text-align:right">Aksi</th></tr></thead>
                <tbody id="saranaRows"></tbody>
              </table>
            </div>
            <div class="toolbar" style="justify-content:space-between;margin-top:8px">
              <div id="pagerInfo" class="pill"></div>
              <div id="pagerNav" class="pagination"></div>
            </div>
          </section>

          <section id="panelJenis" class="card" style="padding:12px;display:none">
            <div class="toolbar" style="justify-content:space-between;margin-bottom:8px">
              <div style="font-weight:600">Jenis Sarana</div>
              <button id="btnAddJenis" class="btn primary">+ Tambah</button>
            </div>
            <div style="margin-bottom:12px">
              <div class="text-sm text-abu-700">
                <strong>Info:</strong> Icon jenis sarana harus berekstensi .png dengan ukuran 32px x 32px. 
                Jika tidak diupload, akan menggunakan icon default dari /assets/icon.
              </div>
            </div>
            <div style="overflow:auto">
              <table class="table">
                <thead><tr><th>Nama Jenis</th><th style="text-align:right;width:120px">Jumlah</th><th style="text-align:right;width:220px">Aksi</th></tr></thead>
                <tbody id="jenisRows"></tbody>
              </table>
            </div>
          </section>
        </main>
      </div>
    </div>

    <dialog id="dlg" class="modal">
      <form method="dialog" class="modal-frame">
        <div class="modal-header">
          <div id="dlgTitle">Form</div>
          <button type="button" class="modal-close" id="dlgCloseX" aria-label="Tutup">✕</button>
        </div>
        <div id="dlgBody" class="modal-body"></div>
        <div class="modal-footer">
          <button class="btn" value="cancel">Batal</button>
          <button class="btn primary" value="ok" id="dlgOk">Simpan</button>
        </div>
      </form>
    </dialog>

    <script>
      const API = "../api";
      const $ = (s,r=document)=>r.querySelector(s);
      const $$ = (s,r=document)=>Array.from(r.querySelectorAll(s));
      const state = { page:1, perPage:20, q:"", total:0, totalPages:1, filterJenis:[], sarana:[], jenisMaster:[] };

      function escapeHtml(s){return String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");}

      async function jfetch(url, opt={}){
        console.log('Fetching URL:', url);
        const res = await fetch(url,{ headers:{'Content-Type':'application/json'}, ...opt });
        const txt = await res.text();
        console.log('Response status:', res.status);
        console.log('Response text:', txt);
        let j; try{ j = JSON.parse(txt) } catch(e){ throw new Error(txt) }
        if (!res.ok || j?.error) throw new Error(j?.error || txt);
        return j;
      }

      async function loadSarana(){
        try {
          const u = new URL(API + '/sarana.php', location.origin);
          u.searchParams.set('page', state.page);
          u.searchParams.set('per_page', state.perPage);
          if (state.q) u.searchParams.set('q', state.q);
          if (state.filterJenis?.length) u.searchParams.set('jenis', state.filterJenis.join(','));
          const r = await jfetch(u.pathname + u.search);
          state.total = r.total; state.totalPages = r.total_pages; const rows = r.data||[];
          state.sarana = rows;
          $('#saranaRows').innerHTML = rows.map(s=>{
            const jenis = Array.isArray(s.jenis)? s.jenis.join(', ') : '';
            return `<tr><td class="font-medium">${escapeHtml(s.nama_sarana)}</td>
              <td>${s.latitude}, ${s.longitude}</td>
              <td>${escapeHtml(`${s.kelurahan}, ${s.kecamatan}, ${s.kabupaten}`)}</td>
              <td>${escapeHtml(jenis)}</td>
              <td style="text-align:right;white-space:nowrap">
                <button class="btn" data-edit="${s.id}">Edit</button>
                <button class="btn danger" data-del="${s.id}">Hapus</button>
              </td></tr>`;
          }).join('');
          $('#pagerInfo').textContent = `Total ${state.total} data`;
          const nav = [];
          const maxButtons = 10; // Maksimal tombol paginasi yang ditampilkan
          const halfButtons = Math.floor(maxButtons / 2);
          
          // Hitung range halaman yang akan ditampilkan
          let startPage, endPage;
          if (state.totalPages <= maxButtons) {
            // Tampilkan semua halaman jika kurang dari atau sama dengan maxButtons
            startPage = 1;
            endPage = state.totalPages;
          } else {
            // Tentukan start dan end page berdasarkan halaman saat ini
            if (state.page <= halfButtons) {
              // Jika di awal, tampilkan dari halaman 1
              startPage = 1;
              endPage = maxButtons;
            } else if (state.page + halfButtons >= state.totalPages) {
              // Jika di akhir, tampilkan dari halaman terakhir
              startPage = state.totalPages - maxButtons + 1;
              endPage = state.totalPages;
            } else {
              // Jika di tengah, tampilkan setengah sebelum dan setengah sesudah
              startPage = state.page - halfButtons;
              endPage = state.page + halfButtons;
            }
          }
          
          // Tambahkan tombol Previous jika tidak di halaman pertama
          if (state.page > 1) {
            nav.push(`<button class="btn" data-goto="${state.page - 1}">‹ Prev</button>`);
          }
          
          // Tambahkan tombol First page jika startPage > 1
          if (startPage > 1) {
            nav.push(`<button class="btn" data-goto="1">1</button>`);
            if (startPage > 2) {
              nav.push(`<span class="pill">…</span>`);
            }
          }
          
          // Tambahkan tombol untuk setiap halaman dalam range
          for (let i = startPage; i <= endPage; i++) {
            const active = i === state.page;
            nav.push(`<button class="btn ${active ? 'primary' : ''}" data-goto="${i}">${i}</button>`);
          }
          
          // Tambahkan tombol Last page jika endPage < totalPages
          if (endPage < state.totalPages) {
            if (endPage < state.totalPages - 1) {
              nav.push(`<span class="pill">…</span>`);
            }
            nav.push(`<button class="btn" data-goto="${state.totalPages}">${state.totalPages}</button>`);
          }
          
          // Tambahkan tombol Next jika tidak di halaman terakhir
          if (state.page < state.totalPages) {
            nav.push(`<button class="btn" data-goto="${state.page + 1}">Next ›</button>`);
          }
          
          $('#pagerNav').innerHTML = nav.join('');
        } catch (err) {
          console.error('Error loading sarana:', err);
          $('#saranaRows').innerHTML = `<tr><td colspan="5">Error loading data: ${err.message}</td></tr>`;
        }
      }

      async function loadJenis(){
        try {
          const rows = await jfetch(API + '/jenis.php');
          state.jenisMaster = rows;
          $('#jenisRows').innerHTML = rows.map(j=>{
            return `<tr><td>${escapeHtml(j.nama_jenis)} ${j.has_custom_icon ? '<span class="pill">✓ Icon Khusus</span>' : '<span class="pill">Icon Default</span>'}</td>
              <td style="text-align:right">${j.count??0}</td>
              <td style="text-align:right;white-space:nowrap">
                <button class="btn" data-view-jenis="${j.id}">Lihat</button>
                <button class="btn" data-edit-jenis="${j.id}" data-nama="${escapeHtml(j.nama_jenis)}">Edit</button>
                <button class="btn danger" data-del-jenis="${j.id}">Hapus</button>
              </td></tr>`;
          }).join('');
        } catch (err) {
          console.error('Error loading jenis:', err);
          $('#jenisRows').innerHTML = `<tr><td colspan="3">Error loading data: ${err.message}</td></tr>`;
        }
      }

      // Handlers Sarana
      document.addEventListener('click', async (e)=>{
        try {
          const el = e.target.closest('[data-goto]');
          if (el){ state.page = parseInt(el.dataset.goto,10); await loadSarana(); return }

          const ed = e.target.closest('[data-edit]');
          if (ed){ const id = parseInt(ed.dataset.edit,10); openEditSarana(id); return }
          const del = e.target.closest('[data-del]');
          if (del){ 
            const id = parseInt(del.dataset.del,10); 
            Swal.fire({
              title: 'Anda yakin?',
              text: "Data yang dihapus tidak dapat dikembalikan!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#d33',
              cancelButtonColor: '#3085d6',
              confirmButtonText: 'Ya, hapus!',
              cancelButtonText: 'Batal'
            }).then(async (result) => {
              if (result.isConfirmed) {
                try {
                  await jfetch(API+'/sarana.php', { method:'POST', body: JSON.stringify({_action:'delete', delete_id:id}) }); 
                  await loadSarana(); 
                  Swal.fire(
                    'Dihapus!',
                    'Data berhasil dihapus.',
                    'success'
                  )
                } catch (err) {
                  console.error('Error deleting sarana:', err);
                  Swal.fire(
                    'Gagal!',
                    'Terjadi kesalahan saat menghapus data.',
                    'error'
                  )
                }
              }
            })
            return 
          }

          const vj = e.target.closest('[data-view-jenis]');
          if (vj){ state.filterJenis = [parseInt(vj.dataset.viewJenis,10)]; $('#panelSarana').style.display='block'; $('#panelJenis').style.display='none'; await loadSarana(); return }

          const ej = e.target.closest('[data-edit-jenis]');
          if (ej){ const id = parseInt(ej.dataset.editJenis,10); openEditJenis(id, ej.getAttribute('data-nama')||''); return }
          const dj = e.target.closest('[data-del-jenis]');
          if (dj){ 
            const id = parseInt(dj.dataset.delJenis,10); 
            if (confirm('Hapus jenis ini?')){
              try {
                await jfetch(API+'/jenis.php', { method:'POST', body: JSON.stringify({_action:'delete', delete_id:id}) }); 
                await loadJenis(); 
              } catch (err) {
                console.error('Error deleting jenis:', err);
                alert('Error deleting jenis: ' + err.message);
              }
            } 
            return 
          }
        } catch (err) {
          console.error('Error in click handler:', err);
        }
      });

      $('#perPage').addEventListener('change', async()=>{
        try {
          state.perPage = parseInt($('#perPage').value,10); 
          state.page=1; 
          await loadSarana(); 
        } catch (err) {
          console.error('Error in perPage change:', err);
        }
      });
      $('#q').addEventListener('keydown', async (e)=>{
        try {
          if (e.key==='Enter'){ 
            state.q=$('#q').value.trim(); 
            state.page=1; 
            await loadSarana(); 
          }
        } catch (err) {
          console.error('Error in search:', err);
        }
      });
      $('#btnAddSarana').addEventListener('click', ()=> openEditSarana(0));

      // Tabs
      $('#tabBtnSarana').onclick = ()=>{
        $('#panelSarana').style.display='block'; 
        $('#panelJenis').style.display='none'; 
      };
      $('#tabBtnJenis').onclick = ()=>{
        $('#panelSarana').style.display='none'; 
        $('#panelJenis').style.display='block'; 
        loadJenis(); 
      };

      async function ensureJenisMaster(){
        if (!Array.isArray(state.jenisMaster) || !state.jenisMaster.length){
          try{ 
            console.log('Loading jenis master data...');
            state.jenisMaster = await jfetch(API + '/jenis.php');
            console.log('Jenis master data loaded:', state.jenisMaster);
          }catch(err){ 
            console.error('Error loading jenis master:', err);
            state.jenisMaster = []; 
          }
        }
      }

      async function openEditSarana(id){
        console.log('openEditSarana called with id:', id);
        await ensureJenisMaster();
        const dlg = $('#dlg');
        const body = document.createElement('div');
        // Determine selected jenis ids for prefill
        let selJenis = [];
        if (id && Array.isArray(state.sarana)){
          const s0 = state.sarana.find(x=>parseInt(x.id,10)===parseInt(id,10));
          if (s0){
            if (s0.jenis_ids){ selJenis = String(s0.jenis_ids).split(',').map(v=>parseInt(v,10)).filter(Boolean); }
            else if (Array.isArray(s0.jenis) && state.jenisMaster.length){
              const nameMap = new Map(state.jenisMaster.map(j=>[j.nama_jenis, j.id]));
              selJenis = s0.jenis.map(n=>nameMap.get(n)).filter(Boolean);
            }
          }
        }
        const jenisHtml = state.jenisMaster.length
          ? `<div class="section" style="grid-column:1/3">
               <div class="section-title">Jenis Sarana</div>
               <div class="jenis-search"><input id="f_jenis_search" placeholder="Ketik untuk mencari jenis sarana..." autocomplete="off"></div>
               <div class="jenis-list">${state.jenisMaster.map(j=>`
                 <label class="jenis-item"><span><input type="checkbox" class="f_jenis" value="${j.id}" ${selJenis.includes(j.id)?'checked':''}> ${escapeHtml(j.nama_jenis)}</span><span class="cnt">${j.count??0}</span></label>
               `).join('')}</div>
             </div>`
          : '';
        console.log('Jenis HTML generated:', jenisHtml);
        console.log('Jenis master count:', state.jenisMaster.length);
        console.log('Selected jenis:', selJenis);

        body.innerHTML = `<div class="modal-form grid-2">
          <div class="field" style="grid-column:1/3"><label>Nama Sarana</label><input id="f_nama" required></div>
          <div class="field"><label>Latitude</label><input id="f_lat" type="number" step="0.0000001" required></div>
          <div class="field"><label>Longitude</label><input id="f_lng" type="number" step="0.0000001" required></div>
          <div class="field"><label>Kabupaten</label><input id="f_kab" required></div>
          <div class="field"><label>Kecamatan</label><input id="f_kec" required></div>
          <div class="field" style="grid-column:1/3"><label>Kelurahan</label><input id="f_kel" required></div>
          ${jenisHtml}
        </div>`;
        $('#dlgTitle').textContent = id? 'Ubah Data Sarana' : 'Tambah Data Sarana';
        $('#dlgBody').innerHTML = '';
        $('#dlgBody').appendChild(body);
        // Log jumlah checkbox jenis yang ditemukan
        setTimeout(() => {
          const jenisCheckboxes = $('.f_jenis');
          console.log('Jenis checkboxes in form:', jenisCheckboxes.length);
          if (jenisCheckboxes.length > 0) {
            const checked = jenisCheckboxes.filter(x => x.checked);
            console.log('Checked jenis checkboxes:', checked.length);
            console.log('Checked jenis values:', checked.map(x => x.value));
          }
        }, 100);
        // aktifkan autosuggest/filter jenis
        const jenisSearch = $('#f_jenis_search');
        if (jenisSearch){
          setTimeout(()=>jenisSearch.focus(), 50);
          jenisSearch.addEventListener('input', ()=>{
            const q = jenisSearch.value.trim().toLowerCase();
            $$('.jenis-item').forEach(it=>{
              const name = it.textContent.toLowerCase();
              it.style.display = name.includes(q)? '' : 'none';
            });
          });
        }
        // Prefill when editing existing record
        if (id && Array.isArray(state.sarana)){
          const s = state.sarana.find(x=>parseInt(x.id,10)===parseInt(id,10));
          if (s){
            $('#f_nama').value = s.nama_sarana || '';
            $('#f_lat').value = s.latitude ?? '';
            $('#f_lng').value = s.longitude ?? '';
            $('#f_kab').value = s.kabupaten || '';
            $('#f_kec').value = s.kecamatan || '';
            $('#f_kel').value = s.kelurahan || '';
          }
        }
        dlg.returnValue=''; dlg.showModal();
        $('#dlgCloseX').onclick = ()=> dlg.close('cancel');
        $('#dlg').onclose = async ()=>{
          if (dlg.returnValue==='ok'){
            const nama_sarana = $('#f_nama').value.trim();
            const latitude = $('#f_lat').value.trim();
            const longitude = $('#f_lng').value.trim();

            if (!nama_sarana || !latitude || !longitude) {
              Swal.fire('Data Tidak Lengkap', 'Nama Sarana, Latitude, dan Longitude wajib diisi.', 'error');
              return;
            }

            const confirmText = id ? 'Apakah Anda yakin ingin merubah data?' : 'Apakah Anda yakin ingin menyimpan data?';
            const result = await Swal.fire({
              title: 'Konfirmasi',
              text: confirmText,
              icon: 'question',
              showCancelButton: true,
              confirmButtonText: 'Ya, simpan!',
              cancelButtonText: 'Batal'
            });

            if (result.isConfirmed) {
              const payload = { id,
                nama_sarana,
                latitude: parseFloat(latitude||0),
                longitude: parseFloat(longitude||0),
                kabupaten: $('#f_kab').value.trim(),
                kecamatan: $('#f_kec').value.trim(),
                kelurahan: $('#f_kel').value.trim()
              };
              // collect jenis_ids if any
              console.log('Collecting jenis IDs...');
              const jboxes = $('.f_jenis');
              console.log('Jenis checkboxes found:', jboxes ? jboxes.length : 'undefined');
              if (jboxes && jboxes.length){
                console.log('Processing checkboxes...');
                const checkedBoxes = jboxes.filter(x=>x.checked);
                console.log('Checked checkboxes count:', checkedBoxes.length);
                const ids = checkedBoxes.map(x=>parseInt(x.value,10)).filter(Boolean);
                console.log('Processed IDs:', ids);
                payload.jenis_ids = ids;
                console.log('Jenis IDs collected:', ids);
              } else {
                console.log('No jenis checkboxes found');
                // Coba cari dengan selector alternatif
                const altBoxes = document.querySelectorAll('.f_jenis');
                console.log('Alternative search found checkboxes:', altBoxes.length);
                if (altBoxes.length > 0) {
                  const checkedAlt = Array.from(altBoxes).filter(x=>x.checked);
                  const altIds = checkedAlt.map(x=>parseInt(x.value,10)).filter(Boolean);
                  console.log('Alternative processed IDs:', altIds);
                  if (altIds.length > 0) {
                    payload.jenis_ids = altIds;
                    console.log('Using alternative jenis IDs:', altIds);
                  }
                }
              }
              try {
                console.log('Sending payload:', payload);
                console.log('Jenis IDs in payload:', payload.jenis_ids);
                await jfetch(API+'/sarana.php', { method:'POST', body: JSON.stringify(payload) });
                await loadSarana();
                Swal.fire({toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, title: 'Data berhasil disimpan', icon: 'success'});
              } catch (err) {
                Swal.fire('Error', err.message, 'error');
              }
            }
          }
        };
      }

      function openEditJenis(id, nama){
        console.log('openEditJenis called with id:', id, 'nama:', nama);
        const dlg = $('#dlg');
        $('#dlgBody').innerHTML = `
          <div class="field">
            <label>Nama Jenis</label>
            <input id="fj" value="${(nama||'').replaceAll('"','&quot;')}">
          </div>
          <div class="field">
            <label>Icon Jenis Sarana (Opsional)</label>
            <input type="file" id="fj_icon" accept=".png" class="w-full px-3 py-2 border rounded-xl">
            <div class="text-xs text-abu-600 mt-1">
              Format: PNG, Ukuran: 32px x 32px
            </div>
            <div class="text-xs text-abu-600 mt-1">
              Catatan: Icon file statis di /assets/icon akan digunakan jika tidak ada icon khusus
            </div>
            ${id ? `<div class="text-xs text-abu-600 mt-1">Biarkan kosong jika tidak ingin mengganti icon</div>` : ''}
          </div>
        `;
        dlg.returnValue=''; dlg.showModal();
        $('#dlg').onclose = async ()=>{
          if (dlg.returnValue==='ok'){
            const namaJenis = $('#fj').value.trim();
            const iconFile = $('#fj_icon').files[0];
            
            if (!namaJenis) {
              Swal.fire('Error', 'Nama jenis sarana wajib diisi', 'error');
              return;
            }
            
            // Jika ada file icon, validasi dulu
            if (iconFile) {
              if (iconFile.type !== 'image/png') {
                Swal.fire('Error', 'File icon harus berekstensi .png', 'error');
                return;
              }
              
              // Validasi ukuran file (maksimal 100KB)
              if (iconFile.size > 100000) {
                Swal.fire('Error', 'Ukuran file icon terlalu besar (maksimal 100KB)', 'error');
                return;
              }
            }
            
            try {
              if (iconFile) {
                // Upload dengan icon
                const formData = new FormData();
                if (id) {
                  formData.append('id', id);
                }
                formData.append('nama_jenis', namaJenis);
                formData.append('icon', iconFile);
                
                // Gunakan fetch langsung untuk upload file
                const response = await fetch(API+'/jenis.php', {
                  method: 'POST',
                  body: formData
                });
                
                if (!response.ok) {
                  const errorData = await response.json();
                  throw new Error(errorData.error || 'Gagal upload jenis sarana dengan icon');
                }
              } else {
                // Upload tanpa icon (seperti sebelumnya)
                const payload = id? { id, nama_jenis: namaJenis } : { nama_jenis: namaJenis };
                console.log('Sending jenis payload:', payload);
                await jfetch(API+'/jenis.php', { method:'POST', body: JSON.stringify(payload) });
              }
              
              await loadJenis();
              Swal.fire({
                toast: true, 
                position: 'top-end', 
                showConfirmButton: false, 
                timer: 3000, 
                title: 'Jenis sarana berhasil disimpan', 
                icon: 'success'
              });
            } catch (err) {
              console.error('Error saving jenis:', err);
              Swal.fire('Error', err.message, 'error');
            }
          }
        };
      }

      // Initial load
      loadSarana().catch(e=>{
        console.error('Error in initial loadSarana:', e);
        $('#saranaRows').innerHTML = `<tr><td colspan="5">Error loading data: ${e.message}</td></tr>`;
      });
    </script>
  </body>
</html>
