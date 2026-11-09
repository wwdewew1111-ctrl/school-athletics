<?php
/** Update known representations of the school's contact number in legal-section text. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
if ($argc > 2 || ($argc === 2 && $argv[1] !== '--apply')) {
    fwrite(STDERR, "Usage: php migrate_contact_phone.php [--apply]\n");
    exit(1);
}
$apply = ($argv[1] ?? '') === '--apply';
$dbFile = __DIR__.'/includes/database.sqlite';
if (!is_file($dbFile)) { fwrite(STDERR, "Database not found; nothing was created.\n"); exit(1); }
$replacements = [
    'tel:+74222545389' => 'tel:+74922545389',
    '(4222) 54-53-89' => '(4922)54-53-89',
    '(4222)54-53-89' => '(4922)54-53-89',
];
try {
    $pdo = new PDO('sqlite:'.$dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    if ($apply) $pdo->beginTransaction();
    $rows = $pdo->query('SELECT id, slug, content FROM legal_sections ORDER BY id')->fetchAll();
    $update = $pdo->prepare('UPDATE legal_sections SET content = ? WHERE id = ? AND content = ?');
    $changed = 0;
    foreach ($rows as $row) {
        $old = (string) $row['content'];
        $new = strtr($old, $replacements);
        if ($old === $new) continue;
        echo ($apply ? '[UPDATE] ' : '[PREVIEW] ').$row['slug'].PHP_EOL;
        if ($apply) {
            $update->execute([$new, $row['id'], $old]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Section changed concurrently: '.$row['slug']);
        }
        $changed++;
    }
    if ($apply) {
        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
        $pdo->commit();
    }
    echo ($apply ? '[DONE] ' : '[DRY RUN] ').$changed.' section(s).'.PHP_EOL;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
