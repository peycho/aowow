<?php

// Real guide-list response, localized defaults and shared HTML bricks; synthetic DB only.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return match ($key) {
            'NAME' => 'Fixture', 'HOST_URL' => 'https://fixture.example', 'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D, default => 0
        }; }
        public static function applyToString(string $value, bool $numbers = true) : string { return $value; }
    }
    class User {
        public static int $groups = 0;
        public static function isInGroup(int $group) : bool { return !$group; }
        public static function canWriteGuide() : bool { return false; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class DB {
        public static bool $editorial = false;
        public static ?string $manual = null;
        public static array $categories = [2, 5];
        public static function Aowow() : self { return new self; }
        public function selectCell(string $sql, mixed ...$args) : mixed { return str_contains($sql, 'seo_descriptions') ? self::$manual : null; }
        public function selectCol(string $sql, mixed ...$args) : array { return self::$categories; }
        public function selectRow(string $sql, mixed ...$args) : array {
            return self::$editorial && str_contains($sql, 'FROM ::articles') ? ['article' => '[p]Editorial introduction.[/p]', 'locale' => Lang::getLocale()->value, 'editAccess' => U_GROUP_STAFF] : [];
        }
        public const string OR = '%or';
    }
    class GuideList {
        public static string $brickFile = 'guide';
        public function __construct(array $conditions, array $options = []) {}
        public function getMatches() : int { return 101; }
        public function getListviewData() : array { return [['id' => 1, 'title' => 'Published guide', 'url' => '?guide=public-guide']]; }
    }
}
namespace {
    use Aowow\{DB, GuideDiscovery, GuidesBaseResponse, Lang, Locale};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 75); define('CLI', true);
    chdir(dirname(__DIR__));
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/SmartAI/SmartAI.class.php', 'includes/components/SmartAI/SmartEvent.class.php',
        'includes/components/SmartAI/SmartAction.class.php', 'includes/components/SmartAI/SmartTarget.class.php', 'localization/lang.class.php',
        'includes/components/guidemgr.class.php', 'includes/components/guidediscovery.class.php', 'includes/components/csrf.class.php', 'includes/components/jsexpression.class.php',
        'includes/components/frontend/markup.class.php', 'includes/components/frontend/tabs.class.php', 'includes/components/frontend/listview.class.php',
        'includes/components/pagetemplate.class.php', 'includes/components/response/baseresponse.class.php', 'includes/components/response/templateresponse.class.php',
        'endpoints/guides/guides.php'] as $file) require $file;
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    $checks = 0; $fixtures = [];
    function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    function response(int $category = 0, int $page = 1) : array {
        $_GET = $page > 1 ? ['page' => (string)$page] : [];
        $_SERVER['QUERY_STRING'] = 'guides'.($category ? '='.$category : '').($page > 1 ? '&page='.$page : '');
        $response = new GuidesBaseResponse($category ? (string)$category : '');
        (new ReflectionMethod(GuidesBaseResponse::class, 'generate'))->invoke($response);
        $template = (new ReflectionProperty(Aowow\BaseResponse::class, 'result'))->getValue($response);
        ob_start(); (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'guideHub'); $html = ob_get_clean();
        $dom = new DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return [$response, $template, new DOMXPath($dom), $html];
    }
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::load($locale);
        foreach (range(0, 9) as $category) {
            [$response, $template, $xpath, $html] = response($category);
            check($xpath->query('//p[@id="guide-introduction"]')->item(0)->textContent === Lang::guide('intro', $category), 'localized first-page introduction');
            check($response->h1 === ($category ? Lang::guide('category', $category) : Lang::game('guides')), 'category heading matches the selected topic');
            check($xpath->query('//nav[@id="guide-categories"]//a')->length === 3, 'root and available public categories are linked');
            foreach ($xpath->query('//nav[@id="guide-categories"]//a') as $link) {
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                check(($query['locale'] ?? 0) == $locale->value && array_key_first($query) === 'guides', 'category links retain locale and routing order');
            }
            if ($category === 2) $fixtures[] = ['locale' => $locale->value, 'html' => $html];
        }
        [$response, $template, $xpath] = response(2, 2);
        check($xpath->query('//p[@id="guide-introduction"]')->length === 0 && $xpath->query('//nav')->length === 1, 'later pages retain navigation without repeating the introduction');
        DB::$editorial = true;
        [$response, $template, $xpath] = response(2);
        check($response->guideIntro === '' && str_contains((string)$response->article, 'Editorial introduction'), 'existing editorial article takes precedence');
        $description = array_find($response->metaTags, fn($tag) => ($tag['name'] ?? '') === 'description')['content'];
        check(str_contains($description, 'Editorial introduction'), 'editorial copy remains the description source');
        DB::$manual = 'Manual description.';
        [$response] = response(2);
        check(array_find($response->metaTags, fn($tag) => ($tag['name'] ?? '') === 'description')['content'] === DB::$manual, 'manual SEO description remains authoritative');
        DB::$manual = null; DB::$editorial = false;
        $template = new PageTemplate('detail-page-generic');
        (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($template, ['relatedGuides' => [
            ['href' => 'https://fixture.example/?guide=public-guide&locale='.$locale->value, 'title' => 'A long guide title <script>alert(1)</script> & "quoted" '.str_repeat('Equipment ', 12)],
            ['href' => 'https://fixture.example/?guide=2', 'title' => Lang::game('guide').' #2']
        ]]);
        ob_start(); (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'relatedGuides'); $html = ob_get_clean();
        check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'related titles cannot inject HTML');
        check(str_contains($html, Lang::guide('relatedGuides')), 'related heading is localized');
        $fixtures[array_key_last($fixtures)]['html'] .= $html;
    }
    if (in_array('--fixtures', $argv, true)) echo json_encode($fixtures, JSON_THROW_ON_ERROR);
    else echo "PASS: $checks guide category, introduction, editorial and HTML checks\n";
}
