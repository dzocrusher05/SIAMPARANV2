<?php
// api/template_xlsx.php — generate XLSX template for sarana import
// Requires PHP ZipArchive. If missing, falls back to CSV.

function output_csv_fallback() {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=template_sarana.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['nama_sarana','latitude','longitude','kabupaten','kecamatan','kelurahan','jenis']);
  // example row (optional)
  fputcsv($out, ['Contoh Apotek', '-6.2', '106.8', 'Kota Contoh', 'Kecamatan A', 'Kelurahan B', 'Sarana Pelayanan Kefarmasian Apotek|Sarana Distribusi Pangan']);
  fclose($out);
  exit;
}

if (!class_exists('ZipArchive')) {
  output_csv_fallback();
}

$contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;

$relsRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

$workbook = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template" sheetId="1" r:id="rId1"/>
  </sheets>
  <definedNames/>
</workbook>
XML;

$workbookRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;

// Inline strings for header row and one sample row
function cell_inline($colRef, $rowNum, $text) {
  $r = $colRef . $rowNum;
  $esc = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
  return "<c r=\"$r\" t=\"inlineStr\"><is><t>$esc</t></is></c>";
}

$headers = [
  'A' => 'nama_sarana',
  'B' => 'latitude',
  'C' => 'longitude',
  'D' => 'kabupaten',
  'E' => 'kecamatan',
  'F' => 'kelurahan',
  'G' => 'jenis',
];

$sample = [
  'A' => 'Contoh Apotek',
  'B' => '-6.2000',
  'C' => '106.8000',
  'D' => 'Kota Contoh',
  'E' => 'Kecamatan A',
  'F' => 'Kelurahan B',
  'G' => 'Sarana Pelayanan Kefarmasian Apotek|Sarana Distribusi Pangan',
];

// Contoh tambahan dengan data kosong
$sample2 = [
  'A' => 'Hanya Nama Sarana',
  'B' => '',
  'C' => '',
  'D' => '',
  'E' => '',
  'F' => '',
  'G' => '',
];

$sample3 = [
  'A' => 'Koordinat Kosong',
  'B' => '',
  'C' => '',
  'D' => '-',
  'E' => '-',
  'F' => '-',
  'G' => '',
];

$row1 = '';
foreach ($headers as $col => $txt) { $row1 .= cell_inline($col, 1, $txt); }
$row2 = '';
foreach ($sample as $col => $txt) { $row2 .= cell_inline($col, 2, $txt); }
$row3 = '';
foreach ($sample2 as $col => $txt) { $row3 .= cell_inline($col, 3, $txt); }
$row4 = '';
foreach ($sample3 as $col => $txt) { $row4 .= cell_inline($col, 4, $txt); }

$sheet1 = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">$row1</row>
    <row r="2">$row2</row>
    <row r="3">$row3</row>
    <row r="4">$row4</row>
  </sheetData>
</worksheet>
XML;

$tmp = tempnam(sys_get_temp_dir(), 'xlsx_tpl_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
  output_csv_fallback();
}

$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $relsRels);
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename=template_sarana.xlsx');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
?>

