<?php
declare(strict_types=1);

/** Extract only local, explicitly referenced documents from a section. */
function legal_document_paths(string $content): array
{
    $paths = [];
    if (preg_match_all('~(?:file\.php\?path=)([^"\'\s&<>]+)~i', $content, $matches)) {
        foreach ($matches[1] as $encoded) {
            $path = rawurldecode($encoded);
            if (preg_match('~^uploads/docs/[A-Za-z0-9_-]+\.(?:pdf|doc|docx|xlsx)$~iD', $path)) {
                $paths[$path] = $path;
            }
        }
    }
    return array_values($paths);
}

function legal_content_digest(array $row): string
{
    return hash('sha256', (string) $row['content']."\0".(string) $row['document']);
}

/** Change file links only within the current section; never delete the underlying file. */
function legal_edit_document_links(string $content, array $changes): string
{
    if (!$changes) return $content;
    $result = preg_replace_callback('~<a\b[^>]*\bhref\s*=\s*(["\'])file\.php\?path=([^"\'\s&<>]+)(?:&(?:amp;)?download=1)?\1[^>]*>.*?</a>~isu',
        static function (array $match) use ($changes): string {
            $old = rawurldecode($match[2]);
            if (!array_key_exists($old, $changes)) return $match[0];
            $change = $changes[$old];
            if ($change === null) return '';
            [$path, $name] = $change;
            $url = 'file.php?path='.rawurlencode($path);
            $download = preg_match('~(?:&amp;|&)download=1~i', $match[0]) || preg_match('~\bdownload(?:\s|=|>)~i', $match[0]);
            $label = preg_match('~>(.*?)</a>$~isu', $match[0], $parts) ? $parts[1] : 'открыть';
            if ($download) return '<a href="'.htmlspecialchars($url.'&download=1', ENT_QUOTES, 'UTF-8').'" download="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
            return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.$label.'</a>';
        }, $content);
    if ($result === null) throw new RuntimeException('Не удалось обновить ссылки на документы.');
    $result = preg_replace('~:\s*·\s*</p>~u', ':</p>', $result);
    return $result ?? throw new RuntimeException('Не удалось обновить ссылки на документы.');
}

/** Add a document to a section without altering its existing layout. */
function legal_append_document(string $content, string $path, string $name): string
{
    $url = 'file.php?path='.rawurlencode($path);
    return $content.'<p>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').': <a href="'.htmlspecialchars($url.'&download=1', ENT_QUOTES, 'UTF-8').'" download="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'">скачать</a> · <a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">посмотреть</a></p>';
}
