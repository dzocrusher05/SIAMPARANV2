<?php
// Pemetaan Sarana — Versi minimal (langkah 1)
require_once __DIR__ . '/../config/auth.php';
if (!isset($_GET['noauth']) && !isLoggedIn()) {
  header('Location: ../admin/login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Peta Sarana</title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Ccircle cx='8' cy='8' r='8' fill='%23111827'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="./assets/app-public.css" />
  <link rel="stylesheet" href="./vendor/leaflet/leaflet.css" />
  <link rel="stylesheet" href="./vendor/leaflet.markercluster/MarkerCluster.css" />
  <link rel="stylesheet" href="./vendor/leaflet.markercluster/MarkerCluster.Default.css" />
  <!-- Fallback CSS from CDN to prevent gray map if local assets fail -->
  <link rel="preconnect" href="https://unpkg.com" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" media="print" onload="this.media='all'" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" media="print" onload="this.media='all'" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .swal2-container {
      z-index: 9999;
    }
  </style>
  <style>
    /* Hide legend button (removed by request) */
    #btnLegend { display: none !important; }
    html,
    body,
    #map {
      height: 100%;
      margin: 0
    }

    .leaflet-container {
      background: #f8fafc
    }

    /* Marker label styling */
    .jenis-pin .pin-wrap {
      position: relative;
      width: 36px;
      height: 50px
    }

    .jenis-pin .pin-label {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      top: 44px;
      background: #fff;
      border: 1px solid #e5e7eb;
      padding: 2px 6px;
      border-radius: 8px;
      font-size: 12px;
      color: #111827;
      white-space: nowrap;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
    }

    /* Floating Buttons */
    .fab {
      position: absolute;
      z-index: 650;
      display: flex;
      gap: 8px
    }

    .btn-fab {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      border: 1px solid #e5e7eb;
      border-radius: 999px;
      background: #ffffffd9;
      backdrop-filter: blur(4px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, .08);
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn-fab:hover {
      background: #fff;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, .12);
    }

    /* Overlay */
    .overlay {
      position: absolute;
      z-index: 660;
      display: none;
      inset: 0;
      justify-content: center;
      align-items: center;
    }

    .overlay.active {
      display: flex;
    }

    .ov-box {
      width: min(88vw, 560px);
      max-height: 68vh;
      overflow: auto;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
      animation: modalAppear 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      margin: auto;
    }

    .ov-head {
      position: sticky;
      top: 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      background: #ffffffeb;
      backdrop-filter: blur(4px);
      border-bottom: 1px solid #eef2f7;
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      font-size: 18px;
      font-weight: 600;
    }

    .ov-body {
      padding: 16px 20px;
    }

    .btn-close {
      border: none;
      background: transparent;
      font-size: 20px;
      cursor: pointer;
      color: #6b7280;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .btn-close:hover {
      color: #111827;
      background: #f3f4f6;
    }

    /* Filter form */
    .grid {
      display: grid;
      gap: 16px
    }

    .grid.cols-2 {
      grid-template-columns: 1fr 1fr
    }

    .field {
      display: flex;
      flex-direction: column
    }

    .field label {
      font-size: 14px;
      color: #6b7280;
      margin-bottom: 6px;
      font-weight: 500;
    }

    .field input,
    .field select {
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 14px;
      transition: all 0.2s ease;
      background: #fff;        /* pastikan tidak gelap di mode gelap */
      color: #111827;          /* teks selalu terlihat */
    }

    .field input:focus,
    .field select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
    }

    .actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid #f3f4f6;
    }

    .btn {
      display: inline-block;
      border: 1px solid #e5e7eb;
      background: #fff;
      border-radius: 10px;
      padding: 10px 16px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn:hover {
      background: #f9fafb;
      transform: translateY(-1px);
    }

    .btn.primary {
      background: #111827;
      color: #fff;
      border-color: #111827;
    }

    .btn.primary:hover {
      background: #1f2937;
      transform: translateY(-1px);
    }

    /* Autosuggest */
    .suggest-wrap {
      position: relative
    }

    .suggest-list {
      position: absolute;
      inset-inline: 0;
      top: 100%;
      margin-top: 6px;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, .14);
      max-height: 50vh;
      overflow: auto;
      display: none;
      z-index: 1000;
    }

    .suggest-item {
      padding: 10px 14px;
      cursor: pointer;
      border-radius: 8px;
      margin: 4px;
    }

    .suggest-item:hover {
      background: #f3f4f6;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 10px;
      border-radius: 6px;
      transition: background 0.2s ease;
      font-size: 13px;
    }

    .legend-item:hover {
      background: #f9fafb;
    }

    /* Legend icon styling */
    .legend-icon {
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
      font-size: 14px;
    }

    /* Animations */
    @keyframes modalAppear {
      from {
        opacity: 0;
        transform: scale(0.8) translateY(20px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    /* Modal overlay background */
    .overlay {
      background: rgba(0, 0, 0, 0);
      transition: background 0.2s ease;
    }

    .overlay.active {
      background: rgba(0, 0, 0, 0.5);
    }

    /* Scrollbar styling */
    .ov-box::-webkit-scrollbar {
      width: 6px;
    }

    .ov-box::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 10px;
    }

    .ov-box::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 10px;
    }

    .ov-box::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }

    /* Toast notification */
    #toast-notification {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      background: #10B981;
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
    }

    #toast-notification.error {
      background: #EF4444;
    }

    .toast-content {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .toast-icon {
      font-size: 20px;
    }

    .toast-message {
      flex: 1;
    }

    /* Styling khusus untuk form edit sarana */
    #dlgEditSarana {
      border: none;
      border-radius: 16px;
      padding: 0;
      width: min(90vw, 500px);
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
      animation: modalAppear 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 20px;
      background: #ffffffeb;
      backdrop-filter: blur(4px);
      border-bottom: 1px solid #eef2f7;
      border-top-left-radius: 16px;
      border-top-right-radius: 16px;
      font-size: 18px;
      font-weight: 600;
    }

    .modal-close {
      border: none;
      background: transparent;
      font-size: 20px;
      cursor: pointer;
      color: #6b7280;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .modal-close:hover {
      color: #111827;
      background: #f3f4f6;
    }

    .modal-body {
      padding: 16px 20px;
      max-height: 60vh;
      overflow-y: auto;
    }

    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      padding: 16px 20px;
      border-top: 1px solid #f3f4f6;
    }

    /* Styling untuk form edit */
    .modal-form {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .modal-form .field {
      display: flex;
      flex-direction: column;
    }

    .modal-form .field label {
      font-size: 14px;
      color: #6b7280;
      margin-bottom: 6px;
      font-weight: 500;
    }

    .modal-form .field input,
    .modal-form .field select {
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .modal-form .field input:focus,
    .modal-form .field select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
    }

    /* Jenis sarana styling */
    .jenis-search {
      margin-bottom: 12px;
    }

    .jenis-search input {
      width: 100%;
      height: 42px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 14px;
      transition: all 0.2s ease;
    }

    .jenis-search input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, .2);
    }

    .jenis-list {
      max-height: 200px;
      overflow-y: auto;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 8px;
    }

    .jenis-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      border-radius: 8px;
      margin-bottom: 4px;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .jenis-item:hover {
      background: #f3f4f6;
    }

    .jenis-item input {
      margin-right: 8px;
    }

    .cnt {
      font-size: 12px;
      color: #6b7280;
      background: #f3f4f6;
      padding: 2px 8px;
      border-radius: 20px;
    }

    .section-title {
      font-weight: 600;
      margin-bottom: 12px;
      color: #111827;
      grid-column: 1 / 3;
    }
  </style>
</head>

<body>
  <div id="map" style="height:100vh"></div>
  <!-- Top-right Admin Button -->
  <div class="fab" style="top:12px; right:12px">
    <a class="btn-fab" href="../admin/index.php" title="Buka Admin">⚙️ Admin</a>
  </div>
  <!-- Bottom-left: Filter & Legend Buttons -->
  <div class="fab" style="left:12px; bottom:12px">
    <button id="btnFilter" class="btn-fab">🔎 Filter</button>
    <button id="btnLegend" class="btn-fab">📋 Legenda</button>
  </div>
  <!-- Bottom-right: GPS & Follow -->
  <div class="fab" style="right:12px; bottom:12px">
    <button id="btnLocate" class="btn-fab" title="Lokasi saya">📍 Lokasi</button>
    <button id="btnFollow" class="btn-fab" title="Ikuti pergerakan">🧭</button>
  </div>
  <!-- Modal Filter - centered popup (initially hidden) -->
  <div id="ovFilter" class="overlay" style="background: rgba(0,0,0,0.5);">
    <div class="ov-box" style="width: min(90vw, 500px); max-height: 80vh;">
      <div class="ov-head">
        <div style="font-weight:700">Filter Data</div><button class="btn-close" data-close="#ovFilter">✕</button>
      </div>
      <div class="ov-body">
        <div class="grid cols-2">
          <div class="field suggest-wrap" style="grid-column:1/3">
            <label>Cari nama sarana</label>
            <input id="f_q" placeholder="Ketik nama sarana..." autocomplete="off" />
            <div id="qSuggest" class="suggest-list"></div>
          </div>
          <div class="field"><label>Kabupaten</label><select id="f_kab"></select></div>
          <div class="field"><label>Kecamatan</label><select id="f_kec"></select></div>
          <div class="field" style="grid-column:1/3"><label>Kelurahan</label><select id="f_kel"></select></div>
          <div class="field" style="grid-column:1/3">
            <label>Jenis Sarana</label>
            <div class="jenis-search"><input id="f_jenis_search" placeholder="Cari jenis sarana..." autocomplete="off" /></div>
            <div id="f_jenis" class="jenis-list"></div>
          </div>
        </div>
        <div class="actions">
          <button id="btnReset" class="btn">Reset</button>
          <button id="btnApply" class="btn primary">Terapkan</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Legend overlay removed by request -->

  <!-- Modal Edit Sarana -->
  <dialog id="dlgEditSarana">
    <div class="modal-header">
      <div id="dlgEditTitle">Edit Data Sarana</div>
      <button type="button" class="modal-close" id="dlgEditCloseX" aria-label="Tutup">✕</button>
    </div>
    <div id="dlgEditBody" class="modal-body"></div>
    <div class="modal-footer">
      <button class="btn" id="dlgEditCancel">Batal</button>
      <button class="btn primary" id="dlgEditOk">Simpan</button>
    </div>
  </dialog>

  <script>
    window.API_BASE = "../api";
    
    // Close modals when clicking outside the modal content
    document.addEventListener('DOMContentLoaded', function() {
      // Get all overlay elements
      const overlays = document.querySelectorAll('.overlay');
      
      overlays.forEach(overlay => {
        // Add click event to overlay background
        overlay.addEventListener('click', function(e) {
          // Check if the click was directly on the overlay (not on its content)
          if (e.target === this) {
            // Remove active class to hide the overlay
            this.classList.remove('active');
          }
        });
      });
      
      // Also close overlays when close buttons are clicked
      const closeButtons = document.querySelectorAll('.btn-close');
      closeButtons.forEach(button => {
        button.addEventListener('click', function() {
          const overlayId = this.getAttribute('data-close');
          if (overlayId) {
            const overlay = document.querySelector(overlayId);
            if (overlay) {
              overlay.classList.remove('active');
            }
          }
        });
      });
    });
    
    (function() {
      function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb;
        s.onerror = cb;
        document.head.appendChild(s);
      }

      function ensureLeaflet(cb) {
        if (window.L) return cb();
        console.log('Loading Leaflet from local...');
        // try local
        loadScript('./vendor/leaflet/leaflet.js', function() {
          if (window.L) {
            console.log('Leaflet loaded successfully from local');
            return cb();
          }
          console.log('Leaflet not found locally, trying CDN...');
          // fallback CDN
          loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', cb);
        });
      }

      function ensureCluster(cb) {
        if (window.L && (L.MarkerClusterGroup || L.markerClusterGroup)) return cb();
        console.log('Loading MarkerCluster from local...');
        loadScript('./vendor/leaflet.markercluster/leaflet.markercluster.js', function() {
          if (window.L && (L.MarkerClusterGroup || L.markerClusterGroup)) {
            console.log('MarkerCluster loaded successfully from local');
            return cb();
          }
          console.log('MarkerCluster not found locally, trying CDN...');
          loadScript('https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', cb);
        });
      }
      ensureLeaflet(function() {
        ensureCluster(function() {
          console.log('Loading app_map.js...');
          loadScript('./app_map.js', function() {
            console.log('app_map.js load attempted');
          });
        });
      });
    })();
  </script>
</body>

</html>
