<?php

namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return match ($key) {
            'NAME' => 'Fixture', 'HOST_URL' => 'https://fixture.example', 'STATIC_URL' => 'https://static.fixture.example',
            'LOCALES' => 0x15D, default => 0
        }; }
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
    }
    class User {
        public static int $groups = 0;
        public static function isInGroup(int $mask) : bool { return !$mask; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
        public static function getCharacters() : array { return []; }
    }
    class ExternalLinks { public static function urls() : array { return []; } }
    class Turnstile { public static function clientConfig() : array { return ['actions' => []]; } }
}
namespace {
    use Aowow\{DB, DibiConnection, DBTypeList, Lang, Locale};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_seo') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_seo database.\n"); exit(1);
    }
    define('AOWOW_REVISION', 75); define('CLI', false); define('CLI_HAS_E', false); define('OS_WIN', false);
    chdir(dirname(__DIR__)); $root = getcwd();
    require 'includes/libs/autoload.php';
    foreach (['includes/defines.php', 'includes/database.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/guidemgr.class.php',
        'includes/components/SmartAI/SmartAI.class.php', 'includes/components/SmartAI/SmartEvent.class.php',
        'includes/components/SmartAI/SmartAction.class.php', 'includes/components/SmartAI/SmartTarget.class.php',
        'localization/lang.class.php', 'includes/components/csrf.class.php', 'includes/components/jsexpression.class.php',
        'includes/components/frontend/markup.class.php', 'includes/components/frontend/listview.class.php', 'includes/components/frontend/tabs.class.php',
        'includes/components/pagetemplate.class.php', 'includes/components/dbtypelist.class.php',
        'includes/components/response/baseresponse.class.php', 'includes/components/response/templateresponse.class.php'] as $file) require $file;
    $db = new DibiConnection([
        'driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username' => 'root', 'password' => '',
        'database' => 'aowow_security_test_seo', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']
    ]);
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW => [time() + 86400, 86400]]);
    class PageList extends DBTypeList {
        public static int $type = 1;
        protected string $queryBase = 'SELECT p.*, p.`id` AS ARRAY_KEY FROM ::seo_pages p';
        protected array $queryOpts = ['p' => ['o' => 'p.`name`']];
        public function getListviewData(int $mask = 0) : array { return []; }
        public function getJSGlobals(int $mask = 0) : array { return []; }
        public function renderTooltip() : string { return ''; }
        public function ids() : array { return array_map('intval', $this->getFoundIDs()); }
    }
    class CategoryFixture extends Aowow\TemplateResponse implements Aowow\ICache {
        use Aowow\TrListPage, Aowow\TrCache;
        protected int $cacheType = CACHE_TYPE_LIST_PAGE;
        public int $type = 1;
        protected string $pageName = 'items';
        public function __construct() { $this->initPagination(); }
        protected function generate() : void {
            $rows = new PageList([['published', 1]], ['pagination' => $this->paginationOptions()]);
            $this->setPagination($rows->getMatches());
            $data = [];
            foreach ($rows->iterate() as $id => $row) $data[] = ['id' => $id, 'name' => $row['name'].' '.$id];
            $this->lvTabs = new Aowow\Tabs([]);
            $this->lvTabs->addListviewTab(new Aowow\Listview(['data' => $data], 'npc'));
            $this->result = new Aowow\Template\PageTemplate('list-page-generic', $this);
            $this->metaTags[] = ['property' => 'og:url', 'content' => 'old'];
        }
        protected function sumSQLStats() : void {}
        protected function display(bool $withError = false) : void {
            foreach ((new ReflectionProperty(Aowow\TemplateResponse::class, 'header'))->getValue($this) as $header) header(...$header);
            (new ReflectionProperty(Aowow\Template\PageTemplate::class, 'scripts'))->setValue($this->result, []);
            (new ReflectionMethod(Aowow\Template\PageTemplate::class, 'update'))->invoke($this->result);
            foreach (['head', 'lvTabs'] as $brick)
                (new ReflectionMethod(Aowow\Template\PageTemplate::class, 'brick'))->invoke($this->result, $brick);
        }
    }
    Lang::load(Locale::tryFromRequest($_GET['locale'] ?? null) ?? Locale::EN);
    if (PHP_SAPI === 'cli-server') {
        (new CategoryFixture())->process(); return;
    }
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    $tables = ['seo_pages', 'home_featuredbox', 'articles', 'seo_descriptions'];
    foreach ($tables as $table) $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table);
    $db->query('CREATE TABLE ::seo_pages (id INT PRIMARY KEY, name VARCHAR(100), published TINYINT NOT NULL, owner INT NOT NULL) ENGINE=InnoDB');
    // Error metadata paths use these empty real schema tables; no runtime config is loaded.
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (['home_featuredbox', 'articles', 'seo_descriptions'] as $table) {
        preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`aowow_'.preg_quote($table, '/').'` \(.*?\) ENGINE=.*?;/s', file_get_contents('setup/sql/01-db_structure.sql'), $schema);
        if (!$schema) throw new RuntimeException('Fixture schema missing: '.$table);
        $db->nativeQuery($schema[0]);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    for ($id = 1; $id <= 161; ++$id)
        $db->query('INSERT INTO ::seo_pages VALUES (%i, %s, %i, %i)', $id, $id <= 151 ? 'Public record' : 'SECRET_DRAFT', $id <= 151 ? 1 : 0, $id <= 151 ? 0 : 1);
    $process = null; $log = null;
    try {
        $seen = [];
        for ($page = 1; $page <= 4; ++$page) {
            $list = new PageList([300, ['published', 1]], ['pagination' => ['page' => $page, 'size' => 50]]);
            check($list->getMatches() === 151, 'total excludes unpublished rows before limit');
            check($list->ids() === range(($page - 1) * 50 + 1, min($page * 50, 151)), 'stable ordered page contains expected distinct IDs');
            $seen = array_merge($seen, $list->ids());
        }
        check($seen === range(1, 151), 'full traversal loses and duplicates no rows');
        $list = new PageList([['published', 1]], ['pagination' => ['page' => 5, 'size' => 50]]);
        check($list->error && $list->getMatches() === 151, 'out-of-range list preserves trustworthy total without first-page fallback');
        $list = new PageList([['owner', 999]], ['pagination' => ['page' => 1, 'size' => 50]]);
        check($list->error && $list->getMatches() === 0, 'empty category has zero rows and total');
        $list = new PageList([['published', 1]], ['pagination' => ['page' => PHP_INT_MAX, 'size' => 50]]);
        check($list->error, 'overflow-sized offset is not sent to SQL');
        foreach ([['page' => 0, 'size' => 50], ['page' => 1, 'size' => 0], ['page' => '2', 'size' => 50]] as $invalid) {
            try { new PageList([], ['pagination' => $invalid]); check(false, 'invalid SQL paging options must be rejected'); }
            catch (InvalidArgumentException) { check(true, 'invalid SQL paging options rejected'); }
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $log = tempnam(sys_get_temp_dir(), 'aowow-seo-pages-');
        $process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes);
        fclose($pipes[0]);
        for ($i = 0; $i < 100; ++$i) {
            $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
            if ($connection) { fclose($connection); break; }
            usleep(20000);
        }
        foreach ([0, 2, 3, 4, 6, 8] as $locale) {
            foreach ([1, 2, 4] as $page) {
                $query = 'items&page='.$page.'&locale='.$locale;
                $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
                $html = file_get_contents('http://'.$address.'/?'.$query, false, $context);
                check(str_contains($http_response_header[0], '200 OK'), 'valid category page returns HTTP 200');
                $dom = new DOMDocument(); $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($dom);
                check($xpath->query('//section[@data-listview="npcs"]//li')->length === ($page === 4 ? 1 : 50), 'initial HTML contains exactly the requested page');
                check(!str_contains($html, 'SECRET_DRAFT'), 'unpublished rows never enter HTML or JS data');
                $canonical = $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href');
                check(str_contains($canonical, 'page=') === ($page !== 1), 'only subsequent pages retain page in canonical');
                check(str_contains($canonical, 'locale=') === ($locale !== 0), 'page canonical retains supported non-English locale');
                check($xpath->query('//nav[@class="server-pagination"]//a[@rel="prev"]')->length === ($page > 1 ? 1 : 0), 'previous page link is crawlable');
                check($xpath->query('//nav[@class="server-pagination"]//a[@rel="next"]')->length === ($page < 4 ? 1 : 0), 'next page link is crawlable');
            }
        }
        foreach (['0', '-1', '01', '1.5', 'bad', '99999999999999999999999', '5', '2%26item%3D1', ['2']] as $invalid) {
            $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
            $html = file_get_contents('http://'.$address.'/?items&'.http_build_query(['page' => $invalid]), false, $context);
            check(str_contains($http_response_header[0], '404'), 'invalid or unavailable category page returns real HTTP 404');
            check(!str_contains($html, 'SECRET_DRAFT') && !str_contains($html, 'href="?npc='), 'invalid page exposes no fallback result rows');
        }
        check(!str_contains(file_get_contents($log), 'Fatal error'), 'SQL/HTTP pagination completes without runtime failures');
        echo "PASS: $checks real SQL and HTTP category pagination checks\n";
    }
    catch (Throwable $error) { if ($log) fwrite(STDERR, file_get_contents($log)); throw $error; }
    finally {
        if ($process) { proc_terminate($process); proc_close($process); }
        if ($log) unlink($log);
        foreach ($tables as $table) $db->query('DROP TABLE IF EXISTS %n', 'aowow_'.$table);
    }
}
