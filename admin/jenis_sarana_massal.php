<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();
$user = getUserData();
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ubah Jenis Sarana Massal</title>
    <link rel="icon" href="../assets/favicon.svg" />
    <link rel="stylesheet" href="../public/assets/app-admin.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
      .btn{display:inline-block;border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;transition:background .2s,border-color .2s,box-shadow .2s}
      .btn:hover{background:#f8fafc}
      .btn.primary{background:#111827;color:#fff;border-color:#111827}
      .btn.primary:hover{background:#0f121a}
      .btn:disabled{opacity:.6;cursor:not-allowed}
      .table{width:100%;border-collapse:collapse}
      .table th,.table td{padding:10px 12px;border-top:1px solid #f1f5f9;font-size:14px;vertical-align:top}
      .table thead th{background:#f8fafc;font-weight:600}
    </style>
  </head>
  <body class="bg-tulang text-abu-900">
    <div class="min-h-screen max-w-4xl mx-auto p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Ubah Jenis Sarana Massal</h1>
        <a href="./index.php" class="underline text-sm">Kembali ke Dashboard</a>
      </div>

      <div class="bg-white rounded-2xl border border-abu-100 shadow-soft p-5 space-y-6">
        <div class="border-b pb-4">
          <h2 class="text-lg font-medium mb-2">Ubah Jenis Sarana Secara Massal</h2>
          <p class="text-sm text-abu-700">Gunakan fitur ini untuk mengganti jenis sarana yang salah secara sekaligus.</p>
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
            <button id="btn_ubah" class="btn primary" disabled>Ubah Jenis Sarana</button>
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
              <input type="file" name="file" accept=".csv,text/csv" required class="block w-full" />
            </div>
            <div>
              <button type="submit" class="btn primary">Import CSV</button>
            </div>
          </form>
          <div id="import_result" class="text-sm mt-3"></div>
        </div>

        <div id="result" class="text-sm"></div>

        <div class="border-t pt-4">
          <h3 class="font-medium mb-2">Daftar Jenis Sarana</h3>
          <div id="list_jenis" class="text-sm max-h-60 overflow-auto nice-scroll bg-abu-50 p-2 rounded"></div>
        </div>

        <div class="border-t pt-4">
          <h3 class="font-medium mb-2">Submenu: Pindahkan Per Data (pilih sarana)</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
              <label class="block text-sm mb-1">Jenis Sumber</label>
              <select id="sm_jenis_src" class="w-full px-3 py-2 border rounded-xl">
                <option value="">Pilih jenis...</option>
              </select>
            </div>
            <div>
              <label class="block text-sm mb-1">Jenis Tujuan</label>
              <select id="sm_jenis_dst" class="w-full px-3 py-2 border rounded-xl">
                <option value="">Pilih jenis...</option>
              </select>
            </div>
            <div class="flex gap-2">
              <button id="sm_load" class="btn" disabled>Muat Data</button>
              <button id="sm_move" class="btn primary" disabled>Pindahkan Data</button>
            </div>
          </div>
          <div class="mt-3"><input id="sm_search" placeholder="Cari nama/wilayah..." class="w-full px-3 py-2 border rounded-xl" /></div>
          <div class="mt-3" style="overflow:auto; max-height:380px">
            <table class="table">
              <thead><tr><th style="width:40px"><input type="checkbox" id="sm_check_all" /></th><th>Nama</th><th>Wilayah</th></tr></thead>
              <tbody id="sm_rows"></tbody>
            </table>
          </div>
          <div id="sm_info" class="text-xs text-abu-600 mt-2"></div>
        </div>
      </div>
    </div>

    <script>
      const API_BASE = "/api";
      // Submenu state and helpers
      const SM = { rows: [], filtered: [], selected: new Set() };
      function setBtnLoading(el, on, textNormal, textLoading){ try{ if(!el) return; el.disabled = !!on; el.textContent = on ? (textLoading||'Memproses...') : (textNormal||el.dataset?.label||'Tombol'); }catch(_){} }
      // SweetAlert helpers (fallback safe)
      function toast(icon, title){ try{ if (window.Swal && Swal.mixin){ Swal.mixin({toast:true,position:'top-end',showConfirmButton:false,timer:3000,timerProgressBar:true}).fire({icon, title}); } else { alert(title); } }catch(_){ alert(title); } }
      async function confirmDialog(text){ try{ if (window.Swal && Swal.fire){ const r = await Swal.fire({title:'Konfirmasi', text, icon:'question', showCancelButton:true, confirmButtonText:'Ya', cancelButtonText:'Batal'}); return !!r.isConfirmed; } }catch(_){ } return confirm(text); }
      function escapeHtml(s){ return String(s??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;"); }
      function renderSmRows(){
        const q = (document.getElementById('sm_search')?.value || '').trim().toLowerCase();
        SM.filtered = SM.rows.filter(r=>{ if (!q) return true; const text = `${r.nama_sarana||''} ${r.kelurahan||''} ${r.kecamatan||''} ${r.kabupaten||''}`.toLowerCase(); return text.includes(q); });
        const tb = document.getElementById('sm_rows');
        tb.innerHTML = SM.filtered.map(r=>{
          const checked = SM.selected.has(r.id) ? 'checked' : '';
          const wil = [r.kelurahan, r.kecamatan, r.kabupaten].filter(Boolean).join(', ');
          return `<tr><td><input type=\"checkbox\" class=\"sm_chk\" data-id=\"${r.id}\" ${checked}></td><td>${escapeHtml(r.nama_sarana||'')}</td><td>${escapeHtml(wil)}</td></tr>`;
        }).join('');
        const canMove = (SM.selected.size>0) && !!(document.getElementById('sm_jenis_dst')?.value);
        document.getElementById('sm_move').disabled = !canMove;
        document.getElementById('sm_info').textContent = `${SM.filtered.length} data, dipilih: ${SM.selected.size}`;
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await loadJenisSarana();

        document.getElementById('jenis_lama').addEventListener('change', async function(){
          const jenisId = this.value; const countDiv = document.getElementById('count_lama'); const btnUbah = document.getElementById('btn_ubah');
          if (!jenisId){ countDiv.textContent=''; btnUbah.disabled=true; return; }
          try{
            const res = await fetchJSON(`${API_BASE}/count_jenis_sarana.php`, { method:'POST', body: JSON.stringify({ jenis_id: jenisId }) });
            countDiv.textContent = `${res.count} data akan diubah`;
            const jenisBaru = document.getElementById('jenis_baru').value; btnUbah.disabled = !jenisBaru || jenisBaru===jenisId;
          }catch(e){ countDiv.textContent = 'Gagal menghitung data'; }
        });
        document.getElementById('jenis_baru').addEventListener('change', function(){ const lama = document.getElementById('jenis_lama').value; document.getElementById('btn_ubah').disabled = !lama || !this.value || (lama===this.value); });

        document.getElementById('import_form').addEventListener('submit', async (e)=>{
          e.preventDefault(); const form = e.target; const resultDiv = document.getElementById('import_result');
          try{
            resultDiv.innerHTML = '<div class="p-3 rounded-xl border bg-abu-50">Mengimport perubahan jenis sarana...</div>';
            const res = await fetch(`${API_BASE}/import_jenis_sarana.php`, { method:'POST', body: new FormData(form) });
            const text = await res.text(); let data; try{ data = JSON.parse(text); }catch{ throw new Error('Response bukan JSON: ' + text); }
            if (!res.ok || data.error) throw new Error(data.error||'Import gagal');
            resultDiv.innerHTML = `<div class="p-3 rounded-xl border bg-green-50 text-green-700">Berhasil import. Total diubah: ${data.total_updated}</div>`; toast('success','Import berhasil'); form.reset(); await loadJenisSarana();
          }catch(err){ resultDiv.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal import: ${err.message}</div>`; toast('error', 'Gagal import: ' + err.message); }
        });

        document.getElementById('btn_ubah').addEventListener('click', async ()=>{
          const jenisLama = document.getElementById('jenis_lama').value; const jenisBaru = document.getElementById('jenis_baru').value; const result = document.getElementById('result');
          if (!jenisLama || !jenisBaru){ result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Harap pilih jenis lama dan baru.</div>`; return; }
          if (jenisLama===jenisBaru){ result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Jenis lama dan baru tidak boleh sama.</div>`; return; }
          try{
            result.innerHTML = `<div class="p-3 rounded-xl border bg-abu-50">Mengubah jenis sarana...</div>`;
            const data = await fetchJSON(`${API_BASE}/jenis_sarana_massal.php`, { method:'POST', body: JSON.stringify({_method:'PUT', old_jenis_id: jenisLama, new_jenis_id: jenisBaru}) });
            result.innerHTML = `<div class="p-3 rounded-xl border bg-green-50 text-green-700">Berhasil mengubah ${data.updated||0} data.</div>`; toast('success','Berhasil ubah massal'); document.getElementById('jenis_lama').value=''; document.getElementById('jenis_baru').value=''; document.getElementById('count_lama').textContent=''; document.getElementById('btn_ubah').disabled=true; await loadJenisSarana();
          }catch(err){ result.innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal ubah massal: ${err.message}</div>`; toast('error','Gagal ubah massal: '+err.message); }
        });

        // Submenu
        document.getElementById('sm_jenis_src').addEventListener('change', ()=>{ document.getElementById('sm_load').disabled = !document.getElementById('sm_jenis_src').value; });
        document.getElementById('sm_load').addEventListener('click', smLoadData);
        document.getElementById('sm_search').addEventListener('input', renderSmRows);
        document.getElementById('sm_check_all').addEventListener('change', (e)=>{ const check=e.target.checked; SM.selected.clear(); if(check){ for(const r of SM.filtered) SM.selected.add(r.id);} renderSmRows(); });
        document.getElementById('sm_rows').addEventListener('click', (e)=>{ const el=e.target.closest('.sm_chk'); if(!el) return; const id=parseInt(el.getAttribute('data-id'),10); if(el.checked) SM.selected.add(id); else SM.selected.delete(id); document.getElementById('sm_move').disabled = (SM.selected.size===0) || !(document.getElementById('sm_jenis_dst').value); document.getElementById('sm_info').textContent = `${SM.filtered.length} data, dipilih: ${SM.selected.size}`; });
        document.getElementById('sm_jenis_dst').addEventListener('change', ()=>{ document.getElementById('sm_move').disabled = (SM.selected.size===0) || !(document.getElementById('sm_jenis_dst').value); });
        document.getElementById('sm_move').addEventListener('click', async()=>{
          try{
            const dst = parseInt(document.getElementById('sm_jenis_dst').value,10); const src = parseInt(document.getElementById('sm_jenis_src').value,10);
            if (!dst || SM.selected.size===0){ toast('info','Pilih jenis tujuan dan data'); return; }
            const dstName = (document.getElementById('sm_jenis_dst').selectedOptions[0]?.textContent||'').trim(); const cnt = SM.selected.size;
            const ok = await confirmDialog(`Pindahkan ${cnt} data ke jenis "${dstName}"?`); if (!ok) return;
            const ids = Array.from(SM.selected);
            setBtnLoading(document.getElementById('sm_move'), true, 'Pindahkan Data', 'Memindahkan...'); setBtnLoading(document.getElementById('sm_load'), true, 'Muat Data', '...');
            const res = await fetch(`${API_BASE}/jenis_sarana_select.php`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ old_jenis_id: src||undefined, new_jenis_id: dst, sarana_ids: ids }) });
            const j = await res.json(); if (!res.ok || j.error) throw new Error(j.error||'Gagal memindahkan'); toast('success',`Berhasil memindahkan ${j.moved} data`);
            const prevSrc = document.getElementById('sm_jenis_src').value; const prevDst = document.getElementById('sm_jenis_dst').value; await smLoadData(); await loadJenisSarana(); try{ if(prevSrc) document.getElementById('sm_jenis_src').value = prevSrc; if(prevDst) document.getElementById('sm_jenis_dst').value = prevDst; }catch(_){ }
          }catch(e){ toast('error','Gagal memindahkan: '+e.message); }
          finally{ setBtnLoading(document.getElementById('sm_move'), false, 'Pindahkan Data'); setBtnLoading(document.getElementById('sm_load'), false, 'Muat Data'); }
        });
      });

      async function loadJenisSarana(){
        try{
          const jenis = await fetchJSON(`${API_BASE}/jenis.php`);
          const jl = document.getElementById('jenis_lama'); const jb = document.getElementById('jenis_baru');
          jl.innerHTML = '<option value="">Pilih jenis sarana...</option>' + jenis.map(j=>`<option value="${j.id}">${j.nama_jenis} (${j.count??0})</option>`).join('');
          jb.innerHTML = '<option value="">Pilih jenis sarana...</option>' + jenis.map(j=>`<option value="${j.id}">${j.nama_jenis}</option>`).join('');
          document.getElementById('list_jenis').innerHTML = jenis.map(j=>`<div class="py-1">${j.nama_jenis} (${j.count??0})</div>`).join('');
          const src = document.getElementById('sm_jenis_src'); const dst = document.getElementById('sm_jenis_dst');
          src.innerHTML = '<option value="">Pilih jenis...</option>' + jenis.map(j=>`<option value="${j.id}">${j.nama_jenis} (${j.count??0})</option>`).join('');
          dst.innerHTML = '<option value="">Pilih jenis...</option>' + jenis.map(j=>`<option value="${j.id}">${j.nama_jenis}</option>`).join('');
        }catch(e){ document.getElementById('result').innerHTML = `<div class="p-3 rounded-xl border bg-red-50 text-red-700">Gagal memuat daftar jenis: ${e.message}</div>`; }
      }

      async function fetchJSON(url, opt={}){ const r = await fetch(url, { headers:{'Content-Type':'application/json'}, ...opt }); const t=await r.text(); let j; try{ j=JSON.parse(t);}catch{ throw new Error(t);} if(!r.ok || j?.error) throw new Error(j?.error||t); return j; }

      async function smLoadData(){ try{ const src=document.getElementById('sm_jenis_src').value; if(!src){ toast('info','Pilih jenis sumber dulu'); return; } setBtnLoading(document.getElementById('sm_load'), true, 'Muat Data', 'Memuat...'); const r=await fetch(`${API_BASE}/jenis_sarana_select.php?jenis_id=${encodeURIComponent(src)}`); const data=await r.json(); if(!Array.isArray(data)) throw new Error('Respon tidak valid'); SM.rows=data; SM.selected.clear(); renderSmRows(); const chAll=document.getElementById('sm_check_all'); if(chAll) chAll.checked=false; }catch(e){ toast('error','Gagal memuat data: '+e.message);} finally{ setBtnLoading(document.getElementById('sm_load'), false, 'Muat Data'); } }
    </script>
  </body>
  </html>
