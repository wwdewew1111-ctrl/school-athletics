<?php
declare(strict_types=1);

// Files are stored under random names; this table retains their original names.
function ensure_upload_names(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS upload_names (path TEXT PRIMARY KEY, original_name TEXT NOT NULL)');
}

function original_upload_name(string $name): string
{
    $parts = preg_split('~[\\\\/]~', $name);
    $name = (string) end($parts);
    if ($name === '' || strlen($name) > 255 || !preg_match('//u', $name)
        || preg_match('/[\x00-\x1F\x7F]/', $name)) {
        throw new RuntimeException('Недопустимое имя файла.');
    }
    return $name;
}

function save_upload_name(PDO $pdo, string $path, string $name): void
{
    ensure_upload_names($pdo);
    $pdo->prepare('INSERT INTO upload_names(path, original_name) VALUES(?, ?)')->execute([$path, original_upload_name($name)]);
}

function file_view_url(string $path): string
{
    return 'file.php?path='.rawurlencode($path);
}
