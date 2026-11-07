(() => {
    'use strict';
    document.querySelectorAll('.legal-form').forEach((form) => {
        const source = form.querySelector('textarea[name="content"]');
        const visual = form.querySelector('.legal-editor');
        const controls = form.querySelector('.visual-controls');
        const sourceControls = form.querySelector('.source-controls');
        const mapControls = form.querySelector('.map-controls');
        const mapInput = form.querySelector('[data-map-url]');
        let baseline = '';
        let changed = false;

        let protectedFrames = [];
        let protectedStyles = [];
        let protectedLinks = [];

        function protect() {
            visual.querySelectorAll('style, iframe, script, object, embed, a[href^="file.php?path="]').forEach((node) => {
                node.dataset.legalProtected = 'true';
                node.setAttribute('contenteditable', 'false');
            });
            const map = visual.querySelector('iframe[src^="https://yandex.ru/map-widget/"]');
            mapControls.hidden = !map;
            mapInput.value = map ? map.getAttribute('src') : '';
        }
        function load() {
            visual.innerHTML = source.value;
            protect();
            protectedFrames = [...visual.querySelectorAll('iframe')];
            protectedStyles = [...visual.querySelectorAll('style')];
            protectedLinks = [...visual.querySelectorAll('a[href^="file.php?path="]')];
            baseline = visual.innerHTML;
            changed = false;
        }
        function saveVisual() {
            if (!changed) return;
            if (protectedFrames.some(node => !visual.contains(node)) || protectedStyles.some(node => !visual.contains(node)) || protectedLinks.some(node => !visual.contains(node))) {
                throw new Error('Карту, оформление и ссылки на документы нельзя удалить в визуальном редакторе. Обновите страницу; для файлов используйте поля ниже.');
            }
            const copy = visual.cloneNode(true);
            copy.querySelectorAll('[data-legal-protected]').forEach((node) => {
                node.removeAttribute('data-legal-protected');
                node.removeAttribute('contenteditable');
            });
            source.value = copy.innerHTML;
            baseline = visual.innerHTML;
            changed = false;
        }
        load();
        controls.hidden = false;
        visual.addEventListener('input', () => { changed = visual.innerHTML !== baseline; });
        visual.addEventListener('click', (event) => {
            if (event.target.closest('a')) event.preventDefault();
        });
        visual.addEventListener('paste', (event) => {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        });
        form.querySelectorAll('[data-command]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                visual.focus();
                document.execCommand(button.dataset.command, false, null);
                changed = visual.innerHTML !== baseline;
            });
        });
        mapInput.addEventListener('input', () => {
            const map = protectedFrames.find(node => node.getAttribute('src').startsWith('https://yandex.ru/map-widget/'));
            if (map) { map.setAttribute('src', mapInput.value); changed = true; }
        });
        sourceControls.addEventListener('toggle', () => {
            if (sourceControls.open) {
                try { saveVisual(); } catch (error) { alert(error.message); sourceControls.open = false; }
            } else load();
        });
        form.addEventListener('submit', (event) => {
            try { if (!sourceControls.open) saveVisual(); }
            catch (error) { event.preventDefault(); alert(error.message); return; }
            const oldMap = source.value.match(/<iframe\b[^>]*\bsrc=["']([^"']+)["']/i);
            if (oldMap && !/^https:\/\/yandex\.ru\/map-widget\//i.test(oldMap[1])) {
                event.preventDefault();
                alert('Укажите корректный адрес виджета Яндекс Карты.');
            }
        });
    });
})();
