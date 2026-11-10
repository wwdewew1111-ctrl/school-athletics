<?php
declare(strict_types=1);
/* HTTP multipart integration tests in an isolated temporary site. */
if (!extension_loaded('gd') || !function_exists('exif_read_data')) throw new RuntimeException('GD and Exif are required');
$root = sys_get_temp_dir().'/school-photo-http-'.bin2hex(random_bytes(8));
$site = $root.'/site';
if (!mkdir($site.'/includes', 0700, true)) throw new RuntimeException('Cannot create test directory');
foreach (['photo_upload.php', 'hero.php', 'news.php', 'athletes.php'] as $file) copy(__DIR__.'/../includes/'.$file, $site.'/includes/'.$file);
$endpoint = <<<'PHP'
<?php
require __DIR__.'/includes/hero.php';
require __DIR__.'/includes/news.php';
require __DIR__.'/includes/athletes.php';
header('Content-Type: application/json');
try {
    $file = $_FILES['photo'] ?? [];
    switch ($_POST['kind'] ?? '') {
        case 'hero': $path = hero_background_upload($file); break;
        case 'news': $path = news_image_upload($file); break;
        case 'athletes': $path = athlete_photo_upload($file); break;
        case 'coaches': $path = athlete_photo_upload($file, 'coaches'); break;
        default: throw new RuntimeException('Invalid kind');
    }
    $absolute = str_starts_with($path, 'uploads/news/') ? news_image_file($path) : __DIR__.'/'.$path;
    $info = getimagesize($absolute);
    echo json_encode(['path' => $path, 'width' => $info[0], 'height' => $info[1], 'type' => $info[2], 'bytes' => filesize($absolute)]);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); }
PHP;
file_put_contents($site.'/test.php', $endpoint);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$php = [PHP_BINARY, '-d', 'extension_dir='.ini_get('extension_dir'), '-d', 'extension=gd', '-d', 'extension=exif', '-d', 'upload_max_filesize=12M', '-d', 'post_max_size=12M', '-S', '127.0.0.1:'.$port, '-t', $site];
$process = proc_open($php, [0 => ['pipe', 'r'], 1 => ['file', $root.'/server.log', 'a'], 2 => ['file', $root.'/server.log', 'a']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start PHP server');
fclose($pipes[0]);
function http_test_request(int $port, string $kind, string $data, string $filename): array
{
    $boundary = 'test'.bin2hex(random_bytes(12));
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"kind\"\r\n\r\n$kind\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"$filename\"\r\nContent-Type: application/octet-stream\r\n\r\n".$data."\r\n--$boundary--\r\n";
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\n", 'content' => $body, 'ignore_errors' => true, 'timeout' => 15]]);
    $response = file_get_contents('http://127.0.0.1:'.$port.'/test.php', false, $context);
    $result = json_decode((string) $response, true);
    if (!is_array($result)) throw new RuntimeException('Invalid HTTP response: '.$response);
    return $result;
}
function remove_test_tree(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path.'/'.$name;
        if (is_dir($child)) remove_test_tree($child); else unlink($child);
    }
    rmdir($path);
}
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('Server did not start: '.file_get_contents($root.'/server.log'));
    $image = imagecreatetruecolor(2400, 1200);
    imagefill($image, 0, 0, imagecolorallocate($image, 25, 80, 130));
    for ($i = 0; $i < 100; $i++) imageline($image, $i * 24, 0, 2399 - $i * 24, 1199, imagecolorallocate($image, 240, 120, 30));
    ob_start(); imagejpeg($image, null, 100); $jpeg = ob_get_clean();
    ob_start(); imagepng($image); $png = ob_get_clean();
    ob_start(); imagewebp($image, null, 95); $webp = ob_get_clean();
    unset($image);
    foreach (['hero' => 1920, 'news' => 1600, 'coaches' => 1200, 'athletes' => 1200] as $kind => $max) {
        foreach (['jpg' => [$jpeg, IMAGETYPE_JPEG], 'png' => [$png, IMAGETYPE_PNG], 'webp' => [$webp, IMAGETYPE_WEBP]] as $ext => [$bytes, $type]) {
            if ($kind === 'news' && $ext === 'webp') continue;
            $result = http_test_request($port, $kind, $bytes, 'test.'.$ext);
            if (isset($result['error']) || $result['width'] !== $max || $result['height'] !== (int) ($max / 2) || $result['type'] !== $type || !str_starts_with($result['path'], 'uploads/'.$kind.'/')) throw new RuntimeException($kind.' '.$ext.': '.json_encode($result));
            if ($ext === 'jpg' && $result['bytes'] >= strlen($jpeg)) throw new RuntimeException($kind.' JPEG not reduced');
            if ($kind === 'hero' && $ext === 'jpg') echo 'Hero JPEG: '.strlen($jpeg).' -> '.$result['bytes']." bytes.\n";
        }
    }
    $bad = http_test_request($port, 'news', $webp, 'test.webp');
    if (!isset($bad['error'])) throw new RuntimeException('Unsupported news WebP accepted');
    $bad = http_test_request($port, 'coaches', 'not an image', 'test.jpg');
    if (!isset($bad['error'])) throw new RuntimeException('Invalid image accepted');
    proc_terminate($process); proc_close($process);
    $process = proc_open([PHP_BINARY, '-n', '-S', '127.0.0.1:'.$port, '-t', $site], [0 => ['pipe', 'r'], 1 => ['file', $root.'/server.log', 'a'], 2 => ['file', $root.'/server.log', 'a']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot restart PHP server');
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('Server did not restart');
    $result = http_test_request($port, 'hero', $jpeg, 'test.jpg');
    if (!isset($result['error']) || !str_contains($result['error'], 'GD')) throw new RuntimeException('Missing GD was not reported: '.json_encode($result));
    echo "HTTP uploads passed for hero, news, coach and athlete formats; invalid files rejected.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    remove_test_tree($root);
}
