<?php

declare(strict_types=1);

/** Administration sessions are scoped to /admin and never trust proxy headers. */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $https = $https || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('school_admin');
    $rawPath = (string) (parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH) ?: '/admin/index.php');
    $adminPath = rtrim(str_replace('\\', '/', dirname($rawPath)), '/').'/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $adminPath,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/** Password hashes are stored outside the document root, or supplied by the host. */
function admin_password_hash(): string
{
    $hash = trim((string) getenv('SCHOOL_ADMIN_PASSWORD_HASH'));
    if ($hash !== '') return $hash;
    $file = dirname(__DIR__, 2).'/school-athletics-admin-password.hash';
    $hash = @file_get_contents($file);
    return is_string($hash) ? trim($hash) : '';
}



/**
 * Check one login under an exclusive shared-hosting file lock. This combines a
 * per-address limit with a global limit, so rotating sessions/IPs cannot bypass
 * the throttle. If storage fails, authentication fails closed.
 */
function admin_verify_login(string $username, string $password): bool
{
    $hash = admin_password_hash();
    if ($hash === '' || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') {
        throw new RuntimeException('Вход временно недоступен. Обратитесь к администратору сервера.');
    }
    $file = dirname(__DIR__, 2).'/school-athletics-admin-attempts.json';
    $oldMask = umask(0077);
    try { $handle = @fopen($file, 'c+'); } finally { umask($oldMask); }
    if ($handle === false) throw new RuntimeException('Вход временно недоступен. Проверьте защищённое хранилище на сервере.');
    @chmod($file, 0600);
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Вход временно недоступен.');
        $raw = stream_get_contents($handle, 131073);
        if ($raw === false || strlen($raw) > 131072) throw new RuntimeException('Вход временно недоступен.');
        $attempts = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($attempts) || count($attempts) > 1000) throw new RuntimeException('Вход временно недоступен.');
        $now = time();
        $attempts = array_values(array_filter($attempts, static function ($entry) use ($now): bool {
            return is_array($entry) && isset($entry['time'], $entry['address'])
                && is_int($entry['time']) && is_string($entry['address']) && $entry['time'] > $now - 900 && $entry['time'] <= $now;
        }));
        // REMOTE_ADDR is supplied by the server; forwarded headers can be forged.
        $address = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $sameAddress = count(array_filter($attempts, static fn ($entry): bool => hash_equals($address, $entry['address'])));
        if ($sameAddress >= 5 || count($attempts) >= 50) {
            throw new RuntimeException('Слишком много попыток входа. Повторите через 15 минут.');
        }
        $valid = password_verify($password, $hash) && hash_equals('admin', $username);
        if ($valid) {
            $attempts = array_values(array_filter($attempts, static fn ($entry): bool => !hash_equals($address, $entry['address'])));
        } else {
            $attempts[] = ['time' => $now, 'address' => $address];
        }
        $json = json_encode($attempts, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Вход временно недоступен.');
        }
        return $valid;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
