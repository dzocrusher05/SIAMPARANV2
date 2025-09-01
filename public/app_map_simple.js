// app_map.js — versi sederhana untuk debugging
(function(){
  const API = window.API_BASE || '../api';
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

  // Inisialisasi peta
  function initMap() {
    console.log('Initializing simple map...');
    const DEFAULT_CENTER = [-2.99, 120.2];
    const DEFAULT_ZOOM = 8;
    
    const map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    console.log('Simple map initialized');
    
    // Base layer
    const baseLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    
    // Ensure map sizes correctly after initial paint
    setTimeout(()=>{ try{ map.invalidateSize(); }catch(_){ } }, 300);
    
    const cluster = L.markerClusterGroup();
    map.addLayer(cluster);
    console.log('Cluster group added to simple map');
    
    // Fungsi untuk memuat data peta
    async function load(){
      try{
        console.log('Loading simple map data...');
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
      }catch(err){
        console.error('Error loading simple map data:', err);
        // Tampilkan pesan error kepada pengguna
      }
    }
    
    // Tambahkan event listener untuk tombol edit sarana di popup
    map.on('popupopen', function(e) {
      console.log('Popup opened in simple map');
      const popup = e.popup;
      const button = popup._contentNode.querySelector('.btn-edit-sarana');
      if (button) {
        console.log('Edit button found in simple map');
        button.onclick = async function() {
          const saranaId = parseInt(this.getAttribute('data-id'), 10);
          if (saranaId) {
            console.log('Edit sarana clicked with ID:', saranaId);
            // Tutup popup sebelum membuka modal
            map.closePopup();
            // Untuk sementara, tampilkan alert
            alert('Edit sarana dengan ID: ' + saranaId);
          }
        };
      }
    });
    
    // Panggil load() pertama kali
    load().catch(e => console.error('Error in initial simple load:', e));
    
    // Simpan referensi fungsi load agar bisa diakses secara global
    window.loadMapData = load;
    console.log('Simple map initialization completed');
  }

  // Inisialisasi aplikasi sederhana
  function init(){
    console.log('Initializing simple map application...');
    
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