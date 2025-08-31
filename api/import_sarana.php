<?php
// api/import_sarana.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

// Robust error reporting in JSON for unexpected issues
set_exception_handler(function($e){
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Terjadi kesalahan tak terduga', 'detail' => $e->getMessage()]);
    exit;
});
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Gunakan metode POST (multipart/form-data)'], 405);
}

// Ambil file dari input: "file" (boleh juga "csv")
$u = $_FILES['file'] ?? $_FILES['csv'] ?? null;
if (!$u || $u['error'] !== UPLOAD_ERR_OK) {
    respond(['error' => 'Upload file XLSX/CSV melalui field "file"'], 422);
}

// Parameter opsional
$hasHeader = (isset($_POST['has_header']) ? ($_POST['has_header'] === '1' || strtolower($_POST['has_header']) === 'true') : true);
$delimOpt  = $_POST['delimiter'] ?? 'auto'; // tetap didukung untuk CSV: auto | , | ; | \t

// Util: deteksi XLSX dari ekstensi/MIME
function is_xlsx_upload($file)
{
    $name = strtolower($file['name'] ?? '');
    $type = strtolower($file['type'] ?? '');
    if (str_ends_with($name, '.xlsx')) return true;
    if (strpos($type, 'spreadsheetml') !== false) return true;
    return false;
}

// Tolak .xls lama dengan pesan yang jelas
if (isset($u['name']) && str_ends_with(strtolower($u['name']), '.xls')) {
    respond(['error' => 'Format .xls tidak didukung. Simpan file sebagai .xlsx lalu coba lagi.'], 422);
}

// Helper CSV: tebak delimiter dari baris pertama
function guess_delim($line)
{
    $candidates = ["," => substr_count($line, ","), ";" => substr_count($line, ";"), "\t" => substr_count($line, "\t")];
    arsort($candidates);
    $best = array_key_first($candidates);
    return $best ?: ",";
}

// Fungsi pembaca CSV satu per satu
function csv_get_row($fh, $delimiter)
{
    return fgetcsv($fh, 0, $delimiter);
}

// Fungsi: baca XLSX sederhana -> generator baris (array nilai string)
function xlsx_rows($path)
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Gagal membuka file XLSX');
    }

    // sharedStrings dengan namespace
    $shared = [];
    $ssIdx = $zip->locateName('xl/sharedStrings.xml', ZipArchive::FL_NODIR);
    if ($ssIdx !== false) {
        $ssXml = $zip->getFromIndex($ssIdx);
        if ($ssXml !== false) {
            $sx = new SimpleXMLElement($ssXml);
            $sx->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sis = $sx->xpath('//s:si') ?: [];
            foreach ($sis as $si) {
                $texts = [];
                $ts = $si->xpath('.//s:t') ?: [];
                foreach ($ts as $t) { $texts[] = (string)$t; }
                $shared[] = implode('', $texts);
            }
        }
    }

    // Tentukan worksheet pertama
    $sheetName = null;
    if ($zip->locateName('xl/worksheets/sheet1.xml', ZipArchive::FL_NODIR) !== false) {
        $sheetName = 'xl/worksheets/sheet1.xml';
    } else {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#', $st)) { $sheetName = $st; break; }
        }
    }
    if (!$sheetName) { $zip->close(); throw new RuntimeException('Worksheet XLSX tidak ditemukan'); }

    $sheetXml = $zip->getFromName($sheetName);
    $zip->close();
    if ($sheetXml === false) throw new RuntimeException('Gagal membaca worksheet XLSX');

    $sx = new SimpleXMLElement($sheetXml);
    $sx->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    // helper: konversi kolom A,B,AA -> index 0-based
    $colToIndex = function ($ref) {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) { $n = $n * 26 + (ord($letters[$i]) - 64); }
        return max(0, $n - 1);
    };

    $rows = $sx->xpath('//s:sheetData/s:row') ?: [];
    foreach ($rows as $row) {
        $cells = [];
        $max   = -1;
        $cs = $row->xpath('s:c') ?: [];
        foreach ($cs as $c) {
            $ref = (string)($c['r'] ?? '');
            $idx = $ref !== '' ? $colToIndex($ref) : ($max + 1);
            $t   = (string)($c['t'] ?? '');
            $v   = '';
            if ($t === 's') { // shared string index
                $si = (int)$c->v;
                $v  = $shared[$si] ?? '';
            } elseif ($t === 'inlineStr') {
                $ts = $c->xpath('.//s:t') ?: [];
                $buf = [];
                foreach ($ts as $tNode) { $buf[] = (string)$tNode; }
                $v = implode('', $buf);
            } else { // number, string value, or general
                $v = isset($c->v) ? (string)$c->v : '';
            }
            $cells[$idx] = $v;
            if ($idx > $max) $max = $idx;
        }
        $out = [];
        for ($i = 0; $i <= $max; $i++) { $out[] = $cells[$i] ?? ''; }
        yield $out;
    }
}

// Normalisasi header -> index
function normalize_label($s)
{
    $s = strtolower((string)$s);
    // Ubah NBSP ke spasi biasa, ganti underscore/dash -> spasi
    $s = preg_replace('/\x{00A0}/u', ' ', $s);
    $s = str_replace(['_', '-'], ' ', $s);
    // Hanya sisakan huruf/angka/spasi
    $s = preg_replace('/[^a-z0-9 ]+/u', ' ', $s);
    // Kompres spasi
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}
function map_columns($header)
{
    $aliases = [
        'nama_sarana' => ['nama sarana', 'nama', 'nama usaha', 'nama toko', 'nama outlet'],
        'latitude'    => ['latitude', 'lat'],
        'longitude'   => ['longitude', 'lng', 'long', 'lon'],
        'kabupaten'   => ['kabupaten', 'kab'],
        'kecamatan'   => ['kecamatan', 'kec'],
        'kelurahan'   => ['kelurahan', 'desa', 'kel'],
        'jenis'       => ['jenis', 'jenis sarana', 'kategori', 'tipe']
    ];
    $idx = [];
    $hnRaw = $header;
    $hn = array_map('normalize_label', $header);

    // 1) Exact/alias match
    foreach ($aliases as $key => $alts) {
        foreach ($alts as $a) {
            $pos = array_search(normalize_label($a), $hn, true);
            if ($pos !== false) { $idx[$key] = $pos; break; }
        }
    }

    // 2) Heuristics contains-based if still missing
    $takeIf = function($condKey, $cb) use (&$idx, $hn) {
        if (!isset($idx[$condKey])) {
            foreach ($hn as $i => $n) { if ($cb($n)) { $idx[$condKey] = $i; break; } }
        }
    };
    $takeIf('nama_sarana', fn($n) => str_contains($n, 'nama'));
    $takeIf('latitude',    fn($n) => $n === 'latitude' || preg_match('/\blat\b|^lat/i', $n));
    $takeIf('longitude',   fn($n) => $n === 'longitude' || preg_match('/\blon\b|\blng\b|^long|^lng/i', $n));
    $takeIf('kabupaten',   fn($n) => str_contains($n, 'kabupaten') || preg_match('/\bkab\b/', $n));
    $takeIf('kecamatan',   fn($n) => str_contains($n, 'kecamatan') || preg_match('/\bkec\b/', $n));
    $takeIf('kelurahan',   fn($n) => str_contains($n, 'kelurahan') || str_contains($n, 'desa') || preg_match('/\bkel\b/', $n));
    $takeIf('jenis',       fn($n) => str_contains($n, 'jenis') || str_contains($n, 'kategori') || str_contains($n, 'tipe'));

    return $idx;
}

$header = null;
$map    = null;

$useXlsx = is_xlsx_upload($u);
if ($useXlsx && !class_exists('ZipArchive')) {
    respond(['error' => 'Import XLSX memerlukan ekstensi PHP Zip (ZipArchive).'], 500);
}
if ($useXlsx && !class_exists('SimpleXMLElement')) {
    respond(['error' => 'Import XLSX memerlukan ekstensi PHP SimpleXML.'], 500);
}

// Untuk kesederhanaan dan kompatibilitas, kita implementasikan pembacaan dengan dua jalur terpisah di bawah.

// Ambil/siapkan ID jenis "Lainnya"
function ensure_jenis_id($pdo, $name)
{
    static $cache = [];
    $key = mb_strtolower(trim($name));
    if (isset($cache[$key])) return $cache[$key];

    $sel = $pdo->prepare("SELECT id FROM jenis_sarana WHERE nama_jenis = ?");
    $sel->execute([$name]);
    $id = $sel->fetchColumn();
    if ($id) {
        $cache[$key] = (int)$id;
        return (int)$id;
    }

    $ins = $pdo->prepare("INSERT INTO jenis_sarana (nama_jenis) VALUES (?)");
    $ins->execute([$name]);
    $newId = (int)$pdo->lastInsertId();
    $cache[$key] = $newId;
    return $newId;
}
// Default jenis id (only if jenis tables exist; set below after detection)
$lainnyaId = null;


// Statement yang di-reuse
$insSarana = $pdo->prepare("INSERT INTO data_sarana (nama_sarana, latitude, longitude, kabupaten, kecamatan, kelurahan) VALUES (?, ?, ?, ?, ?, ?)");

// Compat: detect jenis tables availability
$jenisAvail = false; 
try { $check = $pdo->query("SHOW TABLES LIKE 'jenis_sarana'"); $jenisAvail = (bool)$check->fetchColumn(); } catch (Exception $e) { $jenisAvail = false; }
if ($jenisAvail) { try { $check = $pdo->query("SHOW TABLES LIKE 'sarana_jenis'"); $jenisAvail = $jenisAvail && (bool)$check->fetchColumn(); } catch (Exception $e) { $jenisAvail = false; } }

$insMap = null; $jenisCacheStmt = null; $jenisInsStmt = null;
if ($jenisAvail) {
    $insMap    = $pdo->prepare("INSERT IGNORE INTO sarana_jenis (sarana_id, jenis_id) VALUES (?, ?)");
    // Cache jenis
    $jenisCacheStmt = $pdo->prepare("SELECT id FROM jenis_sarana WHERE nama_jenis = ?");
    $jenisInsStmt   = $pdo->prepare("INSERT INTO jenis_sarana (nama_jenis) VALUES (?)");
    // Ensure default "Lainnya" exists
    $lainnyaId = ensure_jenis_id($pdo, 'Lainnya');
}

// Parser list jenis: dipisah koma atau pipe
function parse_jenis_list($raw)
{
    if ($raw === null) return [];
    $s = trim($raw);
    if ($s === '') return [];
    $parts = preg_split('/[|,]/', $s);
    $out = [];
    foreach ($parts as $p) {
        $n = trim($p);
        if ($n !== '') $out[] = $n;
    }
    return array_unique($out);
}

$inserted = 0;
$skipped  = 0;
$withErr  = 0;
$errors   = []; // kumpulkan beberapa error awal saja
$lineNo   = $hasHeader ? 2 : 1;

// Determine column nullability for coordinates once
$latAllowsNull = true; $lngAllowsNull = true;
try { $c = $pdo->query("SHOW COLUMNS FROM `data_sarana` LIKE 'latitude'")->fetch(PDO::FETCH_ASSOC); if ($c) { $latAllowsNull = strtoupper((string)($c['Null'] ?? 'YES')) === 'YES'; } } catch (Exception $e) {}
try { $c = $pdo->query("SHOW COLUMNS FROM `data_sarana` LIKE 'longitude'")->fetch(PDO::FETCH_ASSOC); if ($c) { $lngAllowsNull = strtoupper((string)($c['Null'] ?? 'YES')) === 'YES'; } } catch (Exception $e) {}

// Jalur 1: CSV
if (!$useXlsx) {
    $tmp = $u['tmp_name'];
    $fh  = fopen($tmp, 'r');
    if (!$fh) respond(['error' => 'Tidak bisa membaca file upload'], 500);

    // Baca baris pertama untuk deteksi delimiter
    $first = fgets($fh);
    if ($first === false) respond(['error' => 'File kosong'], 422);
    $delimiter = $delimOpt === 'auto' ? guess_delim($first) : ($delimOpt === '\\t' ? "\t" : $delimOpt);
    rewind($fh);

    // header mapping
    if ($hasHeader) {
        $header = csv_get_row($fh, $delimiter);
        if ($header === null || $header === false) respond(['error' => 'Header CSV tidak terbaca'], 422);
        $map = map_columns($header);
    } else {
        $map = [ 'nama_sarana'=>0,'latitude'=>1,'longitude'=>2,'kabupaten'=>3,'kecamatan'=>4,'kelurahan'=>5,'jenis'=>6 ];
    }

    if (!isset($map['nama_sarana'])) {
        // Coba fallback ke kolom pertama jika ada header tidak standar
        $map['nama_sarana'] = 0;
    }

    $pdo->beginTransaction();
    try {
        while (($row = csv_get_row($fh, $delimiter)) !== false) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { $lineNo++; continue; }

            $nama = trim($row[$map['nama_sarana']] ?? '');
            $lat  = trim($row[$map['latitude']] ?? '');
            $lng  = trim($row[$map['longitude']] ?? '');
            $kab  = trim($row[$map['kabupaten']] ?? '');
            $kec  = trim($row[$map['kecamatan']] ?? '');
            $kel  = trim($row[$map['kelurahan']] ?? '');
            $jenisRaw = isset($map['jenis']) ? ($row[$map['jenis']] ?? '') : '';

            if ($nama === '') { // hanya wajib nama
                $skipped++; $withErr++; if (count($errors) < 25) $errors[] = "Baris $lineNo: nama_sarana kosong"; $lineNo++; continue;
            }

            // Koordinat: fallback ke 0.0 jika kolom tidak mengizinkan NULL
            $latVal = ($lat === '' || !is_numeric($lat)) ? ($latAllowsNull ? null : 0.0) : (float)$lat;
            $lngVal = ($lng === '' || !is_numeric($lng)) ? ($lngAllowsNull ? null : 0.0) : (float)$lng;

            // Untuk field yang tidak boleh NULL, gunakan string kosong jika tidak ada nilai
            $kabVal = $kab !== '' ? $kab : '';
            $kecVal = $kec !== '' ? $kec : '';
            $kelVal = $kel !== '' ? $kel : '';

            // Insert sarana (text kosong tetap disimpan kosong)
            $insSarana->execute([$nama, $latVal, $lngVal, $kabVal, $kecVal, $kelVal]);
            $sid = (int)$pdo->lastInsertId();
            $inserted++;

            if ($jenisAvail) {
                $names = parse_jenis_list($jenisRaw);
                if (empty($names)) {
                    $insMap->execute([$sid, $lainnyaId]);
                } else {
                    foreach ($names as $jn) {
                        $jenisCacheStmt->execute([$jn]);
                        $jid = $jenisCacheStmt->fetchColumn();
                        if (!$jid) { $jenisInsStmt->execute([$jn]); $jid = (int)$pdo->lastInsertId(); } else { $jid = (int)$jid; }
                        $insMap->execute([$sid, $jid]);
                    }
                }
            }
            $lineNo++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($fh);
        respond(['error' => 'Import gagal', 'detail' => $e->getMessage()], 500);
    }
    fclose($fh);
}

// Jalur 2: XLSX
if ($useXlsx) {
    try {
        $rows = iterator_to_array(xlsx_rows($u['tmp_name']));
        if (!$rows) respond(['error' => 'File kosong'], 422);
        if ($hasHeader) {
            $header = $rows[0];
            $map = map_columns($header);
            $dataRows = array_slice($rows, 1);
        } else {
            $map = [ 'nama_sarana'=>0,'latitude'=>1,'longitude'=>2,'kabupaten'=>3,'kecamatan'=>4,'kelurahan'=>5,'jenis'=>6 ];
            $dataRows = $rows;
        }
        if (!isset($map['nama_sarana'])) { $map['nama_sarana'] = 0; }

        $pdo->beginTransaction();
        foreach ($dataRows as $row) {
            if (!is_array($row)) continue;
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { $lineNo++; continue; }

            $nama = trim($row[$map['nama_sarana']] ?? '');
            $lat  = trim($row[$map['latitude']] ?? '');
            $lng  = trim($row[$map['longitude']] ?? '');
            $kab  = trim($row[$map['kabupaten']] ?? '');
            $kec  = trim($row[$map['kecamatan']] ?? '');
            $kel  = trim($row[$map['kelurahan']] ?? '');
            $jenisRaw = isset($map['jenis']) ? ($row[$map['jenis']] ?? '') : '';

            if ($nama === '') { $skipped++; $withErr++; if (count($errors) < 25) $errors[] = "Baris $lineNo: nama_sarana kosong"; $lineNo++; continue; }

            // Koordinat: fallback ke 0.0 jika kolom tidak mengizinkan NULL
            $latVal = ($lat === '' || !is_numeric($lat)) ? ($latAllowsNull ? null : 0.0) : (float)$lat;
            $lngVal = ($lng === '' || !is_numeric($lng)) ? ($lngAllowsNull ? null : 0.0) : (float)$lng;

            // Untuk field yang tidak boleh NULL, gunakan string kosong jika tidak ada nilai
            $kabVal = $kab !== '' ? $kab : '';
            $kecVal = $kec !== '' ? $kec : '';
            $kelVal = $kel !== '' ? $kel : '';

            $insSarana->execute([$nama, $latVal, $lngVal, $kabVal, $kecVal, $kelVal]);
            $sid = (int)$pdo->lastInsertId();
            $inserted++;

            if ($jenisAvail) {
                $names = parse_jenis_list($jenisRaw);
                if (empty($names)) {
                    $insMap->execute([$sid, $lainnyaId]);
                } else {
                    foreach ($names as $jn) {
                        $jenisCacheStmt->execute([$jn]);
                        $jid = $jenisCacheStmt->fetchColumn();
                        if (!$jid) { $jenisInsStmt->execute([$jn]); $jid = (int)$pdo->lastInsertId(); } else { $jid = (int)$jid; }
                        $insMap->execute([$sid, $jid]);
                    }
                }
            }
            $lineNo++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(['error' => 'Import gagal (XLSX)', 'detail' => $e->getMessage()], 500);
    }
}

respond([
    'ok' => true,
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors_preview' => $errors
], 200);

