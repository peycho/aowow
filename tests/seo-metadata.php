<?php

// Real metadata generation, markup, cached PageTemplate and head rendering;
// synthetic DB values only, no application configuration or external requests.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return match ($key) {
            'NAME' => 'Fixture & Co', 'HOST_URL' => 'https://fixture.example',
            'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D, default => ''
        }; }
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
    }
    class User {
        public static function isInGroup(int $mask) : bool { return false; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class DB {
        public static ?string $manual = null;
        public static function Aowow() : self { return new self; }
        public function selectCell(string $query, mixed ...$args) : mixed { return self::$manual; }
    }
    class Turnstile { public static function clientConfig() : array { return ['actions' => []]; } }
    class ExternalLinks { public static function urls() : array { return []; } }
}
namespace {
    use Aowow\{DB, Lang, Locale, Markup, SeoMetadata};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 75); define('CLI', true);
    chdir(dirname(__DIR__));
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/guidemgr.class.php', 'includes/components/SmartAI/SmartAI.class.php',
        'includes/components/SmartAI/SmartEvent.class.php', 'includes/components/SmartAI/SmartAction.class.php',
        'includes/components/SmartAI/SmartTarget.class.php', 'localization/lang.class.php',
        'includes/components/csrf.class.php', 'includes/components/jsexpression.class.php',
        'includes/components/frontend/markup.class.php', 'includes/components/frontend/tabs.class.php',
        'includes/components/frontend/listview.class.php', 'includes/components/pagetemplate.class.php',
        'includes/components/response/baseresponse.class.php', 'includes/components/response/templateresponse.class.php',
        'includes/components/seometadata.class.php'] as $file) require $file;
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    class MetadataFixture extends Aowow\TemplateResponse {
        use Aowow\TrListPage;
        public function __construct(string $route, int $page = 1) {
            $this->pageName = $this->fullParams = $route;
            $this->pagination = ['page' => $page];
        }
        public function metadata(string $generated, ?Markup $article = null, bool $useArticle = true) : array {
            $this->article = $article;
            $this->buildBasicMetadata($generated, useArticle: $useArticle);
            $tags = [];
            foreach ($this->metaTags as $tag) $tags[$tag['name'] ?? $tag['property']] = $tag['content'];
            return $tags;
        }
    }
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function head(string $query, array $data, bool $cache = false) : array {
        $_SERVER['QUERY_STRING'] = $query;
        $template = new PageTemplate('text-page-generic');
        (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($template, $data);
        if ($cache) $template = unserialize(serialize($template));
        (new ReflectionMethod(PageTemplate::class, 'update'))->invoke($template);
        ob_start(); (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'head'); $html = ob_get_clean();
        $dom = new DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return [new DOMXPath($dom), $html];
    }
    foreach ([0 => 'Page', 2 => 'Page', 3 => 'Seite', 4 => '页', 6 => 'Página', 8 => 'Страница'] as $value => $pageWord) {
        $locale = Locale::from($value); Lang::load($locale);
        $parts = ['Épée & armure', 'Armes', 'Objets', 'Fixture & Co'];
        $data = ['title' => $parts, 'metaTags' => [['property' => 'og:title', 'content' => 'OLD'], ['name' => 'viewport', 'content' => 'initial-scale=0.85']],
            'pagination' => ['page' => 2], 'rss' => '/?rss'];
        foreach ([false, true] as $cache) {
            [$xp, $html] = head('items&page=2&locale='.$value, $data, $cache);
            $expected = 'Épée & armure — Objets — '.$pageWord.' 2 | WotLK 3.3.5 | Fixture & Co';
            check($xp->query('//title')->length === 1 && $xp->query('//title')->item(0)->textContent === $expected, 'localized title uses topic, broad type, validated page and brand: '.($xp->query('//title')->item(0)?->textContent ?? 'missing'));
            check($xp->query('//meta[@property="og:title"]')->item(0)->getAttribute('content') === $expected, 'Open Graph and browser titles agree, including cache hits');
            check($xp->query('//meta[@name="viewport"]')->item(0)->getAttribute('content') === 'width=device-width, initial-scale=1', 'cached viewport follows the current responsive policy');
            check($xp->query('//link[@type="application/rss+xml"]')->item(0)->getAttribute('title') === $expected, 'RSS title attribute uses the same escaped title');
        }
        // Validated response state, not the raw page query, controls the label.
        $data['pagination']['page'] = 1;
        [$first] = head('items&page=2&locale='.$value, $data);
        check(!str_contains($first->query('//title')->item(0)->textContent, $pageWord.' 2'), 'unvalidated page input never creates a title suffix');
        $generated = (new MetadataFixture('items', 2))->metadata('<p>Facts&nbsp; about <b>items</b>.</p><script>SECRET_SCRIPT</script><style>SECRET_STYLE</style>');
        check($generated['viewport'] === 'width=device-width, initial-scale=1', 'real response uses a zoomable device-width viewport');
        check($generated['description'] === $pageWord.' 2. Facts about items.', 'generated descriptions are plain text with localized page identity');
        check($generated['og:description'] === $generated['description'], 'social and search descriptions agree');
        $article = new Markup('[h2]Approved heading[/h2]This [b]published[/b] body is readable.', ['allow' => Markup::CLASS_STAFF]);
        $summary = (new MetadataFixture('guide'))->metadata('Generated fallback', $article);
        check(str_contains($summary['description'], 'published') && !str_contains($summary['description'], '[b]') && !str_contains($summary['description'], 'Generated fallback'), 'selected article has priority over generated fallback');
        $empty = new Markup('   ', ['allow' => Markup::CLASS_STAFF]);
        check((new MetadataFixture('guide'))->metadata('Resolved fallback.', $empty)['description'] === 'Resolved fallback.', 'empty article falls back to facts');
        check((new MetadataFixture('guide'))->metadata('Approved description.', $article, false)['description'] === 'Approved description.', 'article opt-out preserves approved guide description');
        $long = str_repeat('Readable facts. ', 100);
        $bounded = SeoMetadata::description($long, $locale);
        check(mb_strlen($bounded) <= 240 && str_ends_with($bounded, '…') && mb_check_encoding($bounded, 'UTF-8'), 'unbounded generated summaries get a safe word boundary');
        $cjk = SeoMetadata::description(str_repeat('数据库', 200), $locale);
        check(mb_strlen($cjk) === 240 && mb_check_encoding($cjk, 'UTF-8'), 'unspaced text remains valid UTF-8');
        DB::$manual = 'Manual "description" & <literal text>. '.str_repeat('Keep this. ', 40);
        $manual = (new MetadataFixture('items', 2))->metadata('Ignored fallback', $article);
        check($manual['description'] === DB::$manual && $manual['og:description'] === DB::$manual, 'manual overrides preserve wording and length without page prefixes');
        $tags = [['name' => 'description', 'content' => $manual['description']], ['property' => 'og:title', 'content' => 'OLD']];
        [$xp] = head('item=1&locale='.$value, ['title' => ['Entity', 'Item', 'Fixture & Co'], 'metaTags' => $tags]);
        check($xp->query('//meta[@name="description"]')->item(0)->getAttribute('content') === DB::$manual, 'manual description survives actual HTML attribute encoding');
        DB::$manual = null;
        [$home] = head('home&locale='.$value, ['title' => ['Fixture & Co']]);
        check(str_contains($home->query('//title')->item(0)->textContent, ' | WotLK 3.3.5 | Fixture & Co'), 'home title describes database and version in every locale');
        $manualTitle = 'Fixture & Co: Editors’ selected title';
        [$home] = head('home&locale='.$value, ['title' => ['Fixture & Co'], 'homeTitle' => $manualTitle, 'metaTags' => [['property' => 'og:title', 'content' => 'OLD']]], true);
        check($home->query('//title')->item(0)->textContent === $manualTitle && $home->query('//meta[@property="og:title"]')->item(0)->getAttribute('content') === $manualTitle, 'manual home title is present in source, cache and social metadata');
    }
    Lang::load(Locale::EN);
    $attack = '</title><img src=x onerror=alert(1)><script>ATTACK</script>" & entity';
    [$xp, $html] = head('item=1', ['title' => [$attack, 'Item', 'Fixture & Co'], 'metaTags' => [['property' => 'og:title', 'content' => $attack]], 'rss' => '/?rss']);
    check($xp->query('//img')->length === 0 && !str_contains($html, '<script>ATTACK'), 'title and RSS values cannot break out of their HTML contexts');
    check(str_contains($xp->query('//title')->item(0)->textContent, $attack), 'hostile-looking literal entity names remain text');
    [$xp] = head('search=Entity', ['title' => ['Search', 'Fixture & Co']]);
    check($xp->query('//title')->item(0)->textContent === 'Search - Fixture & Co', 'excluded routes retain their existing title convention');
    [$xp] = head('guide=1&rev=2', ['title' => ['Historical revision', 'Guides', 'Fixture & Co'], 'seo' => ['index' => false]]);
    check($xp->query('//title')->item(0)->textContent === 'Historical revision - Guides - Fixture & Co', 'historical revisions do not acquire public discovery titles');
    check(SeoMetadata::title(['Same brand', 'Item', 'Same brand'], 'Same brand', Locale::EN) === 'Same brand — Item | WotLK 3.3.5 | Same brand', 'an entity sharing the brand is retained');
    check(SeoMetadata::description('Value &lt; 5 and C:\\data.', Locale::EN) === 'Value < 5 and C:\\data.', 'literal brackets and meaningful backslashes survive summaries');
    check(!str_contains(file_get_contents('template/pages/home.tpl.php'), 'document.title'), 'homepage no longer replaces the server title after JavaScript loads');
    echo "PASS: $checks metadata title, summary, override, locale, cache and HTML safety checks\n";
}
