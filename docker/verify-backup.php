<?php

declare(strict_types=1);

$database = '/restore-db/database.sqlite';
$storage = '/restore-storage';
if (! is_file($database) || ! is_file($storage.'/app/private/docker-app-key')) {
    throw new RuntimeException('Database atau APP_KEY volume tidak ditemukan.');
}
$db = new PDO('sqlite:'.$database);
if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $db->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
    throw new RuntimeException('Integritas database hasil pemulihan gagal.');
}
$photos = $db->query('SELECT path FROM announcement_photos')->fetchAll(PDO::FETCH_COLUMN);
foreach ($photos as $path) {
    $resolved = realpath($storage.'/app/private/'.$path);
    if ($resolved === false || ! str_starts_with($resolved, $storage.'/app/private/')) {
        throw new RuntimeException('Foto hasil pemulihan tidak lengkap.');
    }
}
$inventory = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isLink()) {
        throw new RuntimeException('Symlink tidak diizinkan pada volume backup.');
    }
    if ($file->isFile()) {
        $inventory[substr($file->getPathname(), strlen($storage) + 1)] = hash_file('sha256', $file->getPathname());
    }
}
ksort($inventory);
echo json_encode([
    'status' => 'passed',
    'sqlite_integrity' => 'ok',
    'announcements' => (int) $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn(),
    'photos' => count($photos),
    'database_sha256' => hash_file('sha256', $database),
    'storage_sha256' => hash('sha256', json_encode($inventory, JSON_THROW_ON_ERROR)),
    'storage_files' => count($inventory),
], JSON_THROW_ON_ERROR).PHP_EOL;
