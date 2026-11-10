<?php
declare(strict_types=1);
require_once __DIR__.'/photo_upload.php';

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

function athlete_photo_upload(array $file, string $folder = 'athletes'): string
{
    if (!in_array($folder, ['athletes', 'coaches'], true)) throw new InvalidArgumentException('Invalid photo folder.');
    return photo_upload($file, $folder, dirname(__DIR__).'/uploads/'.$folder, [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'], 1200);
}
