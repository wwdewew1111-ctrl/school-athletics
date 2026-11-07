<?php
require_once __DIR__.'/db.php';
$pageTitle = $pageTitle ?? ORG_NAME;
$nav = [['Главная','index.php'],['Соревнования','competitions.php'],['Расписание','schedule.php'],['Тренеры','coaches.php'],['Фотоальбом','gallery.php'],['Сведения школы','legal.php']];
$themeUrl = 'theme.css?v='.(string) filemtime(__DIR__.'/../theme.css');
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#a51a1a">
    <title><?=h($pageTitle)?> — СШОР № 4</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{sport:'#b91c1c',ink:'#20242b',panel:'#fff'}}}}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?=h($themeUrl)?>">
    <script>(()=>{try{const s=localStorage.getItem('vision-size');if(['16','19','22'].includes(s))document.documentElement.style.fontSize=s+'px';const c=localStorage.getItem('vision-color');if(['bw','wb'].includes(c))document.documentElement.classList.add('vision-'+c)}catch(e){}})();</script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержанию</a>
<header class="site-header sticky top-0 z-40">
    <div class="site-container">
        <div class="site-header-row">
            <a href="index.php" class="site-brand">
                <img class="brand-mark" src="assets/logo.jpg" width="425" height="600" alt="">
                <span class="brand-name">МБУ ДО «СШОР № 4»<small>по легкой атлетике · Владимир</small></span>
            </a>
            <button id="menuBtn" class="menu-toggle" type="button" aria-label="Открыть меню" aria-controls="mobileNav" aria-expanded="false"><span aria-hidden="true">☰</span></button>
            <nav id="nav" class="desktop-nav" aria-label="Основная навигация">
                <?php foreach ($nav as [$label, $url]): $active = $currentPage === $url || ($url === 'index.php' && $currentPage === ''); ?>
                <a href="<?=h($url)?>"<?=$active ? ' aria-current="page"' : ''?>><?=h($label)?></a>
                <?php endforeach; ?>
            </nav>
            <a href="admin/index.php" class="admin-link">Админ</a>
        </div>
        <nav id="mobileNav" class="mobile-nav" aria-label="Мобильная навигация" hidden>
            <?php foreach ($nav as [$label, $url]): $active = $currentPage === $url || ($url === 'index.php' && $currentPage === ''); ?>
            <a href="<?=h($url)?>"<?=$active ? ' aria-current="page"' : ''?>><?=h($label)?></a>
            <?php endforeach; ?>
            <a href="admin/index.php">Админ-панель</a>
        </nav>
        <div class="vision-controls" aria-label="Настройки отображения">
            <span>Версия для слабовидящих:</span>
            <button class="vision-size" type="button" data-size="16" aria-label="Обычный размер шрифта">А</button>
            <button class="vision-size" type="button" data-size="19" aria-label="Увеличить шрифт">А+</button>
            <button class="vision-size" type="button" data-size="22" aria-label="Максимальный размер шрифта">А++</button>
            <span class="vision-divider" aria-hidden="true"></span>
            <button class="vision-color" type="button" data-color="">Обычный</button>
            <button class="vision-color" type="button" data-color="bw">Ч/Б</button>
            <button class="vision-color" type="button" data-color="wb">Б/Ч</button>
        </div>
    </div>
</header>
<main id="main" class="min-h-[65vh]">
<script>
(()=>{
    const menu=document.getElementById('menuBtn'), nav=document.getElementById('mobileNav');
    menu.addEventListener('click',()=>{const open=nav.hidden;nav.hidden=!open;menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Закрыть меню':'Открыть меню')});
    document.querySelectorAll('.vision-size').forEach(b=>b.onclick=()=>{document.documentElement.style.fontSize=b.dataset.size+'px';try{localStorage.setItem('vision-size',b.dataset.size)}catch(e){}});
    document.querySelectorAll('.vision-color').forEach(b=>b.onclick=()=>{document.documentElement.classList.remove('vision-bw','vision-wb');if(b.dataset.color)document.documentElement.classList.add('vision-'+b.dataset.color);try{localStorage.setItem('vision-color',b.dataset.color)}catch(e){}});
})();
</script>

