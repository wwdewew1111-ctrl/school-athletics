<?php
declare(strict_types=1);

/** Create a private, consistent SQLite snapshot and archive application data. */
function backup_create(PDO $pdo, string $root): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('Для резервного копирования необходимо включить PHP-расширение ZIP.');
    // This sets a new temporary-file permission mask; avoid changing an unknown mask.
    if (!function_exists('umask')) throw new RuntimeException('Резервное копирование недоступно: PHP-функция umask отключена.');
    $root = realpath($root);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($root === false || $tempRoot === false || !is_dir($tempRoot) || !is_writable($tempRoot)) {
        throw new RuntimeException('Недоступен защищённый временный каталог PHP.');
    }
    $normalize = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');
    $sitePath = $normalize($root);
    $tempPath = $normalize($tempRoot);
    if (DIRECTORY_SEPARATOR === '\\') { $sitePath = strtolower($sitePath); $tempPath = strtolower($tempPath); }
    if ($tempPath === $sitePath || str_starts_with($tempPath, $sitePath.'/')) {
        throw new RuntimeException('Временный каталог PHP должен находиться вне корня сайта.');
    }
    $dir = $tempRoot.DIRECTORY_SEPARATOR.'school-backup-'.bin2hex(random_bytes(16));
    // Keep the whole snapshot and archive operation owner-only, not just mkdir.
    $oldUmask = umask(0077);
    if (!@mkdir($dir, 0700)) {
        umask($oldUmask);
        throw new RuntimeException('Не удалось создать временный каталог резервной копии.');
    }
    register_shutdown_function('backup_cleanup', $dir);
    $dbCopy = $dir.DIRECTORY_SEPARATOR.'database.sqlite';
    $archive = $dir.DIRECTORY_SEPARATOR.'backup.zip';
    try {
        // VACUUM INTO produces a transactional snapshot, including committed WAL data.
        $sqliteVersion = (string) $pdo->query('select sqlite_version()')->fetchColumn();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Резервное копирование поддерживает только SQLite.');
        }
        if (version_compare($sqliteVersion, '3.27.0', '>=')) {
            // VACUUM INTO produces a transactional snapshot, including committed WAL data.
            $quotedPath = $pdo->quote($dbCopy);
            if ($quotedPath === false) throw new RuntimeException('Не удалось подготовить путь к копии базы.');
            $pdo->exec('VACUUM main INTO '.$quotedPath);
        } elseif (class_exists('SQLite3') && method_exists('SQLite3', 'backup')) {
            // SQLite's Online Backup API works with older SQLite libraries and creates a consistent snapshot.
            $source = new SQLite3($root.'/includes/database.sqlite', SQLITE3_OPEN_READONLY);
            $source->enableExceptions(true);
            $destination = new SQLite3($dbCopy, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
            $destination->enableExceptions(true);
            try {
                if (!$source->backup($destination)) throw new RuntimeException('SQLite не смог создать снимок базы.');
            } finally {
                $destination->close();
                $source->close();
            }
        } else {
            throw new RuntimeException('Для резервного копирования нужен SQLite 3.27.0+ или включённое расширение SQLite3 с Online Backup API.');
        }
        if (!is_file($dbCopy) || !@chmod($dbCopy, 0600)) throw new RuntimeException('Не удалось защитить копию базы.');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' || version_compare((string) $pdo->query('select sqlite_version()')->fetchColumn(), '3.27.0', '<')) {
            throw new RuntimeException('Для резервного копирования требуется SQLite версии 3.27.0 или новее.');
        }
        $quotedPath = $pdo->quote($dbCopy);
        if ($quotedPath === false) throw new RuntimeException('Не удалось подготовить путь к копии базы.');
        $pdo->exec('VACUUM main INTO '.$quotedPath);
        if (!is_file($dbCopy) || !@chmod($dbCopy, 0600)) throw new RuntimeException('Не удалось защитить копию базы.');
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException('Не удалось создать ZIP-архив.');
        $zipClosed = false;
        try {
            if (!$zip->addFile($dbCopy, 'includes/database.sqlite')) throw new RuntimeException('Не удалось добавить базу в архив.');
            backup_add_tree($zip, $root.'/uploads', 'uploads');
            $parent = dirname($root);
            backup_add_tree($zip, $parent.'/school-athletics-news-uploads', 'school-athletics-news-uploads');
            $settings = $parent.'/school-athletics-social-settings.json';
            if (is_link($settings)) throw new RuntimeException('Файл настроек соцсетей не должен быть ссылкой.');
            if (file_exists($settings) && (!is_file($settings) || !$zip->addFile($settings, 'school-athletics-social-settings.json'))) {
                throw new RuntimeException('Не удалось добавить настройки соцсетей.');
            }
            $zipClosed = true;
            if (!$zip->close()) throw new RuntimeException('Не удалось завершить ZIP-архив.');
        } catch (Throwable $e) {
            if (!$zipClosed) @$zip->close();
            throw $e;
        }
        if (!is_file($archive) || !@chmod($archive, 0600)) throw new RuntimeException('Не удалось защитить ZIP-архив.');
        return ['directory' => $dir, 'archive' => $archive];
    } catch (Throwable $e) {
        backup_cleanup($dir);
        throw $e;
    } finally {
        umask($oldUmask);
    }
}

function backup_add_tree(ZipArchive $zip, string $source, string $prefix): void
{
    if (is_link($source)) throw new RuntimeException('Каталог загрузок не должен быть ссылкой.');
    if (!file_exists($source)) return;
    if (!is_dir($source) || !is_readable($source)) throw new RuntimeException('Не удалось прочитать каталог загрузок.');
    if (!$zip->addEmptyDir($prefix)) throw new RuntimeException('Не удалось добавить каталог в архив.');
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        if ($item->isLink()) throw new RuntimeException('Символические ссылки в загрузках не поддерживаются.');
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
        $name = $prefix.'/'.$relative;
        if ($item->isDir()) {
            if (!$zip->addEmptyDir($name)) throw new RuntimeException('Не удалось добавить каталог в архив.');
        } elseif (!$item->isFile() || !$item->isReadable() || !$zip->addFile($item->getPathname(), $name)) {
            throw new RuntimeException('Не удалось добавить загруженный файл в архив.');
        }
    }
}

function backup_cleanup(string $dir): void
{
    foreach (['backup.zip', 'database.sqlite', 'database.sqlite-journal', 'database.sqlite-wal', 'database.sqlite-shm'] as $name) {
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        if (is_file($path) && !@unlink($path)) error_log('Could not remove temporary backup file: '.$path);
    }
    if (is_dir($dir) && !@rmdir($dir)) error_log('Could not remove temporary backup directory: '.$dir);
}
