</main>
<footer class="site-footer">
    <div class="site-container footer-grid">
        <div><a href="index.php" class="footer-title">МБУ ДО «СШОР № 4»</a><p>Спорт — норма жизни</p><p><?=h(ORG_ADDRESS)?></p></div>
        <div><h2>Контакты</h2><p><a href="tel:+74922545389"><?=h(ORG_PHONE)?></a></p><p>Пн–Пт: 8:30–20:00; Сб: 16:00–20:00</p></div>
        <div><h2>Полезные разделы</h2><nav aria-label="Полезные разделы"><a href="legal.php?section=admission">Прием в школу</a><a href="legal.php?section=anticorruption">Противодействие коррупции</a><a href="legal.php?section=appeals">Обращения граждан</a><a href="legal.php?section=information-security">Информационная безопасность</a><a href="legal.php">Сведения об образовательной организации</a></nav></div>
    </div>
    <div class="footer-bottom">© <?=date('Y')?> МБУ ДО «СШОР № 4 по легкой атлетике»</div>
</footer>
<div id="cookieBanner" class="fixed inset-x-3 bottom-3 z-50 mx-auto hidden max-w-3xl rounded-2xl border border-white/10 bg-neutral-900 p-5 shadow-2xl"><div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center"><p class="flex-1 text-sm text-gray-300">Мы используем cookie для корректной работы сайта. Продолжая работу, вы соглашаетесь с их использованием и обработкой технических данных.</p><button id="cookieAccept" class="rounded-lg bg-sport px-5 py-2 text-sm font-bold">Понятно</button></div></div>
<script>(()=>{const key='school-cookie-consent',box=document.getElementById('cookieBanner');try{if(!localStorage.getItem(key))box.classList.remove('hidden');document.getElementById('cookieAccept').onclick=()=>{localStorage.setItem(key,'accepted');box.classList.add('hidden')}}catch(e){box.classList.remove('hidden');document.getElementById('cookieAccept').onclick=()=>box.classList.add('hidden')}})();</script>
</body></html>
