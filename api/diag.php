<?php
// api/diag.php — simple diagnostics for import prerequisites
header('Content-Type: application/json; charset=utf-8');

function ini_bytes($val) {
  $val = trim((string)$val);
  $last = strtolower($val[strlen($val)-1] ?? '');
  $num = (int)$val;
  switch ($last) {
    case 'g': return $num * 1024 * 1024 * 1024;
    case 'm': return $num * 1024 * 1024;
    case 'k': return $num * 1024;
    default:  return (int)$val;
  }
}

$info = [
  'zip' => class_exists('ZipArchive'),
  'simplexml' => class_exists('SimpleXMLElement'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size' => ini_get('post_max_size'),
  'max_execution_time' => ini_get('max_execution_time'),
  'memory_limit' => ini_get('memory_limit'),
  'limits_bytes' => [
    'upload_max_filesize' => ini_bytes(ini_get('upload_max_filesize')),
    'post_max_size' => ini_bytes(ini_get('post_max_size')),
  ],
];

echo json_encode($info);
?>

