<?php
if (!defined('ADMIN_PAGE_ACTIVE')) { http_response_code(404); exit; }
// Included by the authenticated admin page.
?>
<style>
.legal-editor{min-height:12rem;white-space:pre-wrap;overflow-wrap:anywhere}
.legal-editor:focus{outline:2px solid #f87171;outline-offset:2px}
.legal-editor a{color:#a51a1a;text-decoration:underline}
.legal-editor p{margin:.65rem 0}.legal-editor ul{list-style:disc;padding-left:1.5rem}
.legal-editor dl{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:.5rem 1rem}
.legal-editor dd{margin:0}.legal-editor iframe{max-width:100%;pointer-events:none}
.legal-editor img{max-width:100%}.legal-editor [data-legal-protected]{outline:1px dashed #9ca3af}
@media(max-width:600px){.legal-editor dl{grid-template-columns:1fr}}
</style>
<div class="space-y-4">
<h2 class="text-xl font-bold">Подразделы и документы</h2>
<p class="text-sm text-gray-400">Откройте нужный раздел и измените текст прямо в редакторе. Ссылки на файлы и карту изменяйте через отдельные поля ниже. Изменения применяются после нажатия «Сохранить раздел».</p>
<noscript><p class="rounded bg-red-950 p-3 text-red-200">Для визуального редактора необходим JavaScript. Без него доступно редактирование исходного HTML.</p></noscript>
<?php foreach ($legal as $r): $paths = legal_document_paths((string) $r['content']); ?>
<details class="card" id="legal-<?=$r['id']?>">
<summary class="cursor-pointer font-bold"><?=h($r['title'])?></summary>
<form method="post" enctype="multipart/form-data" class="legal-form mt-4 space-y-4">
<input type="hidden" name="csrf" value="<?=h($csrf)?>">
<input type="hidden" name="action" value="legal"><input type="hidden" name="tab" value="legal">
<input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="revision" value="<?=h(legal_content_digest($r))?>">
<div class="visual-controls" hidden>
<p class="mb-2 font-bold">Текст раздела</p>
<div class="mb-2 flex flex-wrap gap-2" aria-label="Форматирование текста">
<button type="button" class="rounded bg-neutral-700 px-3 py-2 text-sm font-bold" data-command="bold">Жирный</button>
<button type="button" class="rounded bg-neutral-700 px-3 py-2 text-sm italic" data-command="italic">Курсив</button>
<button type="button" class="rounded bg-neutral-700 px-3 py-2 text-sm" data-command="insertUnorderedList">Список</button>
</div>
<div class="legal-editor field" contenteditable="true" role="textbox" aria-label="Текст раздела <?=h($r['title'])?>" aria-multiline="true"></div>
<p class="mt-2 text-xs text-gray-400">Нажмите на текст, чтобы изменить его. При вставке используется обычный текст без постороннего оформления.</p>
</div>
<details class="source-controls"><summary class="cursor-pointer text-sm text-gray-400">Исходный HTML (для опытных пользователей)</summary><label class="block mt-2">Содержимое<textarea class="field mt-1 font-mono text-sm" rows="8" name="content"><?=h($r['content'])?></textarea></label></details>
<div class="map-controls" hidden><label class="block">Адрес виджета Яндекс Карты<input class="field mt-1" type="url" data-map-url></label><p class="mt-1 text-xs text-gray-400">Чтобы изменить карту, вставьте адрес src виджета. Существующая карта сохраняется, если поле не менять.</p></div>
<section class="space-y-3 rounded-lg border border-white/10 p-4"><h3 class="font-bold">Документы в тексте</h3>
<?php if (!$paths): ?><p class="text-sm text-gray-400">В тексте пока нет прикреплённых документов.</p><?php endif; ?>
<?php foreach ($paths as $i => $path): ?>
<div class="rounded border border-white/10 p-3"><p class="break-words text-sm"><a class="text-red-400 underline" href="../<?=h(file_view_url($path))?>" target="_blank" rel="noopener noreferrer"><?=h($uploadNames[$path] ?? basename($path))?></a></p>
<label class="mt-2 block">Заменить файл<input class="field mt-1" type="file" name="replace_<?=$i?>" accept=".pdf,.doc,.docx,.xlsx"></label>
<label class="mt-2 inline-flex items-center gap-2"><input type="checkbox" name="remove_files[]" value="<?=$i?>"> Удалить ссылки на этот документ из раздела</label></div>
<?php endforeach; ?>
<label class="block">Добавить новый документ в конец раздела<input class="field mt-1" type="file" name="new_document" accept=".pdf,.doc,.docx,.xlsx"></label>
<p class="text-xs text-gray-400">При удалении или замене ссылка исчезает из этого раздела; сам старый файл сохраняется на сервере, чтобы не нарушить ссылки в других местах.</p></section>
<section class="space-y-2 rounded-lg border border-white/10 p-4"><h3 class="font-bold">Отдельный документ раздела</h3>
<?php if ($r['document']): ?><p class="text-sm"><a class="text-red-400 underline" href="../<?=h(file_view_url($r['document']))?>" target="_blank" rel="noopener noreferrer"><?=h($uploadNames[$r['document']] ?? basename($r['document']))?></a></p><label class="inline-flex items-center gap-2"><input type="checkbox" name="remove_document" value="1"> Удалить документ раздела</label><?php else: ?><p class="text-sm text-gray-400">Не прикреплён.</p><?php endif; ?>
<label class="block"><?=$r['document'] ? 'Заменить' : 'Прикрепить'?> документ<input class="field mt-1" type="file" name="document" accept=".pdf,.doc,.docx,.xlsx"></label></section>
<button class="rounded bg-red-600 px-4 py-2 font-bold">Сохранить раздел</button>
</form></details>
<?php endforeach; ?></div>
