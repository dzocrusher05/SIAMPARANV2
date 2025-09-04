// app_map.js — peta utama dengan filter, autosuggest, cluster, dan GPS (tanpa tombol Edit Data)
(function(){
  // Konstanta global dan state
  const DEFAULT_CENTER = [-2.99, 120.2];
  const DEFAULT_ZOOM = 8;
  const NAME_ZOOM = 19;
  const API = window.API_BASE || '../api';
  let map = null;
  let watchId = null;
  let meMarker = null, meAccCircle = null;
  let didCenterOnLocate = false;
  window.justAppliedFilter = false;

  // UI
  const UI = {
    btnFilter: null, btnLocate: null, btnFollow: null,
    ovFilter: null,
    q: null, qSuggest: null,
    selKab: null, selKec: null, selKel: null, selJenis: null,
    jenisSearch: null,
    kecInput: null, kelInput: null,
    kecSuggest: null, kelSuggest: null
  };

  // Filter
  const filter = { q:'', kab:'', kec:'', kel:'', jenis:[] };

  function escapeHtml(s){
    return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
  }

  // Peta icon jenis (custom dari DB atau fallback ke assets/icon/<slug>.png)
  const jenisIconMap = {};
  function slugify(s){
    return String(s||'')
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'');
  }
  function getJenisIcon(name){
    const key = String(name||'Lainnya');
    if (jenisIconMap[key]) return jenisIconMap[key];
    return `../assets/icon/${slugify(key)}.png`;
  }
  // Icon marker dengan gambar jenis + label nama sarana
  function makeIcon(kindLabel, nameLabel){
    const iconUrl = getJenisIcon(kindLabel);
    const img = iconUrl ? `<img src="${iconUrl}" alt="${escapeHtml(kindLabel)}" width="24" height="24" style="display:block;margin:0 auto;"/>` : '';
    const html = `<div style="text-align:center;">
      ${img}
      <div style="font-size:11px;color:#000;white-space:nowrap;margin-top:2px;background:rgba(255,255,255,.7);padding:2px 5px;border-radius:3px;box-shadow:0 0 2px #fff;">${escapeHtml(nameLabel)}</div>
    </div>`;
    return L.divIcon({ className:'custom-div-icon', html, iconSize:[80,46], iconAnchor:[40,40], popupAnchor:[0,-40] });
  }

  // API fetch sarana
  async function fetchSarana(limit, bbox){
    const qs = new URLSearchParams();
    qs.set('limit', String(limit || 5000));
    if (bbox) qs.set('bbox', bbox);
    if (filter.q) qs.set('q', filter.q);
    if (filter.kab) qs.set('kabupaten', filter.kab);
    if (filter.kec) qs.set('kecamatan', filter.kec);
    if (filter.kel) qs.set('kelurahan', filter.kel);
    if (filter.jenis?.length) qs.set('jenis', filter.jenis.join(','));
    const res = await fetch(`${API}/sarana.php?${qs.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // API wilayah/jenis
  async function fetchAreas(type, parent){
    try{
      const qs = new URLSearchParams({ type });
      if (parent) qs.set('parent', parent);
      const r = await fetch(`${API}/areas.php?${qs}`);
      if (!r.ok) return [];
      return r.json();
    }catch{ return []; }
  }
  async function fetchJenis(){
    try{
      const r = await fetch(`${API}/jenis.php`);
      if (!r.ok) return [];
      return r.json();
    }catch{ return []; }
  }

  // Inisialisasi peta
  function initMap(){
    map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    // Base layer + fallback
    const providers = [
      { url:'https://tile.openstreetmap.org/{z}/{x}/{y}.png', opts:{ maxZoom:19, attribution:'&copy; OpenStreetMap' } },
      { url:'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', opts:{ subdomains:'abc', maxZoom:19, attribution:'&copy; OpenStreetMap contributors, HOT' } },
      { url:'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', opts:{ subdomains:'abcd', maxZoom:19, attribution:'&copy; OpenStreetMap &copy; CARTO' } },
    ];
    let baseLayer = null; let baseIdx = 0;
    function attachBase(i){
      if (baseLayer) try { map.removeLayer(baseLayer); } catch{}
      baseIdx = i;
      baseLayer = L.tileLayer(providers[i].url, providers[i].opts).addTo(map);
      baseLayer.on('tileerror', ()=>{ if (baseIdx < providers.length-1) attachBase(baseIdx+1); });
    }
    attachBase(0);
    setTimeout(()=>{ try{ map.invalidateSize(); }catch{} }, 300);

    const cluster = L.markerClusterGroup();
    map.addLayer(cluster);

    async function load(){
      try{
        const b = map.getBounds();
        const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(',');
        const rows = await fetchSarana(5000, filter.q ? undefined : bbox);
        cluster.clearLayers();
        const added = [];
        for (const r of rows){
          const lat = parseFloat(r.latitude), lng = parseFloat(r.longitude);
          if (!isFinite(lat) || !isFinite(lng)) continue;
          const jenisArr = Array.isArray(r.jenis) ? r.jenis : [];
          const jenisName = jenisArr.length ? jenisArr[0] : 'Lainnya';
          const m = L.marker([lat,lng], { icon: makeIcon(jenisName, r.nama_sarana) });
          const gmaps = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
          const jenisText = escapeHtml(jenisArr.join(', '));
          m.bindPopup(`
            <div style="min-width:220px">
              <div style="font-weight:600;margin-bottom:4px">${escapeHtml(r.nama_sarana)}</div>
              <div style="color:#4b5563">${escapeHtml(r.kelurahan)}, ${escapeHtml(r.kecamatan)}<br>${escapeHtml(r.kabupaten)}</div>
              ${jenisText?`<div style=\"margin-top:6px;font-size:12px;color:#374151\">Jenis: ${jenisText}</div>`:''}
              <div style="margin-top:8px">
                <a href="${gmaps}" target="_blank" rel="noopener" class="btn" style="margin-right:6px">Buka di Google Maps</a>
              </div>
            </div>
          `);
          cluster.addLayer(m); added.push(m);
        }
        if (filter.q && window.justAppliedFilter) {
          if (added.length === 1) { map.setView(added[0].getLatLng(), NAME_ZOOM); }
          else { const bounds = cluster.getBounds && cluster.getBounds(); if (bounds && bounds.isValid()) map.fitBounds(bounds, { maxZoom: NAME_ZOOM, padding:[20,20] }); }
          window.justAppliedFilter = false;
        }
      }catch(e){ console.error('Error loading map data:', e); }
    }
    window.loadMapData = load;
    load().catch(()=>{});
  }

  function startLocate(){
    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    didCenterOnLocate = false;
    watchId = navigator.geolocation.watchPosition((pos)=>{
      const { latitude, longitude, accuracy } = pos.coords;
      if (map && !didCenterOnLocate) { try{ map.setView([latitude, longitude], 16);}catch{} didCenterOnLocate = true; }
      if (meMarker) {
        meMarker.setLatLng([latitude, longitude]);
        if (meAccCircle) meAccCircle.setLatLng([latitude, longitude]).setRadius(accuracy);
      } else {
        meMarker = L.marker([latitude, longitude], { icon: L.divIcon({ className:'custom-div-icon', html:`<div style=\"background:#3b82f6;border:2px solid #1d4ed8;border-radius:50%;width:16px;height:16px;box-shadow:0 0 0 4px rgba(59,130,246,.3)\"></div>`, iconSize:[16,16], iconAnchor:[8,8] }) }).addTo(map);
        meAccCircle = L.circle([latitude, longitude], { radius:accuracy, color:'#3b82f6', fillColor:'#3b82f6', fillOpacity:.1, weight:1 }).addTo(map);
      }
    }, (err)=>{
      console.error('Geolocation error:', err);
      if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
      alert('Gagal mendapatkan lokasi: ' + (err.message || 'Error tidak diketahui'));
    }, { enableHighAccuracy:true, maximumAge:10000, timeout:10000 });
  }
  function stopLocate(){
    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    if (meMarker) { map.removeLayer(meMarker); meMarker = null; }
    if (meAccCircle) { map.removeLayer(meAccCircle); meAccCircle = null; }
  }

  // Inisialisasi aplikasi + UI
  function init(){
    UI.btnFilter = document.getElementById('btnFilter');
    UI.btnLocate = document.getElementById('btnLocate');
    UI.btnFollow = document.getElementById('btnFollow');
    UI.ovFilter = document.getElementById('ovFilter');
    UI.q = document.getElementById('f_q');
    UI.qSuggest = document.getElementById('qSuggest');
    UI.selKab = document.getElementById('f_kab');
    UI.selKec = document.getElementById('f_kec');
    UI.selKel = document.getElementById('f_kel');
    UI.selJenis = document.getElementById('f_jenis');
    UI.jenisSearch = document.getElementById('f_jenis_search');
    UI.kecInput = document.getElementById('f_kec_input');
    UI.kelInput = document.getElementById('f_kel_input');
    UI.kecSuggest = document.getElementById('kecSuggest');
    UI.kelSuggest = document.getElementById('kelSuggest');
    const btnApply = document.getElementById('btnApply');
    const btnReset = document.getElementById('btnReset');

    // Siapkan opsi default agar dropdown tidak kosong di awal
    if (UI.selKec && !UI.selKec.innerHTML) UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
    if (UI.selKel && !UI.selKel.innerHTML) UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';

    // Toggle filter overlay
    if (UI.btnFilter && UI.ovFilter){ UI.btnFilter.addEventListener('click', ()=> UI.ovFilter.classList.toggle('active')); }

    // Apply/Reset
    if (btnApply){ btnApply.addEventListener('click', ()=>{
      filter.q = (UI.q?.value || '').trim();
      filter.kab = UI.selKab?.value || '';
      filter.kec = (UI.kecInput?.value || UI.selKec?.value || '').trim();
      filter.kel = (UI.kelInput?.value || UI.selKel?.value || '').trim();
      // Kumpulkan pilihan jenis dari checkbox
      if (UI.selJenis) {
        const checks = UI.selJenis.querySelectorAll('input[type="checkbox"]:checked');
        filter.jenis = Array.from(checks).map(i=>i.value);
      }
      window.justAppliedFilter = true;
      if (typeof window.loadMapData==='function') window.loadMapData();
      if (UI.qSuggest) UI.qSuggest.style.display='none';
      if (UI.ovFilter) UI.ovFilter.classList.remove('active');
    }); }
    if (btnReset){ btnReset.addEventListener('click', ()=>{
      if (UI.q) UI.q.value=''; if (UI.selKab) UI.selKab.value='';
      if (UI.selKec) UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
      if (UI.selKel) UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
      if (UI.kecInput) UI.kecInput.value='';
      if (UI.kelInput) UI.kelInput.value='';
      if (UI.kecSuggest){ UI.kecSuggest.style.display='none'; UI.kecSuggest.innerHTML=''; }
      if (UI.kelSuggest){ UI.kelSuggest.style.display='none'; UI.kelSuggest.innerHTML=''; }
      if (UI.jenisSearch) UI.jenisSearch.value='';
      if (UI.selJenis) {
        UI.selJenis.querySelectorAll('input[type="checkbox"]').forEach(cb=>{ cb.checked = false; cb.parentElement.style.display=''; });
      }
      filter.q=''; filter.kab=''; filter.kec=''; filter.kel=''; filter.jenis=[];
      if (typeof window.loadMapData==='function') window.loadMapData();
      if (map) try{ map.setView(DEFAULT_CENTER, DEFAULT_ZOOM); }catch{}
    }); }

    // Dinamis: Muat kecamatan saat kabupaten berubah, dan kelurahan saat kecamatan berubah
    let kecList = [];
    let kelList = [];
    function buildSuggestItems(items){
      return (items||[]).map(nm=>`<div class=\"suggest-item\" data-name=\"${escapeHtml(nm)}\">${escapeHtml(nm)}</div>`).join('');
    }
    function showSuggest(container, items){
      if (!container) return;
      const html = buildSuggestItems(items);
      container.innerHTML = html;
      container.style.display = html ? 'block' : 'none';
    }
    function hideSuggestBox(container){ if (container){ container.style.display='none'; container.innerHTML=''; } }
    function renderSelectOptions(selectEl, items, placeholder){
      if (!selectEl) return;
      const opts = [`<option value="">${placeholder}</option>`]
        .concat((items||[]).map(nm=>`<option value="${escapeHtml(nm)}">${escapeHtml(nm)}</option>`));
      selectEl.innerHTML = opts.join('');
    }

    if (UI.selKab){
      UI.selKab.addEventListener('change', async ()=>{
        const kab = UI.selKab.value || '';
        // Reset dependent selects
        if (UI.selKec) UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
        if (UI.selKel) UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
        // Reset input + lists
        if (UI.kecInput) UI.kecInput.value = '';
        if (UI.kelInput) UI.kelInput.value = '';
        kecList = []; kelList = [];
        hideSuggestBox(UI.kecSuggest); hideSuggestBox(UI.kelSuggest);
        if (!kab) return; // Jika kabupaten kosong, selesai
        try{
          const kecs = await fetchAreas('kecamatan', kab);
          kecList = Array.isArray(kecs) ? kecs.map(k=>k.name).filter(Boolean) : [];
          renderSelectOptions(UI.selKec, kecList, 'Semua Kecamatan');
          // Autosuggest: tampilkan langsung semua kecamatan
          showSuggest(UI.kecSuggest, kecList);
        }catch(_){ /* biarkan default */ }
      });
    }
    async function loadKelurahanFor(kab, kec){
      if (UI.selKel) UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
      kelList = [];
      if (!kab || !kec) return;
      try{
        const kels = await fetchAreas('kelurahan', `${kab}|${kec}`);
        kelList = Array.isArray(kels) ? kels.map(k=>k.name).filter(Boolean) : [];
        renderSelectOptions(UI.selKel, kelList, 'Semua Kelurahan');
        // Autosuggest: tampilkan langsung semua kelurahan
        showSuggest(UI.kelSuggest, kelList);
      }catch(_){ /* biarkan default */ }
    }

    // Listener select kecamatan untuk memuat kelurahan
    if (UI.selKec){
      UI.selKec.addEventListener('change', async ()=>{
        const kab = UI.selKab?.value || '';
        const kec = UI.selKec.value || '';
        if (UI.kecInput) UI.kecInput.value = kec;
        if (UI.kelInput) UI.kelInput.value = '';
        hideSuggestBox(UI.kelSuggest);
        await loadKelurahanFor(kab, kec);
      });
    }

    // Input ketik Kecamatan → filter daftar dan pilih dengan klik/Enter
    if (UI.kecInput){
      UI.kecInput.addEventListener('focus', ()=>{ if (kecList.length) showSuggest(UI.kecSuggest, kecList); });
      UI.kecInput.addEventListener('input', ()=>{
        const q = (UI.kecInput.value||'').toLowerCase().trim();
        const items = q ? kecList.filter(n=>n.toLowerCase().includes(q)) : kecList;
        showSuggest(UI.kecSuggest, items);
      });
      UI.kecInput.addEventListener('keydown', async (e)=>{
        if (e.key==='Escape'){ hideSuggestBox(UI.kecSuggest); return; }
        if (e.key==='Enter'){
          const first = UI.kecSuggest && UI.kecSuggest.querySelector('.suggest-item');
          if (first){
            const nm = first.getAttribute('data-name')||'';
            UI.kecInput.value = nm; if (UI.selKec) UI.selKec.value = nm;
            hideSuggestBox(UI.kecSuggest);
            await loadKelurahanFor(UI.selKab?.value||'', nm);
            e.preventDefault();
          }
        }
      });
      if (UI.kecSuggest){
        UI.kecSuggest.addEventListener('click', async (e)=>{
          const el = e.target.closest('.suggest-item'); if (!el) return;
          const nm = el.getAttribute('data-name')||'';
          UI.kecInput.value = nm; if (UI.selKec) UI.selKec.value = nm;
          hideSuggestBox(UI.kecSuggest);
          await loadKelurahanFor(UI.selKab?.value||'', nm);
        });
      }
    }

    // Input ketik Kelurahan → filter daftar dan pilih dengan klik/Enter
    if (UI.kelInput){
      UI.kelInput.addEventListener('focus', ()=>{ if (kelList.length) showSuggest(UI.kelSuggest, kelList); });
      UI.kelInput.addEventListener('input', ()=>{
        const q = (UI.kelInput.value||'').toLowerCase().trim();
        const items = q ? kelList.filter(n=>n.toLowerCase().includes(q)) : kelList;
        showSuggest(UI.kelSuggest, items);
      });
      UI.kelInput.addEventListener('keydown', (e)=>{
        if (e.key==='Escape'){ hideSuggestBox(UI.kelSuggest); return; }
        if (e.key==='Enter'){
          const first = UI.kelSuggest && UI.kelSuggest.querySelector('.suggest-item');
          if (first){
            const nm = first.getAttribute('data-name')||'';
            UI.kelInput.value = nm; if (UI.selKel) UI.selKel.value = nm;
            hideSuggestBox(UI.kelSuggest);
            e.preventDefault();
          }
        }
      });
      if (UI.kelSuggest){
        UI.kelSuggest.addEventListener('click', (e)=>{
          const el = e.target.closest('.suggest-item'); if (!el) return;
          const nm = el.getAttribute('data-name')||'';
          UI.kelInput.value = nm; if (UI.selKel) UI.selKel.value = nm;
          hideSuggestBox(UI.kelSuggest);
        });
      }
    }

    // Lokasi & ikuti
    if (UI.btnLocate){ UI.btnLocate.addEventListener('click', function(){ if (watchId===null){ this.textContent='Hentikan Pelacakan'; startLocate(); } else { this.textContent='Lokasi Saya'; stopLocate(); } }); }
    if (UI.btnFollow){ UI.btnFollow.addEventListener('click', function(){ if (watchId===null){ UI.btnLocate.textContent='Hentikan Pelacakan'; startLocate(); this.textContent='Mengikuti...'; } else { stopLocate(); } }); }

    // Autosuggest
    let suggestTimer=null;
    function hideSuggest(){ if (UI.qSuggest){ UI.qSuggest.style.display='none'; UI.qSuggest.innerHTML=''; } }
    async function fetchSuggest(term){
      if (!term || term.trim().length<2) return [];
      const qs = new URLSearchParams(); qs.set('q', term.trim()); qs.set('limit','10');
      const r = await fetch(`${API}/sarana.php?${qs}`); if (!r.ok) return []; const data = await r.json();
      return Array.isArray(data)? data: [];
    }
    if (UI.q){
      UI.q.addEventListener('input', ()=>{
        const term = UI.q.value.trim(); clearTimeout(suggestTimer);
        if (term.length<2){ hideSuggest(); return; }
        suggestTimer=setTimeout(async()=>{ const items = await fetchSuggest(term); if (!UI.qSuggest) return; if (!items.length){ hideSuggest(); return; }
          UI.qSuggest.innerHTML = items.map(r=>{ const nm=escapeHtml(r.nama_sarana||''); const addr=[r.kelurahan,r.kecamatan,r.kabupaten].filter(Boolean).join(', '); return `<div class="suggest-item" data-name="${nm}"><div style="font-weight:600">${nm}</div>${addr?`<div style=\"font-size:12px;color:#6b7280\">${escapeHtml(addr)}</div>`:''}</div>`; }).join('');
          UI.qSuggest.style.display='block';
        },250);
      });
      UI.q.addEventListener('keydown', (e)=>{ if (e.key==='Escape') { hideSuggest(); return; } if (e.key==='Enter'){ const first = UI.qSuggest && UI.qSuggest.querySelector('.suggest-item'); if (first){ const nm = first.getAttribute('data-name')||''; UI.q.value=nm; filter.q=nm; window.justAppliedFilter=true; if (typeof window.loadMapData==='function') window.loadMapData(); hideSuggest(); e.preventDefault(); } } });
    }
    if (UI.qSuggest){ UI.qSuggest.addEventListener('click', (e)=>{ const el=e.target.closest('.suggest-item'); if (!el) return; const nm=el.getAttribute('data-name')||''; UI.q.value=nm; filter.q=nm; window.justAppliedFilter=true; if (typeof window.loadMapData==='function') window.loadMapData(); hideSuggest(); }); }
    // Tutup autosuggest jika klik di luar
    document.addEventListener('click', (e)=>{
      const qWrap = document.querySelector('.suggest-wrap');
      if (qWrap && !qWrap.contains(e.target)) hideSuggest();
      const kecWrap = UI.kecInput ? UI.kecInput.closest('.suggest-wrap') : null;
      const kelWrap = UI.kelInput ? UI.kelInput.closest('.suggest-wrap') : null;
      if (kecWrap && !kecWrap.contains(e.target)) hideSuggestBox(UI.kecSuggest);
      if (kelWrap && !kelWrap.contains(e.target)) hideSuggestBox(UI.kelSuggest);
    });

    // Load master filter lalu init map
    Promise.all([ fetchAreas('kabupaten'), fetchJenis() ]).then(([kabs, jens])=>{
      if (UI.selKab) UI.selKab.innerHTML = '<option value="">Semua Kabupaten</option>' + kabs.map(k=>`<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
      // Bangun daftar jenis sebagai checkbox
      if (UI.selJenis) {
        const items = Array.isArray(jens) ? jens : [];
        UI.selJenis.innerHTML = items.map(j=>{
          const nm = escapeHtml(j.nama_jenis||'');
          const cnt = typeof j.count==='number' ? j.count : (j.count??0);
          const checked = filter.jenis.includes(j.nama_jenis) ? ' checked' : '';
          return `<label class="jenis-item"><input type="checkbox" value="${nm}"${checked} /> <span>${nm} <span class="cnt">(${cnt})</span></span></label>`;
        }).join('');
      }
      // Pencarian jenis lokal
      if (UI.jenisSearch && UI.selJenis) {
        UI.jenisSearch.addEventListener('input', ()=>{
          const q = (UI.jenisSearch.value||'').toLowerCase().trim();
          UI.selJenis.querySelectorAll('.jenis-item').forEach(el=>{
            const text = el.textContent.toLowerCase();
            el.style.display = !q || text.includes(q) ? '' : 'none';
          });
        });
      }
      // Bangun peta icon: pakai base64 dari DB bila ada, jika tidak gunakan fallback assets/icon
      try {
        if (Array.isArray(jens)) {
          for (const j of jens) {
            if (j && j.nama_jenis) {
              if (j.icon_base64) jenisIconMap[j.nama_jenis] = `data:image/png;base64,${j.icon_base64}`;
              else jenisIconMap[j.nama_jenis] = `../assets/icon/${slugify(j.nama_jenis)}.png`;
            }
          }
        }
        if (!jenisIconMap['Lainnya']) jenisIconMap['Lainnya'] = `../assets/icon/${slugify('Lainnya')}.png`;
      } catch(_){}
    }).catch(()=>{}).finally(()=> initMap());
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
