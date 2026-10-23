<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Plain-text presentation only; callers supply authorized, resolved content. */
final class SeoMetadata
{
    // An editorial budget for generated summaries, not a search-engine limit.
    private const int DESCRIPTION_LENGTH = 240;
    private const array LABELS = [
        0 => ['Database', 'Page'], 2 => ['Base de données', 'Page'],
        3 => ['Datenbank', 'Seite'], 4 => ['数据库', '页'],
        6 => ['Base de datos', 'Página'], 8 => ['База данных', 'Страница']
    ];

    public static function title(array $parts, string $brand, Locale $locale, bool $home = false, string $manualHome = '', int $page = 1) : string
    {
        if ($home && trim($manualHome) !== '')
            return self::text($manualHome);

        $brand = self::text($brand);
        $parts = array_map(self::text(...), $parts);
        // The final component is the site name; an entity may share that name.
        if ($parts && end($parts) === $brand)
            array_pop($parts);
        $parts = array_values(array_unique(array_filter($parts, fn($part) => $part !== '')));
        if ($home)
            $parts = [self::LABELS[$locale->value][0] ?? self::LABELS[0][0]];

        // Keep the entity/topic and broad type, without repeating intermediate
        // category names. Never truncate an entity name to fit a guessed limit.
        $topic = $parts[0] ?? '';
        if (count($parts) > 1)
            $topic .= ' — '.end($parts);
        if ($page > 1)
            $topic .= ($topic !== '' ? ' — ' : '').self::page($page, $locale);

        return implode(' | ', array_filter([$topic, 'WotLK 3.3.5', $brand], fn($part) => $part !== ''));
    }

    public static function description(string $text, Locale $locale, int $page = 1) : string
    {
        // Remove active blocks before tags so script/style text cannot become a
        // summary. Decode after stripping to preserve literal encoded brackets.
        $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $text);
        $text = self::text(strip_tags(preg_replace('~</?(?:p|div|h[1-6]|li|tr|td|th|br)\b[^>]*>~i', ' ', $text)));
        if ($text === '')
            return '';
        if ($page > 1)
            $text = self::page($page, $locale).'. '.$text;
        if (mb_strlen($text) <= self::DESCRIPTION_LENGTH)
            return $text;

        $short = mb_substr($text, 0, self::DESCRIPTION_LENGTH - 1);
        $space = mb_strrpos($short, ' ');
        if ($space !== false && $space > self::DESCRIPTION_LENGTH / 2)
            $short = mb_substr($short, 0, $space);
        return rtrim($short, " \t\n\r\0\x0B,;:").'…';
    }

    private static function page(int $page, Locale $locale) : string
    {
        return (self::LABELS[$locale->value][1] ?? self::LABELS[0][1]).' '.$page;
    }

    private static function text(string $text) : string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[\s\p{Z}\x00-\x1f\x7f]+/u', ' ', $text) ?? '');
    }
}
