<?php
if (!defined('ADMIN_PAGE_ACTIVE')) { http_response_code(404); exit; }
function newsFields(array $row): void {
    ?>
    <label class="block">Заголовок<input class="field mt-1" name="title" maxlength="200" required value="<?=h($row['title'] ?? '')?>"></label>
    <label class="block">Текст<textarea class="field mt-1" name="body" rows="7" required><?=h($row['body'] ?? '')?></textarea></label>
    <label class="block">Дата публикации<input class="field mt-1" type="date" name="published_date" required value="<?=h($row['published_date'] ?? date('Y-m-d'))?>"></label>
    <?php if (!empty($row['image'])): ?><p><img class="max-h-40 rounded" src="news-image.php?path=<?=rawurlencode($row['image'])?>" alt="Изображение новости"></p><label class="flex items-center gap-2"><input type="checkbox" name="remove_image" value="1"> Удалить изображение</label><?php endif; ?>
    <label class="block"><?=empty($row['image']) ? 'Изображение' : 'Заменить изображение'?> (JPEG или PNG, до 10 МБ)<input class="field mt-1" type="file" name="image" accept="image/jpeg,image/png"></label>
    <?php if (!empty($row['published'])): ?><input type="hidden" name="published" value="1"><p class="text-sm text-gray-400">Опубликовано на сайте.</p><?php else: ?><label class="flex items-center gap-2"><input type="checkbox" name="published" value="1"> Опубликовать на сайте и в соцсетях</label><?php endif; ?>
    <?php
}
?>
<details class="card mb-6"><summary class="cursor-pointer text-xl font-bold">Настройки публикации в VK и Telegram</summary>
<?php try { $socialSettings = social_config(); $socialSettingsError = ''; } catch (Throwable $ex) { $socialSettings = ['vk_token' => '', 'vk_group' => '', 'tg_token' => '', 'tg_chat' => '']; $socialSettingsError = 'Не удалось прочитать настройки соцсетей. Проверьте файл и права доступа.'; } ?>
<?php if ($socialSettingsError): ?><p class="mt-4 text-red-300"><?=h($socialSettingsError)?></p><?php else: ?>
<form method="post" class="mt-4 space-y-4" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="social_settings"><input type="hidden" name="tab" value="news">
<p class="text-sm text-gray-400">Настройки можно заполнить позднее. Токены после сохранения не показываются. Пустое поле токена оставляет текущий токен без изменений.</p>
<div class="grid gap-4 md:grid-cols-2">
<div class="space-y-3"><h3 class="font-bold">VK</h3>
<label class="block">Числовой ID сообщества без минуса<input class="field mt-1" name="vk_group" inputmode="numeric" maxlength="200" value="<?=h($socialSettings['vk_group'])?>"></label>
<label class="block">Токен VK (<?= $socialSettings['vk_token'] !== '' ? 'задан' : 'не задан' ?>)<input class="field mt-1" type="password" name="vk_token" maxlength="4096" autocomplete="new-password"></label>
<label class="flex items-center gap-2"><input type="checkbox" name="clear_vk_token" value="1"> Удалить токен VK</label></div>
<div class="space-y-3"><h3 class="font-bold">Telegram</h3>
<label class="block">ID канала или @username<input class="field mt-1" name="tg_chat" maxlength="200" value="<?=h($socialSettings['tg_chat'])?>"></label>
<label class="block">Токен бота (<?= $socialSettings['tg_token'] !== '' ? 'задан' : 'не задан' ?>)<input class="field mt-1" type="password" name="tg_token" maxlength="4096" autocomplete="new-password"></label>
<label class="flex items-center gap-2"><input type="checkbox" name="clear_tg_token" value="1"> Удалить токен Telegram</label></div></div>
<button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Сохранить настройки</button>
<p class="text-sm text-gray-400">Для старых новостей после настройки нажмите «Повторить синхронизацию». Для защиты токенов используйте HTTPS и надёжный пароль администратора.</p>
</form><?php endif; ?></details>
<div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
<form method="post" enctype="multipart/form-data" class="card space-y-4"><h2 class="text-xl font-bold">Добавить новость</h2>
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="news"><input type="hidden" name="tab" value="news">
<?php newsFields([]); ?><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Сохранить</button>
<p class="text-sm text-gray-400">Без отметки публикации новость останется черновиком. Дата — информационная, отложенная публикация не предусмотрена.</p></form>
<div class="space-y-3"><h2 class="text-xl font-bold">Новости (<?=count($newsItems)?>)</h2>
<?php foreach ($newsItems as $row): ?><details class="card"><summary class="cursor-pointer font-bold"><?=h($row['title'])?> <span class="text-sm text-gray-400">· <?=h($row['published_date'])?> · <?=$row['published'] ? 'опубликована' : 'черновик'?></span></summary>
<form method="post" enctype="multipart/form-data" class="mt-4 space-y-4">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="news"><input type="hidden" name="tab" value="news"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="revision" value="<?=h(hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)))?>">
<?php newsFields($row); ?><button class="rounded bg-red-600 px-4 py-2 font-bold">Сохранить</button></form>
<?php if ($row['published']): ?><div class="mt-4 space-y-1 text-sm text-gray-300"><p>VK: <?=h($row['vk_status'] ?: 'Ожидает синхронизации')?><?= $row['vk_post_id'] ? ' · ID '.h($row['vk_post_id']) : '' ?></p><p>Telegram: <?=h($row['tg_status'] ?: 'Ожидает синхронизации')?><?= $row['tg_message_id'] ? ' · ID '.h($row['tg_message_id']) : '' ?></p></div>
<form method="post" class="mt-3"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="news_sync"><input type="hidden" name="tab" value="news"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="rounded bg-neutral-700 px-3 py-2 text-sm">Повторить синхронизацию</button></form><?php endif; ?>
<form method="post" class="mt-3" onsubmit="return confirm('Удалить новость с сайта? Публикации в соцсетях останутся.')"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="news_delete"><input type="hidden" name="tab" value="news"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button class="rounded bg-red-950 px-3 py-1 text-xs text-red-300">Удалить с сайта</button></form></details><?php endforeach; ?></div></div>
