// app_map.js — dengan filter dan tracking pengguna
(function(){
  // Shared map reference so GPS handlers can access it
  let map = null;
  // Default map view constants and flags
  const DEFAULT_CENTER = [-2.99, 120.2];
  const DEFAULT_ZOOM = 8;
  const NAME_ZOOM = 19; // zoom in dekat agar hanya 1 sarana terlihat
  // gunakan flag global agar bisa diubah dari handler tombol Apply
  window.justAppliedFilter = false; // digunakan agar auto-zoom tidak mengunci interaksi
  let didCenterOnLocate = false; // center once on first geolocation fix
  // Peta nama jenis -> URL icon (data URL atau path aset)
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
    // fallback ke aset default berdasarkan slug nama
    return `../assets/icon/${slugify(key)}.png`;
  }
  const API = window.API_BASE || '../api';
  const UI = {
    btnFilter: null, btnLocate: null, btnFollow: null,
    ovFilter: null,
    q: null, qSuggest: null, selKab: null, selKec: null, selKel: null, selJenis: null
  };
  const filter = { q:'', kab:'', kec:'', kel:'', jenis:[] };
  let watchId = null;
  let meMarker = null; let meAccCircle = null;
  // Objek untuk menyimpan data sarana yang dimuat, diakses dengan ID
  const loadedSarana = {};

  function escapeHtml(s){
    return String(s||'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
  }

  // Fungsi untuk membuat icon marker
  function makeIcon(kind, nameLabel){
    const iconUrl = (typeof getJenisIcon === 'function') ? getJenisIcon(kind) : '';
    const iconImg = iconUrl ? `<img src="${iconUrl}" alt="${escapeHtml(kind)}" width="32" height="32" style="display:block;margin:0 auto;"/>` : '';
    const html = `<div style="text-align:center;">
                    ${iconImg}
                    <div style="font-size: 12px; color: #000; white-space: nowrap; margin-top: 2px; background: rgba(255, 255, 255, 0.7); padding: 2px 5px; border-radius: 3px; box-shadow: 0 0 2px #fff;">${escapeHtml(nameLabel)}</div>
                  </div>`;
    return L.divIcon({ className: 'custom-div-icon', html, iconSize: [80, 46], iconAnchor: [40, 40], popupAnchor: [0, -40] });
  }

  // Fungsi untuk memuat data sarana
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

  // Autosuggest: fetch nama sarana berdasarkan kueri teks (tanpa bbox)
  async function fetchSuggestNames(term, limit=10){
    try{
      if (!term || term.trim().length < 2) return [];
      const qs = new URLSearchParams();
      qs.set('q', term.trim());
      qs.set('limit', String(limit));
      const url = `${API}/sarana.php?${qs.toString()}`;
      const res = await fetch(url);
      if (!res.ok) return [];
      const data = await res.json();
      if (!Array.isArray(data)) return [];
      return data;
    }catch(err){
      console.error('Error fetching name suggestions:', err);
      return [];
    }
  }

  // Fungsi untuk memuat data wilayah
  async function fetchAreas(type, parent){
    try {
      const qs = new URLSearchParams({ type });
      if (parent) qs.set('parent', parent);
      const res = await fetch(`${API}/areas.php?${qs}`);
      if (!res.ok) return [];
      return res.json();
    } catch (err) {
      console.error('Error fetching areas:', err);
      return [];
    }
  }

  // Fungsi untuk memuat data jenis
  async function fetchJenis(){
    try {
      const res = await fetch(`${API}/jenis.php`);
      if (!res.ok) return [];
      return res.json();
    } catch (err) {
      console.error('Error fetching jenis:', err);
      return [];
    }
  }

  // Inisialisasi peta
  function initMap() {
    console.log('Initializing map with filters and tracking...');
    
    map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    console.log('Map initialized');
    
    // Base layer dengan graceful fallback providers jika primary gagal
    let baseLayer = null; let baseIdx = 0;
    const providers = [
      { url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', opts: { maxZoom: 19, attribution: '&copy; OpenStreetMap' } },
      { url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', opts: { subdomains: 'abc', maxZoom: 19, attribution: '&copy; OpenStreetMap contributors, Tiles style by HOT' } },
      { url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', opts: { subdomains: 'abcd', maxZoom: 19, attribution: '&copy; OpenStreetMap &copy; CARTO' } }
    ];
    
    function attachBase(i){
      console.log('Attaching base layer:', i);
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
    console.log('Cluster group added to map');
    
    // Fungsi untuk memuat data peta
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
          // Popup tanpa tombol edit
          m.bindPopup(`
            <div style="min-width:220px">
              <div style="font-weight:600;margin-bottom:4px">${escapeHtml(r.nama_sarana)}</div>
              <div style="color:#4b5563">${escapeHtml(r.kelurahan)}, ${escapeHtml(r.kecamatan)}<br>${escapeHtml(r.kabupaten)}</div>
              ${jenisText?`<div style="margin-top:6px;font-size:12px;color:#374151">Jenis: ${jenisText}</div>`:''}
              <div style="margin-top:8px">
                <a href="${gmaps}" target="_blank" rel="noopener" class="btn" style="margin-right:6px">🧭 Buka di Google Maps</a>
              </div>
            </div>
          `);
          cluster.addLayer(m);
          added.push(m);
        }
        console.log('Added markers:', added.length);
        // Ketika filter nama digunakan, hanya lakukan auto-zoom sekali setelah filter diterapkan.
        if (filter.q && window.justAppliedFilter) {
          if (added.length === 1) {
            const ll = added[0].getLatLng();
            map.setView(ll, NAME_ZOOM);
          } else {
            const bounds = cluster.getBounds && cluster.getBounds();
            if (bounds && typeof bounds.isValid === 'function' && bounds.isValid()) {
              map.fitBounds(bounds, { maxZoom: NAME_ZOOM, padding: [20,20] });
            }
          }
          window.justAppliedFilter = false;
        }
      }catch(err){
        console.error('Error loading map data:', err);
        // Tampilkan pesan error kepada pengguna
      }
    }
    
    // Panggil load() pertama kali
    load().catch(e => console.error('Error in initial load:', e));
    
    // Simpan referensi fungsi load agar bisa diakses secara global
    window.loadMapData = load;
    console.log('Map initialization completed');
  }

  // Fungsi untuk memulai tracking lokasi pengguna
  function startLocate(){
    console.log('Starting geolocation tracking...');
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }
    
    if (UI.btnFollow) UI.btnFollow.textContent = '📌 Ikuti Lokasi Saya';
    
    didCenterOnLocate = false;
    watchId = navigator.geolocation.watchPosition(
      (pos)=>{
        const { latitude, longitude, accuracy } = pos.coords;
        console.log('Geolocation updated:', latitude, longitude, accuracy);
        // Center and zoom to location on first fix
        if (map && !didCenterOnLocate) {
          try { map.setView([latitude, longitude], 16); } catch(_){}
          didCenterOnLocate = true;
        }
        
        if (meMarker) {
          meMarker.setLatLng([latitude, longitude]);
          if (meAccCircle) meAccCircle.setLatLng([latitude, longitude]).setRadius(accuracy);
        } else {
          meMarker = L.marker([latitude, longitude], {
            icon: L.divIcon({
              className: 'custom-div-icon',
              html: `<div style="background:#3b82f6;border:2px solid #1d4ed8;border-radius:50%;width:16px;height:16px;box-shadow:0 0 0 4px rgba(59,130,246,.3)"></div>`,
              iconSize: [16, 16],
              iconAnchor: [8, 8]
            })
          }).addTo(map);
          meAccCircle = L.circle([latitude, longitude], {
            radius: accuracy,
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.1,
            weight: 1
          }).addTo(map);
        }
        
        if (UI.btnFollow && UI.btnFollow.textContent.includes('Ikuti')) {
          UI.btnFollow.textContent = '📍 Mengikuti...';
        }
      },
      (err)=>{
        console.error('Geolocation error:', err);
        if (watchId !== null) {
          navigator.geolocation.clearWatch(watchId);
          watchId = null;
        }
        if (UI.btnFollow) UI.btnFollow.textContent = '📌 Ikuti Lokasi Saya';
        alert('Gagal mendapatkan lokasi: ' + (err.message || 'Error tidak diketahui'));
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 10000 }
    );
  }

  // Fungsi untuk menghentikan tracking lokasi pengguna
  function stopLocate(){
    console.log('Stopping geolocation tracking...');
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }
    
    if (meMarker) { map.removeLayer(meMarker); meMarker = null; }
    if (meAccCircle) { map.removeLayer(meAccCircle); meAccCircle = null; }
    
    if (UI.btnLocate) UI.btnLocate.textContent = '📍 Lokasi Saya';
    if (UI.btnFollow) UI.btnFollow.textContent = '📌 Ikuti Lokasi Saya';
  }

  // Inisialisasi aplikasi
  function init(){
    console.log('Initializing map application with filters and tracking...');
    
    // Inisialisasi UI elements (sinkron dengan public/index.php)
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
    const btnApply = document.getElementById('btnApply');
    const btnReset = document.getElementById('btnReset');
    
    // Event listener untuk tombol filter (toggle overlay .active)
    if (UI.btnFilter && UI.ovFilter) {
      UI.btnFilter.addEventListener('click', function() {
        UI.ovFilter.classList.toggle('active');
      });
    }

    // Tombol Terapkan/Reset pada overlay filter
    if (btnApply) {
      btnApply.addEventListener('click', function(){
        filter.q = (UI.q?.value || '').trim();
        filter.kab = UI.selKab?.value || '';
        filter.kec = UI.selKec?.value || '';
        filter.kel = UI.selKel?.value || '';
        if (UI.selJenis) {
          const selected = Array.from(UI.selJenis.selectedOptions || []).map(o=>o.value);
          filter.jenis = selected;
        }
        // Auto-zoom satu kali setelah apply
        try { window.justAppliedFilter = true; } catch(_){ }
        if (typeof window.loadMapData === 'function') window.loadMapData();
        if (UI.qSuggest) UI.qSuggest.style.display = 'none';
        if (UI.ovFilter) UI.ovFilter.classList.remove('active');
      });
    }
    if (btnReset) {
      btnReset.addEventListener('click', function(){
        if (UI.q) UI.q.value = '';
        if (UI.selKab) UI.selKab.value = '';
        if (UI.selKec) UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
        if (UI.selKel) UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
        if (UI.selJenis) Array.from(UI.selJenis.options).forEach(o=>o.selected=false);
        filter.q=''; filter.kab=''; filter.kec=''; filter.kel=''; filter.jenis=[];
        if (typeof window.loadMapData === 'function') window.loadMapData();
        if (UI.qSuggest) UI.qSuggest.style.display = 'none';
        // Kembalikan ke default view
        try { if (map) map.setView(DEFAULT_CENTER, DEFAULT_ZOOM); } catch(_){}
        window.justAppliedFilter = false;
      });
    }
    
    // Event listener untuk tombol lokasi
    if (UI.btnLocate) {
      UI.btnLocate.addEventListener('click', function() {
        if (watchId === null) {
          // Mulai tracking
          this.textContent = '⏹️ Hentikan Pelacakan';
          startLocate();
        } else {
          // Hentikan tracking
          this.textContent = '📍 Lokasi Saya';
          stopLocate();
        }
      });
    }
    
    // Event listener untuk tombol ikuti
    if (UI.btnFollow) {
      UI.btnFollow.addEventListener('click', function() {
        if (watchId === null) {
          // Mulai tracking dan ikuti
          UI.btnLocate.textContent = '⏹️ Hentikan Pelacakan';
          startLocate();
          this.textContent = '📍 Mengikuti...';
        } else {
          // Hentikan tracking
          stopLocate();
        }
      });
    }
    
    // Event listener untuk input pencarian
    if (UI.q) {
      UI.q.addEventListener('input', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(() => {
          filter.q = this.value.trim();
          if (window.loadMapData) {
            window.loadMapData();
          }
        }, 300);
      });
    }
    
    // Event listener untuk select kabupaten
    if (UI.selKab) {
      UI.selKab.addEventListener('change', async function() {
        filter.kab = this.value;
        if (window.loadMapData) {
          window.loadMapData();
        }
        
        // Update kecamatan dan kelurahan
        if (UI.selKec) {
          UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>';
          if (this.value) {
            try {
              const kecs = await fetchAreas('kecamatan', this.value);
              UI.selKec.innerHTML = '<option value="">Semua Kecamatan</option>' + 
                kecs.map(k => `<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
            } catch (err) {
              console.error('Error loading kecamatan:', err);
            }
          }
        }
        
        if (UI.selKel) {
          UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
        }
      });
    }
    
    // Event listener untuk select kecamatan
    if (UI.selKec) {
      UI.selKec.addEventListener('change', async function() {
        filter.kec = this.value;
        if (window.loadMapData) {
          window.loadMapData();
        }
        
        // Update kelurahan
        if (UI.selKel && this.value) {
          try {
            const kels = await fetchAreas('kelurahan', this.value);
            UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>' + 
              kels.map(k => `<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
          } catch (err) {
            console.error('Error loading kelurahan:', err);
          }
        } else if (UI.selKel) {
          UI.selKel.innerHTML = '<option value="">Semua Kelurahan</option>';
        }
      });
    }
    
    // Event listener untuk select kelurahan
    if (UI.selKel) {
      UI.selKel.addEventListener('change', function() {
        filter.kel = this.value;
        if (window.loadMapData) {
          window.loadMapData();
        }
      });
    }
    
    // Perubahan jenis akan diaplikasikan saat klik Terapkan

    // =====================
    // Autosuggest f_q input
    // =====================
    let suggestTimer = null;
    function hideSuggest(){ if (UI.qSuggest) { UI.qSuggest.style.display='none'; UI.qSuggest.innerHTML=''; } }
    function renderSuggest(items){
      if (!UI.qSuggest) return;
      if (!items.length){ hideSuggest(); return; }
      const html = items.map((r,idx)=>{
        const name = (r.nama_sarana||'').toString();
        const addr = [r.kelurahan, r.kecamatan, r.kabupaten].filter(Boolean).join(', ');
        return `<div class="suggest-item" data-name="${escapeHtml(name)}" data-id="${r.id??''}">
                  <div style="font-weight:600">${escapeHtml(name)}</div>
                  ${addr?`<div style="font-size:12px;color:#6b7280">${escapeHtml(addr)}</div>`:''}
                </div>`;
      }).join('');
      UI.qSuggest.innerHTML = html;
      UI.qSuggest.style.display = 'block';
    }
    if (UI.q) {
      UI.q.addEventListener('input', function(){
        const term = this.value.trim();
        clearTimeout(suggestTimer);
        if (term.length < 2){ hideSuggest(); return; }
        suggestTimer = setTimeout(async()=>{
          const items = await fetchSuggestNames(term, 10);
          renderSuggest(items);
        }, 250);
      });
      // Enter to apply first result if open
      UI.q.addEventListener('keydown', function(e){
        if (e.key === 'Escape'){ hideSuggest(); return; }
        if (e.key === 'Enter'){
          const first = UI.qSuggest && UI.qSuggest.querySelector('.suggest-item');
          if (first){
            const nm = first.getAttribute('data-name')||'';
            UI.q.value = nm;
            filter.q = nm;
            try { window.justAppliedFilter = true; } catch(_){ }
            if (typeof window.loadMapData === 'function') window.loadMapData();
            hideSuggest();
            e.preventDefault();
          }
        }
      });
    }
    if (UI.qSuggest){
      UI.qSuggest.addEventListener('click', function(e){
        const el = e.target.closest('.suggest-item');
        if (!el) return;
        const nm = el.getAttribute('data-name')||'';
        UI.q.value = nm;
        filter.q = nm;
        try { window.justAppliedFilter = true; } catch(_){ }
        if (typeof window.loadMapData === 'function') window.loadMapData();
        hideSuggest();
      });
    }
    // Hide suggest when clicking outside
    document.addEventListener('click', function(e){
      const wrap = document.querySelector('.suggest-wrap');
      if (!wrap) return;
      if (!wrap.contains(e.target)) hideSuggest();
    });
    
    // Load data awal untuk filter
    Promise.all([
      fetchAreas('kabupaten'),
      fetchJenis()
    ]).then(([kabs, jens]) => {
      // Isi select kabupaten
      if (UI.selKab) {
        UI.selKab.innerHTML = '<option value="">Semua Kabupaten</option>' + 
          kabs.map(k => `<option value="${escapeHtml(k.name)}">${escapeHtml(k.name)}</option>`).join('');
      }
      
      // Isi select jenis
      if (UI.selJenis) {
        UI.selJenis.innerHTML = jens.map(j => 
          `<option value="${escapeHtml(j.nama_jenis)}">${escapeHtml(j.nama_jenis)} (${j.count??0})</option>`
        ).join('');
      }
      // Peta icon jenis (custom base64 bila ada, jika tidak pakai aset default)
      try {
        if (Array.isArray(jens)) {
          for (const j of jens) {
            if (j && j.nama_jenis) {
              if (j.icon_base64) {
                jenisIconMap[j.nama_jenis] = `data:image/png;base64,${j.icon_base64}`;
              } else {
                jenisIconMap[j.nama_jenis] = `../assets/icon/${slugify(j.nama_jenis)}.png`;
              }
            }
          }
        }
        if (!jenisIconMap['Lainnya']) jenisIconMap['Lainnya'] = `../assets/icon/${slugify('Lainnya')}.png`;
      } catch (e) { console.warn('Gagal membangun peta icon jenis:', e); }
    }).catch(err => {
      console.error('Error loading initial filter data:', err);
    });
    
    // Inisialisasi peta
    initMap();
  }

  // Jalankan inisialisasi ketika DOM siap
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
