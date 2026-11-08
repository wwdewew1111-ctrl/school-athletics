<?php
/** Publish the prepared admission section and its four PDF forms. CLI only. */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
if ($argc > 2 || ($argc === 2 && $argv[1] !== '--apply')) {
    fwrite(STDERR, "Usage: php migrate_admission.php [--apply]\n"); exit(1);
}
$apply = ($argv[1] ?? '') === '--apply';
$dbFile = __DIR__.'/includes/database.sqlite';
$sourceDir = __DIR__.'/admission-forms';
$targetDir = __DIR__.'/uploads/admission';
$files = [
    'application.pdf' => 'a2ab5a1ea8b46a43a5f39aa339d91fb48ef07328f02c8dc09252169d77d67f82',
    'medical-certificate.pdf' => 'adcac8efb8d57c72a6ef72fc3cf4d3cac078d7681553ee2923b34d5630c62b58',
    'personal-data-consent.pdf' => '91734363037f50fb87964e6fb9e82e1dacdd983151fac36eca137f91daac1cce',
    'photo-consent.pdf' => '2a6586b8ea39b055222079ac8b97bdf6123e0177e7f98130b10e64ca5cc64c38',
];
$content = <<<'HTML'
<h3>В МБУ ДО «СШОР №4 по легкой атлетике» проводится набор детей</h3>
<p>Для зачисления необходимо предоставить следующие документы:</p>
<ul>
<li>заявление о приеме <a href="file.php?path=uploads%2Fadmission%2Fapplication.pdf" target="_blank" rel="noopener noreferrer">по форме</a>;</li>
<li>копия свидетельства о рождении с пропиской, СНИЛС;</li>
<li>медицинский документ, подтверждающий отсутствие у поступающего противопоказаний для освоения программы по легкой атлетике <a href="file.php?path=uploads%2Fadmission%2Fmedical-certificate.pdf" target="_blank" rel="noopener noreferrer">по форме</a>;</li>
<li>согласие на фото и видеосъемку (законного представителя) <a href="file.php?path=uploads%2Fadmission%2Fphoto-consent.pdf" target="_blank" rel="noopener noreferrer">по форме</a>;</li>
<li>согласие обучающихся до 14 лет (Владимирской области) <a href="file.php?path=uploads%2Fadmission%2Fpersonal-data-consent.pdf" target="_blank" rel="noopener noreferrer">по форме</a>.</li>
</ul>
HTML;
$content .= "\n";
try {
    if (!is_file($dbFile)) throw new RuntimeException('Database not found; nothing was created.');
    foreach ($files as $name => $hash) {
        $source = $sourceDir.'/'.$name;
        if (!is_file($source) || hash_file('sha256', $source) !== $hash) throw new RuntimeException('Missing or invalid source PDF: '.$name);
        $target = $targetDir.'/'.$name;
        if (is_link($target)) throw new RuntimeException('Refusing symlink destination: '.$target);
        if (file_exists($target) && (!is_file($target) || hash_file('sha256', $target) !== $hash)) throw new RuntimeException('Existing destination differs; refusing to overwrite: '.$target);
    }
    $pdo = new PDO('sqlite:'.$dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    if ($apply) $pdo->beginTransaction();
    $row = $pdo->query("SELECT id, slug, title, content, document FROM legal_sections WHERE slug='admission'")->fetch();
    if (!$row) throw new RuntimeException('Admission section not found; no data changed.');
    if ((string) $row['content'] !== $content) {
        echo "--- Current admission content ---\n".$row['content']."\n--- Proposed admission content ---\n".$content."\n";
    } else {
        echo "Admission content already matches.\n";
    }
    foreach ($files as $name => $hash) echo (is_file($targetDir.'/'.$name) ? '[PRESENT] ' : '[COPY] ').$name.PHP_EOL;
    if (!$apply) { echo "[DRY RUN] No changes made.\n"; exit(0); }
    if ((string) $row['content'] !== $content) {
        $backupDir = dirname(__DIR__).'/school-athletics-admission-backups';
        if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) throw new RuntimeException('Unable to create backup directory.');
        $backupFile = $backupDir.'/admission-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($backupFile, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Unable to save section backup.');
        chmod($backupFile, 0600);
        echo '[BACKUP] '.$backupFile.PHP_EOL;
    }
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) throw new RuntimeException('Unable to create admission uploads directory.');
    foreach ($files as $name => $hash) {
        $target = $targetDir.'/'.$name;
        if (is_file($target)) continue;
        $out = @fopen($target, 'x');
        if ($out === false) throw new RuntimeException('Unable to create destination (or it already exists): '.$target);
        $in = fopen($sourceDir.'/'.$name, 'rb');
        if ($in === false) { fclose($out); throw new RuntimeException('Unable to open source: '.$name); }
        $copied = stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);
        if ($copied === false || hash_file('sha256', $target) !== $hash) { unlink($target); throw new RuntimeException('Copy verification failed: '.$name); }
        chmod($target, 0644);
    }
    if ((string) $row['content'] !== $content) {
        $update = $pdo->prepare('UPDATE legal_sections SET content=? WHERE id=? AND content=?');
        $update->execute([$content, $row['id'], $row['content']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Section changed concurrently; update cancelled.');
    }
    $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity check: '.$integrity);
    $pdo->commit();
    echo "[DONE] Admission content and forms verified. Other section fields were preserved.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, '[ERROR] '.$e->getMessage().PHP_EOL);
    exit(1);
}
