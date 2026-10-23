<?php

// Real PageTemplate, head and visible breadcrumbs; synthetic identity/content only.
namespace Aowow {
    class Cfg {
        public static string $host = 'https://fixture.example';
        public static string $name = 'Fixture & Co';
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
        public static function get(string $key) : mixed { return match ($key) {
            'NAME' => self::$name, 'HOST_URL' => self::$host,
            'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D, default => ''
        }; }
    }
    class User {
        public static function isInGroup(int $mask) : bool { return false; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class TemplateResponse {}
    class Turnstile { public static function clientConfig() : array { return ['actions' => []]; } }
    class ExternalLinks { public static function urls() : array { return []; } }
}
namespace {
    use Aowow\{Cfg, Lang, Locale};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 75);
    chdir(dirname(__DIR__));
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/SmartAI/SmartAI.class.php',
        'includes/components/SmartAI/SmartEvent.class.php', 'includes/components/SmartAI/SmartAction.class.php',
        'includes/components/SmartAI/SmartTarget.class.php', 'localization/lang.class.php', 'includes/components/csrf.class.php',
        'includes/components/pagetemplate.class.php', 'includes/components/frontend/tabs.class.php',
        'includes/components/frontend/listview.class.php', 'includes/components/guidemgr.class.php'] as $file) require $file;
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    $checks = 0;
    function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
    function fixture(string $query, array $data = [], ?string $cache = null) : array {
        $_SERVER['QUERY_STRING'] = $query;
        parse_str($query, $params);
        $route = array_key_first($params) ?? 'home';
        $template = $cache === null ? new PageTemplate('text-page-generic') : unserialize($cache);
        if ($cache === null)
            (new ReflectionProperty(PageTemplate::class, 'rawData'))->setValue($template, $data + [
                'pageTemplate' => ['pageName' => $route], 'title' => ['Visible title'], 'h1' => 'Visible title',
                'gPageInfo' => ['name' => 'Visible title'], 'metaTags' => [['name' => 'description', 'content' => 'Visible summary.']],
                'ldIntangible' => ['url' => 'https://stale.example/private', 'publisher' => ['name' => 'Site Logo']],
            ]);
        (new ReflectionMethod(PageTemplate::class, 'update'))->invoke($template);
        ob_start();
        foreach (['head', 'serverBreadcrumb'] as $brick)
            (new ReflectionMethod(PageTemplate::class, 'brick'))->invoke($template, $brick);
        $html = ob_get_clean();
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        check($scripts->length <= 1, 'one JSON-LD document per page');
        $ld = $scripts->length ? json_decode($scripts->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR) : [];
        return [$ld['@graph'] ?? [], $xpath, $html, serialize($template), $ld];
    }
    function node(array $graph, string $type) : ?array { return array_find($graph, fn($node) => $node['@type'] === $type); }
    if (in_array('--fixtures', $argv, true)) {
        Lang::load(Locale::EN);
        $fixtures = [];
        foreach (['item=1', 'items&page=2', 'guide=1', 'search=private', 'guide=2', 'guide=1&rev=1'] as $query) {
            $data = str_starts_with($query, 'guide=') ? ['seo' => ['index' => $query !== 'guide=2'], 'article' => 'Approved body'] : [];
            [$graph, $xpath] = fixture($query, $data);
            $html = '';
            foreach ($xpath->query('//link[@rel="canonical"]|//script[@type="application/ld+json"]|//nav[@id="server-breadcrumb"]') as $element)
                $html .= $element->ownerDocument->saveHTML($element);
            $fixtures[] = ['query' => $query, 'html' => $html, 'types' => array_column($graph, '@type')];
        }
        echo json_encode($fixtures, JSON_THROW_ON_ERROR);
        exit;
    }
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::load($locale);
        foreach (['', 'item=1', 'spell=1', 'quest=1', 'npc=1', 'items=4.4&page=2', 'guides=2', 'aboutus'] as $query) {
            [$graph, $xpath, $html, $cache, $ld] = fixture($query);
            check($ld['@context'] === 'https://schema.org', 'current schema vocabulary');
            $website = node($graph, 'WebSite'); $publisher = node($graph, 'Organization');
            $page = node($graph, str_starts_with($query, 'items=') || str_starts_with($query, 'guides=') ? 'CollectionPage' : 'WebPage');
            check($page !== null && node($graph, 'Article') === null, 'page type reflects rendered content');
            check($website['name'] === Cfg::$name && $publisher['name'] === Cfg::$name, 'configured site and publisher identity');
            check($website['url'] === Cfg::$host.'/' && $website['@id'] === Cfg::$host.'/#website', 'one locale-independent site identity');
            check($page['url'] === $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'), 'page identity matches canonical');
            check($page['inLanguage'] === $locale->hreflang('-') && $page['description'] === 'Visible summary.', 'rendered language and manual summary retained');
            check(!str_contains($html, 'stale.example') && !str_contains($html, 'Site Logo'), 'legacy cached publisher and URL cannot leak');
            $crumbs = node($graph, 'BreadcrumbList');
            $visible = $xpath->query('//nav[@id="server-breadcrumb"]');
            check(($crumbs !== null) === ($visible->length > 0), 'breadcrumb schema exists only for a visible trail');
            if ($crumbs) {
                $names = array_map(fn($element) => $element['name'], $crumbs['itemListElement']);
                $visibleNames = [];
                foreach ($xpath->query('//nav[@id="server-breadcrumb"]/*') as $element)
                    $visibleNames[] = trim(ltrim(trim($element->textContent), '› '));
                check($names === $visibleNames, 'schema labels match initial HTML');
                check(array_column($crumbs['itemListElement'], 'position') === range(1, count($names)), 'breadcrumb positions are contiguous');
                check(end($crumbs['itemListElement'])['item'] === $page['url'], 'breadcrumb ends at the current canonical page');
            }
            $ids = array_column($graph, '@id');
            check(count($ids) === count(array_unique($ids)), 'graph identities are unique');
            foreach ($graph as $entry)
                foreach (['publisher', 'isPartOf', 'breadcrumb', 'mainEntity', 'mainEntityOfPage'] as $property)
                    if (isset($entry[$property]['@id'])) check(in_array($entry[$property]['@id'], $ids, true), 'graph reference resolves: '.$property);
        }
        [$graph] = fixture('guide=1&utm_source=ignored', ['seo' => ['guide' => 'public-guide', 'index' => true, 'locale' => Locale::FR, 'author' => '007'], 'article' => '[p]Approved body.[/p]']);
        $article = node($graph, 'Article'); $page = node($graph, 'WebPage');
        check($article['url'] === 'https://fixture.example/?guide=public-guide&locale=2', 'article uses selected authored-language slug');
        check($article['headline'] === $page['name'] && $article['inLanguage'] === 'fr-FR', 'article headline and authored language match public content');
        check($article['mainEntityOfPage']['@id'] === $page['@id'] && $page['mainEntity']['@id'] === $article['@id'], 'article and page reference each other');
        check($article['author']['name'] === '007' && $article['author']['url'] === 'https://fixture.example/?user=007', 'numeric-looking author remains text with a real profile link');
        check(!isset($article['datePublished']) && !isset($article['dateModified']) && !isset($article['image']) && !isset($article['aggregateRating']), 'no invented publication dates, guide illustration or ratings');
    }
    Lang::load(Locale::EN);
    [$graph] = fixture('guide=1', ['seo' => ['index' => true], 'article' => 'Approved body']);
    check(!isset(node($graph, 'Article')['author']), 'hidden author is omitted');
    [$graph] = fixture('guide=1', ['seo' => ['index' => true], 'article' => '']);
    check(node($graph, 'Article') === null, 'empty body does not claim an article');
    foreach (['search=private', 'account=signin', 'admin=guides', 'guide=edit&id=1', 'guide=1&rev=1', 'items&filter=na=arbitrary', 'unknown'] as $query) {
        [$graph] = fixture($query, ['article' => 'SECRET'] + ($query === 'guide=1&rev=1' ? ['seo' => ['index' => true]] : []));
        check($graph === [], 'excluded route has no structured data: '.$query);
    }
    [$graph] = fixture('guide=1', ['seo' => ['index' => false], 'article' => 'SECRET']);
    check($graph === [], 'unpublished or archived guide has no structured data');
    [$graph] = fixture('items');
    check(count(node($graph, 'BreadcrumbList')['itemListElement']) === 2, 'repeated list-root URL is not duplicated in schema');

    $literal = '007 </script><img src=x onerror=alert(1)> & "quoted"';
    Cfg::$name = '007';
    [$graph, $xpath, $html, $cache] = fixture('item=1&utm_source=prime', ['gPageInfo' => ['name' => $literal]]);
    check(node($graph, 'Organization')['name'] === '007' && node($graph, 'WebPage')['name'] === $literal, 'literal names survive JSON serialization');
    check($xpath->query('//img[@onerror]')->length === 0, 'names cannot escape the JSON script or visible breadcrumb');
    Cfg::$host = 'https://replacement.example'; Cfg::$name = 'Replacement brand';
    [$graph, $xpath, $html] = fixture('item=1&utm_source=cache-hit', cache: $cache);
    check(node($graph, 'WebPage')['url'] === 'https://replacement.example/?item=1', 'serialized page refreshes canonical identity');
    check(node($graph, 'WebSite')['name'] === 'Replacement brand' && node($graph, 'Organization')['url'] === 'https://replacement.example/', 'serialized page refreshes deployment identity');
    check(!str_contains(json_encode($graph), 'fixture.example') && !str_contains($html, 'utm_source'), 'no stale deployment or tracking data in graph');
    echo "PASS: $checks structured data, visible breadcrumb, locale, cache and privacy checks\n";
}
