<?php

namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed {
            return match ($key) { 'NAME' => 'Fixture', 'HOST_URL' => 'https://fixture.example',
                'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D, default => 0 };
        }
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
    }
    class User {
        public static int $groups = 0;
        public static function isInGroup(int $mask) : bool { return !$mask; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
        public static function getCharacters() : array { return []; }
    }
    class DB {
        public static function Aowow() : self { return new self; }
        public function selectCell(string $query, mixed ...$args) : mixed { return null; }
    }
}
namespace {
    use Aowow\{Lang, Locale, Markup, InfoboxMarkup, Listview, ServerContent};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 75); define('CLI', true);
    chdir(dirname(__DIR__)); $root = getcwd();
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/guidemgr.class.php',
        'includes/components/SmartAI/SmartAI.class.php', 'includes/components/SmartAI/SmartEvent.class.php',
        'includes/components/SmartAI/SmartAction.class.php', 'includes/components/SmartAI/SmartTarget.class.php',
        'localization/lang.class.php', 'includes/components/csrf.class.php', 'includes/components/jsexpression.class.php',
        'includes/components/frontend/markup.class.php', 'includes/components/frontend/infoboxmarkup.class.php',
        'includes/components/frontend/listview.class.php', 'includes/components/frontend/tabs.class.php',
        'includes/components/servercontent.class.php', 'includes/components/pagetemplate.class.php',
        'includes/components/response/baseresponse.class.php', 'includes/components/response/templateresponse.class.php'] as $file) require $file;
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message, 0, $code);
    });
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function domFor(string $html) : DOMXPath {
        $dom = new DOMDocument();
        $dom->loadHTML('<!doctype html><meta charset="utf-8"><body>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new DOMXPath($dom);
    }
    function safeContent(string $html) : void {
        $xpath = domFor($html);
        check($xpath->query('//script|//style|//iframe|//object|//embed|//svg|//math|//form')->length === 0, 'server content contains no active subtree');
        foreach ($xpath->query('//@*') as $attribute) {
            check(!str_starts_with(strtolower($attribute->nodeName), 'on') && $attribute->nodeName !== 'style', 'server content contains no event handlers or author CSS');
            if (in_array($attribute->nodeName, ['href', 'src'], true))
                check(!preg_match('/^(?:javascript|vbscript|data|file):/i', $attribute->nodeValue), 'server content contains no active URL scheme');
        }
    }
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::load($locale);
        $globals = [3 => ['g_items', [1 => ['name_'.$locale->json() => 'Sword <script>attack()</script>']], []]];
        $text = "[h2]A guide[/h2]First paragraph.\n\nSecond [b]paragraph[/b].\n[ul][li]One[/li][li][item=1][/li][/ul]\n[table]\n[tr][th]Name[/th][th]Value[/th][/tr]\n[tr][td]Gold[/td][td][money=12345][/td][/tr]\n[/table][unsupported]Readable fallback[/unsupported]";
        $markup = new Markup($text, ['allow' => Markup::CLASS_STAFF]);
        $html = $markup->renderHtml($globals); $xpath = domFor($html);
        check($xpath->query('//h2')->item(0)->textContent === 'A guide', 'guide heading is present without JS');
        check($xpath->query('//p')->length >= 2, 'plain paragraphs have paragraph elements');
        check($xpath->query('//ul/li')->length === 2 && $xpath->query('//table//tr')->length === 2, 'lists and tables are rendered');
        check(str_contains($html, 'Readable fallback') && str_contains($html, '1g 23s 45c'), 'unsupported containers preserve text and money remains readable');
        $href = '?item=1'.($locale === Locale::EN ? '' : '&locale='.$locale->value);
        check($xpath->query('//li/a')->item(0)->getAttribute('href') === $href, 'database reference keeps locale and existing route');
        check(str_contains($html, '&lt;script&gt;') && str_contains((string)$markup, 'Markup.printHtml('), 'database names remain data and JS enhancement remains available');
        safeContent($html);
        foreach (['grid', '"grid"', "'grid'"] as $class)
        {
            $grid = (new Markup('[table class='.$class.' onclick=attack()][tr][td=header]Requirement[/td][td=header]Location[/td][/tr][tr][td]Level 80[/td][td]Raid[/td][/tr][/table]', ['allow' => Markup::CLASS_STAFF]))->renderHtml();
            $gridXPath = domFor($grid);
            check($gridXPath->query('//table[@class="grid"]')->length === 1 && $gridXPath->query('//tr/th')->length === 2, 'native grid/header markup retains style and semantics in initial HTML');
            safeContent($grid);
        }
        $unsafeGrid = (new Markup('[table class="grid other" style="color:red" onmouseover=attack()][tr][td=header]Header[/td][/tr][/table]', ['allow' => Markup::CLASS_STAFF]))->renderHtml();
        check(domFor($unsafeGrid)->query('//table/@*')->length === 0, 'table class allowlist excludes arbitrary author attributes');
        safeContent($unsafeGrid);
        $box = new InfoboxMarkup(['Level: 80', '[url=?quest=5]Quest chain[/url]'], []);
        check(str_contains($box->renderHtml(), 'Quest chain'), 'quick facts are in initial HTML');
        check((new ReflectionProperty(Markup::class, '__text'))->getValue($box) === '', 'server quick facts do not mutate deferred JS/completion preparation');
        $list = new Listview(['id' => 'dropped-by', 'name' => 'Dropped by', 'data' => [['id' => 7, 'name' => 'Source NPC', 'percent' => 12.5]]], 'npc');
        $html = $list->renderHtml();
        check(str_contains($html, 'Source NPC') && str_contains($html, '12.5%') && str_contains($html, '?npc=7'), 'acquisition sources have crawlable names, links and chances');
        safeContent($html);
        foreach (['spell' => '@Spell name', 'itemset' => '4Set name'] as $template => $encodedName) {
            $decoded = (new Listview(['data' => [['id' => 1, 'name' => $encodedName]]], $template))->renderHtml();
            check(!str_contains(strip_tags($decoded), $encodedName) && str_contains($decoded, substr($encodedName, 1)), 'list labels omit client quality-sort prefixes');
        }
        $reflection = new ReflectionClass(PageTemplate::class);
        $page = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('locale')->setValue($page, $locale);
        $reflection->getProperty('context')->setValue($page, null);
        $reflection->getProperty('rawData')->setValue($page, [
            'type' => 6, 'typeId' => 7, 'tooltip' => ['inv_misc_questionmark', 0, true],
            'jsGlobals' => [6 => [[], [], [7 => [
                'tooltip_'.$locale->json() => '<table><tr><td>Damage: 100 <a href="?item=1" onclick="attack()">Source</a></td></tr></table>',
                'buff_'.$locale->json() => '<b>Duration: 30 seconds</b><script>attack()</script>'
            ]]]], 'pageTemplate' => ['pageName' => 'spell'], 'gPageInfo' => ['name' => 'Visible spell']
        ]);
        $brick = $reflection->getMethod('brick');
        ob_start();
        try { $brick->invoke($page, 'tooltip'); $tooltip = ob_get_contents(); }
        finally { ob_end_clean(); }
        $xpath = domFor($tooltip);
        check(str_contains($xpath->query('//*[@id="tt7"]')->item(0)->textContent, 'Damage: 100'), 'real tooltip brick includes initial spell facts');
        check($xpath->query('//*[@id="btt7"]//b')->item(0)->textContent === 'Duration: 30 seconds', 'real tooltip brick includes initial aura facts');
        check($xpath->query('//*[@id="tt7"]//*[@onclick]|//*[@id="btt7"]//script')->length === 0, 'initial tooltip brick sanitizes untrusted attributes and active HTML');
        ob_start();
        try { $brick->invoke($page, 'serverNavigation'); $brick->invoke($page, 'serverBreadcrumb'); $navigation = ob_get_contents(); }
        finally { ob_end_clean(); }
        $xpath = domFor($navigation);
        check($xpath->query('//nav[@id="server-navigation"]/a')->length === 8, 'real primary navigation contains crawlable category links');
        check($xpath->query('//nav[@id="server-breadcrumb"]//span')->item(0)->textContent === ' › Visible spell', 'real breadcrumb uses authorized visible entity name');
        foreach ($xpath->query('//nav//a') as $anchor)
            check(str_contains($anchor->getAttribute('href'), 'locale=') === ($locale !== Locale::EN), 'initial navigation retains non-English locale');
    }
    Lang::load(Locale::EN);
    $map = '[map zone=4812 floor=5][pin x=8.9 y=53.8 url=?npc=36678]Professor Putricide[/pin][/map]';
    $html = (new Markup($map, ['allow' => Markup::CLASS_STAFF]))->renderHtml();
    check(domFor($html)->query('//div[@class="server-map-pins"]//a[@href="?npc=36678"]')->length === 1
        && str_contains($html, '(8.9, 53.8)'), 'staff map fallback preserves clickable NPC and coordinates without scripts');
    safeContent($html);
    $quotedMap = (new Markup('[map zone="4812" floor="5"][pin x="0" y="99.9" url="?npc=36678"]Boss[/pin][/map]', []))->renderHtml();
    check(domFor($quotedMap)->query('//a[@href="?npc=36678"]')->length === 1 && str_contains($quotedMap, '(0, 99.9)'), 'quoted map attributes and coordinate limits retain native syntax');
    check(!str_contains((new Markup($map, ['allow' => Markup::CLASS_USER]))->renderHtml(), 'Professor Putricide'), 'restricted map contents match interactive permission boundary');
    foreach (['[pin x=1 y=2 url=?npc=1]Orphan[/pin]', '[map zone=1][pin x=100 y=2]Out of bounds[/pin][/map]',
        '[map zone=1][pin x=1 y=-2]Negative[/pin][/map]', '[map zone=1 floor=-1][pin x=1 y=2]Invalid floor[/pin][/map]'] as $invalid)
        check(!str_contains((new Markup($invalid, []))->renderHtml(), '('), 'invalid or orphan pins do not produce location claims');
    safeContent((new Markup('[map zone=1][pin x=1 y=2 url=javascript:attack()]Safe text[/pin][/map]', []))->renderHtml());
    $restricted = (new Markup('[h2]Readable[/h2][img src="https://static.fixture.example/uploads/guide/images/1.jpg"][money=10000]', ['allow' => Markup::CLASS_USER]))->renderHtml();
    check(!str_contains($restricted, '<h2>') && !str_contains($restricted, '<img') && !str_contains($restricted, '1g'), 'user markup keeps staff-only tags disabled');
    check(str_contains($restricted, 'Readable'), 'restricted wrappers retain readable text');
    check(!str_contains((new Markup('[h2]Readable[/h2]', ['roles' => 0]))->renderHtml(), '<h2>'), 'role-derived user permissions match JS');
    foreach ([
        '<img src=x onerror=attack()>Raw text',
        '[url=javascript:attack()]Bad URL[/url]', '[url=JaVaScRiPt:attack()]Bad[/url]',
        '[url=data:text/html,attack]Bad[/url]', '[url=//evil.example]Bad[/url]',
        '[url="https://safe.example/x"]Link x[/url]',
        '[img src="https://evil.example/x.png" onerror="attack()" alt="Readable alt"]',
        '[img src="https://static.fixture.example/uploads/temp/private.jpg"]',
        '[img src="https://static.fixture.example/uploads/guide/images/../private.jpg"]',
        '[img src="https://static.fixture.example/uploads/guide/images/%31.jpg"]',
        '[table onclick="attack()" style="display:none"][tr][td]Safe cell[/td][/tr][/table]',
        '[div class=hidden onmouseover=attack()]Readable[/div]',
        '[url=?item=1"onmouseover="attack()]Quoted[/url]',
        str_repeat('[b]', 100).'<svg onload=attack()>'.str_repeat('[/b]', 100),
        '[img src="https://static.fixture.example/uploads/guide/images/1.jpg" alt="" width="100" height="50"]'
    ] as $payload) safeContent((new Markup($payload, []))->renderHtml());
    $html = (new Markup('[img src="https://static.fixture.example/uploads/guide/images/1.jpg" alt="Approved" width="100" height="50"]', []))->renderHtml();
    check(domFor($html)->query('//img[@width="100" and @height="50" and @alt="Approved"]')->length === 1, 'approved image and bounded dimensions render');
    check(str_contains((new Markup('[url="https://safe.example/x"]Link[/url]', []))->renderHtml(), 'https://safe.example/x'), 'quoted URL ending in x is preserved');
    foreach ([
        '<table onclick="attack()"><tr><td class="q4">Item level 80 <a href="?item=1">Item</a></td></tr></table>',
        '<svg><foreignObject><script>attack()</script></foreignObject></svg><p>Facts</p>',
        '<a href="jav&#x61;script:attack()">Bad</a><img src=x onerror=attack()>',
        '<div style="background:url(javascript:attack())"><b>Damage: 100</b></div>',
        '<script src="https://evil.example"></script><iframe src="https://evil.example"></iframe>'
    ] as $payload) safeContent(ServerContent::safeHtml($payload));
    check(str_contains(ServerContent::safeHtml('<table><tr><td>Damage: 100</td></tr></table>'), 'Damage: 100'), 'generated tooltip facts remain readable');

    class PageNotFound extends RuntimeException {}
    class PaginationFixture extends Aowow\TemplateResponse {
        use Aowow\TrListPage;
        public int $type = 3;
        protected string $pageName = 'items';
        public function generateNotFound(string $title = '', string $msg = '') : never { throw new PageNotFound; }
        public function initialize() : void { $this->initPagination(); }
        public function total(int $total) : void { $this->setPagination($total); }
    }
    foreach (['0', '-1', '01', '1.5', '', '1oops', '999999999999999999999999', ['2']] as $invalid) {
        $_GET = ['page' => $invalid];
        $fixture = (new ReflectionClass(PaginationFixture::class))->newInstanceWithoutConstructor();
        try { $fixture->initialize(); check(false, 'invalid page must be rejected'); }
        catch (PageNotFound) { check(true, 'invalid page is rejected'); }
    }
    $_GET = ['page' => '2'];
    $fixture = (new ReflectionClass(PaginationFixture::class))->newInstanceWithoutConstructor();
    $fixture->initialize(); $fixture->total(151);
    check($fixture->pagination === ['page' => 2, 'pages' => 4, 'total' => 151, 'size' => 50], 'page counts and offsets use 50-row pages');
    $key = $fixture->getCacheKeyComponents();
    $fixture->listPage = 1;
    check($fixture->getCacheKeyComponents() !== $key, 'server pages have distinct application cache keys');
    $fixture->listPage = 5;
    try { $fixture->total(151); check(false, 'out-of-range page must be rejected'); }
    catch (PageNotFound) { check(true, 'out-of-range page is rejected'); }
    $_SERVER['QUERY_STRING'] = 'items=4.4&page=2&locale=2&filter=sl=9&utm_source=discard';
    check(PageTemplate::buildQuery() === 'https://fixture.example/?items=4.4&filter=sl%3D9&locale=2&page=2', 'canonical retains meaningful server page and normalized modifiers');
    check(PageTemplate::buildQuery(add: ['page' => 1]) === 'https://fixture.example/?items=4.4&filter=sl%3D9&locale=2', 'first page keeps established canonical route');
    if (!in_array('--browser', $argv, true)) { echo "PASS: $checks initial HTML, markup safety and pagination checks\n"; exit; }

    $article = new Markup('[h2]Guide heading[/h2]One public paragraph. [b]Strong text[/b]. [url=?item=1]An item[/url]', ['allow' => Markup::CLASS_STAFF]);
    $list = new Listview(['id' => 'serverfixture', 'name' => 'Sources', 'data' => [['id' => 1, 'name' => 'Source one'], ['id' => 2, 'name' => 'Source two']]], 'npc');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Initial HTML enhancement</title></head><body><pre id="result">PENDING</pre>';
    echo '<div id="article-generic">'.$article->renderHtml().'</div><div id="server-lists-generic">'.$list->renderHtml().'</div><div id="lv-generic"></div>';
    echo '<nav id="server-navigation"><a href="?items">Items</a></nav><div id="toptabs"></div>';
    echo '<script>window.fixtureErrors=[]; window.addEventListener("error", function(e) { fixtureErrors.push([e.message, e.filename, e.lineno, e.error && e.error.stack].join(": ")); }, true); window.g_staticUrl=""; window.g_host=""; window.g_user={debug:false,roles:0};</script>';
    foreach (['static/js/jquery-3.7.0.min.js', 'static/js/basic.js', 'setup/tools/filegen/templates/global.js/0_user.js', 'static/js/locale_enus.js',
        'setup/tools/filegen/templates/global.js/utilities.js',
        'setup/tools/filegen/templates/global.js/markup.js', 'setup/tools/filegen/templates/global.js/listview.js', 'setup/tools/filegen/templates/global.js/tabs.js', 'static/js/server-content.js'] as $file)
        echo '<script>{const source=document.createElement("script"); source.textContent='.json_encode(file_get_contents($file), JSON_HEX_TAG | JSON_THROW_ON_ERROR).';document.head.appendChild(source);}</script>';
    echo <<<'JS'
<script>
window.addEventListener('load', function () {
    var checks = 0;
    function check(ok, label) { ++checks; if (!ok) throw new Error(label); }
    try {
        check(document.querySelector('#article-generic h2').textContent === 'Guide heading', 'initial heading exists before enhancement');
        check(document.querySelectorAll('#server-lists-generic li').length === 2, 'initial source links exist');
        AowowServerContent.lists('server-lists-generic');
        check(!!document.getElementById('server-lists-generic'), 'failed or absent enhancement retains fallback');
        window.g_user = { debug: false, roles: 0 };
        window.g_staticUrl = ''; window.g_host = '';
        window.Locale = { getId: function () { return 0; }, getName: function () { return 'enus'; }, get: function () { return { domain: 'en' }; } };
        Markup.printHtml('[h2]Guide heading[/h2]One public paragraph. [b]Strong text[/b]. [url=?item=1]An item[/url]', 'article-generic', { allow: Markup.CLASS_STAFF });
        check(document.querySelectorAll('#article-generic h2').length === 1, 'enhanced markup replaces same container without duplication');
        check(document.querySelector('#article-generic b').textContent === 'Strong text', 'markup enhancement still runs');
        Listview.templates = { serverfixture: { columns: [{ id: 'name', name: 'Name', value: 'name', compute: function (row, td) { var a = document.createElement('a'); a.href = '?npc=' + row.id; a.textContent = row.name; td.appendChild(a); } }] } };
        var view = new Listview({ id: 'serverfixture', template: 'serverfixture', parent: 'lv-generic', data: [{ id: 1, name: 'Source one' }, { id: 2, name: 'Source two' }], poundable: false, searchable: false, sortable: true });
        check(!!view.container.firstElementChild, 'real Listview creates interactive content');
        AowowServerContent.lists('server-lists-generic');
        check(!document.getElementById('server-lists-generic'), 'successful list enhancement removes server fallback');
        check(document.querySelectorAll('#lv-generic a[href^="?npc="]').length === 2, 'enhanced source links are not duplicated');
        var tabHost = document.createElement('div'); tabHost.id = 'fixture-tabs'; document.body.appendChild(tabHost);
        var tabs = new Tabs({ parent: 'fixture-tabs', poundable: false });
        var pending = new Listview({ id: 'lazy-source', template: 'serverfixture', tabs: tabs, parent: 'lv-generic', data: [{ id: 3, name: 'Lazy source' }], poundable: false, searchable: false });
        // An eager data tab leaves the source list uninitialized until selected.
        tabs.add('Other', {id:'other'});
        var other = document.createElement('div'); other.id = 'tab-other'; document.body.appendChild(other);
        tabs.flush();
        // Register another lazy source after the first tab has rendered.
        var later = new Listview({ id: 'later-source', template: 'serverfixture', tabs: tabs, parent: 'lv-generic', data: [{ id: 4, name: 'Later source' }], poundable: false, searchable: false });
        tabs.flush();
        var fallback = document.createElement('div'); fallback.id = 'server-lazy';
        fallback.innerHTML = '<section data-listview="lazy-source">Initial source</section><section data-listview="later-source">Later source</section>';
        document.body.appendChild(fallback);
        AowowServerContent.lists('server-lazy');
        check(!document.getElementById('server-lazy'), 'tabbed fallback no longer duplicates content outside tabs');
        check(!!later.container.querySelector('section') && later.container.style.display === 'none', 'lazy fallback remains inside its hidden tab');
        tabs.show(later.tabIndex);
        check(!later.container.querySelector('section') && later.container.querySelectorAll('a[href^="?npc="]').length === 1, 'lazy initialization replaces fallback once');
        check(fixtureErrors.length === 0, 'browser scripts run without runtime errors');
        check(!!document.getElementById('server-navigation'), 'navigation fallback remains until actual navigation exists');
        document.getElementById('toptabs').innerHTML = '<a href="?items">Items</a>';
        AowowServerContent.navigation();
        check(!document.getElementById('server-navigation'), 'successful navigation enhancement removes fallback');
        document.getElementById('result').textContent = 'PASS: ' + checks + ' initial content enhancement browser checks';
    } catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message + ' | ' + fixtureErrors.join(' | '); }
});
</script></body></html>
JS;
}
