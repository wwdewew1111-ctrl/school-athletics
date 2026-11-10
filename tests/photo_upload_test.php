<?php
declare(strict_types=1);
/* Offline tests; create all fixtures outside the site. Run with GD and Exif enabled. */
require __DIR__.'/../includes/photo_upload.php';
if (!extension_loaded('gd') || !function_exists('exif_read_data')) throw new RuntimeException('GD and Exif are required');
$dir = sys_get_temp_dir().'/school-photo-test-'.bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) throw new RuntimeException('Cannot create test directory');
function photo_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
try {
    $im = imagecreatetruecolor(1800, 900);
    imagefill($im, 0, 0, imagecolorallocate($im, 35, 95, 150));
    for ($i = 0; $i < 100; $i++) imageline($im, $i * 18, 0, 1799 - $i * 18, 899, imagecolorallocate($im, 220, 120, 30));
    imagejpeg($im, $dir.'/original.jpg', 100);
    unset($im);
    photo_compress($dir.'/original.jpg', $dir.'/output.jpg', IMAGETYPE_JPEG, 1200);
    $info = getimagesize($dir.'/output.jpg');
    photo_assert($info[0] === 1200 && $info[1] === 600 && $info[2] === IMAGETYPE_JPEG, 'JPEG dimensions/type');
    photo_assert(filesize($dir.'/output.jpg') < filesize($dir.'/original.jpg'), 'JPEG not smaller');
    $im = imagecreatetruecolor(1000, 500);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 20, 30, 40, 127));
    imagefilledrectangle($im, 0, 0, 499, 499, imagecolorallocatealpha($im, 200, 30, 40, 0));
    imagepng($im, $dir.'/original.png'); imagewebp($im, $dir.'/original.webp', 95); unset($im);
    foreach ([IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'] as $type => $ext) {
        photo_compress($dir.'/original.'.$ext, $dir.'/output.'.$ext, $type, 600);
        $info = getimagesize($dir.'/output.'.$ext);
        photo_assert($info[0] === 600 && $info[1] === 300 && $info[2] === $type, strtoupper($ext).' dimensions/type');
        $reader = $type === IMAGETYPE_PNG ? 'imagecreatefrompng' : 'imagecreatefromwebp';
        $out = $reader($dir.'/output.'.$ext);
        photo_assert(((imagecolorat($out, 580, 150) >> 24) & 0x7f) >= 120, strtoupper($ext).' transparency');
        unset($out);
    }
    // EXIF orientations 1–8: compare all four corners against a reference image.
    $im = imagecreatetruecolor(80, 40);
    imagefilledrectangle($im, 0, 0, 39, 19, imagecolorallocate($im, 240, 10, 10));
    imagefilledrectangle($im, 40, 0, 79, 19, imagecolorallocate($im, 10, 240, 10));
    imagefilledrectangle($im, 0, 20, 39, 39, imagecolorallocate($im, 10, 10, 240));
    imagefilledrectangle($im, 40, 20, 79, 39, imagecolorallocate($im, 240, 240, 10));
    imagejpeg($im, $dir.'/corners.jpg', 100); unset($im);
    $jpeg = file_get_contents($dir.'/corners.jpg');
    $orders = [1 => [0,1,2,3], 2 => [1,0,3,2], 3 => [3,2,1,0], 4 => [2,3,0,1], 5 => [0,2,1,3], 6 => [2,0,3,1], 7 => [3,1,2,0], 8 => [1,3,0,2]];
    $colors = [[240,10,10], [10,240,10], [10,10,240], [240,240,10]];
    foreach ($orders as $orientation => $order) {
        $tiff = "II\x2a\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00".$tiff;
        file_put_contents($dir.'/oriented.jpg', substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2));
        photo_compress($dir.'/oriented.jpg', $dir.'/oriented-out.jpg', IMAGETYPE_JPEG, 1200);
        $out = imagecreatefromjpeg($dir.'/oriented-out.jpg');
        $w = imagesx($out); $h = imagesy($out);
        photo_assert(($orientation >= 5 ? $w === 40 && $h === 80 : $w === 80 && $h === 40), 'Orientation dimensions '.$orientation);
        $points = [[5,5],[$w-6,5],[5,$h-6],[$w-6,$h-6]];
        foreach ($points as $i => [$x,$y]) {
            $actual = imagecolorsforindex($out, imagecolorat($out, $x, $y));
            foreach (['red','green','blue'] as $channel => $name) photo_assert(abs($actual[$name] - $colors[$order[$i]][$channel]) < 40, 'Orientation '.$orientation.' corner '.$i);
        }
        unset($out);
    }
    try { photo_compress($dir.'/original.jpg', $dir.'/missing/out.jpg', IMAGETYPE_JPEG, 1200); throw new LogicException('Missing directory accepted'); }
    catch (RuntimeException $e) { photo_assert(!is_file($dir.'/missing/out.jpg'), 'Partial file left behind'); }
    try { photo_upload(['error' => UPLOAD_ERR_OK, 'size' => filesize($dir.'/original.jpg'), 'tmp_name' => $dir.'/original.jpg'], 'test', $dir, [IMAGETYPE_JPEG => 'jpg'], 1200); throw new LogicException('Non-upload accepted'); }
    catch (RuntimeException $e) {}
    photo_assert(photo_upload([], 'test', $dir, [IMAGETYPE_JPEG => 'jpg'], 1200) === '', 'Empty upload');
    ini_set('memory_limit', '24M');
    try { photo_check_memory(4000, 4000, 1200); throw new LogicException('Memory check did not reject oversized image'); }
    catch (RuntimeException $e) {}
    ini_set('memory_limit', '-1');
    echo "Photo compression, formats, transparency, EXIF orientations and failure tests passed.\n";
} finally {
    foreach (glob($dir.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    rmdir($dir);
}
