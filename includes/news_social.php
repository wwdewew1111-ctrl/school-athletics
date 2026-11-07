<?php
declare(strict_types=1);

/* The admin-managed file must be outside the site's document root. */
function social_settings_path(): string
{
    return dirname(__DIR__, 2).'/school-athletics-social-settings.json';
}

function social_settings_read(): array
{
    $path = social_settings_path();
    if (!file_exists($path)) return [];
    $json = @file_get_contents($path);
    $data = $json === false ? null : json_decode($json, true);
    if (!is_array($data)) throw new RuntimeException('Не удалось прочитать настройки соцсетей. Проверьте файл и права доступа.');
    return array_intersect_key($data, array_flip(['vk_token', 'vk_group', 'tg_token', 'tg_chat']));
}

function social_config(): array
{
    $saved = social_settings_read();
    $env = ['vk_token' => 'NEWS_VK_TOKEN', 'vk_group' => 'NEWS_VK_GROUP_ID', 'tg_token' => 'NEWS_TELEGRAM_TOKEN', 'tg_chat' => 'NEWS_TELEGRAM_CHAT_ID'];
    $config = [];
    foreach ($env as $key => $name) $config[$key] = array_key_exists($key, $saved) ? (string) $saved[$key] : trim((string) getenv($name));
    return $config;
}

function social_settings_save(array $post): void
{
    $saved = social_settings_read();
    foreach (['vk_group', 'tg_chat'] as $key) {
        if (!isset($post[$key]) || !is_string($post[$key])) throw new RuntimeException('Некорректный идентификатор соцсети.');
        $value = trim($post[$key]);
        if (strlen($value) > 200 || preg_match('/[\x00-\x20\x7f]/', $value)) throw new RuntimeException('Идентификаторы не должны содержать пробелы или управляющие символы.');
        $saved[$key] = $value;
    }
    if ($saved['vk_group'] !== '' && !preg_match('/^[1-9][0-9]*$/D', $saved['vk_group'])) throw new RuntimeException('ID сообщества VK должен быть положительным числом без знака минус.');
    foreach (['vk_token', 'tg_token'] as $key) {
        $value = $post[$key] ?? '';
        if (!is_string($value)) throw new RuntimeException('Некорректный токен.');
        $value = trim($value);
        if (strlen($value) > 4096 || preg_match('/[\x00-\x20\x7f]/', $value)) throw new RuntimeException('Токен не должен содержать пробелы или управляющие символы.');
        if (!empty($post['clear_'.$key]) && $value !== '') throw new RuntimeException('Нельзя одновременно удалить и заменить один токен.');
        if (!empty($post['clear_'.$key])) $saved[$key] = '';
        elseif ($value !== '') $saved[$key] = $value;
    }
    $path = social_settings_path();
    $temp = @tempnam(dirname($path), '.social-');
    if ($temp === false) throw new RuntimeException('Не удалось создать файл настроек вне сайта. Проверьте права доступа.');
    try {
        @chmod($temp, 0600);
        $json = json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !@rename($temp, $path)) throw new RuntimeException('Не удалось сохранить настройки соцсетей. Проверьте права доступа.');
        @chmod($path, 0600);
    } finally {
        if (file_exists($temp)) @unlink($temp);
    }
}

function social_request(string $url, array $fields, ?array $file = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('На сервере не включено PHP-расширение cURL.');
    $ch = curl_init($url);
    if ($file !== null) $fields[$file['field']] = new CURLFile($file['path'], $file['mime'], basename($file['path']));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $file === null ? http_build_query($fields) : $fields,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Ошибка сети: '.$error);
    $result = json_decode($body, true);
    if (!is_array($result)) throw new RuntimeException('Некорректный ответ API (HTTP '.$status.').');
    if (isset($result['description']) && stripos((string) $result['description'], 'message is not modified') !== false) return ['ok' => true, 'result' => true];
    if (isset($result['error'])) {
        $detail = is_array($result['error']) ? ($result['error']['error_msg'] ?? 'ошибка API') : $result['error'];
        throw new RuntimeException('API: '.substr((string) $detail, 0, 300));
    }
    if ($status < 200 || $status >= 300 || (isset($result['ok']) && !$result['ok'])) {
        throw new RuntimeException('API: '.substr((string) ($result['description'] ?? 'HTTP '.$status), 0, 300));
    }
    return $result;
}

function social_vk(string $method, array $params, array $config): mixed
{
    $params['access_token'] = $config['vk_token'];
    $params['v'] = '5.199';
    $response = social_request('https://api.vk.com/method/'.$method, $params);
    if (!array_key_exists('response', $response)) throw new RuntimeException('VK вернул неполный ответ.');
    return $response['response'];
}

function social_vk_photo(string $path, array $config): string
{
    $server = social_vk('photos.getWallUploadServer', ['group_id' => $config['vk_group']], $config);
    $url = (string) ($server['upload_url'] ?? '');
    $host = parse_url($url, PHP_URL_HOST);
    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !is_string($host) || !preg_match('/(^|\.)(vk\.com|userapi\.com)$/iD', $host)) throw new RuntimeException('VK вернул недопустимый адрес загрузки.');
    $absolute = news_image_file($path);
    $upload = social_request($url, [], ['field' => 'photo', 'path' => $absolute, 'mime' => str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg']);
    foreach (['server', 'photo', 'hash'] as $key) if (!isset($upload[$key])) throw new RuntimeException('VK вернул неполный ответ загрузки фото.');
    $saved = social_vk('photos.saveWallPhoto', ['group_id' => $config['vk_group'], 'server' => $upload['server'], 'photo' => $upload['photo'], 'hash' => $upload['hash']], $config);
    if (!isset($saved[0]['owner_id'], $saved[0]['id'])) throw new RuntimeException('VK не сохранил фотографию.');
    return 'photo'.$saved[0]['owner_id'].'_'.$saved[0]['id'].(empty($saved[0]['access_key']) ? '' : '_'.$saved[0]['access_key']);
}

function social_vk_sync(array $news, array $config): array
{
    if ($config['vk_token'] === '' || !preg_match('/^[1-9][0-9]*$/D', $config['vk_group'])) throw new RuntimeException('VK не настроен: необходимы токен и числовой ID сообщества.');
    if ($news['vk_post_id'] !== '' && $news['vk_group_id'] !== $config['vk_group']) throw new RuntimeException('ID сообщества VK изменился; автоматическая синхронизация остановлена.');
    $params = ['owner_id' => '-'.$config['vk_group'], 'message' => $news['title']."\n\n".$news['body']];
    $attachment = $news['vk_attachment'];
    if ($news['image'] !== '' && ($news['vk_image'] !== $news['image'] || $attachment === '')) $attachment = social_vk_photo($news['image'], $config);
    if ($news['image'] !== '') $params['attachments'] = $attachment;
    elseif ($news['vk_post_id'] !== '') { $params['attachments'] = ''; $attachment = ''; }
    if ($news['vk_post_id'] !== '') {
        $params['post_id'] = $news['vk_post_id'];
        $ok = social_vk('wall.edit', $params, $config);
        if (!$ok) throw new RuntimeException('VK не подтвердил изменение записи.');
        $id = $news['vk_post_id'];
    } else {
        $params['from_group'] = 1;
        $response = social_vk('wall.post', $params, $config);
        if (empty($response['post_id'])) throw new RuntimeException('VK не вернул ID записи.');
        $id = (string) $response['post_id'];
    }
    return [$id, $config['vk_group'], $news['image'], $attachment];
}

function social_telegram(string $method, array $params, array $config, ?array $file = null): mixed
{
    $response = social_request('https://api.telegram.org/bot'.$config['tg_token'].'/'.$method, $params, $file);
    if (empty($response['ok']) || !array_key_exists('result', $response)) throw new RuntimeException('Telegram не подтвердил запрос.');
    return $response['result'];
}

function social_tg_sync(array $news, array $config): array
{
    if ($config['tg_token'] === '' || $config['tg_chat'] === '') throw new RuntimeException('Telegram не настроен: необходимы токен бота и ID канала.');
    if ($news['tg_message_id'] !== '' && $news['tg_chat_id'] !== $config['tg_chat']) throw new RuntimeException('ID канала Telegram изменился; автоматическая синхронизация остановлена.');
    $message = $news['title']."\n\n".$news['body'];
    preg_match_all('/./us', $message, $characters);
    $kind = $news['image'] !== '' ? 'photo' : 'text';
    if (count($characters[0]) > ($kind === 'photo' ? 1024 : 4096)) throw new RuntimeException('Текст превышает лимит Telegram: 1024 символа с фото или 4096 без фото.');
    $params = ['chat_id' => $config['tg_chat']];
    $file = $kind === 'photo' ? ['field' => 'photo', 'path' => news_image_file($news['image']), 'mime' => str_ends_with($news['image'], '.png') ? 'image/png' : 'image/jpeg'] : null;
    if ($news['tg_message_id'] === '') {
        $params[$kind === 'photo' ? 'caption' : 'text'] = $message;
        $response = social_telegram($kind === 'photo' ? 'sendPhoto' : 'sendMessage', $params, $config, $file);
        if (empty($response['message_id'])) throw new RuntimeException('Telegram не вернул ID сообщения.');
        return [(string) $response['message_id'], $config['tg_chat'], $kind, $news['image']];
    }
    if ($news['tg_kind'] !== $kind) throw new RuntimeException('Telegram не позволяет изменить тип существующего сообщения: фото и текст без фото. Измените сообщение вручную.');
    $params['message_id'] = $news['tg_message_id'];
    if ($kind === 'text') {
        $params['text'] = $message;
        social_telegram('editMessageText', $params, $config);
    } elseif ($news['tg_image'] === $news['image']) {
        $params['caption'] = $message;
        social_telegram('editMessageCaption', $params, $config);
    } else {
        $params['media'] = json_encode(['type' => 'photo', 'media' => 'attach://photo', 'caption' => $message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        social_telegram('editMessageMedia', $params, $config, $file);
    }
    return [$news['tg_message_id'], $news['tg_chat_id'], $kind, $news['image']];
}

function news_sync(PDO $pdo, int $id): array
{
    $config = social_config();
    $messages = [];
    foreach (['vk', 'tg'] as $network) {
        $stmt = $pdo->prepare('SELECT * FROM news WHERE id=?');
        $stmt->execute([$id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$news || !$news['published']) break;
        try {
            if ($network === 'vk' && $news['vk_post_id'] !== '' && $news['vk_group_id'] === $config['vk_group'] && $news['vk_status'] === 'Синхронизировано' && $news['vk_hash'] === hash('sha256', $news['title']."\0".$news['body']."\0".$news['image'])) { $messages[] = 'VK: без изменений'; continue; }
            if ($network === 'tg' && $news['tg_message_id'] !== '' && $news['tg_chat_id'] === $config['tg_chat'] && $news['tg_status'] === 'Синхронизировано' && $news['tg_hash'] === hash('sha256', $news['title']."\0".$news['body']."\0".$news['image'])) { $messages[] = 'TG: без изменений'; continue; }
            $digest = hash('sha256', $news['title']."\0".$news['body']."\0".$news['image']);
            if ($network === 'vk') {
                [$post, $group, $image, $attachment] = social_vk_sync($news, $config);
                $pdo->prepare('UPDATE news SET vk_post_id=?,vk_group_id=?,vk_image=?,vk_attachment=?,vk_hash=?,vk_status=? WHERE id=?')->execute([$post, $group, $image, $attachment, $digest, 'Синхронизировано', $id]);
            } else {
                [$message, $chat, $kind, $image] = social_tg_sync($news, $config);
                $pdo->prepare('UPDATE news SET tg_message_id=?,tg_chat_id=?,tg_kind=?,tg_image=?,tg_hash=?,tg_status=? WHERE id=?')->execute([$message, $chat, $kind, $image, $digest, 'Синхронизировано', $id]);
            }
            $messages[] = strtoupper($network).': синхронизировано';
        } catch (Throwable $ex) {
            $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'Внутренняя ошибка синхронизации.';
            $error = str_replace(array_filter([$config['vk_token'], $config['tg_token']]), '[скрыто]', $error);
            $pdo->prepare('UPDATE news SET '.$network.'_status=? WHERE id=?')->execute([$error, $id]);
            $messages[] = strtoupper($network).': '.$error;
        }
    }
    return $messages;
}

