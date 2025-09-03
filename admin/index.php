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
      .container{max-width:1100px;margin:0 auto;padding:16px;transition:max-width .25s ease,padding .2s ease}
      .container.wide{max-width:100vw;padding-left:8px;padding-right:8px}
      .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.04)}
      .table{width:100%;border-collapse:collapse}
      .table th,.table td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:14px;vertical-align:top}
      .table thead th{background:#f8fafc;font-weight:600}
      .btn{display:inline-block;border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
      .btn.primary{background:#111827;color:#fff;border-color:#111827}
      .btn.danger{border-color:#ef4444;color:#ef4444}
      .grid{display:grid;gap:10px}
      /* Sidebar collapse support + smooth transition */
      #layoutGrid{transition:grid-template-columns .25s ease}
      #layoutGrid aside{transition:width .25s ease, opacity .2s ease}
      #layoutGrid.collapsed{grid-template-columns:0 1fr !important}
      #layoutGrid.collapsed aside{width:0;opacity:0;pointer-events:none}
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
      /* Export dropdown */
      .dropdown{ position:relative }
      /* Dropdown menus (animated) */
      .dropdown-menu{ position:absolute; right:0; top:100%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,.12); padding:6px; z-index:20; min-width:140px; display:block; opacity:0; visibility:hidden; transform: translateY(4px) scale(.98); transition: opacity .18s ease, transform .18s ease, visibility 0s linear .18s }
      .dropdown-menu.show{ opacity:1; visibility:visible; transform: translateY(0) scale(1); transition: opacity .18s ease, transform .18s ease, visibility 0s linear 0s }
      /* Floating menu to ignore parent overflow and table height */
      .dropdown-menu.float{ position:fixed !important; z-index:1000; }
      .dropdown-menu.show{ display:block }
      .dropdown-menu .btn{ display:block; width:100%; text-align:left; margin:2px 0 }

      /* Wilayah filter popup polish */
      #menuWilFilter{ border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.15); width:min(420px,96vw); overflow:hidden; padding:0; }
      #menuWilFilter .fw-body{ padding:10px 12px; max-height:320px; overflow:auto; }
      #menuWilFilter .fw-grid{ display:grid; gap:10px; grid-template-columns:1fr 1fr; }
      #menuWilFilter .fw-full{ grid-column:1 / 3; }
      #menuWilFilter .field label{ display:block; font-size:12px; color:#6b7280; margin-bottom:4px; }
      #menuWilFilter select{ width:100%; height:38px; border:1px solid #e5e7eb; border-radius:10px; padding:6px 10px; background:#fff; }
      #menuWilFilter .fw-actions{ display:flex; justify-content:space-between; gap:8px; padding:10px 12px; border-top:1px solid #eef2f7; background:#fafafa; position:sticky; bottom:0; }
      /* Export popup polish (mirror wilayah styles) */
      #menuExport{ border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,.15); width:min(520px,96vw); overflow:hidden; padding:0; }
      #menuExport .fw-body{ padding:10px 12px; max-height:360px; overflow:auto; }
      #menuExport .fw-grid{ display:grid; gap:10px; grid-template-columns:1fr 1fr; }
      #menuExport .fw-full{ grid-column:1 / 3; }
      #menuExport .field label{ display:block; font-size:12px; color:#6b7280; margin-bottom:4px; }
      #menuExport select{ width:100%; height:38px; border:1px solid #e5e7eb; border-radius:10px; padding:6px 10px; background:#fff; }
      #menuExport .fw-actions{ display:flex; justify-content:space-between; gap:8px; padding:10px 12px; border-top:1px solid #eef2f7; background:#fafafa; position:sticky; bottom:0; }
      #menuExport .fw-search{ margin-bottom:6px }
      #menuExport .fw-search input{ width:100%; height:36px; border:1px solid #e5e7eb; border-radius:10px; padding:6px 10px; }
      #menuExport select[size]{ height:auto; max-height:180px; }
      /* Jenis list in Export popup */
      #menuExport .jenis-list{ display:grid; grid-template-columns:1fr 1fr; gap:8px; max-height:260px; overflow:auto; border:1px solid #e5e7eb; border-radius:10px; background:#fff; padding:8px }
      #menuExport .jenis-item{ display:flex; align-items:center; gap:8px; padding:8px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
      #menuExport .jenis-item:hover{ background:#f8fafc; border-color:#e5e7eb }
      #menuExport .jenis-item input{ margin-right:6px }
      #menuExport .jenis-item .cnt{ margin-left:auto; font-size:12px; color:#6b7280; background:#f3f4f6; padding:2px 8px; border-radius:999px }
      #menuWilFilter .fw-search{ margin-bottom:6px }
      #menuWilFilter .fw-search input{ width:100%; height:36px; border:1px solid #e5e7eb; border-radius:10px; padding:6px 10px; }
      #menuWilFilter select[size]{ height:auto; max-height:180px; }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="toolbar" style="justify-content:space-between;margin-bottom:12px">
        <div style="font-weight:700;display:flex;align-items:center;gap:8px">
          <span>Dashboard Admin</span>
          <button id="btnToggleSidebar" class="btn" title="Sembunyikan Sidebar" aria-label="Toggle Sidebar">
            <span id="iconSidebar" aria-hidden="true" style="display:inline-flex;align-items:center;gap:6px">
              <!-- icon placeholder; replaced by JS -->
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </span>
          </button>
        </div>
        <div class="toolbar" style="gap:10px">
          <a class="btn" href="../public/index.php" target="_blank" title="Buka halaman peta">🗺️ Lihat Peta</a>
          <div class="pill">Login: <?php echo htmlspecialchars($user['nama_lengkap']??''); ?></div>
        </div>
      </div>

      <div id="layoutGrid" class="grid cols-2" style="grid-template-columns: 260px 1fr; align-items:start">
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
                <!-- Filter Wilayah dipindah ke popup pada kolom Wilayah -->
                <select id="perPage" class="field" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:10px">
                  <option value="20">20/hal</option>
                  <option value="50">50/hal</option>
                  <option value="100">100/hal</option>
                </select>
                <div class="dropdown">
                  <button id="btnExport" class="btn">Export ▾</button>
                  <div id="menuExport" class="dropdown-menu">
                    <button class="btn" data-export="csv">Export CSV</button>
                    <button class="btn" data-export="xlsx">Export XLSX</button>
                  </div>
                </div>
                <button id="btnAddSarana" class="btn primary">+ Tambah</button>
              </div>
            </div>
            <div style="overflow:auto">
              <table class="table">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>Koordinat</th>
                    <th style="position:relative">
                      <span style="display:inline-flex;align-items:center;gap:6px">
                        Wilayah
                        <button id="btnWilFilter" class="btn" title="Filter Wilayah" style="padding:4px 8px">Filter</button>
                        <span id="wilFilterBadge" class="pill" style="display:none"></span>
                      </span>
                      <div id="menuWilFilter" class="dropdown-menu" style="right:0; top:calc(100% + 6px);">
                        <div class="fw-body">
                          <div class="fw-grid">
                            <div class="field"><label>Kabupaten</label>
                              <div class="fw-search"><input id="wf_kab_search" placeholder="Cari kabupaten..."></div>
                              <select id="wf_kab" size="8">
                                <option value="">Semua Kabupaten</option>
                              </select>
                            </div>
                            <div class="field"><label>Kecamatan</label>
                              <div class="fw-search"><input id="wf_kec_search" placeholder="Cari kecamatan..."></div>
                              <select id="wf_kec" size="8">
                                <option value="">Semua Kecamatan</option>
                              </select>
                            </div>
                            <div class="field fw-full"><label>Kelurahan</label>
                              <div class="fw-search"><input id="wf_kel_search" placeholder="Cari kelurahan..."></div>
                              <select id="wf_kel" size="8">
                                <option value="">Semua Kelurahan</option>
                              </select>
                            </div>
                          </div>
                        </div>
                        <div class="fw-actions">
                          <button id="wf_clear" class="btn" type="button">Bersihkan</button>
                          <button id="wf_apply" class="btn primary" type="button">Terapkan</button>
                        </div>
                      </div>
                    </th>
                    <th style="position:relative">
                      <span style="display:inline-flex;align-items:center;gap:6px">
                        Jenis
                        <button id="btnJenisFilter" class="btn" title="Filter Jenis" style="padding:4px 8px">Filter</button>
                        <span id="jenisFilterBadge" class="pill" style="display:none"></span>
                      </span>
                      <div id="menuJenisFilter" class="dropdown-menu" style="right:0; top:calc(100% + 6px); width:280px; max-height:320px; overflow:auto">
                        <div style="padding:6px">
                          <input id="jf_search" placeholder="Cari jenis..." class="field" style="width:100%; padding:8px 10px; border:1px solid #e5e7eb; border-radius:10px" />
                        </div>
                        <div id="jf_list" style="padding:4px 6px; max-height:220px; overflow:auto"></div>
                        <div style="display:flex; justify-content:space-between; gap:6px; padding:6px">
                          <button id="jf_clear" class="btn" type="button">Bersihkan</button>
                          <button id="jf_apply" class="btn primary" type="button">Terapkan</button>
                        </div>
                      </div>
                    </th>
                    <th style="text-align:right">Aksi</th>
                  </tr>
                </thead>
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
      const state = { page:1, perPage:20, q:"", total:0, totalPages:1, filterJenis:[], sarana:[], jenisMaster:[], kab:'', kec:'', kel:'' };

      // Sidebar toggle (persisted)
      (function(){
        const layout = document.getElementById('layoutGrid');
        const btn = document.getElementById('btnToggleSidebar');
        const container = document.querySelector('.container');
        if (!layout || !btn || !container) return;
        const KEY='admin.sidebarCollapsed';
        const icon = document.getElementById('iconSidebar');
        const ICON_HIDE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>'; // chevron-left
        const ICON_SHOW = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'; // chevron-right
        function apply(collapsed){
          if (collapsed){
            layout.classList.add('collapsed');
            container.classList.add('wide');
            if (icon) icon.innerHTML = ICON_SHOW;
            btn.title='Tampilkan Sidebar (Ctrl+I)';
            btn.setAttribute('aria-label','Tampilkan Sidebar');
          } else {
            layout.classList.remove('collapsed');
            container.classList.remove('wide');
            if (icon) icon.innerHTML = ICON_HIDE;
            btn.title='Sembunyikan Sidebar (Ctrl+I)';
            btn.setAttribute('aria-label','Sembunyikan Sidebar');
          }
        }
        try{ apply(localStorage.getItem(KEY)==='1'); }catch(_){ apply(false); }
        btn.addEventListener('click', ()=>{
          const wantCollapse = !layout.classList.contains('collapsed');
          apply(wantCollapse);
          try{ localStorage.setItem(KEY, wantCollapse ? '1' : '0'); }catch(_){ }
        });
        // Keyboard shortcut: Ctrl + I
        document.addEventListener('keydown', (e)=>{
          try{
            const key = e.key || e.code;
            if (e.ctrlKey && (key==='i' || key==='I' || key==='KeyI')){
              e.preventDefault();
              btn.click();
            }
          }catch(_){ }
        });
      })();

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

      // API wilayah (reuse pola di public)
      async function fetchAreas(type, parent){
        try{
          const qs = new URLSearchParams({ type });
          if (parent) qs.set('parent', parent);
          const url = API + '/areas.php?' + qs.toString();
          const res = await fetch(url);
          if (!res.ok) return [];
          const data = await res.json();
          return Array.isArray(data) ? data : [];
        }catch(_){ return []; }
      }

      async function loadSarana(){
        try {
          const u = new URL(API + '/sarana.php', location.origin);
          u.searchParams.set('page', state.page);
          u.searchParams.set('per_page', state.perPage);
          if (state.q) u.searchParams.set('q', state.q);
          if (state.saranaIdSearch) u.searchParams.set('id', String(state.saranaIdSearch));
          if (state.filterJenis?.length) u.searchParams.set('jenis', state.filterJenis.join(','));
          if (state.kab) u.searchParams.set('kabupaten', state.kab);
          if (state.kec) u.searchParams.set('kecamatan', state.kec);
          if (state.kel) u.searchParams.set('kelurahan', state.kel);
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
      // Debounced search on input for easier find (no need to press Enter)
      (function(){
        let t=null; const inp=$('#q');
        inp.addEventListener('input', ()=>{
          clearTimeout(t);
          t=setTimeout(async()=>{ try{ state.q=inp.value.trim(); state.page=1; await loadSarana(); }catch(e){ console.error(e) } }, 300);
        });
      })();
      $('#btnAddSarana').addEventListener('click', ()=> openEditSarana(0));

      // ============ Wilayah Filter (dropdown popup) ============
      (function(){
        const btn = document.getElementById('btnWilFilter');
        const menu = document.getElementById('menuWilFilter');
        const badge = document.getElementById('wilFilterBadge');
        const selKab = document.getElementById('wf_kab');
        const selKec = document.getElementById('wf_kec');
        const selKel = document.getElementById('wf_kel');
        const sKab = document.getElementById('wf_kab_search');
        const sKec = document.getElementById('wf_kec_search');
        const sKel = document.getElementById('wf_kel_search');
        let cacheKab = [], cacheKec = [], cacheKel = [];
        const btnApply = document.getElementById('wf_apply');
        const btnClear = document.getElementById('wf_clear');
        if (!btn || !menu) return;

        function updateBadge(){
          const parts = [state.kab, state.kec, state.kel].filter(Boolean);
          if (parts.length){ badge.style.display='inline-block'; badge.textContent = parts.join(' • '); }
          else { badge.style.display='none'; }
        }

        async function populateKab(selected){
          cacheKab = await fetchAreas('kabupaten');
          renderKab(sKab?.value||'', selected);
        }
        async function populateKec(kab, selected){
          if (selKec) selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
          cacheKec = [];
          if (!kab) return;
          cacheKec = await fetchAreas('kecamatan', kab);
          renderKec(sKec?.value||'', selected);
        }
        async function populateKel(kab, kec, selected){
          if (selKel) selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
          cacheKel = [];
          if (!kab || !kec) return;
          cacheKel = await fetchAreas('kelurahan', `${kab}|${kec}`);
          renderKel(sKel?.value||'', selected);
        }

        function renderKab(term, selected){
          if (!selKab) return;
          const t = (term||'').toLowerCase().trim();
          selKab.innerHTML = '<option value="">Semua Kabupaten</option>' + cacheKab
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) selKab.value = selected;
        }
        function renderKec(term, selected){
          if (!selKec) return;
          const t = (term||'').toLowerCase().trim();
          selKec.innerHTML = '<option value="">Semua Kecamatan</option>' + cacheKec
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) selKec.value = selected;
        }
        function renderKel(term, selected){
          if (!selKel) return;
          const t = (term||'').toLowerCase().trim();
          selKel.innerHTML = '<option value="">Semua Kelurahan</option>' + cacheKel
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) selKel.value = selected;
        }

        btn.addEventListener('click', async (e)=>{
          e.stopPropagation();
          // Initialize selects with current state
          await populateKab(state.kab);
          await populateKec(state.kab, state.kec);
          await populateKel(state.kab, state.kec, state.kel);
          // Position floating menu like jenis filter
          const rect = btn.getBoundingClientRect();
          menu.classList.add('float');
          menu.style.width = '320px';
          menu.style.maxHeight = '60vh';
          menu.style.top = (rect.bottom + 8) + 'px';
          const right = Math.max(8, window.innerWidth - rect.right);
          menu.style.right = right + 'px';
          menu.style.left = '';
          menu.classList.toggle('show');
        });
        document.addEventListener('click', (e)=>{
          if (!menu.contains(e.target) && e.target !== btn){
            menu.classList.remove('show');
            menu.classList.remove('float');
            menu.style.top = menu.style.right = menu.style.left = menu.style.width = menu.style.maxHeight = '';
          }
        });

        // Cascade inside popup
        selKab && selKab.addEventListener('change', async ()=>{
          const kab = selKab.value || '';
          sKec && (sKec.value=''); sKel && (sKel.value='');
          await populateKec(kab, '');
          await populateKel('', '', '');
        });
        selKec && selKec.addEventListener('change', async ()=>{
          const kab = selKab.value || '';
          const kec = selKec.value || '';
          sKel && (sKel.value='');
          await populateKel(kab, kec, '');
        });
        // Search handlers
        sKab && sKab.addEventListener('input', ()=> renderKab(sKab.value||'', selKab?.value||''));
        sKec && sKec.addEventListener('input', ()=> renderKec(sKec.value||'', selKec?.value||''));
        sKel && sKel.addEventListener('input', ()=> renderKel(sKel.value||'', selKel?.value||''));

        btnClear && btnClear.addEventListener('click', async ()=>{
          if (selKab) selKab.value='';
          if (sKab) sKab.value=''; if (sKec) sKec.value=''; if (sKel) sKel.value='';
          await populateKec('', '');
          await populateKel('', '', '');
        });
        btnApply && btnApply.addEventListener('click', async ()=>{
          try{
            state.kab = selKab ? (selKab.value || '') : '';
            state.kec = selKec ? (selKec.value || '') : '';
            state.kel = selKel ? (selKel.value || '') : '';
            state.page = 1;
            await loadSarana();
          }catch(err){ console.error('Apply wilayah filter error:', err); }
          finally{ menu.classList.remove('show'); updateBadge(); }
        });
        // Initialize badge on load
        updateBadge();
      })();

      // ============ Jenis Filter (dropdown + autosuggest) ============
      (function(){
        const btn = document.getElementById('btnJenisFilter');
        const menu = document.getElementById('menuJenisFilter');
        const badge = document.getElementById('jenisFilterBadge');
        const list = document.getElementById('jf_list');
        const input = document.getElementById('jf_search');
        const btnApply = document.getElementById('jf_apply');
        const btnClear = document.getElementById('jf_clear');
        if (!btn || !menu || !list) return;

        function updateBadge(){
          const n = (state.filterJenis||[]).length;
          if (n>0){ badge.style.display='inline-block'; badge.textContent = `${n} dipilih`; }
          else { badge.style.display='none'; }
        }

        function renderList(q){
          const term = (q||'').toLowerCase();
          const data = Array.isArray(state.jenisMaster) ? state.jenisMaster : [];
          list.innerHTML = data
            .filter(j => !term || (j.nama_jenis||'').toLowerCase().includes(term))
            .map(j => {
              const checked = state.filterJenis.includes(parseInt(j.id,10)) ? 'checked' : '';
              return `<label style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;cursor:pointer">
                        <input type="checkbox" class="jf_chk" value="${j.id}" ${checked}/>
                        <span style="flex:1">${(j.nama_jenis||'')}</span>
                        <span class="pill">${j.count??0}</span>
                      </label>`;
            }).join('') || '<div style="padding:8px;color:#6b7280">Tidak ada hasil</div>';
        }

        async function ensureJenis(){
          if (Array.isArray(state.jenisMaster) && state.jenisMaster.length) return;
          try{
            const rows = await jfetch(API + '/jenis.php');
            state.jenisMaster = rows || [];
          }catch(e){ state.jenisMaster = []; }
        }

        btn.addEventListener('click', async (e)=>{
          e.stopPropagation();
          await ensureJenis();
          renderList('');
          // Compute floating position to ignore parent overflow
          const rect = btn.getBoundingClientRect();
          menu.classList.add('float');
          menu.style.width = '280px';
          menu.style.maxHeight = '60vh';
          menu.style.top = (rect.bottom + 8) + 'px';
          // align right to the button's right edge
          const right = Math.max(8, window.innerWidth - rect.right);
          menu.style.right = right + 'px';
          menu.style.left = '';
          menu.classList.toggle('show');
          setTimeout(()=>{ try{ input && input.focus(); }catch(_){} }, 50);
        });
        document.addEventListener('click', (e)=>{
          if (!menu.contains(e.target) && e.target !== btn){
            menu.classList.remove('show');
            // clean floating inline styles
            menu.classList.remove('float');
            menu.style.top = menu.style.right = menu.style.left = menu.style.width = menu.style.maxHeight = '';
          }
        });
        input && input.addEventListener('input', ()=> renderList(input.value.trim()));
        list.addEventListener('click', (e)=>{
          const el = e.target.closest('.jf_chk'); if (!el) return;
          const id = parseInt(el.value,10);
          if (el.checked){ if (!state.filterJenis.includes(id)) state.filterJenis.push(id); }
          else { state.filterJenis = state.filterJenis.filter(v=>v!==id); }
          updateBadge();
        });
        btnClear && btnClear.addEventListener('click', ()=>{
          state.filterJenis = [];
          renderList(input ? input.value.trim() : '');
          updateBadge();
        });
        btnApply && btnApply.addEventListener('click', async ()=>{
          try{
            state.page = 1;
            await loadSarana();
          }catch(err){ console.error('Apply jenis filter error:', err); }
          finally{ menu.classList.remove('show'); updateBadge(); }
        });
        // Initialize badge on load
        updateBadge();
      })();

      // Export popup (jenis + wilayah)
      (function(){
        const btn = document.getElementById('btnExport');
        const menu = document.getElementById('menuExport');
        if (!btn || !menu) return;

        // Build menu content on first open
        let built = false;
        // Local export selections (independent from table filters)
        let exJenisSel = [];
        let exCacheKab = [], exCacheKec = [], exCacheKel = [];

        function buildMenu(){
          if (built) return;
          menu.innerHTML = `
            <div class="fw-body">
              <div class="field fw-full"><label>Jenis Sarana</label>
                <div class="fw-search"><input id="ex_jf_search" placeholder="Cari jenis sarana..."></div>
                <div id="ex_jf_list" class="jenis-list"></div>
              </div>
              <div class="fw-grid" style="margin-top:8px">
                <div class="field"><label>Kabupaten</label>
                  <div class="fw-search"><input id="ex_kab_search" placeholder="Cari kabupaten..."></div>
                  <select id="ex_kab" size="8"><option value="">Semua Kabupaten</option></select>
                </div>
                <div class="field"><label>Kecamatan</label>
                  <div class="fw-search"><input id="ex_kec_search" placeholder="Cari kecamatan..."></div>
                  <select id="ex_kec" size="8"><option value="">Semua Kecamatan</option></select>
                </div>
                <div class="field fw-full"><label>Kelurahan</label>
                  <div class="fw-search"><input id="ex_kel_search" placeholder="Cari kelurahan..."></div>
                  <select id="ex_kel" size="8"><option value="">Semua Kelurahan</option></select>
                </div>
              </div>
            </div>
            <div class="fw-actions">
              <button id="ex_clear" class="btn" type="button">Bersihkan</button>
              <button id="ex_do" class="btn primary" type="button">Export XLSX</button>
            </div>`;
          built = true;

          wireUp();
        }

        function renderJenis(term){
          const list = document.getElementById('ex_jf_list');
          if (!list) return;
          const t = (term||'').toLowerCase().trim();
          const data = Array.isArray(state.jenisMaster) ? state.jenisMaster : [];
          list.innerHTML = data
            .filter(j => !t || (j.nama_jenis||'').toLowerCase().includes(t))
            .map(j => {
              const checked = exJenisSel.includes(parseInt(j.id,10)) ? 'checked' : '';
              return `<label class="jenis-item"><span><input type="checkbox" class="ex_jf_chk" value="${j.id}" ${checked}> ${escapeHtml(j.nama_jenis||'')}</span><span class="cnt">${j.count??0}</span></label>`;
            }).join('') || '<div style="padding:8px;color:#6b7280">Tidak ada hasil</div>';
        }

        async function populateKab(selected){
          exCacheKab = await fetchAreas('kabupaten');
          renderKab((document.getElementById('ex_kab_search')?.value)||'', selected);
        }
        async function populateKec(kab, selected){
          const sel = document.getElementById('ex_kec');
          if (sel) sel.innerHTML = '<option value="">Semua Kecamatan</option>';
          exCacheKec = [];
          if (!kab) return;
          exCacheKec = await fetchAreas('kecamatan', kab);
          renderKec((document.getElementById('ex_kec_search')?.value)||'', selected);
        }
        async function populateKel(kab, kec, selected){
          const sel = document.getElementById('ex_kel');
          if (sel) sel.innerHTML = '<option value="">Semua Kelurahan</option>';
          exCacheKel = [];
          if (!kab || !kec) return;
          exCacheKel = await fetchAreas('kelurahan', `${kab}|${kec}`);
          renderKel((document.getElementById('ex_kel_search')?.value)||'', selected);
        }

        function renderKab(term, selected){
          const sel = document.getElementById('ex_kab'); if (!sel) return;
          const t = (term||'').toLowerCase().trim();
          sel.innerHTML = '<option value="">Semua Kabupaten</option>' + exCacheKab
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) sel.value = selected;
        }
        function renderKec(term, selected){
          const sel = document.getElementById('ex_kec'); if (!sel) return;
          const t = (term||'').toLowerCase().trim();
          sel.innerHTML = '<option value="">Semua Kecamatan</option>' + exCacheKec
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) sel.value = selected;
        }
        function renderKel(term, selected){
          const sel = document.getElementById('ex_kel'); if (!sel) return;
          const t = (term||'').toLowerCase().trim();
          sel.innerHTML = '<option value="">Semua Kelurahan</option>' + exCacheKel
            .filter(k=>!t || (k.name||'').toLowerCase().includes(t))
            .map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          if (selected) sel.value = selected;
        }

        function wireUp(){
          // Prefill from current filters
          exJenisSel = Array.isArray(state.filterJenis) ? [...state.filterJenis] : [];
          const exKab = state.kab || '';
          const exKec = state.kec || '';
          const exKel = state.kel || '';

          // Ensure jenis master
          (async()=>{
            try{ await ensureJenisMaster(); }catch(_){}
            renderJenis('');
            const search = document.getElementById('ex_jf_search');
            const list = document.getElementById('ex_jf_list');
            search && search.addEventListener('input', ()=> renderJenis(search.value||''));
            list && list.addEventListener('change', (e)=>{
              const el = e.target.closest('.ex_jf_chk'); if (!el) return;
              const id = parseInt(el.value,10);
              if (el.checked){ if (!exJenisSel.includes(id)) exJenisSel.push(id); }
              else { exJenisSel = exJenisSel.filter(v=>v!==id); }
            });
          })();

          // Populate wilayah with current state
          (async()=>{
            await populateKab(exKab);
            await populateKec(exKab, exKec);
            await populateKel(exKab, exKec, exKel);
            const selKab = document.getElementById('ex_kab');
            const selKec = document.getElementById('ex_kec');
            const sKab = document.getElementById('ex_kab_search');
            const sKec = document.getElementById('ex_kec_search');
            const sKel = document.getElementById('ex_kel_search');
            const selKel = document.getElementById('ex_kel');
            selKab && selKab.addEventListener('change', async ()=>{
              const kab = selKab.value || '';
              if (sKec) sKec.value=''; if (sKel) sKel.value='';
              await populateKec(kab, '');
              await populateKel('', '', '');
            });
            selKec && selKec.addEventListener('change', async ()=>{
              const kab = document.getElementById('ex_kab').value || '';
              const kec = document.getElementById('ex_kec').value || '';
              if (sKel) sKel.value='';
              await populateKel(kab, kec, '');
            });
            sKab && sKab.addEventListener('input', ()=> renderKab(sKab.value||'', selKab?.value||''));
            sKec && sKec.addEventListener('input', ()=> renderKec(sKec.value||'', selKec?.value||''));
            sKel && sKel.addEventListener('input', ()=> renderKel(sKel.value||'', selKel?.value||''));
          })();

          // Actions
          document.getElementById('ex_clear')?.addEventListener('click', async ()=>{
            exJenisSel = [];
            try{ renderJenis(document.getElementById('ex_jf_search')?.value||''); }catch(_){ }
            const selKab = document.getElementById('ex_kab');
            const selKec = document.getElementById('ex_kec');
            const selKel = document.getElementById('ex_kel');
            const sKab = document.getElementById('ex_kab_search');
            const sKec = document.getElementById('ex_kec_search');
            const sKel = document.getElementById('ex_kel_search');
            if (selKab) selKab.value=''; if (sKab) sKab.value=''; if (sKec) sKec.value=''; if (sKel) sKel.value='';
            await populateKec('', ''); await populateKel('', '', '');
          });
          document.getElementById('ex_do')?.addEventListener('click', ()=>{
            try{
              const u = new URL(API + '/sarana.php', location.origin);
              u.searchParams.set('export', 'xlsx');
              // jenis param (ids)
              if (exJenisSel.length) u.searchParams.set('jenis', exJenisSel.join(','));
              const kab = document.getElementById('ex_kab')?.value || '';
              const kec = document.getElementById('ex_kec')?.value || '';
              const kel = document.getElementById('ex_kel')?.value || '';
              if (kab) u.searchParams.set('kabupaten', kab);
              if (kec) u.searchParams.set('kecamatan', kec);
              if (kel) u.searchParams.set('kelurahan', kel);
              window.open(u.toString(), '_blank');
            }catch(err){ console.error('Export XLSX error:', err); }
            menu.classList.remove('show');
          });
        }

        btn.addEventListener('click', async (e)=>{
          e.stopPropagation();
          buildMenu();
          // Position like other float menus
          const rect = btn.getBoundingClientRect();
          menu.classList.add('float');
          menu.style.width = 'min(520px, 96vw)';
          menu.style.maxHeight = '60vh';
          menu.style.top = (rect.bottom + 8) + 'px';
          const right = Math.max(8, window.innerWidth - rect.right);
          menu.style.right = right + 'px';
          menu.style.left = '';
          menu.classList.toggle('show');
        });
        document.addEventListener('click', (e)=>{
          if (!menu.contains(e.target) && e.target !== btn){
            menu.classList.remove('show');
            menu.classList.remove('float');
            menu.style.top = menu.style.right = menu.style.left = menu.style.width = menu.style.maxHeight = '';
          }
        });
      })();

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
        // Cari data jenis untuk preview icon
        let jenisData = null;
        try { if (Array.isArray(state.jenisMaster)) jenisData = state.jenisMaster.find(j=>parseInt(j.id,10)===parseInt(id||0,10)); } catch(_){ }
        const hasCustom = !!(jenisData && jenisData.has_custom_icon);
        const customSrc = jenisData && jenisData.icon_base64 ? `data:image/png;base64,${jenisData.icon_base64}` : '';
        const defaultSrc = `../assets/icon/${String(nama||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')}.png`;
        const previewSrc = hasCustom ? customSrc : defaultSrc;
        $('#dlgBody').innerHTML = `
          <div class="field">
            <label>Nama Jenis</label>
            <input id="fj" value="${(nama||'').replaceAll('"','&quot;')}">
          </div>
          <div class="field">
            <label>Icon Jenis Sarana (Opsional)</label>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
              <img id="fj_preview" src="${previewSrc}" alt="preview" width="32" height="32" style="border:1px solid #e5e7eb;border-radius:6px;background:#fff" />
              ${hasCustom ? '<span class="pill">Icon Khusus</span>' : '<span class="pill">Icon Default</span>'}
            </div>
            <input type="file" id="fj_icon" accept=".png" class="w-full px-3 py-2 border rounded-xl">
            <div class="text-xs text-abu-600 mt-1">Format: PNG, Ukuran: 32px x 32px</div>
            <div class="text-xs text-abu-600 mt-1">Catatan: Icon file statis di /assets/icon akan digunakan jika tidak ada icon khusus</div>
            ${id ? `<div class=\"text-xs text-abu-600 mt-1\">Biarkan kosong jika tidak ingin mengganti icon</div>` : ''}
            ${id ? `<label style=\"display:flex;align-items:center;gap:8px;margin-top:8px\"><input type=\"checkbox\" id=\"fj_remove_icon\"> Hapus icon khusus</label>` : ''}
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
                // Jika user centang hapus icon khusus
                const chk = document.getElementById('fj_remove_icon');
                if (chk && chk.checked) payload.remove_icon = true;
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
      // Apply URL params (q or sid) for deep-linking
      (function(){
        try{
          const p = new URLSearchParams(location.search);
          const q0 = (p.get('q')||'').trim();
          const sid = parseInt(p.get('sid')||p.get('id')||'0',10);
          if (q0) { state.q = q0; const iq = document.getElementById('q'); if (iq) iq.value = q0; }
          if (sid>0) { state.saranaIdSearch = sid; }
        }catch(_){ }
      })();
      loadSarana().catch(e=>{
        console.error('Error in initial loadSarana:', e);
        $('#saranaRows').innerHTML = `<tr><td colspan="5">Error loading data: ${e.message}</td></tr>`;
      });
    </script>
  </body>
</html>
