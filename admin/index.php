<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/upload_names.php';
require_once __DIR__.'/../includes/legal_editor.php';
require_once __DIR__.'/../includes/news.php';
require_once __DIR__.'/../includes/news_social.php';

// Session and authentication must be handled before any HTML is sent.
session_start();
$pdo = db();
coach_columns($pdo);
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];
$error = '';
$notice = (string) ($_SESSION['notice'] ?? '');
unset($_SESSION['notice']);
$tabs = ['news' => 'Новости', 'competitions' => 'Соревнования', 'schedule' => 'Расписание', 'coaches' => 'Тренеры', 'gallery' => 'Фотоальбомы', 'legal' => 'Юридические данные'];
$days = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];
$tab = (string) ($_GET['tab'] ?? 'competitions');
if (!isset($tabs[$tab])) {
    $tab = 'competitions';
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// Generated once with password_hash(..., PASSWORD_DEFAULT); never store the plaintext password here.
const ADMIN_PASSWORD_HASH = '$2y$12$8YIVDGbR0yC4huhwShwtneR9SJwfoGjfyaUWc8niBBlkTgsB8/.je';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))
        && hash_equals('admin', (string) ($_POST['username'] ?? ''))
        && password_verify((string) ($_POST['password'] ?? ''), ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный логин, пароль или токен формы.';
}
$logged = !empty($_SESSION['admin']);

// Only an authenticated session with a valid CSRF token may change data.
if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    $postTab = (string) ($_POST['tab'] ?? 'competitions');
    $tab = isset($tabs[$postTab]) ? $postTab : 'competitions';
    $newFiles = [];
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Сессия формы истекла. Обновите страницу.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $upload = static function (array $file, string $folder, array $extensions) use (&$newFiles, $pdo): string {
            $path = upload_file($file, $folder, $extensions);
            if ($path !== '') {
                $newFiles[] = $path;
                save_upload_name($pdo, $path, (string) ($file['name'] ?? ''));
            }
            return $path;
        };
        $existing = static function (string $table, int $id) use ($pdo): array {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('Запись не найдена.');
            }
            return $row;
        };
        $syncId = 0;
        $noticeText = 'Изменения сохранены.';
        $pdo->beginTransaction();
        switch ($action) {
            case 'social_settings':
                social_settings_save($_POST);
                $noticeText = 'Настройки соцсетей сохранены. Для ранее опубликованных новостей запустите повторную синхронизацию вручную.';
                break;
            case 'news':
                news_schema($pdo);
                $oldNews = $id > 0 ? $existing('news', $id) : null;
                if ($oldNews && !hash_equals(hash('sha256', json_encode($oldNews, JSON_UNESCAPED_UNICODE)), (string) ($_POST['revision'] ?? ''))) {
                    throw new RuntimeException('Новость уже изменена. Обновите страницу.');
                }
                $title = trim((string) ($_POST['title'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));
                $date = (string) ($_POST['published_date'] ?? '');
                $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if ($title === '' || $body === '' || !$dateValue || $dateValue->format('Y-m-d') !== $date || strlen($title) > 600 || strlen($body) > 50000) {
                    throw new RuntimeException('Укажите заголовок, текст и корректную дату публикации.');
                }
                $file = $_FILES['image'] ?? [];
                if (!empty($_POST['remove_image']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) throw new RuntimeException('Нельзя одновременно удалить и заменить изображение.');
                $image = news_image_upload($file);
                if ($image !== '') $newFiles[] = $image;
                $image = $image ?: (!empty($_POST['remove_image']) ? '' : ($oldNews['image'] ?? ''));
                $published = isset($_POST['published']) ? 1 : 0;
                if ($oldNews && $oldNews['published'] && !$published) throw new RuntimeException('Опубликованную новость нельзя перевести в черновик: удалите её, если необходимо.');
                if ($oldNews) {
                    $pdo->prepare('UPDATE news SET title=?,body=?,published_date=?,image=?,published=? WHERE id=?')->execute([$title, $body, $date, $image, $published, $id]);
                } else {
                    $pdo->prepare('INSERT INTO news(title,body,published_date,image,published) VALUES(?,?,?,?,?)')->execute([$title, $body, $date, $image, $published]);
                    $id = (int) $pdo->lastInsertId();
                }
                if ($published) $syncId = $id;
                break;
            case 'news_sync':
                news_schema($pdo);
                $oldNews = $existing('news', $id);
                if (!$oldNews['published']) throw new RuntimeException('Черновик нельзя синхронизировать.');
                $syncId = $id;
                break;
            case 'news_delete':
                news_schema($pdo);
                $existing('news', $id);
                $pdo->prepare('DELETE FROM news WHERE id=?')->execute([$id]);
                $noticeText = 'Новость удалена с сайта. Записи в соцсетях сохранены.';
                break;
            case 'delete':
                $table = (string) ($_POST['table'] ?? '');
                if (!in_array($table, ['competitions', 'schedules', 'coaches', 'albums', 'photos'], true) || $id < 1) {
                    throw new RuntimeException('Недопустимая запись.');
                }
                $row = $existing($table, $id);
                if ($table === 'photos') {
                    $pdo->prepare('UPDATE albums SET cover = ? WHERE id = ? AND cover = ?')->execute(['', $row['album_id'], $row['path']]);
                }
                $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
                break;
            case 'competition':
                $title = trim((string) ($_POST['title'] ?? ''));
                $date = (string) ($_POST['event_date'] ?? '');
                $place = trim((string) ($_POST['place'] ?? ''));
                $status = (string) ($_POST['status'] ?? '');
                if ($title === '' || $date === '' || $place === '' || !in_array($status, ['upcoming', 'completed'], true)) {
                    throw new RuntimeException('Заполните обязательные поля соревнования.');
                }
                $old = $id > 0 ? $existing('competitions', $id) : null;
                $doc = $upload($_FILES['document'] ?? [], 'docs', ['pdf', 'doc', 'docx']);
                $values = [$title, $date, $place, trim((string) ($_POST['age_group'] ?? '')), $status, $doc ?: ($old['document'] ?? '')];
                if ($old) {
                    $pdo->prepare('UPDATE competitions SET title=?,event_date=?,place=?,age_group=?,status=?,document=? WHERE id=?')->execute([...$values, $id]);
                } else {
                    $pdo->prepare('INSERT INTO competitions(title,event_date,place,age_group,status,document) VALUES(?,?,?,?,?,?)')->execute($values);
                }
                break;
            case 'schedule':
                $weekday = (string) ($_POST['weekday'] ?? '');
                $time = trim((string) ($_POST['time'] ?? ''));
                $group = trim((string) ($_POST['group_name'] ?? ''));
                $discipline = trim((string) ($_POST['discipline'] ?? ''));
                $venue = trim((string) ($_POST['venue'] ?? ''));
                $coachId = (string) ($_POST['coach_id'] ?? '') !== '' ? (int) $_POST['coach_id'] : null;
                if (!in_array($weekday, $days, true) || $time === '' || $group === '' || $discipline === '' || $venue === '') {
                    throw new RuntimeException('Заполните обязательные поля расписания.');
                }
                if ($coachId !== null) {
                    $existing('coaches', $coachId);
                }
                $values = [$weekday, $time, $group, $discipline, $coachId, $venue];
                if ($id > 0) {
                    $existing('schedules', $id);
                    $pdo->prepare('UPDATE schedules SET weekday=?,time=?,group_name=?,discipline=?,coach_id=?,venue=? WHERE id=?')->execute([...$values, $id]);
                } else {
                    $pdo->prepare('INSERT INTO schedules(weekday,time,group_name,discipline,coach_id,venue) VALUES(?,?,?,?,?,?)')->execute($values);
                }
                break;
            case 'coach':
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Укажите ФИО тренера.');
                $old = $id > 0 ? $existing('coaches', $id) : null;
                $file = $_FILES['photo'] ?? [];
                if (!empty($_POST['remove_photo']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Нельзя одновременно удалить и заменить фотографию.');
                }
                $photo = $upload($file, 'coaches', ['jpg', 'jpeg', 'png', 'webp']);
                $photo = $photo ?: (!empty($_POST['remove_photo']) ? '' : ($old['photo'] ?? ''));
                $experienceText = trim((string) ($_POST['experience_text'] ?? ''));
                $experience = preg_match('/^\s*(\d+)/u', $experienceText, $matches) ? (int) $matches[1] : 0;
                $values = [
                    $name, trim((string) ($_POST['title'] ?? '')), trim((string) ($_POST['specialization'] ?? '')),
                    $experience, $photo, trim((string) ($_POST['achievements'] ?? '')),
                    trim((string) ($_POST['education'] ?? '')), trim((string) ($_POST['qualification'] ?? '')),
                    trim((string) ($_POST['academic_degree'] ?? '')), trim((string) ($_POST['academic_title'] ?? '')),
                    trim((string) ($_POST['professional_development'] ?? '')), trim((string) ($_POST['professional_retraining'] ?? '')),
                    $experienceText, isset($_POST['visible']) ? 1 : 0,
                ];
                if ($old) {
                    $pdo->prepare('UPDATE coaches SET name=?,title=?,specialization=?,experience=?,photo=?,achievements=?,education=?,qualification=?,academic_degree=?,academic_title=?,professional_development=?,professional_retraining=?,experience_text=?,visible=? WHERE id=?')->execute([...$values, $id]);
                } else {
                    $pdo->prepare('INSERT INTO coaches(name,title,specialization,experience,photo,achievements,education,qualification,academic_degree,academic_title,professional_development,professional_retraining,experience_text,visible) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
                }
                break;
            case 'album':
                $title = trim((string) ($_POST['title'] ?? ''));
                $date = (string) ($_POST['album_date'] ?? '');
                if ($title === '' || $date === '') {
                    throw new RuntimeException('Укажите название и дату альбома.');
                }
                $old = $id > 0 ? $existing('albums', $id) : null;
                $cover = $upload($_FILES['cover'] ?? [], 'gallery', ['jpg', 'jpeg', 'png', 'webp']) ?: ($old['cover'] ?? '');
                if ($old) {
                    $pdo->prepare('UPDATE albums SET title=?,album_date=?,cover=? WHERE id=?')->execute([$title, $date, $cover, $id]);
                } else {
                    $pdo->prepare('INSERT INTO albums(title,album_date,cover) VALUES(?,?,?)')->execute([$title, $date, $cover]);
                    $id = (int) $pdo->lastInsertId();
                }
                foreach (($_FILES['photos']['name'] ?? []) as $i => $name) {
                    $file = ['name' => $name, 'tmp_name' => $_FILES['photos']['tmp_name'][$i] ?? '', 'error' => $_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $_FILES['photos']['size'][$i] ?? 0];
                    $path = $upload($file, 'gallery', ['jpg', 'jpeg', 'png', 'webp']);
                    if ($path === '') {
                        continue;
                    }
                    $pdo->prepare('INSERT INTO photos(album_id,path,caption) VALUES(?,?,?)')->execute([$id, $path, '']);
                    if ($cover === '') {
                        $cover = $path;
                        $pdo->prepare('UPDATE albums SET cover=? WHERE id=?')->execute([$cover, $id]);
                    }
                }
                break;
            case 'legal':
                $row = $existing('legal_sections', $id);
                if (!hash_equals(legal_content_digest($row), (string) ($_POST['revision'] ?? ''))) {
                    throw new RuntimeException('Раздел уже изменён. Обновите страницу и повторите изменения.');
                }
                $content = (string) ($_POST['content'] ?? '');
                $oldPaths = legal_document_paths((string) $row['content']);
                $newPaths = legal_document_paths($content);
                if (array_diff($oldPaths, $newPaths) || array_diff($newPaths, $oldPaths)) {
                    throw new RuntimeException('Ссылки на документы изменены в тексте. Используйте кнопки управления файлами.');
                }
                $remove = $_POST['remove_files'] ?? [];
                if (!is_array($remove)) throw new RuntimeException('Неверный список документов.');
                $changes = [];
                foreach ($oldPaths as $i => $path) {
                    $file = $_FILES['replace_'.$i] ?? [];
                    $hasUpload = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                    $delete = in_array((string) $i, $remove, true);
                    if ($delete && $hasUpload) throw new RuntimeException('Для одного файла нельзя одновременно выбрать удаление и замену.');
                    if ($delete) {
                        $changes[$path] = null;
                    } elseif ($hasUpload) {
                        $newPath = $upload($file, 'docs', ['pdf', 'doc', 'docx', 'xlsx']);
                        $changes[$path] = [$newPath, original_upload_name((string) $file['name'])];
                    }
                }
                $content = legal_edit_document_links($content, $changes);
                $added = $upload($_FILES['new_document'] ?? [], 'docs', ['pdf', 'doc', 'docx', 'xlsx']);
                if ($added !== '') $content = legal_append_document($content, $added, original_upload_name((string) $_FILES['new_document']['name']));
                $doc = (string) $row['document'];
                $legacyUpload = $_FILES['document'] ?? [];
                if (!empty($_POST['remove_document']) && ($legacyUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Нельзя одновременно удалить и заменить документ раздела.');
                }
                if (!empty($_POST['remove_document'])) $doc = '';
                $replacement = $upload($legacyUpload, 'docs', ['pdf', 'doc', 'docx', 'xlsx']);
                if ($replacement !== '') $doc = $replacement;
                $pdo->prepare('UPDATE legal_sections SET content=?,document=? WHERE id=?')->execute([$content, $doc, $id]);
                break;
            default:
                throw new RuntimeException('Неизвестное действие.');
        }
        $pdo->commit();
        if ($syncId > 0) {
            try { $noticeText .= ' '.implode(' · ', news_sync($pdo, $syncId)); }
            catch (Throwable $syncError) { $noticeText .= ' Ошибка синхронизации; проверьте статус новости.'; }
        }
        $_SESSION['notice'] = $noticeText;
        header('Location: index.php?tab='.urlencode($tab));
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($newFiles as $path) {
            @unlink(str_starts_with($path, 'uploads/news/') ? news_image_file($path) : dirname(__DIR__).'/'.$path);
        }
        $error = $ex instanceof RuntimeException ? $ex->getMessage() : 'Не удалось сохранить изменения.';
    }
}

// Load view data only after authentication and request handling.
if ($logged) {
    news_schema($pdo);
    $newsItems = $pdo->query('SELECT * FROM news ORDER BY published_date DESC,id DESC')->fetchAll();
    $competitions = $pdo->query('SELECT * FROM competitions ORDER BY event_date DESC')->fetchAll();
    $schedules = $pdo->query('SELECT s.*,c.name coach FROM schedules s LEFT JOIN coaches c ON c.id=s.coach_id ORDER BY s.id DESC')->fetchAll();
    $coaches = $pdo->query('SELECT * FROM coaches ORDER BY name')->fetchAll();
    $albums = $pdo->query('SELECT * FROM albums ORDER BY album_date DESC')->fetchAll();
    $photos = $pdo->query('SELECT p.*,a.title album_title FROM photos p JOIN albums a ON a.id=p.album_id ORDER BY p.id DESC')->fetchAll();
    $legal = $pdo->query('SELECT * FROM legal_sections ORDER BY id')->fetchAll();
    $legalTitles = require __DIR__.'/../includes/legal_navigation.php';
    foreach ($legal as &$legalRow) {
        if (isset($legalTitles[$legalRow['slug']])) $legalRow['title'] = $legalTitles[$legalRow['slug']];
    }
    unset($legalRow);
    ensure_upload_names($pdo);
    $uploadNames = $pdo->query('SELECT path, original_name FROM upload_names')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function adminDelete(string $table, int $id, string $tab, string $csrf): void
{
    ?>
    <form method="post" class="inline" onsubmit="return confirm('Удалить запись?')">
        <input type="hidden" name="csrf" value="<?=h($csrf)?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="table" value="<?=h($table)?>">
        <input type="hidden" name="id" value="<?=$id?>">
        <input type="hidden" name="tab" value="<?=h($tab)?>">
        <button class="rounded bg-red-950 px-3 py-1 text-xs text-red-300">Удалить</button>
    </form>
    <?php
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $logged ? 'Админ-панель' : 'Вход' ?> — СШОР № 4</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#0f0f0f;color:#f5f5f5;font-family:system-ui,sans-serif}.field{width:100%;border:1px solid #333;border-radius:.5rem;background:#0b0b0b;padding:.65rem;color:white}label{font-size:.85rem;color:#d1d5db}.card{background:#1a1a1a;border:1px solid #2b2b2b;border-radius:1rem;padding:1.25rem}</style>
    <link rel="stylesheet" href="../theme.css?v=<?=h((string) filemtime(__DIR__.'/../theme.css'))?>">
</head>
<body>
<?php if (!$logged): ?>
<main class="grid min-h-screen place-items-center p-4">
    <form method="post" class="w-full max-w-md rounded-2xl border border-white/10 bg-neutral-900 p-8">
        <a href="../index.php" class="text-sm text-red-400">← На сайт</a>
        <h1 class="mt-5 text-3xl font-black">Панель управления</h1>
        <p class="mt-2 text-gray-400">МБУ ДО «СШОР № 4»</p>
        <?php if ($error): ?><p class="mt-5 rounded-lg bg-red-950 p-3 text-red-200"><?=h($error)?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?=h($csrf)?>">
        <input type="hidden" name="action" value="login">
        <label class="mt-6 block text-sm">Логин<input name="username" autocomplete="username" required class="field mt-2"></label>
        <label class="mt-4 block text-sm">Пароль<input type="password" name="password" autocomplete="current-password" required class="field mt-2"></label>
        <button class="mt-6 w-full rounded-lg bg-red-600 p-3 font-bold">Войти</button>
    </form>
</main>
<?php else: ?>
<header class="border-b border-white/10 bg-black"><div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5"><div><a href="../index.php" class="text-sm text-red-400">← На сайт</a><h1 class="mt-1 text-2xl font-black">Управление сайтом</h1></div><a href="?logout=1" class="rounded-lg border border-white/20 px-4 py-2 text-sm">Выйти</a></div></header>
<main class="mx-auto max-w-7xl px-4 py-8">
<?php if ($error): ?><div class="mb-5 rounded-xl bg-red-950 p-4 text-red-200"><?=h($error)?></div><?php elseif ($notice): ?><div class="mb-5 rounded-xl bg-green-950 p-4 text-green-200"><?=h($notice)?></div><?php endif; ?>
<nav class="mb-8 flex flex-wrap gap-2"><?php foreach ($tabs as $key => $label): ?><a class="rounded-lg px-4 py-2 <?=$tab === $key ? 'bg-red-600' : 'bg-neutral-800 hover:bg-neutral-700'?>" href="?tab=<?=h($key)?>"><?=h($label)?></a><?php endforeach; ?></nav>
<?php if ($tab === 'news'): define('ADMIN_PAGE_ACTIVE', true); require __DIR__.'/news-form.php'; else: ?>

<?php if($tab==='competitions'):?><div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]"><form method="post" enctype="multipart/form-data" class="card space-y-4"><h2 class="text-xl font-bold">Добавить соревнование</h2><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="competition"><input type="hidden" name="tab" value="competitions"><label class="block">Название<input class="field mt-1" name="title" required></label><label class="block">Дата<input class="field mt-1" type="date" name="event_date" required></label><label class="block">Место<input class="field mt-1" name="place" required></label><label class="block">Возрастная категория<input class="field mt-1" name="age_group"></label><label class="block">Статус<select class="field mt-1" name="status"><option value="upcoming">Предстоящее</option><option value="completed">Завершено</option></select></label><label class="block">Положение / результаты (PDF, DOC, DOCX)<input class="field mt-1" type="file" name="document" accept=".pdf,.doc,.docx"></label><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Добавить</button></form><div class="space-y-3"><h2 class="text-xl font-bold">Соревнования (<?=$competitions?count($competitions):0?>)</h2><?php foreach($competitions as $r):?><details class="card"><summary class="cursor-pointer font-bold"><?=h($r['title'])?> <span class="text-sm text-gray-400">· <?=h($r['event_date'])?></span></summary><form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="competition"><input type="hidden" name="tab" value="competitions"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="old_document" value="<?=h($r['document'])?>"><label>Название<input class="field mt-1" name="title" value="<?=h($r['title'])?>" required></label><label>Дата<input class="field mt-1" type="date" name="event_date" value="<?=h($r['event_date'])?>" required></label><label>Место<input class="field mt-1" name="place" value="<?=h($r['place'])?>" required></label><label>Возраст<input class="field mt-1" name="age_group" value="<?=h($r['age_group'])?>"></label><label>Статус<select class="field mt-1" name="status"><option value="upcoming" <?=$r['status']==='upcoming'?'selected':''?>>Предстоящее</option><option value="completed" <?=$r['status']==='completed'?'selected':''?>>Завершено</option></select></label><label>Новый файл<input class="field mt-1" type="file" name="document" accept=".pdf,.doc,.docx"></label><div class="col-span-full flex items-center gap-3"><button class="rounded bg-neutral-700 px-4 py-2">Сохранить</button></div></form><?php adminDelete('competitions',(int)$r['id'],'competitions',$csrf)?></details><?php endforeach;?></div></div>
<?php elseif($tab==='schedule'):?><div class="grid gap-6 lg:grid-cols-[.8fr_1.2fr]"><form method="post" class="card space-y-4"><h2 class="text-xl font-bold">Добавить тренировку</h2><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="schedule"><input type="hidden" name="tab" value="schedule"><label class="block">День<select class="field mt-1" name="weekday"><?php foreach($days as $d):?><option><?=h($d)?></option><?php endforeach;?></select></label><label class="block">Время<input class="field mt-1" name="time" placeholder="16:00–17:30" required></label><label class="block">Группа<input class="field mt-1" name="group_name" placeholder="НП-1 / УТГ / ССМ" required></label><label class="block">Дисциплина<input class="field mt-1" name="discipline" required></label><label class="block">Тренер<select class="field mt-1" name="coach_id"><option value="">Не выбран</option><?php foreach($coaches as $c):?><option value="<?=$c['id']?>"><?=h($c['name'])?></option><?php endforeach;?></select></label><label class="block">Зал / площадка<input class="field mt-1" name="venue" required></label><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Добавить</button></form><div class="space-y-3"><h2 class="text-xl font-bold">Занятия</h2><?php foreach($schedules as $r):?><details class="card"><summary class="cursor-pointer font-bold"><?=h($r['weekday'])?> · <?=h($r['time'])?> · <?=h($r['group_name'])?></summary><form method="post" class="mt-4 grid gap-3 sm:grid-cols-2"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="schedule"><input type="hidden" name="tab" value="schedule"><input type="hidden" name="id" value="<?=$r['id']?>"><label>День<select class="field mt-1" name="weekday"><?php foreach($days as $d):?><option <?=$d===$r['weekday']?'selected':''?>><?=h($d)?></option><?php endforeach;?></select></label><label>Время<input class="field mt-1" name="time" value="<?=h($r['time'])?>"></label><label>Группа<input class="field mt-1" name="group_name" value="<?=h($r['group_name'])?>"></label><label>Дисциплина<input class="field mt-1" name="discipline" value="<?=h($r['discipline'])?>"></label><label>Тренер<select class="field mt-1" name="coach_id"><option value="">Не выбран</option><?php foreach($coaches as $c):?><option value="<?=$c['id']?>" <?=((int)$r['coach_id']===(int)$c['id']?'selected':'')?>><?=h($c['name'])?></option><?php endforeach;?></select></label><label>Зал<input class="field mt-1" name="venue" value="<?=h($r['venue'])?>"></label><div class="col-span-full flex gap-3"><button class="rounded bg-neutral-700 px-4 py-2">Сохранить</button></div></form><?php adminDelete('schedules',(int)$r['id'],'schedule',$csrf)?></details><?php endforeach;?></div></div>
<?php elseif($tab==='coaches'):?><?php define('ADMIN_PAGE_ACTIVE', true); require __DIR__.'/coaches-form.php'; ?>
<?php elseif($tab==='gallery'):?><div class="grid gap-6 lg:grid-cols-[.8fr_1.2fr]"><form method="post" enctype="multipart/form-data" class="card space-y-4"><h2 class="text-xl font-bold">Создать альбом / добавить фото</h2><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="album"><input type="hidden" name="tab" value="gallery"><label class="block">Название альбома<input class="field mt-1" name="title" required></label><label class="block">Дата<input class="field mt-1" type="date" name="album_date" required></label><label class="block">Обложка<input class="field mt-1" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><label class="block">Фотографии (можно выбрать несколько)<input class="field mt-1" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Создать альбом</button></form><div class="space-y-3"><h2 class="text-xl font-bold">Альбомы</h2><?php foreach($albums as $r):?><details class="card"><summary class="cursor-pointer font-bold"><?=h($r['title'])?> <span class="text-sm text-gray-400">· <?=h($r['album_date'])?></span></summary><form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="album"><input type="hidden" name="tab" value="gallery"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="old_cover" value="<?=h($r['cover'])?>"><label>Название<input class="field mt-1" name="title" value="<?=h($r['title'])?>" required></label><label>Дата<input class="field mt-1" type="date" name="album_date" value="<?=h($r['album_date'])?>" required></label><label>Новая обложка<input class="field mt-1" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><label>Добавить фото<input class="field mt-1" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><div class="col-span-full flex gap-3"><button class="rounded bg-neutral-700 px-4 py-2">Сохранить</button></div></form><?php adminDelete('albums',(int)$r['id'],'gallery',$csrf)?></details><?php endforeach;?><?php foreach($photos as $p):?><div class="card flex items-center justify-between gap-3 text-sm"><span><?=h($p['album_title'])?> · <?=h(basename($p['path']))?></span><?php adminDelete('photos',(int)$p['id'],'gallery',$csrf)?></div><?php endforeach;?></div></div>
<?php elseif($tab==='legal'):?><?php define('ADMIN_PAGE_ACTIVE', true); require __DIR__.'/legal-form.php'; ?><?php endif;?>
<?php endif; ?>
</main>
<?php if ($tab === 'legal'): ?><script src="legal-editor.js" defer></script><?php endif; ?>
<footer class="mt-12 border-t border-white/10 py-5 text-center text-xs text-gray-500">Администрирование сайта · СШОР № 4 по легкой атлетике</footer>
<?php endif; ?>
</body>
</html>

