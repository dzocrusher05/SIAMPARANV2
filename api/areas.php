<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_common.php';

$type = $_GET['type'] ?? 'kabupaten';
$parent = $_GET['parent'] ?? null;

if ($type === 'kabupaten') {
    $stmt = $pdo->query("SELECT DISTINCT kabupaten AS name FROM data_sarana ORDER BY kabupaten ASC");
    respond($stmt->fetchAll());
}

if ($type === 'kecamatan' && $parent) {
    $stmt = $pdo->prepare("SELECT DISTINCT kecamatan AS name FROM data_sarana WHERE kabupaten=? ORDER BY kecamatan ASC");
    $stmt->execute([$parent]);
    respond($stmt->fetchAll());
}

if ($type === 'kelurahan' && $parent) {
    $parts = explode('|', $parent);
    if (count($parts) === 2) {
        [$kab, $kec] = $parts;
        $stmt = $pdo->prepare("SELECT DISTINCT kelurahan AS name FROM data_sarana WHERE kabupaten=? AND kecamatan=? ORDER BY kelurahan ASC");
        $stmt->execute([$kab, $kec]);
        respond($stmt->fetchAll());
    }
}

respond(['error' => 'parameter tidak valid'], 422);
