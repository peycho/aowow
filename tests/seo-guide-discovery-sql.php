<?php

// Real reference indexing, approval, publication joins and cached template hooks.
namespace Aowow {
    class Cfg {
        public static string $directory = '';
        public static function get(string $key) : mixed { return match ($key) {
            'HOST_URL' => 'https://fixture.example', 'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D,
            'CACHE_MODE' => 1, 'CACHE_DECAY' => 604800, 'CACHE_DIR' => self::$directory, default => ''
        }; }
        public static function applyToString(string $value, bool $numbers = true) : string { return $value; }
    }
    class User {
        public static int $id = 99;
        public static int $groups = 0;
        public static function isInGroup(int $group) : bool { return (bool)(self::$groups & $group); }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class TemplateResponse {}
}
namespace {
    use Aowow\{DB, DibiConnection, GuideDiscovery, GuideMgr, Lang, Locale, User};
    use Aowow\Template\PageTemplate;
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_seo') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_seo database.\n"); exit(1);
    }
    define('AOWOW_REVISION', 75); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    define('AOWOW_CACHE_KEY', str_repeat('22', 32));
    Aowow\Cfg::$directory = require __DIR__.'/fixtures/cache-directory.php';
    chdir(dirname(__DIR__));
    require 'includes/libs/autoload.php';
    foreach (['defines.php', 'database.php', 'locale.class.php', 'type.class.php', 'utilities.php', 'game/uitext.class.php',
        'components/SmartAI/SmartAI.class.php', 'components/SmartAI/SmartEvent.class.php', 'components/SmartAI/SmartAction.class.php',
        'components/SmartAI/SmartTarget.class.php', 'components/guidemgr.class.php', 'components/guidediscovery.class.php',
        'components/pagetemplate.class.php', 'components/errorlog.class.php', 'components/response/baseresponse.class.php', 'components/response/textresponse.class.php'] as $file)
        require 'includes/'.$file;
    require 'localization/lang.class.php';
    require 'endpoints/admin/guide.php';
    $db = new DibiConnection(['driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username' => 'root', 'password' => '',
        'database' => 'aowow_security_test_seo', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']]);
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW => [time() + 86400, 86400]]);
    $db->onEvent[] = [DB::class, 'profiler'];               // include the production transaction diagnostics path
    $checks = 0;
    function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    function seedGuide(int $id, int $status = 3, int $rev = 1, int $locale = 0, string $body = '[item=100]') : void {
        global $db;
        $db->query('INSERT INTO ::guides %v', ['id' => $id, 'status' => $status, 'rev' => $rev, 'locale' => $locale,
            'category' => $locale ? 3 : 2, 'title' => $status === 3 ? 'Public '.$id : 'SECRET_PENDING_TITLE',
            'name' => 'Public name', 'url' => $id === 8 ? 'edit' : 'guide-'.$id]);
        $db->query('INSERT INTO ::articles %v', ['type' => 300, 'typeId' => $id, 'rev' => max(1, $rev),
            'locale' => $locale, 'article' => $body, 'editAccess' => U_GROUP_STAFF]);
    }
    $tables = ['guides', 'articles', 'guide_references', 'guides_changelog'];
    try {
        $schema = file_get_contents('setup/sql/01-db_structure.sql');
        foreach ($tables as $table) {
            $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table);
            preg_match('/CREATE TABLE `aowow_'.preg_quote($table, '/').'` \(.*?\) ENGINE=.*?;/s', $schema, $match);
            check(isset($match[0]), 'production table exists: '.$table);
            $db->nativeQuery($match[0]);
        }
        Lang::load(Locale::EN);
        $body = '[item=100] [ITEM=100] [spell=200] [url=?quest=300]Quest[/url] [url=https://fixture.example/?npc=400]NPC[/url] '
            .'[url=https://foreign.example/?item=999]Foreign[/url] [url=javascript:alert(1)]Unsafe[/url] '
            .'[code][item=888][/code] [pre][item=777][/pre] \\[item=666] [item=-1] [item=0] [item=9999999999]';
        $refs = GuideDiscovery::references($body, U_GROUP_STAFF);
        check($refs === [['type' => 3, 'typeId' => 100], ['type' => 6, 'typeId' => 200], ['type' => 5, 'typeId' => 300], ['type' => 1, 'typeId' => 400]], 'visible local references are deduplicated; code, escaping, unsafe/external links and invalid IDs are excluded');
        seedGuide(1, body: '[item=100] [spell=200]');
        seedGuide(2, status: 2);
        $db->query('INSERT INTO ::articles %v', ['type' => 300, 'typeId' => 2, 'rev' => 2, 'locale' => 0, 'article' => '[item=999]', 'editAccess' => 2]);
        seedGuide(3, status: 5); seedGuide(4, status: 1, rev: 0); seedGuide(5, locale: 2);
        seedGuide(6, body: 'No references'); seedGuide(7); $db->query('DELETE FROM ::articles WHERE typeId = 7');
        seedGuide(8); seedGuide(9, body: '[item=100] [spell=200]');
        foreach (range(1, 9) as $id) check(GuideDiscovery::indexGuide($id), 'backfill public revision: '.$id);
        check(GuideDiscovery::indexRevision(2, 2, Locale::EN, '[item=999]', 2), 'unapproved revision may be indexed without becoming public');
        check(GuideDiscovery::indexRevision(3, 1, Locale::EN, '[item=100]', 2), 'stale archived index fixture');
        check(GuideDiscovery::indexRevision(4, 1, Locale::EN, '[item=100]', 2), 'stale unpublished index fixture');
        $rankQueries = 0;
        $db->onEvent[] = static function (Dibi\Event $event) use (&$rankQueries) : void {
            if (str_contains($event->sql, 'FROM aowow_guide_references r') || str_contains($event->sql, 'FROM `aowow_guide_references` r')) ++$rankQueries;
        };
        foreach ([0, U_GROUP_ADMIN] as $groups) {
            User::$groups = $groups;
            $links = GuideDiscovery::related(3, 100, Locale::EN);
            check(array_column($links, 'id') === [1, 2, 8, 9], 'live publication, missing-body and locale predicates apply to every reader');
            check($links[1]['title'] === 'Guide #2' && !str_contains(json_encode($links), 'SECRET'), 'pending metadata remains private even to staff in public discovery');
            check($links[2]['href'] === 'https://fixture.example/?guide=8', 'reserved action slug uses numeric canonical');
        }
        check($rankQueries === 1, 'warm public discovery reuses the ranked query across readers');
        check(GuideDiscovery::related(3, 999, Locale::EN) === [], 'new draft references are not discovery links');
        check(array_column(GuideDiscovery::related(3, 100, Locale::FR), 'id') === [5], 'related links follow authored locale');
        check(GuideDiscovery::related(3, 100, Locale::FR)[0]['href'] === 'https://fixture.example/?guide=guide-5&locale=2', 'non-English guide URL remains explicit');
        check(GuideDiscovery::related(300, 2, Locale::EN, 2) === [], 'unapproved source revision cannot infer related topics');
        $shared = GuideDiscovery::related(300, 1, Locale::EN, 1);
        check($shared[0]['id'] === 9 && !in_array(1, array_column($shared, 'id'), true), 'guide relations rank shared references and exclude self');
        check(GuideDiscovery::categories(Locale::EN) === [2] && GuideDiscovery::categories(Locale::FR) === [3], 'category hubs contain only available public article languages');

        // Exercise the actual approval method: index the exact revision it publishes.
        $admin = (new ReflectionClass(Aowow\AdminGuideResponse::class))->newInstanceWithoutConstructor();
        $approve = new ReflectionMethod(Aowow\AdminGuideResponse::class, 'update');
        check($approve->invoke($admin, 2, 3), 'real approval indexes before publication');
        check($db->selectCell('SELECT rev FROM ::guides WHERE id = 2') === 2, 'approval selects the indexed latest revision');
        check(array_column(GuideDiscovery::related(3, 999, Locale::EN), 'id') === [2], 'approval activates the new reference immediately');
        check(!in_array(2, array_column(GuideDiscovery::related(3, 100, Locale::EN), 'id'), true), 'old revision references disappear immediately');
        check($approve->invoke($admin, 2, 4), 'rejection keeps the approved revision readable');
        check(GuideDiscovery::related(3, 999, Locale::EN)[0]['title'] === 'Guide #2', 'rejection does not disclose mutable metadata');

        $many = implode(' ', array_map(fn($id) => '[item='.$id.']', range(1000, 1250)));
        check(GuideDiscovery::indexRevision(6, 1, Locale::EN, $many, 2), 'multi-batch reference replacement');
        check($db->selectCell('SELECT COUNT(*) FROM ::guide_references WHERE guideId = 6') === 251, 'all batched references persist');
        $db->query("CREATE TRIGGER discovery_fail BEFORE INSERT ON ::guide_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture rollback'");
        check(!GuideDiscovery::indexRevision(6, 1, Locale::EN, '[item=1]', 2), 'failed replacement is reported');
        check($db->selectCell('SELECT COUNT(*) FROM ::guide_references WHERE guideId = 6') === 251, 'transaction preserves previous index after failure');
        check(!$approve->invoke($admin, 1, 3), 'failed indexing prevents publication');
        $db->query('DROP TRIGGER discovery_fail');
        foreach (range(10, 18) as $id) { seedGuide($id); GuideDiscovery::indexGuide($id); }
        check(count(GuideDiscovery::related(3, 100, Locale::EN)) === 6, 'related results are bounded');

        // A serialized database detail must observe publication withdrawal live.
        $_GET = []; $page = new PageTemplate('detail-page-generic');
        (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($page, ['type' => 3, 'typeId' => 100, 'relatedGuides' => []]);
        $page->registerDisplayHook('relatedGuides', [GuideDiscovery::class, 'relatedHook']);
        $brick = new ReflectionMethod(PageTemplate::class, 'brick');
        ob_start(); $brick->invoke($page, 'relatedGuides'); $html = ob_get_clean();
        check(str_contains($html, 'id="related-guides"') && str_contains($html, 'href="https://fixture.example/?guide='), 'links render in initial HTML');
        $cache = serialize($page); $db->query('UPDATE ::guides SET status = 5');
        $page = unserialize($cache); ob_start(); $brick->invoke($page, 'relatedGuides'); $html = ob_get_clean();
        check(trim($html) === '', 'cached detail immediately removes withdrawn guides');
        foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
            Lang::load($locale);
            foreach (range(0, 9) as $category) check(Lang::guide('intro', $category) !== '', 'localized category introduction');
            check(Lang::guide('relatedGuides') !== '' && Lang::guide('browseTopics') !== '', 'localized discovery labels');
        }
        echo "PASS: $checks real SQL guide discovery, approval, references, cache and locale checks\n";
    }
    finally { foreach (array_reverse($tables) as $table) $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table); }
}
