// app_map.js — langkah 1: peta minimal + marker cluster
(function(){
  const API = window.API_BASE || '../api';
  const UI = {
    btnFilter: null, btnLegend: null, btnLocate: null, btnFollow: null,
    ovFilter: null, ovLegend: null,
    q: null, qSuggest: null, selKab: null, selKec: null, selKel: null, selJenis: null,
    legendBody: null
  };
  const filter = { q:'', kab:'', kec:'', kel:'', jenis:[] };
  let watchId = null;
  let meMarker = null; let meAccCircle = null;
  // Objek untuk menyimpan data sarana yang dimuat, diakses dengan ID
  const loadedSarana = {};

  // Peta jenis -> warna + glyph (nama harus sama dengan di DB)
  const JENIS_META = {
    'Lainnya':                                  {color:'#e5e7eb', glyph:'dot', icon: 'lainnya.png'},
    'Sarana Distribusi Kosmetik':               {color:'#fde68a', glyph:'sparkles', icon: 'cosmetic.png'},
    'Sarana Distribusi Obat Bahan Alam':        {color:'#bbf7d0', glyph:'leaf', icon: 'herbal.png'},
    'Sarana Distribusi Obat IFK':               {color:'#fda4af', glyph:'warehouse', icon: 'medicine.png'},
    'Sarana Distribusi Pangan':                 {color:'#c7d2fe', glyph:'truck', icon: 'shopping-basket.png'},
    'Sarana Pelayanan Kefarmasian Rumah Sakit': {color:'#bae6fd', glyph:'hospital', icon: 'hospital.png'},
    'Sarana Distribusi Obat PBF':               {color:'#fecaca', glyph:'box', icon: 'pharmacyindus.png'},
    'Sarana Distribusi Suplemen Kesehatan':     {color:'#ddd6fe', glyph:'pill', icon: 'dietary-suplement.png'},
    'Sarana Lain-Lain Toko Obat':               {color:'#fde68a', glyph:'bag', icon: 'tokoobat.png'},
    'Sarana Pelayanan Kefarmasian Apotek':      {color:'#a5f3fc', glyph:'bowl', icon: 'pharmacy.png'},
    'Sarana Pelayanan Kefarmasian Klinik':      {color:'#d9f99d', glyph:'cross', icon: 'clinic.png'},
    'Sarana Pelayanan Kefarmasian Puskesmas':   {color:'#99f6e4', glyph:'clinic', icon: 'puskesmas.png'},
    'Sarana Produksi Fortifikasi':              {color:'#e9d5ff', glyph:'beaker'},
    'Sarana Produksi PIRT':                     {color:'#fed7aa', glyph:'home', icon: 'pirt.png'},
    'Sarana Produksi Pangan Olahan':            {color:'#bfdbfe', glyph:'factory', icon: 'md.png'},
    'Sarana Produksi Sediaan Farmasi':          {color:'#fecaca', glyph:'flask'}
  };

  function escapeHtml(s){
    return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }

  function glyphSVG(name){
    const stroke="#111827", sw=1.5, fill="none";
    switch(name){
      case 'truck': return `<path d="M2 13h9V7H2v6Zm9 0h4l2.5-3H11v3Z" fill="${stroke}" stroke="${stroke}" stroke-width="${sw}" /><circle cx="6.5" cy="17" r="1.5" fill="${stroke}"/><circle cx="14.5" cy="17" r="1.5" fill="${stroke}"/>`;
      case 'pill': return `<rect x="6" y="6" width="12" height="12" rx="6" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/><path d="M7.5 13.5l9-9" stroke="${stroke}" stroke-width="${sw}" />`;
      case 'leaf': return `<path d="M12 5c-4 0-7 3-7 7 0 3 2.5 5.5 5.5 5.5C14 17 17 14 18 10c1.5-3 2.5-4 4-5-3 .5-5-.5-10 0Z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'sparkles': return `<path d="M12 5l1.5 3.5L17 10l-3.5 1.5L12 15l-1.5-3.5L7 10l3.5-1.5L12 5Z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'box': return `<path d="M4 8l8-4 8 4-8 4-8-4Z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/><path d="M12 12v8m8-12v8l-8 4-8-4V8" stroke="${stroke}" stroke-width="${sw}"/>`;
      case 'warehouse': return `<path d="M3 10l9-4 9 4v8H3v-8Z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/><path d="M7 12h4v6H7zM13 12h4v6h-4z" stroke="${stroke}" stroke-width="${sw}"/>`;
      case 'cross': return `<path d="M10 5h4v4h4v4h-4v4h-4v-4H6v-4h4V5z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'hospital': return `<rect x="6" y="6" width="12" height="12" rx="2.5" fill="${fill}" stroke="${stroke}" stroke-width="${sw}"/><path d="M12 8v8M8 12h8" stroke="${stroke}" stroke-width="${sw}"/>`;
      case 'bowl': return `<path d="M5 10h14a7 7 0 01-14 0Z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'clinic': return `<path d="M6 18V8h12v10H6z M12 6v4m-2-2h4" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'bag': return `<path d="M7 8h10v10H7z M9 8a3 3 0 016 0" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'factory': return `<path d="M4 18h16v2H4z M6 16V9l4 3V9l4 3V8h4v8" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'beaker': return `<path d="M8 5h8v2l-3 5v4H11v-4L8 7z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'home': return `<path d="M5 11l7-6 7 6v8H5v-8z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      case 'flask': return `<path d="M10 5h4v6l3 5H7l3-5V5z" stroke="${stroke}" stroke-width="${sw}" fill="${fill}"/>`;
      default: return `<circle cx="12" cy="12" r="4" fill="${stroke}" />`;
    }
  }

  function makeIcon(kind, nameLabel){
    const meta = JENIS_META[kind] || JENIS_META['Lainnya'];
    let iconHtml;

    if (meta.icon) {
      iconHtml = `<img src="/assets/icon/${meta.icon}" style="width:24px; height:24px;">`;
    } else {
      const bg = meta.color || '#e5e7eb';
      iconHtml = `<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2c-3.86 0-7 3.14-7 7 0 5.25 7 13 7 13s7-7.75 7-13c0-3.86-3.14-7-7-7z" fill="${bg}" stroke="#11182722"/>
        <circle cx="12" cy="9" r="4.2" fill="#ffffff"/>
        <g transform="translate(12,9) scale(0.55) translate(-12,-12)">
          ${glyphSVG(meta.glyph)}
        </g>
      </svg>`;
    }

    const html = `<div style="text-align: center;">
                    ${iconHtml}
                    <div style="font-size: 12px; color: #000; white-space: nowrap; margin-top: 2px; background: rgba(255, 255, 255, 0.7); padding: 2px 5px; border-radius: 3px; box-shadow: 0 0 2px #fff;">${escapeHtml(nameLabel)}</div>
                  </div>`;

    return L.divIcon({
      className: 'custom-div-icon',
      html: html,
      iconSize: [80, 40],
      iconAnchor: [40, 40],
      popupAnchor: [0, -40]
    });
  }

  async function fetchSarana(limit, bbox){
    try {
      const qs = new URLSearchParams();
      if (limit) qs.set('limit', String(limit)); else qs.set('limit','5000');
      if (bbox) qs.set('bbox', bbox);
      if (filter.q) qs.set('q', filter.q);
      if (filter.kab) qs.set('kabupaten', filter.kab);
      if (filter.kec) qs.set('kecamatan', filter.kec);
      if (filter.kel) qs.set('kelurahan', filter.kel);
      if (filter.jenis?.length) qs.set('jenis', filter.jenis.join(','));
      const url = `${API}/sarana.php?${qs.toString()}`;
      console.log('Fetching data from URL:', url);
      const res = await fetch(url);
      console.log('Response status:', res.status);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      console.log('Data received:', data);
      return data;
    } catch (err) {
      console.error('Error fetching sarana data:', err);
      throw err;
    }
  }

  async function fetchJenis(){
    try{
      const res = await fetch(`${API}/jenis.php`);
      if (!res.ok) return [];
      return res.json();
    }catch(_){ return []; }
  }

  async function fetchAreas(type, parent){
    const qs = new URLSearchParams({ type });
    if (parent) qs.set('parent', parent);
    const res = await fetch(`${API}/areas.php?${qs}`);
    if (!res.ok) return [];
    return res.json();
  }

  function renderLegend(items){
    const box = document.getElementById('legend');
    if (!box) return;
    const parts = [];
    parts.push(`<div class="title">Legenda</div>`);
    for (const it of items){
      const name = it.nama_jenis || it.name || it;
      const icon = makeIcon(name);
      parts.push(`<div class="item"><div class="icon">${icon.options.html}</div><div>${escapeHtml(name)}</div></div>`);
    }
    box.innerHTML = parts.join('');
    box.hidden = false;
  }

  // Toast notification function
  function showToast(message, isSuccess = true) {
    // Hapus toast yang ada
    const existingToast = document.getElementById('toast-notification');
    if (existingToast) existingToast.remove();
    
    // Buat elemen toast
    const toast = document.createElement('div');
    toast.id = 'toast-notification';
    toast.innerHTML = `
      <div class="toast-content">
        <span class="toast-icon">${isSuccess ? '✅' : '❌'}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
      </div>
    `;
    
    // Tambahkan styling untuk toast
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      background: ${isSuccess ? '#10B981' : '#EF4444'};
      color: white;
      padding: 16px 24px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      transform: translateX(150%);
      transition: transform 0.3s ease-out;
      max-width: 400px;
    `;
    
    // Styling untuk konten toast
    const toastContent = toast.querySelector('.toast-content');
    toastContent.style.cssText = 'display: flex; align-items: center; gap: 12px;';
    
    const toastIcon = toast.querySelector('.toast-icon');
    toastIcon.style.cssText = 'font-size: 20px;';
    
    const toastMessage = toast.querySelector('.toast-message');
    toastMessage.style.cssText = 'flex: 1;';
    
    // Tambahkan kelas error jika bukan success
    if (!isSuccess) {
      toast.classList.add('error');
    }
    
    // Tambahkan ke body
    document.body.appendChild(toast);
    
    // Tampilkan toast
    setTimeout(() => {
      toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Sembunyikan setelah 3 detik
    setTimeout(() => {
      toast.style.transform = 'translateX(150%)';
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 300);
    }, 3000);
  }

  function init(){
    const DEFAULT_CENTER = [-2.99, 120.2];
    const DEFAULT_ZOOM = 8;
    const NAME_ZOOM = 19; // zoom in dekat agar hanya 1 sarana terlihat
    let justAppliedFilter = false; // digunakan agar auto-zoom tidak mengunci interaksi
    const map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    // Base layer with graceful fallback providers if primary fails
    let baseLayer = null; let baseIdx = 0;
    const providers = [
      { url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', opts: { maxZoom: 19, attribution: '&copy; OpenStreetMap' } },
      { url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', opts: { subdomains: 'abc', maxZoom: 19, attribution: '&copy; OpenStreetMap contributors, Tiles style by HOT' } },
      { url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', opts: { subdomains: 'abcd', maxZoom: 19, attribution: '&copy; OpenStreetMap &copy; CARTO' } }
    ];
    function attachBase(i){
      if (baseLayer) { try { map.removeLayer(baseLayer); } catch(_){} }
      baseIdx = i;
      baseLayer = L.tileLayer(providers[i].url, providers[i].opts).addTo(map);
      baseLayer.on('tileerror', ()=>{
        if (baseIdx < providers.length - 1) {
          attachBase(baseIdx + 1);
        }
      });
    }
    attachBase(0);
    // Ensure map sizes correctly after initial paint
    setTimeout(()=>{ try{ map.invalidateSize(); }catch(_){ } }, 300);

    const cluster = L.markerClusterGroup();
    map.addLayer(cluster);

    // Tambahkan event listener untuk tombol edit sarana di popup
    map.on('popupopen', function(e) {
        const popup = e.popup;
        const button = popup._contentNode.querySelector('.btn-edit-sarana');
        if (button) {
            button.onclick = async function() {
                const saranaId = parseInt(this.getAttribute('data-id'), 10);
                if (saranaId) {
                    // Tutup popup sebelum membuka modal
                    map.closePopup();
                    openEditSarana(saranaId);
                }
            };
        }
    });

    async function load(){
      try{
        console.log('Loading map data...');
        const b = map.getBounds();
        const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(',');
        console.log('Loading data with bbox:', bbox);
        console.log('Filter:', filter);
        // Jika filter nama (q) diisi, jangan batasi dengan bbox agar hasil di luar viewport tetap muncul
        const rows = await fetchSarana(5000, filter.q ? undefined : bbox);
        console.log('Received rows:', rows);
        cluster.clearLayers();
        // Kosongkan objek loadedSarana sebelum mengisinya kembali
        Object.keys(loadedSarana).forEach(key => delete loadedSarana[key]);
        const added = [];
        for (const r of rows){
          // Simpan data sarana ke objek loadedSarana
          loadedSarana[r.id] = r;
          
          const lat = parseFloat(r.latitude), lng = parseFloat(r.longitude);
          if (!isFinite(lat) || !isFinite(lng)) continue;
          const jenisArr = Array.isArray(r.jenis) ? r.jenis : [];
          const jenisName = jenisArr.length ? jenisArr[0] : 'Lainnya';
          const m = L.marker([lat,lng], { icon: makeIcon(jenisName, r.nama_sarana) });
          // Simpan ID sarana ke properti marker untuk akses mudah (opsional, karena kita sudah punya loadedSarana)
          m.saranaId = r.id;
          const gmaps = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
          const jenisText = escapeHtml(jenisArr.join(', '));
          // Tambahkan tombol Edit Data
          m.bindPopup(`
            <div style="min-width:220px">
              <div style="font-weight:600;margin-bottom:4px">${escapeHtml(r.nama_sarana)}</div>
              <div style="color:#4b5563">${escapeHtml(r.kelurahan)}, ${escapeHtml(r.kecamatan)}<br>${escapeHtml(r.kabupaten)}</div>
              ${jenisText?`<div style="margin-top:6px;font-size:12px;color:#374151">Jenis: ${jenisText}</div>`:''}
              <div style="margin-top:8px">
                <a href="${gmaps}" target="_blank" rel="noopener" class="btn" style="margin-right:6px">🧭 Buka di Google Maps</a>
                <button class="btn btn-edit-sarana" data-id="${r.id}" style="margin-top:6px">✏️ Edit Data</button>
              </div>
            </div>
          `);
          cluster.addLayer(m);
          added.push(m);
        }
        console.log('Added markers:', added.length);
        // Ketika filter nama digunakan, hanya lakukan auto-zoom sekali setelah filter diterapkan.
        if (filter.q && justAppliedFilter) {
          if (added.length === 1) {
            const ll = added[0].getLatLng();
            map.setView(ll, NAME_ZOOM);
          } else {
            const bounds = cluster.getBounds && cluster.getBounds();
            if (bounds && typeof bounds.isValid === 'function' && bounds.isValid()) {
              map.fitBounds(bounds, { maxZoom: NAME_ZOOM, padding: [20,20] });
            }
          }
          justAppliedFilter = false;
        }
      }catch(err){
        console.error('Gagal memuat peta:', err);
        // Tampilkan pesan error kepada pengguna
        showToast('Gagal memuat data peta: ' + (err.message || 'Kesalahan tidak diketahui'), false);
      }
    }

    // Simpan referensi fungsi load agar bisa diakses secara global
    window.loadMapData = load;

    // Overlay wiring
    function toggle(el, show){
      el.classList.toggle('active', !!show);
      // Invalidate size as overlays may affect layout
      setTimeout(()=>{ try{ map.invalidateSize(); }catch(_){ } }, 50);
    }
    UI.btnFilter = document.getElementById('btnFilter');
    UI.btnLegend = document.getElementById('btnLegend');
    UI.btnLocate = document.getElementById('btnLocate');
    UI.btnFollow = document.getElementById('btnFollow');
    UI.ovFilter = document.getElementById('ovFilter');
    UI.ovLegend = document.getElementById('ovLegend');
    UI.legendBody = document.getElementById('legendBody');
    UI.q = document.getElementById('f_q');
    UI.qSuggest = document.getElementById('qSuggest');
    UI.selKab = document.getElementById('f_kab');
    UI.selKec = document.getElementById('f_kec');
    UI.selKel = document.getElementById('f_kel');
    UI.selJenis = document.getElementById('f_jenis');

    document.querySelectorAll('[data-close]')?.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const sel = btn.getAttribute('data-close'); const el = document.querySelector(sel); if (el) toggle(el, false);
      })
    });
    if (UI.btnFilter) UI.btnFilter.onclick = ()=> toggle(UI.ovFilter, !UI.ovFilter.classList.contains('active'));
    if (UI.btnLegend) UI.btnLegend.onclick = ()=> toggle(UI.ovLegend, !UI.ovLegend.classList.contains('active'));

    // Populate legend
    (async ()=>{
      const jenis = await fetchJenis();
      const list = (jenis && jenis.length)? jenis.map(j=>j.nama_jenis) : Object.keys(JENIS_META);
      const html = list.map(n=>{
        const icon = makeIcon(n).options.html;
        return `<div class="legend-item"><div class="icon">${icon}</div><div>${escapeHtml(n)}</div></div>`;
      }).join('');
      if (UI.legendBody) UI.legendBody.innerHTML = html;
    })();

    // Populate filters
    (async ()=>{
      try {
        // kabupaten
        const kab = await fetchAreas('kabupaten');
        UI.selKab.innerHTML = '<option value="">(Semua)</option>' + kab.map(x=>`<option value="${escapeHtml(x.name)}">${escapeHtml(x.name)}</option>`).join('');
        UI.selKab.onchange = async ()=>{
          const kabV = UI.selKab.value; UI.selKec.innerHTML = '<option value="">(Semua)</option>'; UI.selKel.innerHTML = '<option value="">(Semua)</option>';
          if (kabV){
            try {
              const kec = await fetchAreas('kecamatan', kabV);
              UI.selKec.innerHTML += kec.map(x=>`<option value="${escapeHtml(x.name)}">${escapeHtml(x.name)}</option>`).join('');
            } catch (err) {
              console.error('Error fetching kecamatan:', err);
            }
          }
        };
        UI.selKec.onchange = async ()=>{
          const kabV = UI.selKab.value; const kecV = UI.selKec.value; UI.selKel.innerHTML = '<option value="">(Semua)</option>';
          if (kabV && kecV){
            try {
              const kel = await fetchAreas('kelurahan', kabV+'|'+kecV);
              UI.selKel.innerHTML += kel.map(x=>`<option value="${escapeHtml(x.name)}">${escapeHtml(x.name)}</option>`).join('');
            } catch (err) {
              console.error('Error fetching kelurahan:', err);
            }
          }
        };
        // jenis
        const jenis = await fetchJenis();
        UI.selJenis.innerHTML = jenis.map(j=>`<option value="${j.id}">${escapeHtml(j.nama_jenis)}</option>`).join('');
      } catch (err) {
        console.error('Error populating filters:', err);
      }
    })();

    // Apply / Reset
    const btnApply = document.getElementById('btnApply');
    const btnReset = document.getElementById('btnReset');
    if (btnApply) btnApply.onclick = ()=>{
      filter.q = UI.q.value.trim();
      filter.kab = UI.selKab.value||''; filter.kec = UI.selKec.value||''; filter.kel = UI.selKel.value||'';
      filter.jenis = Array.from(UI.selJenis.selectedOptions||[]).map(o=>parseInt(o.value,10)).filter(Boolean);
      justAppliedFilter = true;
      toggle(UI.ovFilter,false); load();
    };
    if (btnReset) btnReset.onclick = ()=>{
      UI.q.value=''; UI.selKab.value=''; UI.selKec.value=''; UI.selKel.value='';
      Array.from(UI.selJenis.options).forEach(o=>o.selected=false);
      filter.q=''; filter.kab=''; filter.kec=''; filter.kel=''; filter.jenis=[];
      justAppliedFilter = false;
      map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
      load();
    };

    // Autosuggest for q
    let sugTimer=null; let sugOpen=false;
    if (UI.q && UI.qSuggest){
      const openSug = ()=>{ UI.qSuggest.style.display='block'; sugOpen=true };
      const closeSug = ()=>{ UI.qSuggest.style.display='none'; sugOpen=false };
      UI.q.addEventListener('input', ()=>{
        clearTimeout(sugTimer);
        const v = UI.q.value.trim(); if (!v){ closeSug(); return }
        sugTimer = setTimeout(async ()=>{
          try{
            const res = await fetch(`${API}/suggest.php?q=${encodeURIComponent(v)}&limit=20`);
            const items = res.ok ? await res.json() : [];
            UI.qSuggest.innerHTML = items.map(it=>`<div class="suggest-item" data-lat="${it.latitude}" data-lng="${it.longitude}" data-name="${escapeHtml(it.nama_sarana)}">${escapeHtml(it.nama_sarana)} — ${escapeHtml(it.kelurahan)}, ${escapeHtml(it.kecamatan)}</div>`).join('');
            if (items.length){ openSug(); } else { closeSug(); }
          }catch(_){ closeSug(); }
        }, 250);
      });
      UI.qSuggest.addEventListener('click', (e)=>{
        const it = e.target.closest('.suggest-item'); if (!it) return;
        const name = it.getAttribute('data-name'); const lat = parseFloat(it.getAttribute('data-lat')); const lng = parseFloat(it.getAttribute('data-lng'));
        UI.q.value = name; filter.q = name; closeSug(); justAppliedFilter = true;
        if (isFinite(lat)&&isFinite(lng)) {
          map.setView([lat,lng], NAME_ZOOM);
        }
        // Tutup overlay filter agar peta dapat diinteraksi dengan jelas
        if (UI.ovFilter) toggle(UI.ovFilter, false);
        // Muat ulang data agar hanya sarana tersebut tampil sesuai filter nama
        load();
      });
      document.addEventListener('click', (e)=>{ if (!e.target.closest('.suggest-wrap')) closeSug(); });
    }

    // Locate & follow
    // Helper to show/update current location marker and accuracy circle
    function updateMyLocation(lat, lng, acc){
      const latlng = [lat, lng];
      if (!meMarker){
        const meIcon = L.divIcon({
          className: 'me-pin',
          html: '<div style="width:16px;height:16px;border-radius:50%;background:#2563eb;border:2px solid #fff;box-shadow:0 0 0 4px rgba(37,99,235,.25);"></div>',
          iconSize: [16,16], iconAnchor: [8,8]
        });
        meMarker = L.marker(latlng, { icon: meIcon, zIndexOffset: 10000 }).addTo(map);
        meMarker.bindPopup('<b>Lokasi saya</b>');
      } else {
        meMarker.setLatLng(latlng);
      }
      if (isFinite(acc)){
        if (!meAccCircle){ meAccCircle = L.circle(latlng, { radius: acc, color:'#2563eb', fillColor:'#60a5fa', fillOpacity:0.15, weight:1 }).addTo(map); }
        else { meAccCircle.setLatLng(latlng).setRadius(acc); }
      }
    }

    if (UI.btnLocate) UI.btnLocate.onclick = ()=>{
      map.locate({ setView:true, maxZoom:16, enableHighAccuracy:true });
    };
    map.on('locationfound', (e)=>{
      const lat = (e.latlng && e.latlng.lat) || e.latitude;
      const lng = (e.latlng && e.latlng.lng) || e.longitude;
      updateMyLocation(lat, lng, e.accuracy);
      // Make sure my marker is visible on first locate
      if (!watchId) {
        map.setView([lat, lng], Math.max(map.getZoom(), 16));
      }
    });
    map.on('locationerror', ()=>{ /* ignore */ });

    if (UI.btnFollow) UI.btnFollow.onclick = ()=>{
      if (watchId){
        navigator.geolocation.clearWatch(watchId);
        watchId=null;
        UI.btnFollow.textContent='🧭';
        return;
      }
      if (!navigator.geolocation) return;
      UI.btnFollow.textContent='⏺️';
      watchId = navigator.geolocation.watchPosition(pos=>{
        const { latitude:lat, longitude:lng, accuracy:acc } = pos.coords;
        updateMyLocation(lat, lng, acc);
        map.setView([lat,lng], Math.max(map.getZoom(), 16));
      }, ()=>{ /* ignore */ }, { enableHighAccuracy:true, maximumAge:10000, timeout:10000 });
    };

    // Build legend from API jenis (fallback: keys in JENIS_META)
    (async ()=>{
      const apiJenis = await fetchJenis();
      if (apiJenis && apiJenis.length){ renderLegend(apiJenis); }
      else { renderLegend(Object.keys(JENIS_META)); }
    })();

    map.on('moveend', load);
    load();
  }

  // Salin fungsi util dari admin jika belum ada
  function escapeHtml(s){
    return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }

  async function jfetch(url, opt={}){
    const res = await fetch(url,{ headers:{'Content-Type':'application/json'}, ...opt });
    const txt = await res.text();
    let j; try{ j = JSON.parse(txt) } catch(e){ throw new Error(txt) }
    if (!res.ok || j?.error) throw new Error(j?.error || txt);
    return j;
  }

  // State untuk menyimpan data jenis master dan data sarana yang sedang diedit
  const editState = {
    jenisMaster: [],
    currentSarana: null
  };

  // Fungsi untuk memastikan data jenis master tersedia
  async function ensureJenisMaster(){
    if (!Array.isArray(editState.jenisMaster) || !editState.jenisMaster.length){
      try{ 
        editState.jenisMaster = await jfetch(API + '/jenis.php'); 
      }catch(_){ 
        editState.jenisMaster = []; 
      }
    }
  }

  // Fungsi untuk membuka modal edit sarana
  async function openEditSarana(id){
    console.log("openEditSarana called with id:", id);
    await ensureJenisMaster();
    
    // Ambil data sarana yang akan diedit dari objek loadedSarana
    let saranaData = loadedSarana[id];
    
    if (!saranaData) {
        console.error("Data sarana dengan ID " + id + " tidak ditemukan di loadedSarana.");
        showToast("Data sarana tidak ditemukan.", false);
        return;
    }

    editState.currentSarana = saranaData;

    const dlg = document.getElementById('dlgEditSarana');
    if (!dlg) {
        console.error("Modal edit sarana tidak ditemukan.");
        showToast("Terjadi kesalahan: Modal tidak ditemukan.", false);
        return;
    }

    const body = document.createElement('div');
    
    // Tentukan jenis yang dipilih untuk prefill
    let selJenis = [];
    if (saranaData.jenis_ids) {
        selJenis = String(saranaData.jenis_ids).split(',').map(v=>parseInt(v,10)).filter(Boolean);
    } else if (Array.isArray(saranaData.jenis) && editState.jenisMaster.length) {
        const nameMap = new Map(editState.jenisMaster.map(j=>[j.nama_jenis, j.id]));
        selJenis = saranaData.jenis.map(n=>nameMap.get(n)).filter(Boolean);
    }
    
    const jenisHtml = editState.jenisMaster.length
      ? `<div class="section-title">Jenis Sarana</div>
         <div class="field" style="grid-column:1/3">
           <div class="jenis-search">
             <input id="f_jenis_search_edit" placeholder="Ketik untuk mencari jenis sarana..." autocomplete="off">
           </div>
           <div class="jenis-list">
             ${editState.jenisMaster.map(j=>`
               <label class="jenis-item">
                 <span style="display: flex; align-items: center;">
                   <input type="checkbox" class="f_jenis_edit" value="${j.id}" ${selJenis.includes(j.id)?'checked':''}> 
                   <span style="margin-left: 8px;">${escapeHtml(j.nama_jenis)}</span>
                 </span>
                 <span class="cnt">${j.count??0}</span>
               </label>
             `).join('')}
           </div>
         </div>`
      : '';

    body.innerHTML = `<div class="modal-form grid cols-2">
      <div class="field" style="grid-column:1/3"><label>Nama Sarana</label><input id="f_nama_edit" required value="${escapeHtml(saranaData.nama_sarana || '')}"></div>
      <div class="field"><label>Latitude</label><input id="f_lat_edit" type="number" step="0.0000001" required value="${saranaData.latitude ?? ''}"></div>
      <div class="field"><label>Longitude</label><input id="f_lng_edit" type="number" step="0.0000001" required value="${saranaData.longitude ?? ''}"></div>
      <div class="field"><label>Kabupaten</label><input id="f_kab_edit" required value="${escapeHtml(saranaData.kabupaten || '')}"></div>
      <div class="field"><label>Kecamatan</label><input id="f_kec_edit" required value="${escapeHtml(saranaData.kecamatan || '')}"></div>
      <div class="field" style="grid-column:1/3"><label>Kelurahan</label><input id="f_kel_edit" required value="${escapeHtml(saranaData.kelurahan || '')}"></div>
      ${jenisHtml}
    </div>`;
    
    document.getElementById('dlgEditTitle').textContent = 'Ubah Data Sarana';
    document.getElementById('dlgEditBody').innerHTML = '';
    document.getElementById('dlgEditBody').appendChild(body);
    
    // Aktifkan autosuggest/filter jenis
    const jenisSearch = document.getElementById('f_jenis_search_edit');
    if (jenisSearch){
      setTimeout(()=>jenisSearch.focus(), 50);
      jenisSearch.addEventListener('input', ()=>{
        const q = jenisSearch.value.trim().toLowerCase();
        const items = document.querySelectorAll('.jenis-item');
        items.forEach(it=>{
          const name = it.textContent.toLowerCase();
          it.style.display = name.includes(q)? '' : 'none';
        });
      });
    }
    
    dlg.returnValue=''; 
    dlg.showModal();
    
    // Tangani saat tombol Simpan diklik
    document.getElementById('dlgEditOk').onclick = async function() {
      console.log("Save button clicked");
      const dlg = document.getElementById('dlgEditSarana');
      dlg.close(); // Close the modal first

      // Konfirmasi sebelum menyimpan
      const result = await Swal.fire({
        title: 'Konfirmasi',
        text: "Apakah Anda yakin ingin merubah data?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, simpan!',
        cancelButtonText: 'Batal'
      });

      if (!result.isConfirmed) {
        dlg.showModal(); // Re-open the modal if cancelled
        return;
      }
      
      try {
          // Validasi input
          const namaSarana = document.getElementById('f_nama_edit').value.trim();
          const latitude = parseFloat(document.getElementById('f_lat_edit').value||0);
          const longitude = parseFloat(document.getElementById('f_lng_edit').value||0);
          const kabupaten = document.getElementById('f_kab_edit').value.trim();
          const kecamatan = document.getElementById('f_kec_edit').value.trim();
          const kelurahan = document.getElementById('f_kel_edit').value.trim();
          
          if (!namaSarana || !latitude || !longitude || !kabupaten || !kecamatan || !kelurahan) {
            showToast("Semua field wajib diisi!", false);
            return;
          }
          
          const payload = { 
              id: saranaData.id,
              nama_sarana: namaSarana,
              latitude: latitude,
              longitude: longitude,
              kabupaten: kabupaten,
              kecamatan: kecamatan,
              kelurahan: kelurahan
          };
          // collect jenis_ids if any
          const jboxes = document.querySelectorAll('.f_jenis_edit');
          if (jboxes.length){
            const ids = Array.from(jboxes).filter(x=>x.checked).map(x=>parseInt(x.value,10)).filter(Boolean);
            payload.jenis_ids = ids;
          }
          
          console.log("Sending payload:", payload);
          const response = await jfetch(API+'/sarana.php', { method:'POST', body: JSON.stringify(payload) });
          console.log("Response received:", response);
          
          // Setelah berhasil menyimpan, muat ulang data peta
          await window.loadMapData(); 
          showToast("Data sarana berhasil diperbarui.");
          
          // Tutup modal
          dlg.close();
      } catch (err) {
          console.error("Gagal menyimpan data sarana:", err);
          showToast("Gagal menyimpan perubahan data sarana: " + (err.message || "Kesalahan tidak diketahui"), false);
      }
    };
    
    // Tangani saat tombol Batal diklik
    document.getElementById('dlgEditCancel').onclick = function() {
      console.log("Cancel button clicked");
      dlg.close();
    };
    
    // Tangani saat tombol X diklik
    document.getElementById('dlgEditCloseX').onclick = function() {
      console.log("Close X button clicked");
      dlg.close();
    };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();