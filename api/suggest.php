<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

/**
 * Endpoint saran pencarian sarana.
 * GET:
 *   q      : string (min 2 huruf)
 *   limit  : int (default 20, max 100)
 *   page   : int (default 1)
 * Output: [{id,nama_sarana,kabupaten,kecamatan,kelurahan,latitude,longitude,jenis:[...]}, ...]
 */

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = max(1, min(100, intval($_GET['limit'] ?? 20))); // Meningkatkan limit default menjadi 20
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if (mb_strlen($q) < 2) {
    respond([]); // terlalu pendek -> kosong (biar cepat)
}

// Gunakan penilaian relevansi berdasarkan posisi kata kunci dalam nama sarana
$sql = "SELECT s.id, s.nama_sarana, s.latitude, s.longitude,
               s.kabupaten, s.kecamatan, s.kelurahan,
               GROUP_CONCAT(DISTINCT j.nama_jenis ORDER BY j.nama_jenis SEPARATOR '|') AS jenis_list,
               CASE
                   WHEN s.nama_sarana LIKE ? THEN 4  -- Cocok di awal nama
                   WHEN s.nama_sarana LIKE ? THEN 3  -- Cocok di tengah nama
                   WHEN s.kelurahan LIKE ? THEN 2    -- Cocok dengan kelurahan
                   WHEN s.kecamatan LIKE ? THEN 1    -- Cocok dengan kecamatan
                   ELSE 0
               END AS relevance
        FROM data_sarana s
        LEFT JOIN sarana_jenis sj ON sj.sarana_id = s.id
        LEFT JOIN jenis_sarana j  ON j.id = sj.jenis_id
        WHERE (s.nama_sarana LIKE ? OR s.kelurahan LIKE ? OR s.kecamatan LIKE ? OR s.kabupaten LIKE ? OR j.nama_jenis LIKE ?)
        GROUP BY s.id, s.nama_sarana, s.latitude, s.longitude, s.kabupaten, s.kecamatan, s.kelurahan, relevance
        HAVING relevance > 0
        ORDER BY relevance DESC, s.nama_sarana ASC
        LIMIT ? OFFSET ?";

$likeStart = "$q%";
$likeContains = "%$q%";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $likeStart);
$stmt->bindValue(2, $likeContains);
$stmt->bindValue(3, $likeContains);
$stmt->bindValue(4, $likeContains);
$stmt->bindValue(5, $likeContains);
$stmt->bindValue(6, $likeContains);
$stmt->bindValue(7, $likeContains);
$stmt->bindValue(8, $likeContains);
$stmt->bindValue(9, $likeContains);
$stmt->bindValue(10, $limit, PDO::PARAM_INT);
$stmt->bindValue(11, $offset, PDO::PARAM_INT);

$stmt->execute();

$out = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $r['jenis'] = $r['jenis_list'] ? explode('|', $r['jenis_list']) : [];
    unset($r['jenis_list'], $r['relevance']);
    $out[] = $r;
}

respond($out);
