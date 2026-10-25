<?php

// Run the real generator and endpoint against isolated tables from the stock
// schema. Never use configured application databases or retained client data.
namespace Aowow {
    class Cfg {
        public static string $directory = '';
        public static int $locales = 5; // English + French
        public static bool $sounds = false;
        public static bool $profiler = false;
        public static function get(string $key) : mixed { return match ($key) {
            'HOST_URL' => 'https://fixture.example', 'LOCALES' => self::$locales,
            'SOUNDS_ENABLE' => self::$sounds, 'PROFILER_ENABLE' => self::$profiler,
            'CACHE_MODE' => 1, 'CACHE_DECAY' => 604800, 'CACHE_DIR' => self::$directory, default => ''
        }; }
    }
    class Lang {
        public static function getLocale() : Locale { return Locale::FR; } // A saved preference must not affect sitemaps.
    }
    class TextResponse {
        protected array $_get = [];
        public string $result = '';
        public function __construct(string $param) {
            foreach ($this->expectedGET as $key => $filter)
                $this->_get[$key] = isset($_GET[$key]) ? filter_var($_GET[$key], $filter['filter'], $filter) : null;
        }
        public function run() : string { $this->generate(); return $this->result; }
    }
    class TemplateResponse {
        public function __construct(string $param) {}
        public function generateNotFound(string $title, string $message) : never { throw new \OutOfBoundsException($message); }
    }
}
namespace {
    use Aowow\{Cfg, DB, DibiConnection, GuideMgr, Locale, Sitemap, SitemapBaseResponse};
    use Aowow\Template\PageTemplate;
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_seo') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_seo database.\n"); exit(1);
    }
    define('AOWOW_REVISION', 75); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    define('AOWOW_CACHE_KEY', str_repeat('22', 32));
    Cfg::$directory = require __DIR__.'/fixtures/cache-directory.php';
    $root = dirname(__DIR__);
    require $root.'/includes/libs/autoload.php';
    foreach (['defines.php', 'database.php', 'locale.class.php', 'type.class.php', 'utilities.php',
        'components/guidemgr.class.php', 'components/pagetemplate.class.php', 'components/sitemap.class.php'] as $file)
        require $root.'/includes/'.$file;
    require $root.'/endpoints/sitemap/sitemap.php';
    $db = new DibiConnection([
        'driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username' => 'root', 'password' => '',
        'database' => 'aowow_security_test_seo', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']
    ]);
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW => [time() + 86400, 86400]]);
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function insertRows(string $table, array $rows) : void {
        global $db;
        $columns = [];
        foreach (array_keys($rows[0]) as $key) $columns[$key] = array_column($rows, $key);
        $db->query('INSERT INTO %n %m', $table, $columns);
    }
    function xml(?string $xml) : SimpleXMLElement {
        check(is_string($xml), 'existing sitemap has XML');
        $tree = simplexml_load_string($xml);
        check($tree !== false, 'XML is well formed without double escaping');
        check($tree->getNamespaces(true)[''] === 'http://www.sitemaps.org/schemas/sitemap/0.9', 'protocol namespace');
        return $tree;
    }
    function locations(SimpleXMLElement $xml) : array {
        $out = [];
        foreach ($xml->children() as $node) $out[] = (string)$node->loc;
        return $out;
    }
    $tables = array_map(fn($p) => str_replace('::', 'aowow_', $p[0]), (new ReflectionClass(Sitemap::class))->getConstant('PAGES'));
    $tables = [...$tables, 'aowow_guides', 'aowow_articles'];
    $schema = file_get_contents($root.'/setup/sql/01-db_structure.sql');
    try {
        foreach ($tables as $table) {
            $db->query('DROP TABLE IF EXISTS %n', $table);
            preg_match('/CREATE TABLE `'.preg_quote($table, '/').'` \(.*?\) ENGINE=.*?;/s', $schema, $match);
            if (!$match) throw new RuntimeException('Stock schema missing: '.$table);
            $db->nativeQuery($match[0]);
        }
        $item = ['classBak' => 0, 'subClassBak' => 0, 'soundOverrideSubclass' => 0,
            'subSubClass' => 0, 'repairPrice' => 0, 'slot' => 0, 'model' => '', 'eventId' => 0, 'gemEnchantmentId' => 0];
        insertRows('::items', [
            ['id' => 1, 'cuFlags' => 0] + $item, ['id' => 2, 'cuFlags' => CUSTOM_UNAVAILABLE] + $item,
            ['id' => 3, 'cuFlags' => CUSTOM_EXCLUDE_FOR_LISTVIEW] + $item
        ]);
        $db->query('INSERT INTO ::sounds %v', ['id' => 1, 'cat' => 0, 'name' => 'Fixture sound', 'flags' => 0]);
        // Every editorial status, with and without an approved revision. A newer
        // pending article must not determine the sitemap language or timestamp.
        $id = 0; $expected = []; $date = 1700000000;
        foreach ([1, 2, 3, 4, 5] as $status) foreach ([0, 2] as $rev) {
            ++$id;
            $locale = $id % 2 ? 2 : 0;
            $db->query('INSERT INTO ::guides %v', [
                'id' => $id, 'status' => $status, 'rev' => $rev, 'url' => 'Guide-'.$id,
                'locale' => $locale, 'category' => 2, 'approveDate' => $date
            ]);
            foreach ([2, 3] as $articleRev) $db->query('INSERT INTO ::articles %v', [
                'type' => 300, 'typeId' => $id, 'locale' => $articleRev === 2 ? $locale : 2,
                'rev' => $articleRev, 'article' => $articleRev === 2 ? 'Published body' : 'SECRET_PENDING_BODY'
            ]);
            if ($status !== 5 && $rev) $expected[] = 'https://fixture.example/?guide=guide-'.$id;
        }
        insertRows('::guides', [
            ['id' => 20, 'status' => 3, 'rev' => 2, 'url' => 'edit', 'locale' => 2, 'category' => 3, 'approveDate' => 0],
            ['id' => 21, 'status' => 3, 'rev' => 2, 'url' => 'no-article', 'locale' => 0, 'category' => 1, 'approveDate' => $date],
            ['id' => 22, 'status' => 3, 'rev' => 2, 'url' => 'future', 'locale' => 2, 'category' => 4, 'approveDate' => time() + 86400]
        ]);
        foreach ([20, 22] as $guide) $db->query('INSERT INTO ::articles %v', [
            'type' => 300, 'typeId' => $guide, 'rev' => 2, 'locale' => 2, 'article' => 'Published French'
        ]);
        $_SERVER['QUERY_STRING'] = 'search=tracking&locale=2';
        $original = $_SERVER;
        $en = xml(Sitemap::generate('guide', 1));
        check(locations($en) === $expected, 'only exact approved bodies survive every status/revision combination');
        foreach ($en->url as $url) check((string)$url->lastmod === gmdate('c', $date), 'approval date stays stable for pending edits');
        $fr = xml(Sitemap::generate('guide', 1, Locale::FR));
        check(locations($fr) === ['https://fixture.example/?guide=20&locale=2', 'https://fixture.example/?guide=future&locale=2'], 'authored locale and safe numeric fallback share canonical policy');
        check(count($fr->url[0]->lastmod) === 0 && count($fr->url[1]->lastmod) === 0, 'unknown and future dates omitted');
        $items = xml($itemXml = Sitemap::generate('item', 1));
        check(locations($items) === ['https://fixture.example/?item=1'], 'hidden/unavailable records excluded and cookie ignored');
        check(count($items->url[0]->lastmod) === 0, 'no fabricated import or community update date');
        $queries = 0;
        $db->onEvent[] = static function (Dibi\Event $event) use (&$queries) : void { ++$queries; };
        check(Sitemap::generate('item', 1) === $itemXml, 'cached sitemap preserves XML byte for byte');
        check($queries === 0, 'warm catalog sitemap performs no SQL');
        check($_SERVER === $original, 'explicit URL generation never changes request globals');
        check(Sitemap::generate('sound', 1) === null && Sitemap::generate('areatrigger', 1) === null, 'disabled sounds and staff routes excluded');
        check(Sitemap::generate('guide', 0) === null && Sitemap::generate('guide', 2) === null, 'invalid and out-of-range pages rejected');
        check(Sitemap::generate('guide', 1, Locale::DE) === null, 'disabled locale rejected');
        check(Sitemap::generate('special', 2) === null && Sitemap::$maxPage === 1, 'single-page boundary reported');
        check(Sitemap::generate('unknown', 1) === null && Sitemap::$maxPage === 0, 'previous error bounds do not leak');
        $special = locations(xml(Sitemap::generate('special', 1)));
        check(in_array('https://fixture.example/?items', $special) && !in_array('https://fixture.example/?npcs', $special), 'populated browse hubs only');
        check(in_array('https://fixture.example/?guides=2', $special) && !in_array('https://fixture.example/?guides=1', $special), 'only populated public guide categories');
        check(!in_array('https://fixture.example/?profiler', $special), 'disabled profiler omitted');
        Cfg::$profiler = true; Cfg::$sounds = true;
        check(in_array('https://fixture.example/?profiler', locations(xml(Sitemap::generate('special', 1)))), 'enabled profiler retained');
        check(locations(xml(Sitemap::generate('sound', 1))) === ['https://fixture.example/?sound=1'], 'enabled sound route retained');
        Cfg::$sounds = false;
        // Cross the actual production chunk boundary with real inserts.
        for ($start = 1; $start <= 10001; $start += 1000) {
            $rows = [];
            for ($i = $start; $i < min($start + 1000, 10002); ++$i) $rows[] = ['id' => $i, 'name' => 'fixture-'.$i];
            insertRows('::icons', $rows);
        }
        $page1 = locations(xml(Sitemap::generate('icon', 1)));
        $page2 = locations(xml(Sitemap::generate('icon', 2)));
        check(count($page1) === 10000 && $page2 === ['https://fixture.example/?icon=10001'], 'deterministic chunk boundary has no omissions or duplicates');
        check(count(array_intersect($page1, $page2)) === 0, 'chunks do not overlap');
        $index = locations(xml(Sitemap::generate('', 1)));
        check(in_array('https://fixture.example/?sitemap=icon&page=2', $index), 'index advertises last chunk');
        check(in_array('https://fixture.example/?sitemap=icon&locale=2&page=2', $index), 'localized sitemap query ordering and XML escaping');
        check(!array_filter($index, fn($url) => str_contains($url, 'sound') || str_contains($url, 'areatrigger')), 'index excludes disabled/private families');
        foreach ($index as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $locale = Locale::tryFromRequest($query['locale'] ?? 0);
            $child = xml(Sitemap::generate($query['sitemap'], (int)($query['page'] ?? 1), $locale));
            check(count($child->children()) > 0 && count($child->children()) <= 50000, 'every advertised child resolves within protocol limit');
            foreach ($child->url as $entry) {
                parse_str(parse_url((string)$entry->loc, PHP_URL_QUERY) ?: '', $identity);
                check(PageTemplate::canonicalUrl($identity ?: ['home' => ''], add: ['locale' => $locale->value]) === (string)$entry->loc, 'sitemap entry matches shared canonical identity');
            }
        }
        foreach (['0', '-1', 'garbage', ['2'], '999999999999999999999999'] as $invalid) {
            $_GET = ['page' => $invalid];
            try { (new SitemapBaseResponse('item'))->run(); check(false, 'malformed endpoint page must be 404'); }
            catch (OutOfBoundsException) { check(true, 'malformed page rejected'); }
        }
        $_GET = ['locale' => '2'];
        check(locations(xml((new SitemapBaseResponse('item'))->run())) === ['https://fixture.example/?item=1&locale=2'], 'endpoint forwards explicit locale');
        $db->query('UPDATE ::guides SET status = 5');
        $_GET = [];
        try { (new SitemapBaseResponse('guide'))->run(); check(false, 'withdrawn guide sitemap must disappear'); }
        catch (OutOfBoundsException) { check(true, 'publication withdrawal takes effect immediately'); }
        check(!str_contains((new SitemapBaseResponse(''))->run(), 'sitemap=guide'), 'fresh index removes withdrawn guide family');
        echo "PASS: $checks sitemap XML, canonical, locale, visibility, dates, pagination and endpoint checks\n";
    }
    finally { foreach (array_reverse($tables) as $table) $db->query('DROP TABLE IF EXISTS %n', $table); }
}
