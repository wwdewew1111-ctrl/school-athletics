<?php
/** Replace external map embeds with links without changing the stored section. */
function public_map_links(string $html): string
{
    return preg_replace_callback('~<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>\s*</iframe\s*>~is', static function (array $match): string {
        $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^https://yandex\.ru/map-widget/~i', $url)) return $match[0];
        return '<p><a href="'.h($url).'" target="_blank" rel="noopener noreferrer">Открыть карту проезда на Яндекс Картах</a> (внешний сайт)</p>';
    }, $html) ?? $html;
}
