<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/athletes.php';
$pdo = db();
coach_columns($pdo);
athlete_schema($pdo);
if (isset($_GET['id'])) {
    $athleteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stmt = $pdo->prepare('SELECT a.*,c.name coach_name FROM athletes a JOIN coaches c ON c.id=a.coach_id WHERE a.id=? AND c.visible=1');
    $stmt->execute([$athleteId ?: 0]);
    $athlete = $stmt->fetch();
    if (!$athlete) http_response_code(404);
    $rows = $athlete ? [$athlete] : [];
} else {
    $rows = $pdo->query('SELECT a.*,c.name coach_name FROM athletes a JOIN coaches c ON c.id=a.coach_id WHERE c.visible=1 ORDER BY a.name')->fetchAll();
}
$pageTitle = 'Спортсмены';
require __DIR__.'/includes/header.php';
?>
<section class="mx-auto max-w-7xl px-4 py-14">
<p class="font-bold uppercase tracking-widest text-red-500">Команда школы</p>
<h1 class="mt-2 text-4xl font-black"><?=isset($_GET['id']) ? 'Карточка спортсмена' : 'Спортсмены'?></h1>
<?php if (isset($_GET['id'])): ?><a class="mt-4 inline-block text-red-400" href="athletes.php">← Все спортсмены</a><?php endif; ?>
<p class="mt-3 text-gray-400"><?=isset($_GET['id']) ? 'Информация о спортсмене школы.' : 'Спортсмены школы и их достижения.'?></p>
<div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($rows as $athlete): ?>
<article class="panel coach-card overflow-hidden rounded-2xl border border-white/5">
<div class="coach-photo grid h-64 place-items-center bg-gradient-to-br from-neutral-800 to-red-950">
<?php if ($athlete['photo']): ?><img class="h-full w-full object-cover" src="<?=h($athlete['photo'])?>" alt="<?=h($athlete['name'])?>" loading="lazy"><?php else: ?><span class="text-7xl text-white/20" aria-hidden="true"><i class="fa-solid fa-person-running"></i></span><?php endif; ?>
</div>
<div class="coach-info p-5"><h2 class="text-xl font-bold"><?=h($athlete['name'])?></h2>
<?php if ($athlete['birth_date'] !== ''): ?><p class="mt-3 text-sm text-gray-300">Дата рождения: <?=h(athlete_display_date($athlete['birth_date']))?></p><?php endif; ?>
<?php if ($athlete['sports_title'] !== ''): ?><p class="mt-3 text-sm text-gray-300">Спортивное звание: <?=h($athlete['sports_title'])?></p><?php endif; ?>
<?php if ($athlete['school_entry_date'] !== ''): ?><p class="mt-3 text-sm text-gray-300">Дата поступления в школу: <?=h(athlete_display_date($athlete['school_entry_date']))?></p><?php endif; ?>
<p class="mt-3 text-sm text-gray-300">Тренер: <a class="font-semibold text-red-400 underline" href="coaches.php?id=<?=(int) $athlete['coach_id']?>"><?=h($athlete['coach_name'])?></a></p>
<?php if (trim((string) $athlete['achievements']) !== ''): ?><h3 class="mt-4 font-semibold">Достижения</h3><p class="mt-2 whitespace-pre-wrap text-sm text-gray-300"><?=h($athlete['achievements'])?></p><?php endif; ?>
</div></article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-gray-400"><?=isset($_GET['id']) ? 'Спортсмен не найден.' : 'Информация о спортсменах появится позже.'?></p><?php endif; ?>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
