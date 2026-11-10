<?php
/** Update only the two recognized phone fields in the main legal section. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
if ($argc > 2 || ($argc === 2 && $argv[1] !== '--apply')) {
    fwrite(STDERR, "Usage: php migrate_contact_phone.php [--apply]\n");
    exit(1);
}
$apply = ($argv[1] ?? '') === '--apply';
$dbFile = __DIR__.'/includes/database.sqlite';
if (!is_file($dbFile)) { fwrite(STDERR, "[ERROR] Database not found; nothing was created.\n"); exit(1); }

try {
    $pdo = new PDO('sqlite:'.$dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    if ($apply) $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT id, content FROM legal_sections WHERE slug = ?');
    $statement->execute(['main']);
    $row = $statement->fetch();
    if (!$row) throw new RuntimeException('Main section not found; no data changed.');

    $before = (string) $row['content'];
    $after = $before;
    $target = '<a href="tel:+74922533333">+7(4922)53-33-33</a>';
    $fields = [
        'organization' => [
            'pattern' => '~(<dt>Контактные телефоны образовательной организации\*</dt><dd>)(.*?)(</dd>)~su',
            'old' => [
                '<a href="tel:+74222545389">(4222) 54-53-89</a>',
                '<a href="tel:+74222545389">(4222)54-53-89</a>',
                '<a href="tel:+74922545389">(4922)54-53-89</a>',
                '<a href="tel:+74922545389">(4922) 54-53-89</a>',
            ],
        ],
        'founder' => [
            'pattern' => '~(<dt>Учредители образовательной организации</dt><dd>(?:(?!</dd>).)*?Контактный телефон учредителя:\s*)(<a\b[^>]*>(?:(?!</a>|</dd>).)*</a>)((?:(?!</dd>).)*</dd>)~su',
            'old' => [
                '<a href="tel:+74292533333">8 (4292) 53-33-33</a>',
                '<a href="tel:+74922533333">8 (4922) 53-33-33</a>',
            ],
        ],
    ];
    foreach ($fields as $name => $field) {
        $count = preg_match_all($field['pattern'], $after, $matches, PREG_OFFSET_CAPTURE);
        if ($count !== 1) throw new RuntimeException("Field $name is missing or ambiguous; update it manually in the admin panel. No data changed.");
        $value = $matches[2][0][0];
        if ($value === $target) {
            echo "[PRESERVED] $name: $target\n";
            continue;
        }
        if (!in_array($value, $field['old'], true)) {
            throw new RuntimeException("Field $name differs from the expected content; update it manually in the admin panel. No data changed.");
        }
        echo "[PREVIEW] $name: $value -> $target\n";
        $after = substr_replace($after, $target, $matches[2][0][1], strlen($value));
    }

    if ($apply && $after !== $before) {
        $update = $pdo->prepare('UPDATE legal_sections SET content = ? WHERE id = ? AND content = ?');
        $update->execute([$after, $row['id'], $before]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Section changed concurrently; update cancelled.');
    }
    if ($apply) {
        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
        $pdo->commit();
        echo "[DONE] Database verified.\n";
    } else {
        echo "[DRY RUN] No changes made.\n";
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
