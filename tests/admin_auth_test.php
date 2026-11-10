<?php
declare(strict_types=1);
/* Offline regression test: uses only a temporary directory and a test password. */
$root = sys_get_temp_dir().'/school-auth-test-'.bin2hex(random_bytes(8));
$site = $root.'/site/includes';
if (!mkdir($site, 0700, true)) throw new RuntimeException('Cannot create test directory');
copy(__DIR__.'/../includes/admin_auth.php', $site.'/admin_auth.php');
require $site.'/admin_auth.php';
$password = 'Test-password-for-local-use-2026';
file_put_contents($root.'/school-athletics-admin-password.hash', password_hash($password, PASSWORD_DEFAULT));
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
try {
    if (admin_verify_login('admin', 'wrong')) throw new RuntimeException('Wrong password accepted');
    if (!admin_verify_login('admin', $password)) throw new RuntimeException('Correct password rejected');
    for ($i = 0; $i < 5; $i++) {
        if (admin_verify_login('admin', 'wrong')) throw new RuntimeException('Wrong password accepted');
    }
    try {
        admin_verify_login('admin', $password);
        throw new LogicException('Throttle failed');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), '15')) throw $e;
    }
    unlink($root.'/school-athletics-admin-password.hash');
    try {
        admin_verify_login('admin', $password);
        throw new LogicException('Missing hash did not fail closed');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'недоступен')) throw $e;
    }
    echo "Authentication, lockout and missing-hash tests passed.\n";
} finally {
    @unlink($root.'/school-athletics-admin-password.hash');
    @unlink($root.'/school-athletics-admin-attempts.json');
    @unlink($site.'/admin_auth.php');
    @rmdir($site);
    @rmdir($root.'/site');
    @rmdir($root);
}
