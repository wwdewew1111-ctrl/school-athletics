<?php
if (!defined('ADMIN_PAGE_ACTIVE')) { http_response_code(404); exit; }
?>
<form method="post" enctype="multipart/form-data" class="card max-w-3xl space-y-4">
    <h2 class="text-xl font-bold">Фон первого блока главной страницы</h2>
    <p class="text-sm text-gray-400">Загрузите одну фотографию JPEG, PNG или WebP размером до 10 МБ. Лучше подойдёт горизонтальное фото: края могут обрезаться на разных экранах.</p>
    <input type="hidden" name="csrf" value="<?=h($csrf)?>">
    <input type="hidden" name="action" value="hero_background">
    <input type="hidden" name="tab" value="hero">
    <?php if ($heroBackground): ?>
        <p class="font-bold">Текущая фотография</p>
        <img class="max-h-64 rounded-xl" src="../<?=h($heroBackground)?>" alt="Текущий фон главной страницы">
        <label class="flex items-center gap-2"><input type="checkbox" name="remove_background" value="1"> Убрать фотографию и вернуть обычный фон</label>
    <?php else: ?>
        <p class="text-sm text-gray-400">Фотография не установлена. Используется обычный фон.</p>
    <?php endif; ?>
    <label class="block"><?= $heroBackground ? 'Заменить фотографию' : 'Выбрать фотографию' ?><input class="field mt-1" type="file" name="background" accept="image/jpeg,image/png,image/webp"></label>
    <button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Сохранить</button>
</form>
