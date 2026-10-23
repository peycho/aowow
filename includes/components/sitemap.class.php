<?php

namespace Aowow;

use Aowow\Template\PageTemplate;

if (!defined('AOWOW_REVISION'))
    die('invalid access');

class Sitemap
{
    public const string ERR_TITLE  = 'Invalid sitemap';
    public const string ERR_PAGE   = 'This sitemap does not exist.';
    public const string ERR_OFFSET = 'The maximum page for this sitemap is %d.';

    // Leave room for XML escaping and localized URLs; also enforce the byte cap.
    private const int MAX_ENTRIES = 10000;
    private const int MAX_BYTES   = 50 * 1024 * 1024;

    public static int $maxPage = 0;

    // Area triggers require staff access and must never be advertised to guests.
    private const array PAGES = [
        'npc' => ['::creature', 'npcs'], 'object' => ['::objects', 'objects'],
        'item' => ['::items', 'items'], 'itemset' => ['::itemset', 'itemsets'],
        'quest' => ['::quests', 'quests'], 'spell' => ['::spell', 'spells'],
        'zone' => ['::zones', 'zones'], 'faction' => ['::factions', 'factions'],
        'pet' => ['::pet', 'pets'], 'achievement' => ['::achievement', 'achievements'],
        'title' => ['::titles', 'titles'], 'event' => ['::events', 'events'],
        'class' => ['::classes', 'classes'], 'race' => ['::races', 'races'],
        'skill' => ['::skillline', 'skills'], 'currency' => ['::currencies', 'currencies'],
        'sound' => ['::sounds', 'sounds'], 'icon' => ['::icons', 'icons'],
        'emote' => ['::emotes', 'emotes'], 'enchantment' => ['::itemenchantment', 'enchantments'],
        'mail' => ['::mails', 'mails']
    ];

    public static function generate(string $page, int $offset, ?Locale $locale = null) : ?string
    {
        self::$maxPage = 0;
        if ($page !== '' && $page !== 'special' && $page !== 'guide' &&
            (!isset(self::PAGES[$page]) || !self::enabled($page)))
            return null;

        $locale ??= Locale::getFallback();
        if (!$locale->validate() || $offset < 1)
            return null;

        if ($page === '')
            return $offset === 1 ? self::index() : null;
        if ($page === 'special')
        {
            self::$maxPage = 1;
            return $offset === 1 ? self::special($locale) : null;
        }

        $pages = (int)ceil(self::count($page, $locale) / self::MAX_ENTRIES);
        self::$maxPage = $pages;
        if ($offset > $pages)
            return null;

        $root = self::root('urlset');
        $start = self::MAX_ENTRIES * ($offset - 1);
        if ($page === 'guide')
        {
            $rows = DB::Aowow()->selectAssoc('SELECT g.`id` AS ARRAY_KEY, g.`id`, g.`url`, a.`locale`, g.`approveDate`
                FROM ::guides g JOIN ::articles a ON a.`type` = %i AND a.`typeId` = g.`id` AND a.`rev` = g.`rev`
                WHERE %sql ORDER BY g.`id`, a.`locale` LIMIT %i, %i',
                Type::GUIDE, self::guideWhere($locale), $start, self::MAX_ENTRIES);
            foreach ($rows as $row)
                self::entry($root, 'guide', GuideMgr::publicUrlToken($row['url'] ?? '', $row['id']),
                    $locale, (int)$row['approveDate']);
        }
        else
        {
            // Neither imported records nor community submissions record the
            // significant-update time of the main content. Do not invent one.
            // Removing the comment/media joins also eliminates the old co.flags
            // typo for screenshots/videos and the multiplicative join workload.
            $ids = DB::Aowow()->selectCol('SELECT `id` FROM %n WHERE %sql ORDER BY `id` LIMIT %i, %i',
                self::PAGES[$page][0], self::recordWhere(), $start, self::MAX_ENTRIES);
            foreach ($ids as $id)
                self::entry($root, $page, $id, $locale);
        }

        return self::xml($root);
    }

    private static function enabled(string $page) : bool
    {
        return $page !== 'sound' || (bool)Cfg::get('SOUNDS_ENABLE');
    }

    private static function recordWhere() : string
    {
        return '(`cuFlags` & '.(CUSTOM_EXCLUDE_FOR_LISTVIEW | CUSTOM_UNAVAILABLE).') = 0';
    }

    private static function guideWhere(Locale $locale) : string
    {
        // A pending edit retains its approved body. Require the exact article
        // row the public endpoint selects, and use that body's authored locale.
        return 'g.`status` <> '.GuideMgr::STATUS_ARCHIVED.' AND g.`rev` > 0 AND a.`locale` = '.$locale->value;
    }

    private static function count(string $page, Locale $locale) : int
    {
        if ($page === 'guide')
            return (int)DB::Aowow()->selectCell('SELECT COUNT(*) FROM ::guides g
                JOIN ::articles a ON a.`type` = %i AND a.`typeId` = g.`id` AND a.`rev` = g.`rev` WHERE %sql',
                Type::GUIDE, self::guideWhere($locale));
        return (int)DB::Aowow()->selectCell('SELECT COUNT(*) FROM %n WHERE %sql', self::PAGES[$page][0], self::recordWhere());
    }

    private static function index() : ?string
    {
        $root = self::root('sitemapindex');
        foreach (Locale::cases() as $locale)
        {
            if (!$locale->validate())
                continue;
            self::location($root->addChild('sitemap'), self::url('sitemap', 'special', $locale));
            foreach ([...array_keys(self::PAGES), 'guide'] as $page)
            {
                if (!self::enabled($page))
                    continue;
                $pages = (int)ceil(self::count($page, $locale) / self::MAX_ENTRIES);
                for ($i = 1; $i <= $pages; $i++)
                    self::location($root->addChild('sitemap'), self::url('sitemap', $page, $locale, ['page' => $i]));
            }
        }
        return self::xml($root);
    }

    private static function special(Locale $locale) : ?string
    {
        $root = self::root('urlset');
        foreach (['home', 'talent', 'petcalc', 'compare', 'maps'] as $route)
            self::entry($root, $route, '', $locale);
        if (Cfg::get('PROFILER_ENABLE'))
            self::entry($root, 'profiler', '', $locale);

        // Bounded browse roots, not arbitrary filter permutations. Each root
        // leads to the category navigation and server-rendered pagination.
        foreach (self::PAGES as $page => [, $route])
            if (self::enabled($page) && self::count($page, $locale))
                self::entry($root, $route, '', $locale);

        // Guide lists filter on the guide's language, unlike its detail page.
        $categories = DB::Aowow()->selectCol('SELECT DISTINCT g.`category` FROM ::guides g
            JOIN ::articles a ON a.`type` = %i AND a.`typeId` = g.`id` AND a.`rev` = g.`rev`
            WHERE %sql AND g.`locale` = %i ORDER BY g.`category`', Type::GUIDE, self::guideWhere($locale), $locale->value);
        if ($categories)
            self::entry($root, 'guides', '', $locale);
        foreach ($categories as $category)
            if ($category >= 1 && $category <= 9)
                self::entry($root, 'guides', $category, $locale);

        return self::xml($root);
    }

    private static function url(string $route, string|int $value, Locale $locale, array $params = []) : string
    {
        return PageTemplate::canonicalUrl([$route => (string)$value, ...$params, 'locale' => $locale->value]);
    }

    private static function entry(SimpleXML $root, string $route, string|int $value, Locale $locale, int $lastmod = 0) : void
    {
        $url = $root->addChild('url');
        self::location($url, self::url($route, $value, $locale));
        // Only the approval time of the selected public guide body is reliable.
        // Seed data with unknown/zero dates and future timestamps omit lastmod.
        if ($lastmod > 0 && $lastmod <= time())
            $url->addChild('lastmod', gmdate('c', $lastmod));
    }

    private static function location(SimpleXML $node, string $url) : void
    {
        // SimpleXMLElement::addChild does not escape ampersands itself.
        $node->addChild('loc', htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    private static function root(string $name) : SimpleXML
    {
        return new SimpleXML('<?xml version="1.0" encoding="UTF-8"?><'.$name.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    }

    private static function xml(SimpleXML $root) : ?string
    {
        $xml = $root->asXML();
        return $xml !== false && strlen($xml) <= self::MAX_BYTES && count($root->children()) <= 50000 ? $xml : null;
    }
}
