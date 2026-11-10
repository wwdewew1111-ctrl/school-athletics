<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/athletes.php';
$pdo = db();
coach_columns($pdo);
athlete_schema($pdo);
$coachId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM coaches WHERE id=? AND visible=1');
    $stmt->execute([$coachId ?: 0]);
    $coach = $stmt->fetch();
    if (!$coach) http_response_code(404);
    $rows = $coach ? [$coach] : [];
} else {
    $rows = $pdo->query('SELECT * FROM coaches WHERE visible=1 ORDER BY name')->fetchAll();
}
$athletesByCoach = [];
if ($rows) {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT id,coach_id,name FROM athletes WHERE coach_id=? ORDER BY name');
        $stmt->execute([$coachId]);
    } else {
        $stmt = $pdo->query('SELECT a.id,a.coach_id,a.name FROM athletes a JOIN coaches c ON c.id=a.coach_id WHERE c.visible=1 ORDER BY a.name');
    }
    foreach ($stmt as $athlete) $athletesByCoach[(int) $athlete['coach_id']][] = $athlete;
}
$pageTitle = 'Тренеры';
require __DIR__.'/includes/header.php';
?>
<section class="mx-auto max-w-7xl px-4 py-14">
<p class="font-bold uppercase tracking-widest text-red-500">Команда школы</p>
<h1 class="mt-2 text-4xl font-black"><?=isset($_GET['id']) ? 'Карточка тренера' : 'Тренерский состав'?></h1>
<?php if (isset($_GET['id'])): ?><a class="mt-4 inline-block text-red-400" href="coaches.php">← Все тренеры</a><?php endif; ?>
<p class="mt-3 text-gray-400"><?=isset($_GET['id']) ? 'Информация о тренере школы.' : 'Тренеры-преподаватели школы олимпийского резерва.'?></p>
<div class="mt-9 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($rows as $c): ?>
<article class="panel coach-card overflow-hidden rounded-2xl border border-white/5">
<div class="coach-photo grid h-64 place-items-center bg-gradient-to-br from-neutral-800 to-red-950">
<?php if ($c['photo']): ?><img class="h-full w-full object-cover" src="<?=h($c['photo'])?>" alt="<?=h($c['name'])?>" loading="lazy"><?php else: ?><span class="text-7xl text-white/20" aria-hidden="true"><i class="fa-solid fa-person-running"></i></span><?php endif; ?>
</div>
<div class="coach-info p-5"><h2 class="text-xl font-bold"><?=h($c['name'])?></h2>
<p class="coach-title mt-2 text-sm font-semibold text-red-400"><?=h($c['title'])?></p>
<?php if ($c['specialization'] !== ''): ?><p class="mt-2 text-sm text-gray-300"><?=h($c['specialization'])?></p><?php endif; ?>
<?php if ($c['experience_text'] !== ''): ?><p class="mt-3 text-sm text-gray-400">Опыт работы: <?=h($c['experience_text'])?></p><?php elseif ((int) $c['experience'] > 0): ?><p class="mt-3 text-sm text-gray-400">Опыт работы: <?=h((string) $c['experience'])?> лет</p><?php endif; ?>
<?php if (!empty($athletesByCoach[(int) $c['id']])): ?>
<div class="mt-4 border-t border-white/10 pt-4"><h3 class="font-semibold">Спортсмены</h3><ul class="mt-2 space-y-1 text-sm text-gray-300">
<?php foreach ($athletesByCoach[(int) $c['id']] as $athlete): ?><li><a class="font-semibold text-red-400 underline" href="athletes.php?id=<?=(int) $athlete['id']?>"><?=h($athlete['name'])?></a></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>
<details class="mt-4 border-t border-white/10 pt-4"><summary class="cursor-pointer font-semibold text-red-400">Подробнее</summary>
<dl class="mt-4 space-y-3 text-sm">
<?php foreach (['education' => 'Образование', 'qualification' => 'Квалификационная категория', 'professional_development' => 'Повышение квалификации', 'professional_retraining' => 'Профессиональная переподготовка', 'achievements' => 'Достижения'] as $field => $label): ?>
<?php if (trim((string) $c[$field]) !== ''): ?><div><dt class="text-gray-400"><?=h($label)?></dt><dd class="mt-1 whitespace-pre-wrap text-gray-200"><?=h($c[$field])?></dd></div><?php endif; ?>
<?php endforeach; ?>
</dl></details></div></article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-gray-400"><?=isset($_GET['id']) ? 'Тренер не найден.' : 'Информация о тренерах появится позже.'?></p><?php endif; ?>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
