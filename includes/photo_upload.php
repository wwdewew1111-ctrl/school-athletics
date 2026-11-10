<?php
declare(strict_types=1);

/* GD allocates pixel buffers outside ordinary upload-size accounting. */
function photo_check_memory(int $width, int $height, int $maxSide): void
{
    $rawLimit = trim((string) ini_get('memory_limit'));
    if ($rawLimit === '' || $rawLimit === '-1') return;
    $limit = (float) $rawLimit;
    $suffix = strtolower(substr($rawLimit, -1));
    if ($suffix === 'g') $limit *= 1024;
    if ($suffix === 'g' || $suffix === 'm') $limit *= 1024;
    if (in_array($suffix, ['g', 'm', 'k'], true)) $limit *= 1024;
    // Allow for source, a rotated copy, target and GD overhead with headroom.
    $scale = min(1, $maxSide / max($width, $height));
    $estimate = $width * $height * 10 + $width * $height * $scale * $scale * 5 + 16 * 1024 * 1024;
    if ($estimate > $limit - memory_get_usage(true)) {
        throw new RuntimeException('Недостаточно памяти для обработки фотографии. Уменьшите её размер и повторите загрузку.');
    }
}


/** Save a photo in its original format, limiting dimensions and re-encoding it. */
function photo_upload(array $file, string $folder, string $directory, array $types, int $maxSide): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return '';
    if ($error !== UPLOAD_ERR_OK || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Не удалось загрузить фотографию (максимум 10 МБ).');
    }
    $source = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($source)) throw new RuntimeException('Не удалось загрузить фотографию.');
    $info = @getimagesize($source);
    if (!$info || !isset($types[$info[2]]) || $info[0] < 1 || $info[1] < 1 || $info[0] + $info[1] > 10000 || max($info[0] / $info[1], $info[1] / $info[0]) > 20) {
        throw new RuntimeException('Недопустимый формат или размеры фотографии.');
    }
    if (!extension_loaded('gd')) throw new RuntimeException('Сжатие фотографий недоступно: включите расширение PHP GD на сервере.');
    photo_check_memory($info[0], $info[1], $maxSide);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Не удалось создать папку для фотографий.');
    $filename = bin2hex(random_bytes(16)).'.'.$types[$info[2]];
    $destination = $directory.'/'.$filename;
    photo_compress($source, $destination, $info[2], $maxSide);
    return 'uploads/'.$folder.'/'.$filename;
}

/** Encode to a temporary file; a failed conversion never leaves a partial upload. */
function photo_compress(string $source, string $destination, int $type, int $maxSide): void
{
    $readers = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $writers = [IMAGETYPE_JPEG => 'imagejpeg', IMAGETYPE_PNG => 'imagepng', IMAGETYPE_WEBP => 'imagewebp'];
    $reader = $readers[$type] ?? '';
    $writer = $writers[$type] ?? '';
    if (!extension_loaded('gd') || !function_exists($reader) || !function_exists($writer)) {
        throw new RuntimeException('Сжатие этого формата недоступно: проверьте настройки PHP GD на сервере.');
    }
    // Re-encoding discards EXIF, so its orientation must be read and applied first.
    $orientation = 1;
    if ($type === IMAGETYPE_JPEG) {
        if (!function_exists('exif_read_data')) throw new RuntimeException('Для корректного поворота JPEG включите расширение PHP Exif на сервере.');
        $exif = @exif_read_data($source, 'IFD0');
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation < 1 || $orientation > 8) $orientation = 1;
    }
    $image = @$reader($source);
    if (!$image) throw new RuntimeException('Не удалось прочитать фотографию.');
    $temp = false;
    try {
        if (in_array($orientation, [2, 4, 5, 7], true) && !imageflip($image, IMG_FLIP_HORIZONTAL)) throw new RuntimeException('Не удалось обработать ориентацию фотографии.');
        $angle = [3 => 180, 4 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if (!$rotated) throw new RuntimeException('Не удалось обработать ориентацию фотографии.');
            $image = $rotated;
            unset($rotated);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));
        if ($scale < 1) {
            $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            if (!$target) throw new RuntimeException('Не удалось изменить размер фотографии.');
            if ($type !== IMAGETYPE_JPEG) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
            }
            if (!imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height)) {
                unset($target);
                throw new RuntimeException('Не удалось изменить размер фотографии.');
            }
            $image = $target;
            unset($target);
        }
        if ($type !== IMAGETYPE_JPEG) { imagealphablending($image, false); imagesavealpha($image, true); }
        if (!is_dir(dirname($destination))) throw new RuntimeException('Не удалось сохранить фотографию.');
        $temp = @tempnam(dirname($destination), '.photo-');
        if ($temp === false) throw new RuntimeException('Не удалось сохранить фотографию.');
        if (realpath(dirname($temp)) !== realpath(dirname($destination))) throw new RuntimeException('Не удалось сохранить фотографию.');
        $quality = $type === IMAGETYPE_PNG ? 6 : 82;
        if (!@$writer($image, $temp, $quality) || !is_file($temp) || filesize($temp) < 1 || !@rename($temp, $destination)) {
            throw new RuntimeException('Не удалось сохранить сжатую фотографию.');
        }
        $temp = false;
    } finally {
        unset($image);
        if ($temp !== false) @unlink($temp);
    }
}
