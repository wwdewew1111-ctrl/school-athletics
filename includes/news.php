<?php
declare(strict_types=1);
require_once __DIR__.'/photo_upload.php';

function news_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL,
        published_date TEXT NOT NULL, image TEXT NOT NULL DEFAULT '', published INTEGER NOT NULL DEFAULT 0,
        vk_post_id TEXT NOT NULL DEFAULT '', vk_group_id TEXT NOT NULL DEFAULT '', vk_image TEXT NOT NULL DEFAULT '', vk_attachment TEXT NOT NULL DEFAULT '', vk_hash TEXT NOT NULL DEFAULT '', vk_status TEXT NOT NULL DEFAULT '',
        tg_message_id TEXT NOT NULL DEFAULT '', tg_chat_id TEXT NOT NULL DEFAULT '', tg_kind TEXT NOT NULL DEFAULT '', tg_image TEXT NOT NULL DEFAULT '', tg_hash TEXT NOT NULL DEFAULT '', tg_status TEXT NOT NULL DEFAULT ''
    )");
    $columns = array_column($pdo->query('PRAGMA table_info(news)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['vk_attachment', 'vk_hash', 'tg_hash'] as $column) {
        if (!in_array($column, $columns, true)) $pdo->exec("ALTER TABLE news ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
    }
}

function news_image_file(string $path): string
{
    if (!preg_match('~^uploads/news/([a-f0-9]{32}\.(?:jpg|png))$~D', $path, $match)) throw new RuntimeException('Некорректный путь к изображению.');
    return dirname(dirname(__DIR__)).'/school-athletics-news-uploads/'.$match[1];
}

function news_image_upload(array $file): string
{
    return photo_upload($file, 'news', dirname(dirname(__DIR__)).'/school-athletics-news-uploads', [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'], 1600);
}

function news_excerpt(string $text, int $limit = 180): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    preg_match_all('/./us', $text, $chars);
    return count($chars[0]) > $limit ? implode('', array_slice($chars[0], 0, $limit)).'…' : $text;
}
