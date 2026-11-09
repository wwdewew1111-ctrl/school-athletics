<?php
if (!defined('ADMIN_PAGE_ACTIVE')) { http_response_code(404); exit; }

function athleteFields(array $row, array $coaches): void
{
    ?>
    <label>ФИО спортсмена<input class="field mt-1" name="name" value="<?=h((string) ($row['name'] ?? ''))?>" required maxlength="200"></label>
    <label>Тренер<select class="field mt-1" name="coach_id" required>
        <option value="">Выберите тренера</option>
        <?php foreach ($coaches as $coach): if (empty($coach['visible'])) continue; ?>
        <option value="<?=(int) $coach['id']?>" <?=((int) ($row['coach_id'] ?? 0) === (int) $coach['id']) ? 'selected' : ''?>><?=h($coach['name'])?></option>
        <?php endforeach; ?>
    </select></label>
    <label>Дата рождения<input class="field mt-1" type="date" name="birth_date" value="<?=h((string) ($row['birth_date'] ?? ''))?>"></label>
    <label>Спортивное звание<input class="field mt-1" name="sports_title" value="<?=h((string) ($row['sports_title'] ?? ''))?>" maxlength="200"></label>
    <label>Дата поступления в школу<input class="field mt-1" type="date" name="school_entry_date" value="<?=h((string) ($row['school_entry_date'] ?? ''))?>"></label>
    <label class="sm:col-span-2">Достижения<textarea class="field mt-1" rows="4" name="achievements" maxlength="20000"><?=h((string) ($row['achievements'] ?? ''))?></textarea></label>
    <div class="sm:col-span-2 rounded border border-white/10 p-3"><p class="font-bold">Фотография</p>
    <?php if (!empty($row['photo'])): ?><img class="mt-2 max-h-40 max-w-full rounded" src="../<?=h($row['photo'])?>" alt="Фото <?=h($row['name'])?>"><label class="mt-2 inline-flex items-center gap-2"><input type="checkbox" name="remove_photo" value="1"> Удалить фото</label><?php else: ?><p class="mt-2 text-sm text-gray-400">Фото не загружено.</p><?php endif; ?>
    <label class="mt-2 block">Загрузить фото (JPEG, PNG, WebP, до 10 МБ)<input class="field mt-1" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div>
    <?php
}
?>
<div class="space-y-6">
<p class="text-sm text-gray-400">Выберите тренера из опубликованных карточек. Чтобы скрыть или удалить тренера, сначала переназначьте его спортсменов.</p>
<details class="card"><summary class="cursor-pointer text-xl font-bold">Добавить спортсмена</summary>
<form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="athlete"><input type="hidden" name="tab" value="athletes">
<?php athleteFields([], $coaches); ?><div class="sm:col-span-2"><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Добавить</button></div></form></details>
<div class="grid gap-4 md:grid-cols-2"><h2 class="text-xl font-bold md:col-span-2">Спортсмены (<?=count($athletes)?>)</h2>
<?php foreach ($athletes as $row): ?><details class="card"><summary class="cursor-pointer font-bold"><?=h($row['name'])?> <span class="text-sm text-gray-400">· <?=h($row['coach_name'])?></span></summary>
<form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="athlete"><input type="hidden" name="tab" value="athletes"><input type="hidden" name="id" value="<?=(int) $row['id']?>">
<?php athleteFields($row, $coaches); ?><div class="sm:col-span-2"><button class="rounded bg-red-600 px-4 py-2 font-bold">Сохранить</button></div></form>
<div class="mt-3"><?php adminDelete('athletes', (int) $row['id'], 'athletes', $csrf); ?></div></details><?php endforeach; ?></div></div>
