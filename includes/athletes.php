<?php
declare(strict_types=1);

function athlete_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS athletes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        coach_id INTEGER NOT NULL,
        achievements TEXT NOT NULL DEFAULT '',
        photo TEXT NOT NULL DEFAULT '',
        FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE RESTRICT
    )");
    $columns = array_column($pdo->query('PRAGMA table_info(athletes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['birth_date', 'sports_title', 'school_entry_date'] as $column) {
        if (!in_array($column, $columns, true)) $pdo->exec("ALTER TABLE athletes ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS athletes_coach_id ON athletes(coach_id)');
}

function athlete_valid_date(string $value): bool
{
    if ($value === '') return true;
    if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $parts)) return false;
    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

function athlete_display_date(string $value): string
{
    return athlete_valid_date($value) && $value !== '' ? substr($value, 8, 2).'.'.substr($value, 5, 2).'.'.substr($value, 0, 4) : $value;
}

function athlete_photo_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return '';
    if ($error !== UPLOAD_ERR_OK || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Не удалось загрузить фотографию (максимум 10 МБ).');
    }
    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]]) || $info[0] + $info[1] > 10000 || max($info[0] / $info[1], $info[1] / $info[0]) > 20) {
        throw new RuntimeException('Нужна фотография JPEG, PNG или WebP с допустимыми размерами.');
    }
    $dir = dirname(__DIR__).'/uploads/athletes';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку для фотографий спортсменов.');
    $path = 'uploads/athletes/'.bin2hex(random_bytes(16)).'.'.$types[$info[2]];
    if (!move_uploaded_file((string) $file['tmp_name'], dirname(__DIR__).'/'.$path)) throw new RuntimeException('Не удалось сохранить фотографию.');
    return $path;
}
