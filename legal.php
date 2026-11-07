<?php
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/upload_names.php';
$pdo = db();
$sections = $pdo->query('SELECT * FROM legal_sections ORDER BY id')->fetchAll();
$navigationTitles = require __DIR__.'/includes/legal_navigation.php';
$sectionsBySlug = [];
foreach ($sections as $section) $sectionsBySlug[$section['slug']] = $section;
$navigation = [];
foreach ($navigationTitles as $key => $title) {
    if (isset($sectionsBySlug[$key])) {
        $navigation[] = ['slug' => $key, 'title' => $title];
    }
}
$slug = (string) ($_GET['section'] ?? 'main');
$current = null;
$educationProgram = null;
foreach ($sections as $r) {
    if ($r['slug'] === $slug) $current = $r;
    if ($r['slug'] === 'education-program') $educationProgram = $r;
}
if (!$current) $current = $sections[0] ?? ['slug' => '', 'title' => 'Сведения', 'content' => '', 'document' => ''];
if (isset($navigationTitles[$current['slug']])) $current['title'] = $navigationTitles[$current['slug']];
$content = (string) $current['content'];
if ($current['slug'] === 'facilities' && isset($sectionsBySlug['accessibility'])) {
    $accessibility = $sectionsBySlug['accessibility'];
    if (trim((string) $accessibility['content']) !== '' || $accessibility['document'] !== '') {
        $content .= '<h3>Доступная среда</h3>'.(string) $accessibility['content'];
        if ($accessibility['document'] !== '') {
            $content .= '<p><a href="'.h(file_view_url($accessibility['document'])).'" target="_blank" rel="noopener noreferrer">Открыть документ о доступной среде</a></p>';
        }
    }
}
if ($current['slug'] === 'education' && $educationProgram) {
    // Keep the educational-program document links unchanged; only expand the internal details link.
    $pattern = '~<p\b[^>]*>\s*<a\b[^>]*\bhref\s*=\s*(["\'])legal\.php\?section=education-program\1[^>]*>\s*(?:смотреть\s+)?подробнее\s*</a>\s*</p>~iu';
    $content = preg_replace_callback($pattern, static fn (): string => (string) $educationProgram['content'], $content, 1) ?? $content;
}
$pageTitle = $current['title'];
require __DIR__.'/includes/header.php';
?><style>.prose-school{white-space:pre-wrap}</style><section class="mx-auto max-w-7xl px-4 py-14"><p class="font-bold uppercase tracking-widest text-red-500">Открытая информация</p><h1 class="mt-2 text-4xl font-black">Сведения об образовательной организации</h1><div class="mt-9 grid gap-8 lg:grid-cols-[290px_1fr]"><nav class="panel h-fit rounded-2xl p-3" aria-label="Юридические разделы"><?php foreach($navigation as $item):?><a class="mb-1 block rounded-lg px-4 py-3 text-sm <?=($item['slug']===$current['slug']?'bg-red-700 font-bold':'text-gray-300 hover:bg-white/5')?>" href="legal.php?section=<?=h($item['slug'])?>"><?=h($item['title'])?></a><?php endforeach;?></nav><article class="panel rounded-2xl p-6 sm:p-9"><h2 class="text-2xl font-black"><?=h($current['title'])?></h2><div class="prose-school mt-5 leading-relaxed"><?=$content?></div><?php if($current['document']):?><a class="mt-6 inline-flex items-center gap-2 rounded-lg bg-sport px-4 py-3 font-bold" href="<?=h(file_view_url($current['document']))?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-arrow-down"></i>Открыть документ</a><?php endif;?></article></div></section><?php require __DIR__.'/includes/footer.php';

