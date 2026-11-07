<?php
if (!defined('ADMIN_PAGE_ACTIVE')) { http_response_code(404); exit; }

function coachFields(array $r): void
{
    $fields = [
        'name' => 'ФИО', 'title' => 'Должность', 'specialization' => 'Преподаваемые дисциплины',
        'education' => 'Уровень профессионального образования', 'qualification' => 'Квалификационная категория',
        'academic_degree' => 'Ученая степень', 'academic_title' => 'Ученое звание',
        'experience_text' => 'Продолжительность опыта работы в профессиональной сфере',
    ];
    foreach ($fields as $key => $label): ?>
        <label><?=h($label)?><input class="field mt-1" name="<?=h($key)?>" value="<?=h((string) ($r[$key] ?? ''))?>" <?=$key === 'name' ? 'required' : ''?>></label>
    <?php endforeach;
    foreach (['professional_development' => 'Сведения о повышении квалификации', 'professional_retraining' => 'Сведения о профессиональной переподготовке', 'achievements' => 'Достижения'] as $key => $label): ?>
        <label class="sm:col-span-2"><?=h($label)?><textarea class="field mt-1" rows="3" name="<?=h($key)?>"><?=h((string) ($r[$key] ?? ''))?></textarea></label>
    <?php endforeach; ?>
    <div class="sm:col-span-2 rounded border border-white/10 p-3"><p class="font-bold">Фотография</p>
    <?php if (!empty($r['photo'])): ?><img class="mt-2 max-h-40 max-w-full rounded" src="../<?=h($r['photo'])?>" alt="Фото <?=h($r['name'])?>"><label class="mt-2 inline-flex items-center gap-2"><input type="checkbox" name="remove_photo" value="1"> Удалить фото</label><?php else: ?><p class="mt-2 text-sm text-gray-400">Фото не загружено.</p><?php endif; ?>
    <label class="mt-2 block">Загрузить фото (JPG, PNG, WEBP)<input class="field mt-1" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label></div>
    <label class="sm:col-span-2 inline-flex items-center gap-2"><input type="checkbox" name="visible" value="1" <?=($r['visible'] ?? 1) ? 'checked' : ''?>> Показывать карточку на сайте</label>
    <?php
}
?>
<div class="space-y-6">
<p class="text-sm text-gray-400">Карточки заполняются отдельно от таблицы XLSX в разделе «Педагогический состав». Скрытые тренеры сохранены для существующих записей расписания.</p>
<details class="card"><summary class="cursor-pointer text-xl font-bold">Добавить тренера</summary>
<form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="coach"><input type="hidden" name="tab" value="coaches">
<?php coachFields([]); ?><div class="sm:col-span-2"><button class="rounded-lg bg-red-600 px-5 py-3 font-bold">Добавить</button></div></form></details>
<div class="grid gap-4 md:grid-cols-2"><h2 class="text-xl font-bold md:col-span-2">Тренеры (<?=count($coaches)?>)</h2>
<?php foreach ($coaches as $r): ?><details class="card"><summary class="cursor-pointer font-bold"><?=h($r['name'])?> <span class="text-sm text-gray-400">· <?=h($r['title'])?><?=empty($r['visible']) ? ' · скрыт' : ''?></span></summary>
<form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-2">
<input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="action" value="coach"><input type="hidden" name="tab" value="coaches"><input type="hidden" name="id" value="<?=(int) $r['id']?>">
<?php coachFields($r); ?><div class="sm:col-span-2"><button class="rounded bg-red-600 px-4 py-2 font-bold">Сохранить</button></div></form>
<div class="mt-3"><?php adminDelete('coaches', (int) $r['id'], 'coaches', $csrf); ?></div></details><?php endforeach; ?></div></div>

