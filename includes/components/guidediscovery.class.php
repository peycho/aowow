<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Publication is checked at lookup time; this index is never an access grant. */
final class GuideDiscovery
{
    private const array ROUTES = [
        'npc' => 1, 'object' => 2, 'item' => 3, 'itemset' => 4, 'quest' => 5, 'spell' => 6,
        'zone' => 7, 'faction' => 8, 'pet' => 9, 'achievement' => 10, 'title' => 11,
        'event' => 12, 'class' => 13, 'race' => 14, 'skill' => 15, 'currency' => 17,
        'sound' => 19, 'icon' => 29, 'emote' => 501, 'enchantment' => 502, 'areatrigger' => 503, 'mail' => 504
    ];

    public static function references(string $body, int $access) : array
    {
        require_once __DIR__.'/servercontent.class.php';
        $level = match (true) {
            (bool)($access & (U_GROUP_ADMIN | U_GROUP_VIP | U_GROUP_DEV)) => 40,
            (bool)($access & U_GROUP_STAFF) => 30,
            (bool)($access & U_GROUP_PREMIUM) => 20,
            (bool)($access & U_GROUP_PENDING) => 1,
            default => 10
        };
        $dom = new \DOMDocument();
        $prior = libxml_use_internal_errors(true);
        try
        {
            $dom->loadHTML('<meta charset="UTF-8">'.ServerContent::markup($body, [], $level), LIBXML_NONET);
            $out = [];
            foreach ((new \DOMXPath($dom))->query('//a[not(ancestor::code or ancestor::pre)]/@href') as $link)
            {
                $href = $link->value;
                if (!str_starts_with($href, '?'))
                {
                    $base = rtrim(Cfg::get('HOST_URL'), '/').'/';
                    if (!str_starts_with($href, $base.'?'))
                        continue;
                }
                parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $query);
                $route = array_key_first($query);
                if (!isset(self::ROUTES[$route]) || !is_string($query[$route]) || !preg_match('/^[1-9][0-9]{0,9}$/D', $query[$route]))
                    continue;
                $id = (int)$query[$route];
                if ($id > 2147483647)
                    continue;
                $type = self::ROUTES[$route];
                $out[$type.':'.$id] = ['type' => $type, 'typeId' => $id];
            }
            return array_values($out);
        }
        finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    }

    public static function indexRevision(int $guide, int $rev, Locale $locale, string $body, int $access) : bool
    {
        $references = self::references($body, $access);
        $db = DB::Aowow();
        $db->query('START TRANSACTION');
        try
        {
            $db->query('DELETE FROM ::guide_references WHERE `guideId` = %i AND `rev` = %i AND `locale` = %i', $guide, $rev, $locale->value);
            foreach (array_chunk($references, 200) as $chunk)
            {
                $rows = [];
                foreach ($chunk as $reference)
                    $rows[] = ['guideId' => $guide, 'rev' => $rev, 'locale' => $locale->value, ...$reference];
                $columns = [];
                foreach (array_keys($rows[0]) as $key)
                    $columns[$key] = array_column($rows, $key);
                $db->query('INSERT INTO ::guide_references %m', $columns);
            }
            $db->query('COMMIT');
            return true;
        }
        catch (\Exception $e) { $db->query('ROLLBACK'); return false; }
    }

    public static function preparePublication(int $id) : ?int
    {
        $article = DB::Aowow()->selectRow('SELECT `rev`, `locale`, `article`, `editAccess` FROM ::articles
            WHERE `type` = %i AND `typeId` = %i ORDER BY `rev` DESC LIMIT 1', Type::GUIDE, $id);
        if (!$article || !self::indexRevision($id, $article['rev'], Locale::from($article['locale']), $article['article'] ?? '', $article['editAccess']))
            return null;
        return $article['rev'];
    }

    /** Used by approval and the bounded upgrade/backfill generator. */
    public static function indexGuide(int $id) : bool
    {
        $articles = DB::Aowow()->selectAssoc('SELECT a.`rev`, a.`locale`, a.`article`, a.`editAccess` FROM ::articles a
            JOIN ::guides g ON g.`id` = a.`typeId` AND g.`rev` = a.`rev`
            WHERE a.`type` = %i AND g.`id` = %i AND g.`rev` > 0 AND g.`status` != %i AND (g.`status` = %i OR g.`rev` > 0)',
            Type::GUIDE, $id, GuideMgr::STATUS_ARCHIVED, GuideMgr::STATUS_APPROVED);
        if ($articles === null)
            return false;
        foreach ($articles as $article)
            if (!self::indexRevision($id, $article['rev'], Locale::from($article['locale']), $article['article'] ?? '', $article['editAccess']))
                return false;
        return true;
    }

    public static function categories(Locale $locale) : array
    {
        return DB::Aowow()->selectCol('SELECT DISTINCT g.`category` FROM ::guides g
            JOIN ::articles a ON a.`type` = %i AND a.`typeId` = g.`id` AND a.`rev` = g.`rev`
            WHERE g.`locale` = %i AND a.`locale` = %i AND g.`rev` > 0 AND g.`category` BETWEEN 1 AND 9
                AND g.`status` != %i AND (g.`status` = %i OR g.`rev` > 0) ORDER BY g.`category`',
            Type::GUIDE, $locale->value, $locale->value, GuideMgr::STATUS_ARCHIVED, GuideMgr::STATUS_APPROVED) ?? [];
    }

    public static function related(int $type, int $id, Locale $locale, int $revision = 0) : array
    {
        if ($id <= 0 || (!in_array($type, self::ROUTES, true) && $type !== Type::GUIDE))
            return [];
        $join = '';
        if ($type === Type::GUIDE)
        {
            if ($revision < 1)
                return [];
            $join = DB::Aowow()->translate('JOIN ::guide_references source ON source.`type` = r.`type` AND source.`typeId` = r.`typeId`
                AND source.`guideId` = %i AND source.`rev` = %i AND source.`locale` = %i
                JOIN ::guides origin ON origin.`id` = source.`guideId` AND origin.`rev` = source.`rev`
                    AND origin.`status` != %i AND (origin.`status` = %i OR origin.`rev` > 0)',
                $id, $revision, $locale->value, GuideMgr::STATUS_ARCHIVED, GuideMgr::STATUS_APPROVED);
        }
        $where = $type === Type::GUIDE ? DB::Aowow()->translate('g.`id` != %i', $id) : DB::Aowow()->translate('r.`type` = %i AND r.`typeId` = %i', $type, $id);
        $rows = DB::Aowow()->selectAssoc('SELECT g.`id`, g.`url`, g.`title`, g.`status`, COUNT(*) AS `matches`
            FROM ::guide_references r %sql JOIN ::guides g ON g.`id` = r.`guideId` AND g.`rev` = r.`rev`
            JOIN ::articles a ON a.`type` = %i AND a.`typeId` = g.`id` AND a.`rev` = r.`rev` AND a.`locale` = r.`locale`
            WHERE %sql AND r.`locale` = %i AND g.`rev` > 0 AND g.`status` != %i AND (g.`status` = %i OR g.`rev` > 0)
            GROUP BY g.`id`, g.`url`, g.`title`, g.`status` ORDER BY `matches` DESC, g.`id` ASC LIMIT 6',
            $join, Type::GUIDE, $where, $locale->value, GuideMgr::STATUS_ARCHIVED, GuideMgr::STATUS_APPROVED) ?? [];
        foreach ($rows as &$row)
        {
            // Free-text guide metadata is not revisioned, even for staff readers.
            if ($row['status'] !== GuideMgr::STATUS_APPROVED)
                $row['title'] = Lang::game('guide').' #'.$row['id'];
            $row['href'] = Template\PageTemplate::canonicalUrl(['guide' => GuideMgr::publicUrlToken($row['url'] ?: '', $row['id']), 'locale' => $locale->value]);
        }
        return $rows;
    }

    public static function relatedHook(Template\PageTemplate &$page, array &$links) : void
    {
        $links = [];
        if ($page->type === Type::GUIDE && (($page->seo['index'] ?? false) !== true || array_key_exists('rev', $_GET)))
            return;
        $links = self::related($page->type, $page->typeId, $page->seo['locale'] ?? Lang::getLocale(), $page->guideRevision ?? 0);
    }
}
