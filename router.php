<?php
/** Local development router: PHP's built-in server does not read .htaccess. */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && preg_match('~^/sveden(?:/([A-Za-z0-9_]+))?/?$~D', $path, $match)) {
    require __DIR__.'/legal.php';
    return true;
}
return false;
