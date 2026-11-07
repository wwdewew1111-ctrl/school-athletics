<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
if ($argc !== 2 || !in_array($argv[1], ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php migrate_useful_sections.php --dry-run|--apply\n");
    exit(2);
}

// Do not initialize or seed an absent production database.
$database = __DIR__.'/includes/database.sqlite';
if (!is_file($database)) {
    fwrite(STDERR, "[ERROR] Existing SQLite database not found; nothing changed.\n");
    exit(1);
}

$files = [
    'medical-certificate.pdf' => 'adcac8efb8d57c72a6ef72fc3cf4d3cac078d7681553ee2923b34d5630c62b58',
    'admission-application.pdf' => 'a2ab5a1ea8b46a43a5f39aa339d91fb48ef07328f02c8dc09252169d77d67f82',
    'photo-consent.pdf' => '2a6586b8ea39b055222079ac8b97bdf6123e0177e7f98130b10e64ca5cc64c38',
    'personal-data-consent-under-14.pdf' => '91734363037f50fb87964e6fb9e82e1dacdd983151fac36eca137f91daac1cce',
];
foreach ($files as $name => $hash) {
    $path = __DIR__.'/assets/admission/'.$name;
    if (!is_file($path) || !hash_equals($hash, (string) hash_file('sha256', $path))) {
        fwrite(STDERR, "[ERROR] Missing or changed source document: $path\n");
        exit(1);
    }
}

$sections = require __DIR__.'/includes/useful_section_content.php';
$apply = $argv[1] === '--apply';
try {
    $pdo = new PDO('sqlite:'.$database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->beginTransaction();
    $select = $pdo->prepare('SELECT id, content, document FROM legal_sections WHERE slug = ?');
    $insert = $pdo->prepare('INSERT INTO legal_sections(slug,title,content,document) VALUES(?,?,?,?)');
    $update = $pdo->prepare('UPDATE legal_sections SET content = ? WHERE id = ? AND content = ? AND document = ?');
    foreach ($sections as $slug => $section) {
        $select->execute([$slug]);
        $row = $select->fetch();
        if (!$row) {
            $insert->execute([$slug, $section['title'], $section['content'], '']);
            echo "[CREATED] $slug\n";
            continue;
        }
        $old = (string) $row['content'];
        $document = (string) $row['document'];
        if ($old === $section['content']) {
            echo "[UNCHANGED] $slug\n";
            continue;
        }
        if ($document !== '' || !in_array(trim($old), $section['replace'], true)) {
            echo "[PRESERVED] $slug: existing content or attachment requires manual review\n";
            continue;
        }
        $update->execute([$section['content'], $row['id'], $old, $document]);
        if ($update->rowCount() !== 1) throw new RuntimeException("Concurrent change: $slug");
        echo "[UPDATED] $slug\n";
    }

    // Correct only the specific verified old numbers, not arbitrary administrator data.
    $select->execute(['main']);
    $main = $select->fetch();
    if ($main) {
        $old = (string) $main['content'];
        $new = str_replace(
            ['tel:+74222545389', '(4222)54-53-89', '(4222) 54-53-89', 'tel:+74292533333', '8 (4292) 53-33-33', '8 (4292)53-33-33'],
            ['tel:+74922545389', '(4922)54-53-89', '(4922) 54-53-89', 'tel:+74922533333', '8 (4922) 53-33-33', '8 (4922)53-33-33'],
            $old
        );
        if ($new !== $old) {
            $update->execute([$new, $main['id'], $old, (string) $main['document']]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Concurrent change: main');
            echo "[UPDATED] main: verified telephone corrections\n";
        } else {
            echo "[UNCHANGED] main: no known telephone errors found\n";
        }
    } else {
        echo "[PRESERVED] main: section absent\n";
    }
    $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
    if ($apply) {
        $pdo->commit();
        echo "[OK] Changes committed; SQLite integrity: $integrity\n";
    } else {
        $pdo->rollBack();
        echo "[OK] Dry run rolled back; SQLite integrity: $integrity\n";
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
