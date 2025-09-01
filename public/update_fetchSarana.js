// File dokumentasi untuk memperbarui fungsi fetchSarana
// Yang perlu diubah di public/app_map.js:

// Ganti fungsi fetchSarana menjadi:
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