<?php

// Real URL policy, metadata refresh, serialized PageTemplate and head template.
// No application configuration, external requests or database is loaded.
namespace Aowow {
    class Cfg {
        public static int $locales = 0x15D;
        public static function get(string $key) : mixed {
            return match ($key) {
                'HOST_URL' => 'https://fixture.example', 'STATIC_URL' => 'https://static.fixture.example',
                'LOCALES' => self::$locales, default => ''
            };
        }
    }
    class Lang {
        public static Locale $locale = Locale::EN;
        public static function getLocale() : Locale { return self::$locale; }
    }
    class User {
        public static function isInGroup(int $group) : bool { return false; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class TemplateResponse {}
    class ExternalLinks { public static function urls() : array { return []; } }
    class Turnstile { public static function clientConfig() : array { return ['actions' => []]; } }
}
namespace {
    use Aowow\{Cfg, Lang, Locale};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 75);
    chdir(dirname(__DIR__));
    require 'includes/defines.php';
    require 'includes/locale.class.php';
    require 'includes/utilities.php';
    require 'includes/components/csrf.class.php';
    require 'includes/components/pagetemplate.class.php';
    require 'includes/components/guidemgr.class.php';
    require 'includes/components/jsexpression.class.php';
    require 'includes/components/frontend/listview.class.php';
    require 'includes/components/frontend/tabs.class.php';

    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }
    foreach ([
        '' => '',
        'home&utm_source=campaign' => '',
        'item=19019&utm_source=campaign&fbclid=123&refresh=filecache&foo=bar' => '/?item=19019',
        'quest=7786&filter=ignored&page=2' => '/?quest=7786',
        'spell=133&level=80' => '/?spell=133',
        'items&filter=qu=4;minle=80&utm_medium=test' => '/?items&filter=qu%3D4%3Bminle%3D80',
        'items=2.1&locale=2&filter=qu=4' => '/?items=2.1&filter=qu%3D4&locale=2',
        'items=2.1&filter=qu=4&locale=2' => '/?items=2.1&filter=qu%3D4&locale=2',
        'guide=shadowmourne&id=8&rev=4&utm_source=test' => '/?guide=shadowmourne&rev=4',
        'guide=edit&rev=2&id=8' => '/?guide=edit&id=8&rev=2',
        'guide=changelog&rev=2&id=8' => '/?guide=changelog&id=8',
        'search=Icecrown+Citadel%26x%3D1%23section' => '/?search=Icecrown%20Citadel%26x%3D1%23section',
        'search=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E' => '/?search=%22%3E%3Cscript%3Ealert%281%29%3C%2Fscript%3E',
        'items&filter[]=invalid&locale[]=2' => '/?items',
        'item[]=19019' => '/?item',
        'item=19019&locale=1' => '/?item=19019', // Unsupported locale.
        'item=19019&locale=99999999999999999999999' => '/?item=19019',
        'item=19019&locale=2oops' => '/?item=19019',
        'item=19019&locale=02' => '/?item=19019&locale=2',
        'item=19019&locale=0' => '/?item=19019',
        'sitemap=item&page=2&utm_source=test' => '/?sitemap=item&page=2',
    ] as $query => $suffix) {
        $_SERVER['QUERY_STRING'] = $query;
        check(PageTemplate::buildQuery() === 'https://fixture.example'.$suffix, 'normalized URL: '.$query);
        check(PageTemplate::buildQuery(host: 'https://other.example/') === 'https://other.example'.($suffix ?: '/'), 'configured host override: '.$query);
    }
    $_SERVER['QUERY_STRING'] = 'item=19019&locale=2&filter=ignored';
    check(PageTemplate::buildQuery(add: ['locale' => 3]) === 'https://fixture.example/?item=19019&locale=3', 'locale alternate replaces current locale');
    check(PageTemplate::buildQuery(remove: ['locale']) === 'https://fixture.example/?item=19019', 'explicit removal works');
    Cfg::$locales = 1;
    check(PageTemplate::buildQuery() === 'https://fixture.example/?item=19019', 'disabled locales are omitted');
    Cfg::$locales = 0x15D;

    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::$locale = $locale;
        $_SERVER['QUERY_STRING'] = 'item=19019&utm_source=cache';
        $expected = 'https://fixture.example/?item=19019'.($locale === Locale::EN ? '' : '&locale='.$locale->value);
        check(PageTemplate::buildQuery() === $expected, 'rendered locale is represented: '.$locale->name);
        $_SERVER['QUERY_STRING'] = '';
        check(PageTemplate::buildQuery(add: ['locale' => $locale->value]) === 'https://fixture.example'.($locale === Locale::EN ? '' : '/?home&locale='.$locale->value), 'home alternate keeps routing pair first');

        // Prime the cache with request-specific stale URLs, then serve a second
        // request through the real update and head renderer.
        $template = new PageTemplate('text-page-generic');
        (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($template, [
            'title' => ['Fixture'], 'metaTags' => [
                ['property' => 'og:url', 'content' => 'https://fixture.example/?item=19019&utm_source=old'],
                ['name' => 'description', 'content' => 'Manual description stays intact'],
            ],
            'ldIntangible' => ['url' => 'old', 'mainEntityOfPage' => ['@id' => 'old']],
        ]);
        $cached = serialize($template);
        $_SERVER['QUERY_STRING'] = 'item=19019&locale='.$locale->value.'&utm_source=new&unknown[]=bad';
        $template = unserialize($cached);
        (new ReflectionMethod(PageTemplate::class, 'update'))->invoke($template);
        ob_start();
        (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'head');
        $html = ob_get_clean();
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $canonical = 'https://fixture.example/?item=19019'.($locale === Locale::EN ? '' : '&locale='.$locale->value);
        check($xpath->query('//link[@rel="canonical"]')->length === 1, 'one canonical');
        check($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href') === $canonical, 'head canonical matches policy');
        check($xpath->query('//meta[@property="og:url"]')->item(0)->getAttribute('content') === $canonical, 'cached Open Graph identity refreshes');
        $ld = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        check($ld['url'] === $canonical && $ld['mainEntityOfPage']['@id'] === $canonical, 'cached structured data identity refreshes');
        check($xpath->query('//meta[@name="description"]')->item(0)->getAttribute('content') === 'Manual description stays intact', 'manual metadata survives refresh');
        check(!str_contains($html, 'utm_source') && !str_contains($html, 'unknown'), 'tracking and array-shaped parameters never enter SEO output');
        check(str_contains($html, '&amp;locale=2'), 'HTML attributes escape URL separators');
        $alternates = $xpath->query('//link[@hreflang]');
        check($alternates->length === 7, 'six supported locale links and default');
        foreach ($alternates as $alternate) {
            $url = $alternate->getAttribute('href');
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            check(array_key_first($query) === 'item', 'alternate keeps routing pair first');
            check(($query['locale'] ?? null) !== '0', 'English alternate uses canonical bare URL');
        }
        $links = array_map(fn($node) => [$node->getAttribute('hreflang'), $node->getAttribute('href')], iterator_to_array($alternates));
        $firstLinks ??= $links;
        check($links === $firstLinks, 'all six locale versions emit reciprocal alternate sets');
    }
    Lang::$locale = Locale::EN;
    $_SERVER['QUERY_STRING'] = 'search=%22%3E%3Cimg%20src=x%20onerror=alert(1)%3E&locale=0';
    $template = new PageTemplate('text-page-generic');
    (new ReflectionMethod(PageTemplate::class, 'update'))->invoke($template);
    ob_start();
    (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'head');
    $html = ob_get_clean();
    check(!str_contains($html, '<img') && !str_contains($html, 'href="https://fixture.example/?search=">'), 'route values cannot escape SEO attributes');

    function headFixture(string $query, array $data = [], ?string $cache = null) : array {
        $_SERVER['QUERY_STRING'] = $query;
        $template = $cache !== null ? unserialize($cache) : new PageTemplate('text-page-generic');
        if ($cache === null)
            (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($template, $data + [
                'metaTags' => [['property' => 'og:url', 'content' => 'old']],
                'ldIntangible' => ['url' => 'old', 'mainEntityOfPage' => ['@id' => 'old']],
            ]);
        (new ReflectionMethod(PageTemplate::class, 'update'))->invoke($template);
        ob_start();
        (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'head');
        $html = ob_get_clean();
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return [new DOMXPath($dom), $template, $html];
    }
    foreach (['search=paladin', 'account=signin', 'admin=guides', 'my-guides', 'user=Someone',
        'profile=123', 'profiles', 'guild=123', 'guilds', 'arena-team=123', 'arena-teams',
        'guide=edit&id=5', 'guide=new', 'guide=changelog&id=5', 'items&filter=na=arbitrary',
        'items&filter[]=bad', 'unknown', 'item[]=1'] as $query) {
        [$xpath] = headFixture($query);
        check($xpath->query('//meta[@name="robots" and @content="noindex, follow"]')->length === 1, 'excluded HTML has noindex: '.$query);
        check($xpath->query('//link[@rel="canonical"]|//link[@hreflang]|//script[@type="application/ld+json"]')->length === 0, 'excluded HTML advertises no canonical, alternate or structured data: '.$query);
    }
    foreach (['', 'item=1', 'quest=1', 'npc=1', 'spell=1', 'objects', 'guides',
        'itemsets&filter=cl=2', 'items=4.4&filter=sl=9', 'items=4.-4&filter=na=Darkmoon%20Card', 'sounds&filter=ty=17',
        'items&filter=', 'item=1&filter=ignored', 'classes&filter=ignored'] as $query) {
        [$xpath] = headFixture($query);
        check($xpath->query('//meta[@name="robots"]')->length === 0 && $xpath->query('//link[@rel="canonical"]')->length === 1, 'useful public route is indexable: '.$query);
    }
    foreach (['guide=1', 'guide=shadowmourne'] as $query) {
        [$xpath] = headFixture($query, ['seo' => ['guide' => 'shadowmourne', 'locale' => Locale::FR, 'index' => true]]);
        check($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href') === 'https://fixture.example/?guide=shadowmourne&locale=2', 'guide aliases share authored-language canonical');
        check($xpath->query('//link[@hreflang]')->length === 0, 'single-language guide has no invented translation links');
    }
    [$xpath] = headFixture('guide=shadowmourne&rev=2', ['seo' => ['guide' => 'shadowmourne', 'index' => true]]);
    check($xpath->query('//meta[@name="robots"]')->length === 1 && $xpath->query('//link[@rel="canonical"]')->length === 0, 'historical/preview revision is noindex');
    foreach (['guides', 'itemsets&filter=cl=2'] as $query) {
        [$xpath] = headFixture($query);
        check($xpath->query('//link[@hreflang]')->length === 0, 'distinct guide collections and filters do not claim equivalent translations');
    }
    $tabs = new Aowow\Tabs([]);
    $tabs->addListviewTab(new Aowow\Listview(['data' => []]));
    [$xpath] = headFixture('items', ['lvTabs' => $tabs]);
    check($xpath->query('//meta[@name="robots"]')->length === 1, 'empty category is noindex');
    [$xpath] = headFixture('item=1', ['lvTabs' => $tabs]);
    check($xpath->query('//meta[@name="robots"]')->length === 0, 'empty related tabs do not exclude a valid detail');
    $tabs->addListviewTab(new Aowow\Listview(['data' => [['id' => 1]]]));
    [$xpath] = headFixture('items', ['lvTabs' => $tabs]);
    check($xpath->query('//meta[@name="robots"]')->length === 0, 'populated category remains indexable');
    [$xpath, $template] = headFixture('items&filter=na=arbitrary');
    [$xpath] = headFixture('items', cache: serialize($template));
    check($xpath->query('//meta[@name="robots"]')->length === 0 && $xpath->query('//script[@type="application/ld+json"]')->length === 1, 'request noindex does not contaminate serialized metadata');
    [$xpath] = headFixture('item=1', ['seo' => ['index' => false]]);
    check($xpath->query('//meta[@name="robots"]')->length === 1, '404/maintenance response overrides otherwise public request');
    foreach ([null, [], ['2'], '2oops', '2.0', '-1', 1, 5, 99, '99999999999999999999999'] as $invalid)
        check(Locale::tryFromRequest($invalid) === null, 'malformed/unsupported locale is rejected');
    foreach ([0, 2, 3, 4, 6, 8] as $valid)
        check(Locale::tryFromRequest((string)$valid) === Locale::from($valid), 'supported explicit locale accepted');
    Cfg::$locales = 1;
    check(Locale::tryFromRequest(2) === null, 'disabled explicit locale rejected');
    [$xpath] = headFixture('item=1', ['metaTags' => [['property' => 'og:locale:alternate', 'content' => 'fr_FR']]]);
    check($xpath->query('//meta[@property="og:locale:alternate"]')->length === 0, 'cached Open Graph alternates respect current locale configuration');
    Cfg::$locales = 0x15D;
    foreach (['' => '5', 'edit' => '5', 'NEW' => '5', '123' => '5', 'bad/slug' => '5', 'Guide-Slug' => 'guide-slug'] as $slug => $token)
        check(Aowow\GuideMgr::publicUrlToken((string)$slug, 5) === $token, 'guide URL token cannot dispatch to an action or another numeric ID');
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::$locale = $locale;
        $template = new PageTemplate('text-page-generic');
        $template->pageTemplate = ['pageName' => 'home'];
        ob_start();
        (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, 'pageTemplate');
        $html = ob_get_clean();
        check(str_contains($html, 'Locale.set('.$locale->value.');'), 'home language menu receives actual rendered locale');
    }
    echo "PASS: $checks SEO URL and cached-head checks\n";
}
