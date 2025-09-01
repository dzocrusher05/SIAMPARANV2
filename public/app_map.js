// app_map.js — dengan filter dan tracking pengguna
(function(){
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
    const bg = '#e5e7eb';
    const iconHtml = `<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 2c-3.86 0-7 3.14-7 7 0 5.25 7 13 7 13s7-7.75 7-13c0-3.86-3.14-7-7-7z" fill="${bg}" stroke="#11182722"/>
      <circle cx="12" cy="9" r="4.2" fill="#ffffff"/>
    </svg>`;

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
    const DEFAULT_CENTER = [-2.99, 120.2];
    const DEFAULT_ZOOM = 8;
    const NAME_ZOOM = 19; // zoom in dekat agar hanya 1 sarana terlihat
    let justAppliedFilter = false; // digunakan agar auto-zoom tidak mengunci interaksi
    
    const map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
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
    
    watchId = navigator.geolocation.watchPosition(
      (pos)=>{
        const { latitude, longitude, accuracy } = pos.coords;
        console.log('Geolocation updated:', latitude, longitude, accuracy);
        
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
    
    // Inisialisasi UI elements
    UI.btnFilter = document.getElementById('btnFilter');
    UI.btnLocate = document.getElementById('btnLocate');
    UI.btnFollow = document.getElementById('btnFollow');
    UI.ovFilter = document.getElementById('ovFilter');
    UI.q = document.getElementById('q');
    UI.qSuggest = document.getElementById('qSuggest');
    UI.selKab = document.getElementById('selKab');
    UI.selKec = document.getElementById('selKec');
    UI.selKel = document.getElementById('selKel');
    UI.selJenis = document.getElementById('selJenis');
    
    // Event listener untuk tombol filter
    if (UI.btnFilter) {
      UI.btnFilter.addEventListener('click', function() {
        if (UI.ovFilter) {
          UI.ovFilter.hidden = !UI.ovFilter.hidden;
        }
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
    
    // Event listener untuk select jenis
    if (UI.selJenis) {
      UI.selJenis.addEventListener('change', function() {
        const selected = Array.from(this.selectedOptions).map(o => o.value);
        filter.jenis = selected;
        if (window.loadMapData) {
          window.loadMapData();
        }
      });
    }
    
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