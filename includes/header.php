<?php
require_once __DIR__.'/db.php';
$pageTitle = $pageTitle ?? ORG_NAME;
$nav = [['Главная','index.php'],['Соревнования','competitions.php'],['Расписание','schedule.php'],['Тренеры','coaches.php'],['Спортсмены','athletes.php'],['Фотоальбом','gallery.php'],['Сведения об образовательной организации','/sveden/common']];
$themeUrl = 'theme.css?v='.(string) filemtime(__DIR__.'/../theme.css');
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
if (isset($route) && $route !== null) $currentPage = 'legal.php';
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php if (!empty($publicBasePath)): ?><base href="<?=h($publicBasePath)?>"><?php endif; ?>
    <meta name="theme-color" content="#a51a1a">
    <title><?=h($pageTitle)?> — СШОР № 4</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{sport:'#b91c1c',ink:'#20242b',panel:'#fff'}}}}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?=h($themeUrl)?>">
    <script>(()=>{try{const s=localStorage.getItem('vision-size');if(['16','19','22'].includes(s))document.documentElement.style.fontSize=(Number(s)/16*100)+'%';const c=localStorage.getItem('vision-color');if(['bw','wb','yellow'].includes(c))document.documentElement.classList.add('vision-'+c)}catch(e){}})();</script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержанию</a>
<header class="site-header sticky top-0 z-40">
    <div class="site-container">
        <div class="site-header-row">
            <a href="index.php" class="site-brand">
                <img class="brand-mark" src="assets/logo.jpg" width="425" height="600" alt="">
                <span class="brand-name"><?=h(ORG_NAME)?><small>Владимир</small></span>
            </a>
            <button id="menuBtn" class="menu-toggle" type="button" aria-label="Открыть меню" aria-controls="mobileNav" aria-expanded="false"><span aria-hidden="true">☰</span></button>
            <nav id="nav" class="desktop-nav" aria-label="Основная навигация">
                <?php foreach ($nav as [$label, $url]): $active = $currentPage === $url || ($url === 'index.php' && $currentPage === '') || ($url === '/sveden/common' && $currentPage === 'legal.php'); ?>
                <a href="<?=h($url)?>"<?=$active ? ' aria-current="page"' : ''?>><?=h($label)?></a>
                <?php endforeach; ?>
            </nav>
            <a href="admin/index.php" class="admin-link">Админ</a>
        </div>
        <nav id="mobileNav" class="mobile-nav" aria-label="Мобильная навигация" hidden>
            <?php foreach ($nav as [$label, $url]): $active = $currentPage === $url || ($url === 'index.php' && $currentPage === '') || ($url === '/sveden/common' && $currentPage === 'legal.php'); ?>
            <a href="<?=h($url)?>"<?=$active ? ' aria-current="page"' : ''?>><?=h($label)?></a>
            <?php endforeach; ?>
            <a href="admin/index.php">Админ-панель</a>
        </nav>
        <div class="vision-controls" role="group" aria-label="Настройки отображения">
            <span>Версия для слабовидящих:</span>
            <div class="vision-group" role="group" aria-label="Размер шрифта">
                <button class="vision-size" type="button" data-size="16" aria-label="Обычный размер шрифта" aria-pressed="true">А</button>
                <button class="vision-size" type="button" data-size="19" aria-label="Увеличенный размер шрифта" aria-pressed="false">А+</button>
                <button class="vision-size" type="button" data-size="22" aria-label="Максимальный размер шрифта" aria-pressed="false">А++</button>
            </div>
            <span class="vision-divider" aria-hidden="true"></span>
            <div class="vision-group" role="group" aria-label="Цветовая схема">
                <button class="vision-color" type="button" data-color="" aria-label="Обычная цветовая схема" aria-pressed="true">Обычный</button>
                <button class="vision-color" type="button" data-color="bw" aria-label="Чёрный текст на белом фоне" aria-pressed="false">Ч/Б</button>
                <button class="vision-color" type="button" data-color="wb" aria-label="Белый текст на чёрном фоне" aria-pressed="false">Б/Ч</button>
                <button class="vision-color" type="button" data-color="yellow" aria-label="Жёлтый текст на чёрном фоне" aria-pressed="false">Ж/Ч</button>
            </div>
            <button class="vision-reset" type="button">Сбросить настройки</button>
        </div>
    </div>
</header>
<main id="main" class="min-h-[65vh]" tabindex="-1">
<script>
(()=>{
    const menu=document.getElementById('menuBtn'), nav=document.getElementById('mobileNav');
    const sizes=[...document.querySelectorAll('.vision-size')], colors=[...document.querySelectorAll('.vision-color')];
    const setPressed=(buttons,attribute,value)=>buttons.forEach(b=>b.setAttribute('aria-pressed',String(b.dataset[attribute]===value)));
    let size='16', color='';
    try {
        const savedSize=localStorage.getItem('vision-size'), savedColor=localStorage.getItem('vision-color');
        if(['16','19','22'].includes(savedSize)) size=savedSize;
        if(['bw','wb','yellow'].includes(savedColor)) color=savedColor;
    } catch(e) {}
    setPressed(sizes,'size',size); setPressed(colors,'color',color);
    menu.addEventListener('click',()=>{const open=nav.hidden;nav.hidden=!open;menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Закрыть меню':'Открыть меню')});
    menu.addEventListener('keydown',e=>{if(e.key==='Escape'&&!nav.hidden){nav.hidden=true;menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Открыть меню')}});
    nav.addEventListener('keydown',e=>{if(e.key==='Escape'){nav.hidden=true;menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Открыть меню');menu.focus()}});
    sizes.forEach(b=>b.addEventListener('click',()=>{document.documentElement.style.fontSize=(Number(b.dataset.size)/16*100)+'%';setPressed(sizes,'size',b.dataset.size);try{localStorage.setItem('vision-size',b.dataset.size)}catch(e){}}));
    colors.forEach(b=>b.addEventListener('click',()=>{document.documentElement.classList.remove('vision-bw','vision-wb','vision-yellow');if(b.dataset.color)document.documentElement.classList.add('vision-'+b.dataset.color);setPressed(colors,'color',b.dataset.color);try{localStorage.setItem('vision-color',b.dataset.color)}catch(e){}}));
    document.querySelector('.vision-reset').addEventListener('click',()=>{document.documentElement.style.removeProperty('font-size');document.documentElement.classList.remove('vision-bw','vision-wb','vision-yellow');setPressed(sizes,'size','16');setPressed(colors,'color','');try{localStorage.removeItem('vision-size');localStorage.removeItem('vision-color')}catch(e){}});
})();
</script>

