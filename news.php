<?php
declare(strict_types=1);
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/news.php';
$pdo = db();
news_schema($pdo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$item = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM news WHERE id=? AND published=1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) http_response_code(404);
}
$items = !$id ? $pdo->query('SELECT * FROM news WHERE published=1 ORDER BY published_date DESC,id DESC')->fetchAll() : [];
$pageTitle = $item ? $item['title'] : 'Новости';
require __DIR__.'/includes/header.php';
?>
<section class="mx-auto max-w-7xl px-4 py-14"><p class="font-bold uppercase tracking-widest text-red-500">События школы</p>
<?php if ($id && !$item): ?><h1 class="mt-2 text-3xl font-black">Новость не найдена</h1><a class="mt-5 inline-block text-red-400" href="news.php">← Все новости</a>
<?php elseif ($item): ?><a class="mt-4 inline-block text-red-400" href="news.php">← Все новости</a><article class="panel mt-6 max-w-4xl rounded-2xl p-6 sm:p-9"><p class="text-sm font-bold text-red-400"><?=h(date('d.m.Y', strtotime($item['published_date'])))?></p><h1 class="mt-3 text-3xl font-black"><?=h($item['title'])?></h1><?php if ($item['image']): ?><img class="mt-6 max-h-[40rem] w-full rounded-xl object-contain" src="news-image.php?path=<?=rawurlencode($item['image'])?>" alt=""><?php endif; ?><div class="mt-6 whitespace-pre-wrap leading-relaxed text-gray-300"><?=h($item['body'])?></div></article>
<?php else: ?><h1 class="mt-2 text-4xl font-black">Новости</h1><div class="news-grid mt-8"><?php if (!$items): ?><p class="text-gray-400">Новости скоро появятся.</p><?php endif; foreach ($items as $row): ?><article class="news-card"><?php if ($row['image']): ?><a class="news-card__image" href="news.php?id=<?=(int)$row['id']?>" tabindex="-1" aria-hidden="true"><img src="news-image.php?path=<?=rawurlencode($row['image'])?>" alt="" loading="lazy"></a><?php else: ?><div class="news-card__placeholder" aria-hidden="true"><span>Новости школы</span></div><?php endif; ?><div class="news-card__content"><time class="news-card__date" datetime="<?=h($row['published_date'])?>"><?=h(date('d.m.Y', strtotime($row['published_date'])))?></time><h2 class="news-card__title"><a href="news.php?id=<?=(int)$row['id']?>"><?=h($row['title'])?></a></h2><p class="news-card__excerpt"><?=h(news_excerpt($row['body']))?></p><a class="news-card__more" href="news.php?id=<?=(int)$row['id']?>" aria-label="<?=h('Читать новость: '.$row['title'])?>">Читать новость <span aria-hidden="true">→</span></a></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
