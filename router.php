<?php
/** Local development router: PHP's built-in server does not read .htaccess. */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path)) { http_response_code(400); return true; }
$decoded = rawurldecode($path);
if (preg_match('~(?:^|/)\.(?:[^/]*)(?:/|$)|^/(?:includes|tests)(?:/|$)|^/(?:router|(?:run_)?migrate_.*)\.php(?:/|$)|\.(?:sqlite(?:[-.][^/]*)?|db(?:[-.][^/]*)?|log|sh|md|zip|bak|tar|gz|pem|key|ini|conf|dist|sql|swp)(?:$|\.)~i', $decoded)
    || preg_match('~^/uploads/.*\.(?:php[0-9]*|phtml|pht|phar|phps|phpt|shtml|cgi|pl|py|rb)(?:/|$)~i', $decoded)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}
if (preg_match('~^/sveden(?:/([A-Za-z0-9_]+))?/?$~D', $path)) {
    require __DIR__.'/legal.php';
    return true;
}
return false;
