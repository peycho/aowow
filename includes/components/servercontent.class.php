<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Initial HTML uses only content already authorized and loaded by the response. */
final class ServerContent
{
    private const array REFERENCES = [
        'npc' => 1, 'object' => 2, 'item' => 3, 'itemset' => 4, 'quest' => 5,
        'spell' => 6, 'zone' => 7, 'faction' => 8, 'pet' => 9, 'achievement' => 10,
        'title' => 11, 'event' => 12, 'class' => 13, 'race' => 14, 'skill' => 15,
        'currency' => 17, 'sound' => 19, 'icondb' => 29, 'icon' => 29,
        'emote' => 501, 'enchantment' => 502, 'areatrigger' => 503, 'mail' => 504, 'guide' => 300
    ];
    private const array BLOCKS = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'quote', 'pre', 'code'];
    private const array INLINE = ['b', 'i', 'u', 's', 'del', 'small', 'sub', 'sup', 'span', 'color', 'url', 'tooltip', 'acronym', 'center'];

    public static function text(string $value) : string
    {
        return htmlspecialchars(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function label(string $key) : string
    {
        $labels = [
            0 => ['home' => 'Home', 'navigation' => 'Browse', 'previous' => 'Previous', 'next' => 'Next', 'page' => 'Page', 'results' => 'Results', 'javascript' => 'JavaScript enables interactive filters, maps and tools.'],
            2 => ['home' => 'Accueil', 'navigation' => 'Parcourir', 'previous' => 'Précédent', 'next' => 'Suivant', 'page' => 'Page', 'results' => 'Résultats', 'javascript' => 'JavaScript active les filtres, cartes et outils interactifs.'],
            3 => ['home' => 'Startseite', 'navigation' => 'Durchsuchen', 'previous' => 'Zurück', 'next' => 'Weiter', 'page' => 'Seite', 'results' => 'Ergebnisse', 'javascript' => 'JavaScript aktiviert interaktive Filter, Karten und Werkzeuge.'],
            4 => ['home' => '首页', 'navigation' => '浏览', 'previous' => '上一页', 'next' => '下一页', 'page' => '页', 'results' => '结果', 'javascript' => '启用 JavaScript 可使用交互式筛选、地图和工具。'],
            6 => ['home' => 'Inicio', 'navigation' => 'Explorar', 'previous' => 'Anterior', 'next' => 'Siguiente', 'page' => 'Página', 'results' => 'Resultados', 'javascript' => 'JavaScript activa los filtros, mapas y herramientas interactivos.'],
            8 => ['home' => 'Главная', 'navigation' => 'Обзор', 'previous' => 'Назад', 'next' => 'Далее', 'page' => 'Страница', 'results' => 'Результаты', 'javascript' => 'JavaScript включает интерактивные фильтры, карты и инструменты.']
        ];
        return $labels[Lang::getLocale()->value][$key] ?? $labels[0][$key] ?? $key;
    }

    public static function url(string $route, string|int $value = '', array $params = []) : string
    {
        $locale = Lang::getLocale();
        if ($locale !== Locale::EN)
            $params['locale'] = $locale->value;
        if (($params['page'] ?? 0) == 1)
            unset($params['page']);
        ksort($params, SORT_STRING);
        $pair = rawurlencode($route).((string)$value !== '' ? '='.rawurlencode((string)$value) : '');
        return '?'.$pair.($params ? '&'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function safeUrl(string $url) : ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || preg_match('~[\x00-\x20\x7f\\\\]|%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)~i', $url))
            return null;
        if ($url[0] === '?' || $url[0] === '#')
            return $url;
        if ($url[0] === '/' && !str_starts_with($url, '//'))
            return $url;
        if (preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL))
        {
            $parts = parse_url($url);
            if (!isset($parts['user']) && !isset($parts['pass']))
                return $url;
        }
        return null;
    }

    private static function image(string $src, array $attrs) : string
    {
        $url = self::safeUrl($src);
        $base = rtrim((string)Cfg::get('STATIC_URL'), '/');
        if (!$url || !$base)
            return self::text($attrs['alt'] ?? '');
        if (str_starts_with($url, '/static/'))
            $url = $base.substr($url, 7);
        if (!str_starts_with($url, $base.'/') || preg_match('~(?:\.{2}|%|\\\\)~', substr($url, strlen($base))))
            return self::text($attrs['alt'] ?? '');
        $path = substr($url, strlen($base) + 1);
        if (!preg_match('~^(?:uploads/guide/images/[0-9]+|images/[a-zA-Z0-9_/-]+)\.(?:jpe?g|png|gif|webp)$~D', $path))
            return self::text($attrs['alt'] ?? '');
        $html = '<img src="'.self::text($url).'" alt="'.self::text($attrs['alt'] ?? '').'" loading="lazy"';
        foreach (['width', 'height'] as $key)
            if (isset($attrs[$key]) && ctype_digit((string)$attrs[$key]) && $attrs[$key] > 0 && $attrs[$key] <= 4096)
                $html .= ' '.$key.'="'.(int)$attrs[$key].'"';
        return $html.'>';
    }

    /** Sanitize generated tooltip/notice HTML as well as its untrusted DB strings. */
    public static function safeHtml(string $html) : string
    {
        if ($html === '')
            return '';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prior = libxml_use_internal_errors(true);
        try
        {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="server-root">'.substr($html, 0, 1048576).'</div></body></html>', LIBXML_NONET);
            $root = $dom->getElementById('server-root');
            return $root ? self::htmlChildren($root) : '';
        }
        finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    }

    private static function htmlChildren(\DOMNode $parent, int $depth = 0) : string
    {
        if ($depth > 48)
            return '';
        $out = '';
        foreach ($parent->childNodes as $node)
        {
            if ($node instanceof \DOMText)
            {
                $out .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$node instanceof \DOMElement)
                continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button', 'base', 'link', 'meta'], true))
                continue;
            if ($tag === 'img')
            {
                $out .= self::image($node->getAttribute('src'), ['alt' => $node->getAttribute('alt'), 'width' => $node->getAttribute('width'), 'height' => $node->getAttribute('height')]);
                continue;
            }
            $children = self::htmlChildren($node, $depth + 1);
            if (!in_array($tag, ['a', 'b', 'strong', 'em', 'i', 'u', 's', 'small', 'sub', 'sup', 'span', 'div', 'p', 'br', 'hr', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code'], true))
            {
                $out .= $children;
                continue;
            }
            $attrs = '';
            if ($tag === 'a')
            {
                $url = self::safeUrl($node->getAttribute('href'));
                if (!$url) { $out .= $children; continue; }
                $attrs = ' href="'.self::text($url).'"';
            }
            if (preg_match('/^q\d{1,2}$/D', $node->getAttribute('class')))
                $attrs .= ' class="'.$node->getAttribute('class').'"';
            foreach (['colspan', 'rowspan'] as $key)
                if (in_array($tag, ['td', 'th'], true) && ctype_digit($node->getAttribute($key)) && (int)$node->getAttribute($key) <= 50)
                    $attrs .= ' '.$key.'="'.(int)$node->getAttribute($key).'"';
            $out .= '<'.$tag.$attrs.'>'.$children.(in_array($tag, ['br', 'hr'], true) ? '' : '</'.$tag.'>');
        }
        return $out;
    }

    /** A bounded tree parser; raw HTML and unsupported attributes never become HTML. */
    public static function markup(string $text, array $globals = [], int $allow = 30) : string
    {
        $text = str_replace(["\r\n", "\r"], "\n", substr($text, 0, 1048576));
        $tokens = preg_split('/((?<!\\\\)\[\/?(?:[a-z][a-z0-9]*|\*)[^\]\n]{0,1024}\])/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $root = ['tag' => 'root', 'children' => []];
        $stack = [&$root];
        $nodes = 0;
        foreach ($tokens as $token)
        {
            $parent = &$stack[count($stack) - 1];
            if (++$nodes > 20000 || !preg_match('/^\[(\/?)([a-z][a-z0-9]*|\*)(.*)\]$/isD', $token, $m))
            {
                $parent['children'][] = str_replace('\\[', '[', $token);
                continue;
            }
            $tag = strtolower($m[2]);
            if ($m[1] === '/')
            {
                for ($i = count($stack) - 1; $i > 0; --$i)
                    if ($stack[$i]['tag'] === $tag)
                    {
                        array_splice($stack, $i);
                        break;
                    }
                continue;
            }
            if (!in_array($tag, [...self::BLOCKS, ...self::INLINE, 'br', 'hr', 'img', 'money', 'anchor'], true) && !isset(self::REFERENCES[$tag]))
                continue;
            if (count($stack) >= 48)
                continue;
            $parent['children'][] = ['tag' => $tag, 'attrs' => $m[3], 'children' => []];
            $last = array_key_last($parent['children']);
            if (!in_array($tag, ['br', 'hr', 'img', 'money', 'anchor'], true) && !isset(self::REFERENCES[$tag]))
                $stack[] = &$parent['children'][$last];
            unset($parent);
        }
        return self::paragraphs(self::markupChildren($root['children'], $globals, $allow));
    }

    private static function paragraphs(string $html) : string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prior = libxml_use_internal_errors(true);
        try
        {
            $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="paragraph-root">'.$html.'</div></body></html>', LIBXML_NONET);
            $root = $dom->getElementById('paragraph-root');
            if (!$root) return '';
            $paragraph = null;
            $lastBreak = null;
            foreach (iterator_to_array($root->childNodes) as $node)
            {
                if ($node instanceof \DOMElement && in_array($node->tagName, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'pre', 'code', 'blockquote', 'hr'], true))
                {
                    $paragraph = $lastBreak = null;
                    continue;
                }
                if ($node instanceof \DOMText && trim($node->textContent) === '' && !$paragraph)
                    continue;
                if ($node instanceof \DOMElement && $node->tagName === 'br' && $lastBreak)
                {
                    $lastBreak->parentNode->removeChild($lastBreak);
                    $root->removeChild($node);
                    $paragraph = $lastBreak = null;
                    continue;
                }
                if (!$paragraph)
                {
                    $paragraph = $dom->createElement('p');
                    $root->insertBefore($paragraph, $node);
                }
                $paragraph->appendChild($node);
                if ($node instanceof \DOMElement && $node->tagName === 'br')
                    $lastBreak = $node;
                else if (!$node instanceof \DOMText || trim($node->textContent) !== '')
                    $lastBreak = null;
            }
            $out = '';
            foreach ($root->childNodes as $node) $out .= $dom->saveHTML($node);
            return $out;
        }
        finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    }

    private static function markupChildren(array $children, array $globals, int $allow, string $parent = '') : string
    {
        $out = '';
        foreach ($children as $node)
        {
            if (is_string($node))
            {
                if (in_array($parent, ['table', 'tr', 'ul', 'ol'], true) && trim($node) === '')
                    continue;
                $out .= nl2br(self::text($node), false);
                continue;
            }
            $tag = $node['tag'];
            $attrs = $node['attrs'];
            $body = self::markupChildren($node['children'], $globals, $allow, $tag);
            // Match the interactive parser's author permission levels. Unsupported
            // decoration remains readable; staff-only images and money are omitted.
            $minimum = in_array($tag, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'color', 'center', 'tooltip', 'img', 'money'], true) ? 30 : 10;
            if (!isset(self::REFERENCES[$tag]) && $allow < $minimum)
            {
                $out .= $body;
                continue;
            }
            if (isset(self::REFERENCES[$tag]))
            {
                if (!preg_match('/^=(-?\d+)\b/', $attrs, $id))
                    continue;
                $type = self::REFERENCES[$tag];
                $row = $globals[$type][1][(int)$id[1]] ?? [];
                $name = $row['name_'.Lang::getLocale()->json()] ?? $row['name'] ?? ucfirst($tag).' #'.$id[1];
                $out .= '<a href="'.self::text(self::url($tag === 'icondb' ? 'icon' : $tag, (int)$id[1])).'">'.self::text((string)$name).'</a>';
                continue;
            }
            if ($tag === 'img')
            {
                $image = [];
                preg_match_all('/\b(src|alt|width|height)\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s]+))/i', $attrs, $parts, PREG_SET_ORDER);
                foreach ($parts as $part)
                    $image[strtolower($part[1])] = ($part[2] ?? '') !== '' ? $part[2] : (($part[3] ?? '') !== '' ? $part[3] : ($part[4] ?? ''));
                $out .= self::image($image['src'] ?? (str_starts_with($attrs, '=') ? substr($attrs, 1) : ''), $image);
                continue;
            }
            if ($tag === 'money')
            {
                if (preg_match('/^=(\d{1,12})$/D', $attrs, $m))
                {
                    $money = (int)$m[1];
                    $out .= intdiv($money, 10000).'g '.intdiv($money % 10000, 100).'s '.($money % 100).'c';
                }
                continue;
            }
            if ($tag === 'anchor')
            {
                if (preg_match('/^=([a-z0-9_-]{1,64})$/iD', $attrs, $m))
                    $out .= '<span id="markup-'.$m[1].'"></span>';
                continue;
            }
            if ($tag === 'url')
            {
                $url = self::safeUrl(str_starts_with($attrs, '=') ? trim(substr($attrs, 1), "\"' ") : strip_tags($body));
                $out .= $url && ($allow >= 10 || $url[0] === '?' || $url[0] === '#' || $url[0] === '/') ? '<a href="'.self::text($url).'">'.($body !== '' ? $body : self::text($url)).'</a>' : $body;
                continue;
            }
            $htmlTag = match ($tag) { 'quote' => 'blockquote', 'del' => 's', 'color', 'tooltip', 'acronym', 'center' => 'span', default => $tag };
            $out .= '<'.$htmlTag.'>'.$body.(in_array($tag, ['br', 'hr'], true) ? '' : '</'.$htmlTag.'>');
        }
        return $out;
    }

    public static function listHtml(string $template, string $id, ?string $name, array $rows, array $globals = []) : string
    {
        $route = match ($template) {
            'classs' => 'class', 'holiday', 'holidaycal' => 'event', 'icongallery' => 'icon',
            default => $template
        };
        if (!isset(self::REFERENCES[$route]) && !in_array($route, ['icon', 'areatrigger', 'mail'], true))
            return '';
        $label = $name && !str_starts_with($name, '$') ? $name : ucwords(str_replace('-', ' ', $id ?: $route));
        $html = '<section class="server-list" data-listview="'.self::text($id).'"><h3>'.self::text($label).'</h3><ul>';
        foreach ($rows as $key => $row)
        {
            if (!is_array($row))
                continue;
            $rowId = $row['id'] ?? $key;
            if (!is_scalar($rowId) || !preg_match('/^-?\d+$/D', (string)$rowId))
                continue;
            $text = (string)($row['name'] ?? $row['title'] ?? ucfirst($route).' #'.$rowId);
            if (in_array($route, ['item', 'itemset', 'spell'], true) && preg_match($route === 'spell' ? '/^[@0-9]/' : '/^[0-7]/', $text))
                $text = substr($text, 1);
            $url = $route === 'guide' && isset($row['url']) ? self::safeUrl((string)$row['url']) : self::url($route, (int)$rowId);
            $html .= '<li>'.($url ? '<a href="'.self::text($url).'">'.self::text($text).'</a>' : self::text($text));
            foreach (['level', 'reqlevel', 'count', 'percent'] as $field)
                if (isset($row[$field]) && is_numeric($row[$field]))
                {
                    $value = match ($field) {
                        'level' => Lang::game('level').': '.$row[$field],
                        'reqlevel' => Lang::game('reqLevel', [$row[$field]]),
                        'count' => '×'.$row[$field],
                        default => $row[$field].'%'
                    };
                    $html .= ' <span>'.self::text($value).'</span>';
                }
            $html .= '</li>';
        }
        return $html.'</ul></section>';
    }
}
