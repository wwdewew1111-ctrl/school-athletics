<?php
/** One-time migration: create missing empty sections without modifying existing records. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__.'/includes/db.php';
$navigation = require __DIR__.'/includes/legal_navigation.php';
$pdo = db();
$pdo->beginTransaction();
try {
    $statement = $pdo->prepare('INSERT OR IGNORE INTO legal_sections(slug,title,content,document) VALUES(?,?,?,?)');
    foreach ($navigation as $slug => $title) {
        $statement->execute([$slug, $title, '', '']);
        echo ($statement->rowCount() ? '[CREATED] ' : '[PRESERVED] ').$slug.PHP_EOL;
    }
    $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
    $pdo->commit();
    echo '[OK] SQLite integrity: '.$integrity.PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
