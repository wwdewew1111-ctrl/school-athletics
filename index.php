<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/news.php';
require_once __DIR__.'/includes/hero.php';
$pdo = db();
news_schema($pdo);
$pageTitle = 'Главная';
$heroBackground = hero_background($pdo);
$next = $pdo->query("SELECT * FROM competitions WHERE event_date>=date('now') ORDER BY event_date LIMIT 3")->fetchAll();
$newsItems = $pdo->query('SELECT * FROM news WHERE published=1 ORDER BY published_date DESC,id DESC LIMIT 3')->fetchAll();
require __DIR__.'/includes/header.php';
?>
<section class="hero-section<?php if ($heroBackground): ?> hero-section--photo<?php endif; ?>">
    <?php if ($heroBackground): ?><div class="hero-photo" style="background-image: url('<?=h($heroBackground)?>')" aria-hidden="true"></div><?php endif; ?>
    <div class="hero-inner">
        <div class="hero-card">
            <p class="hero-eyebrow">Владимир · лёгкая атлетика</p>
            <h1>Сильнее сегодня.<br><span>Быстрее завтра.</span></h1>
            <p class="hero-description"><?=h(ORG_NAME)?> — место, где растут характер, сила и чемпионские результаты.</p>
            <div class="hero-actions">
                <a class="hero-button hero-button--primary" href="legal.php?section=admission">Приём в школу <span aria-hidden="true">→</span></a>
                <a class="hero-button hero-button--secondary" href="schedule.php">Расписание тренировок</a>
            </div>
            <a class="hero-more" href="/sveden/common">Сведения об образовательной организации <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-16"><div class="mb-8 flex items-end justify-between"><div><p class="font-bold uppercase tracking-widest text-red-500">На старт</p><h2 class="mt-2 text-3xl font-black">Ближайшие соревнования</h2></div><a href="competitions.php" class="text-sm text-red-400 hover:text-red-300">Все соревнования →</a></div><div class="grid gap-4 md:grid-cols-3"><?php if (!$next): ?><p class="text-gray-400">Ближайшие старты скоро появятся в календаре.</p><?php endif; foreach ($next as $c): ?><article class="panel rounded-2xl border border-white/5 p-6"><p class="text-sm font-bold text-red-400"><?=h(date('d.m.Y', strtotime($c['event_date'])))?></p><h3 class="mt-3 text-xl font-bold"><?=h($c['title'])?></h3><p class="mt-3 text-sm text-gray-400"><i class="fa-solid fa-location-dot mr-2"></i><?=h($c['place'])?></p><p class="mt-2 text-sm text-gray-400"><?=h($c['age_group'])?></p></article><?php endforeach; ?></div></section>
<section class="bg-neutral-900"><div class="mx-auto max-w-7xl px-4 py-16"><div class="mb-8 flex items-end justify-between gap-4"><div><p class="font-bold uppercase tracking-widest text-red-500">События школы</p><h2 class="mt-2 text-3xl font-black">Новости</h2></div><a href="news.php" class="text-sm text-red-400 hover:text-red-300">Все новости →</a></div><div class="news-grid"><?php if (!$newsItems): ?><p class="text-gray-400">Здесь будут появляться новости школы, результаты соревнований и объявления.</p><?php endif; foreach ($newsItems as $item): ?><article class="news-card"><?php if ($item['image']): ?><a class="news-card__image" href="news.php?id=<?=(int)$item['id']?>" tabindex="-1" aria-hidden="true"><img src="news-image.php?path=<?=rawurlencode($item['image'])?>" alt="" loading="lazy"></a><?php else: ?><div class="news-card__placeholder" aria-hidden="true"><span>Новости школы</span></div><?php endif; ?><div class="news-card__content"><time class="news-card__date" datetime="<?=h($item['published_date'])?>"><?=h(date('d.m.Y', strtotime($item['published_date'])))?></time><h3 class="news-card__title"><a href="news.php?id=<?=(int)$item['id']?>"><?=h($item['title'])?></a></h3><p class="news-card__excerpt"><?=h(news_excerpt($item['body']))?></p><a class="news-card__more" href="news.php?id=<?=(int)$item['id']?>" aria-label="<?=h('Читать новость: '.$item['title'])?>">Читать новость <span aria-hidden="true">→</span></a></div></article><?php endforeach; ?></div></div></section>
<section class="mx-auto max-w-7xl px-4 py-16"><h2 class="text-3xl font-black">Полезная информация</h2><div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><?php foreach ([['Сведения об образовательной организации','legal.php'],['Противодействие коррупции','legal.php?section=anticorruption'],['Дистанционное обучение','legal.php?section=distance'],['История школы','legal.php?section=history']] as [$t, $u]): ?><a class="panel rounded-xl p-5 hover:ring-1 hover:ring-red-500" href="<?=h($u)?>"><i class="fa-solid fa-arrow-right mr-2 text-red-500"></i><?=h($t)?></a><?php endforeach; ?></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
