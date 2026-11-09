<?php
/** Replace the known Telegram link in the school's social field with MAX. CLI only. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
if ($argc > 2 || ($argc === 2 && $argv[1] !== '--apply')) {
    fwrite(STDERR, "Usage: php migrate_social_max.php [--apply]\n");
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

    $vk = '<a href="https://vk.ru/club50148004" target="_blank" rel="noopener noreferrer">ВКонтакте</a>';
    $telegram = '<a href="https://t.me/shor_4_vladimir33" target="_blank" rel="noopener noreferrer">Telegram</a>';
    $max = '<a href="https://max.ru/channel_shor_4" target="_blank" rel="noopener noreferrer">MAX</a>';
    $prefix = '<dt>Официальная страница образовательной организации в социальных сетях</dt><dd>'.$vk;
    $before = (string) $row['content'];
    $withTelegram = $prefix.', '.$telegram.'</dd>';
    $withVkOnly = $prefix.'</dd>';
    $withMax = $prefix.', '.$max.'</dd>';

    if (substr_count($before, $withMax) === 1 && substr_count($before, $prefix) === 1) {
        echo "[PRESERVED] MAX is already present.\n";
    } elseif (substr_count($before, $withTelegram) === 1 && substr_count($before, $prefix) === 1) {
        $after = str_replace($withTelegram, $withMax, $before);
    } elseif (substr_count($before, $withVkOnly) === 1 && substr_count($before, $prefix) === 1) {
        $after = str_replace($withVkOnly, $withMax, $before);
    } else {
        throw new RuntimeException('Social field differs from the expected content; update it manually in the admin panel.');
    }

    if (isset($after)) {
        echo ($apply ? '[UPDATE] ' : '[PREVIEW] ')."Replace the known social field with VK and MAX.\n";
        if ($apply) {
            $update = $pdo->prepare('UPDATE legal_sections SET content = ? WHERE id = ? AND content = ?');
            $update->execute([$after, $row['id'], $before]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Section changed concurrently; update cancelled.');
        }
    }
    if ($apply) {
        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
        $pdo->commit();
    }
    echo $apply ? "[DONE] Database verified.\n" : "[DRY RUN] No changes made.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
