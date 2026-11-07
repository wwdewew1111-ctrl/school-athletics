<?php
require_once __DIR__.'/includes/db.php';
$pdo = db();
coach_columns($pdo);
$rows = $pdo->query('SELECT * FROM coaches WHERE visible=1 ORDER BY name')->fetchAll();
$pageTitle = 'Тренеры';
require __DIR__.'/includes/header.php';
?>
<section class="mx-auto max-w-7xl px-4 py-14">
<p class="font-bold uppercase tracking-widest text-red-500">Команда школы</p>
<h1 class="mt-2 text-4xl font-black">Тренерский состав</h1>
<p class="mt-3 text-gray-400">Тренеры-преподаватели школы олимпийского резерва.</p>
<div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($rows as $c): ?>
<article class="panel coach-card overflow-hidden rounded-2xl border border-white/5">
<div class="coach-photo grid h-64 place-items-center bg-gradient-to-br from-neutral-800 to-red-950">
<?php if ($c['photo']): ?><img class="h-full w-full object-cover" src="<?=h($c['photo'])?>" alt="<?=h($c['name'])?>"><?php else: ?><span class="text-7xl text-white/20" aria-hidden="true"><i class="fa-solid fa-person-running"></i></span><?php endif; ?>
</div>
<div class="coach-info p-5"><h2 class="text-xl font-bold"><?=h($c['name'])?></h2>
<p class="coach-title mt-2 text-sm font-semibold text-red-400"><?=h($c['title'])?></p>
<?php if ($c['specialization'] !== ''): ?><p class="mt-2 text-sm text-gray-300"><?=h($c['specialization'])?></p><?php endif; ?>
<?php if ($c['experience_text'] !== ''): ?><p class="mt-3 text-sm text-gray-400">Опыт работы: <?=h($c['experience_text'])?></p><?php elseif ((int) $c['experience'] > 0): ?><p class="mt-3 text-sm text-gray-400">Опыт работы: <?=h((string) $c['experience'])?> лет</p><?php endif; ?>
<details class="mt-4 border-t border-white/10 pt-4"><summary class="cursor-pointer font-semibold text-red-400">Подробнее</summary>
<dl class="mt-4 space-y-3 text-sm">
<?php foreach (['education' => 'Образование', 'qualification' => 'Квалификационная категория', 'academic_degree' => 'Ученая степень', 'academic_title' => 'Ученое звание', 'professional_development' => 'Повышение квалификации', 'professional_retraining' => 'Профессиональная переподготовка', 'achievements' => 'Достижения'] as $field => $label): ?>
<?php if (trim((string) $c[$field]) !== ''): ?><div><dt class="text-gray-400"><?=h($label)?></dt><dd class="mt-1 whitespace-pre-wrap text-gray-200"><?=h($c[$field])?></dd></div><?php endif; ?>
<?php endforeach; ?>
</dl></details></div></article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-gray-400">Информация о тренерах появится позже.</p><?php endif; ?>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
