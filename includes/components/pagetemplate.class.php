<?php

namespace Aowow\Template;

use \Aowow\Util, \Aowow\Cfg, \Aowow\Lang, \Aowow\User;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class PageTemplate
{

    private const array SEO_DATABASE_ROUTES = [
        'achievement', 'achievements', 'areatrigger', 'areatriggers', 'class', 'classes',
        'currency', 'currencies', 'emote', 'emotes', 'enchantment', 'enchantments',
        'event', 'events', 'faction', 'factions', 'icon', 'icons', 'item', 'items',
        'itemset', 'itemsets', 'mail', 'mails', 'npc', 'npcs', 'object', 'objects',
        'pet', 'pets', 'quest', 'quests', 'race', 'races', 'skill', 'skills',
        'sound', 'sounds', 'spell', 'spells', 'title', 'titles', 'zone', 'zones'
    ];

    private const array SEO_LIST_ROUTES = [
        'achievements', 'areatriggers', 'classes', 'currencies', 'emotes', 'enchantments',
        'events', 'factions', 'icons', 'items', 'itemsets', 'mails', 'npcs', 'objects',
        'pets', 'quests', 'races', 'skills', 'sounds', 'spells', 'titles', 'zones', 'guides'
    ];

    private const array SEO_FILTER_ROUTES = [
        'items', 'itemsets', 'npcs', 'objects', 'quests', 'spells', 'achievements',
        'enchantments', 'areatriggers', 'sounds', 'icons', 'guilds', 'arena-teams', 'profiles'
    ];

    // Existing useful landing pages retained while category hubs are developed.
    // New arbitrary combinations require review rather than expanding crawl space.
    private const array SEO_REVIEWED_FILTERS = [
        'itemsets' => ['cl=1', 'cl=2', 'cl=3', 'cl=4', 'cl=5', 'cl=6', 'cl=7', 'cl=9', 'cl=11', 'ta=2'],
        'items=4.4' => ['sl=9', 'sl=10', 'cr=18;crs=1;crv=0;sl=3'],
        'items=4.3' => ['sl=5'],
        'items=4.2' => ['sl=10'],
        'items=4.-4' => ['na=Darkmoon Card'],
        'sounds' => ['ty=17']
    ];

    private const string GUIDE_RATING_TPL = "$(document).ready(function() { $('#guiderating').append(GetStars(%.10F, %s, %u, %u)); });\n";

    private readonly \Aowow\Locale $locale;

    private array $displayHooks = [];

    private array $dataLoader = [];
    private array $scripts    = [];
    private array $js         = [];
    private array $css        = [];

    // generic data, that's just accessed from the template
    private array $rawData  = [];                           // copied from $context
    private array $pageData = [];                           // processed by display hooks
    private array $structuredData = [];                    // rebuilt from the current public response

    // template data that needs further processing .. ! WARNING ! they will not get aut fetched from $context as they are already defined here
    private string $gStaticUrl;
    private string $gHost;
    private string $gServerTime;
    private string $gUser;
    private string $gFavorites;
    private bool   $hasAnalytics  = false;
    private bool   $consentFooter = false;
    private string $dbProfiles    = '';

    private readonly string $user;                          // becomes User object

    /*******************/
    /* basic execution */
    /*                 */
    /* 1) Init         */
    /*******************/

    public function __construct(private string $template, private ?\Aowow\TemplateResponse $context = null)
    {
        $this->locale = Lang::getLocale();
        $this->user   = User::class;

        self::__wakeup();                                   // init non-cached properties;
    }

    public function addDataLoader(string ...$dataFile) : void
    {
        foreach ($dataFile as $df)
            $this->dataLoader[] = $df;
    }

    public function addScript(int $type, string $str, int $flags = 0x0) : bool
    {
        $tpl = match ($type)
        {
            SC_CSS_FILE   => '<link rel="stylesheet" type="text/css" href="%1$s%2$s" />',
            SC_CSS_STRING => '<style type="text/css">%1$s</style>',
            SC_JS_FILE    => '<script type="text/javascript" src="%1$s%2$s"></script>',
            SC_JS_STRING  => '<script type="text/javascript">%1$s</script>',
            default       => ''
        };

        if (!$str)
        {
            trigger_error('PageTemplate::addScript - content empty', E_USER_WARNING);
            return false;
        }

        if (!$tpl)
        {
            trigger_error('PageTemplate::addScript - unknown script type #'.$type, E_USER_WARNING);
            return false;
        }

        // insert locale string
        if ($flags & SC_FLAG_LOCALIZED)
            $str = sprintf($str, Lang::getLocale()->json());

        $this->scripts[] = [$type, $str, $flags, $tpl];
        return true;
    }

    /* (optional) set pre-render hooks */

    public function registerDisplayHook(string $var, callable $fn, mixed $extraParam = null) : void
    {
        $this->displayHooks[$var][] = [$fn, $extraParam];
    }

    private function getDisplayHooks(string $var) : array
    {
        return $this->displayHooks[$var] ?? [];
    }

    /* 3) self test, ready to be cached now */

    public function prepare() : bool
    {
        if (!self::test('template/pages/', $this->template))
        {
            trigger_error('Error: nonexistent template requested: template/pages/'.$this->template.'.tpl.php', E_USER_WARNING);
            return false;
        }

        // TODO - more checks and preparations

        return true;
    }

    /* 4) display */

    public function render() : void
    {
        $this->update();

        include('template/pages/'.$this->template.'.tpl.php');
    }


    /***********/
    /* loaders */
    /***********/

    // "template_exists"
    public static function test(string $path, string $file) : bool
    {
        if (!preg_match('/^[\w\-_]+(\.tpl(\.php)?)?$/i', $file))
            return false;

        if ($path && preg_match('/\\{2,}|\/{2,}|\.{2,}|~/i', $path))
            return false;

        if (!is_file('template/'.$path.$file))
            return false;

        return true;
    }

    // load brick
    private function brick(string $file, array $localVars = []) : void
    {
        $file .= '.tpl.php';

        if (!self::test('bricks/', $file))
        {
            trigger_error('Nonexistent template requested: template/bricks/'.$file, E_USER_WARNING);
            return;
        }

        foreach ($localVars as $n => $v)
            $$n = $v;

        include('template/bricks/'.$file);
    }

    private function brickIf(mixed $boolish, string $file, array $localVars = []) : void
    {
        if ($boolish)
            $this->brick($file, $localVars);
    }

    // load brick with more text then vars
    private function localizedBrick(string $file, array $localVars = []) : void
    {
        foreach ($localVars as $n => $v)
            $$n = $v;

        $_file = $file.'_'.$this->locale->value.'.tpl.php';
        if (self::test('localized/', $_file))
        {
            include('template/localized/'.$_file);
            return;
        }

        $_file = $file.'_'.$this->locale->getFallback()->value.'.tpl.php';
        if (self::test('localized/', $_file))
        {
            include('template/localized/'.$_file);
            return;
        }

        trigger_error('Nonexistent template requested: template/localized/'.$_file, E_USER_WARNING);
    }

    private function localizedBrickIf(mixed $boolish, string $file, array $localVars = []) : void
    {
        if ($boolish)
            $this->localizedBrick($file, $localVars);
    }


    /****************/
    /* Util wrapper */
    /****************/

    private function cfg(string $name) : mixed
    {
        return Cfg::get($name);
    }

    private function json(mixed $var, int $jsonFlags = 0x0, bool $varRef = false) : string
    {
        if (!is_string($var))
            return Util::toJavaScript($var, $jsonFlags | JSON_HEX_TAG | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: "{}";

        return Util::toJavaScript($varRef ? $this->$var : $var, $jsonFlags | JSON_HEX_TAG | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE) ?: "{}";
    }

    private function escHTML(string $var, bool $varRef = false) : string|array
    {
        return Util::htmlEscape($varRef ? $this->$var : $var);
    }

    // Read current settings when rendering, including serialized page templates.
    private function headerImage() : ?array
    {
        if (!Cfg::get('HEADER_IMAGE_ENABLE'))
            return null;

        $urls = [Cfg::get('HEADER_IMAGE_URL'), Cfg::get('HEADER_IMAGE_LINK')];
        foreach ($urls as $url)
        {
            if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL))
                return null;

            $parts = parse_url($url);
            if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
                isset($parts['user']) || isset($parts['pass']))
                return null;
        }

        return ['image' => $urls[0], 'link' => $urls[1]];
    }

    // Read at render time, including cached templates, so tokens remain session-specific.
    private function csrfField() : string
    {
        return '<input type="hidden" name="csrfToken" value="'.\Aowow\Csrf::token().'">';
    }

    private function escJS(string $var, bool $varRef = false) : string|array
    {
        return Util::jsEscape($varRef ? $this->$var : $var);
    }

    private function ucFirst(string $var, bool $varRef = false) : string
    {
        return Util::ucFirst($varRef ? $this->$var : $var);
    }


    /*****************/
    /* render helper */
    /*****************/

    private function documentTitle() : string
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $route = array_key_first($query) ?? 'home';
        if (!$this->isIndexable() || !in_array($route, [...self::SEO_DATABASE_ROUTES, 'home', 'guide', 'guides'], true))
            return implode(' - ', $this->title ?? []);

        require_once __DIR__.'/seometadata.class.php';
        return \Aowow\SeoMetadata::title($this->title ?? [], (string)Cfg::get('NAME'),
            $this->seo['locale'] ?? $this->locale, $route === 'home', $this->homeTitle ?? '',
            self::supportsPagination($route) ? ($this->pagination['page'] ?? 1) : 1);
    }

    private function concat(string $arrVar, string $separator = '') : string
    {
        if (!is_array($this->$arrVar))
            return '';

        return implode($separator, $this->$arrVar);
    }

    private function renderArray(string|array $arrVar, int $lpad = 0) : string
    {
        $data = [];
        if (is_string($arrVar) && isset($this->$arrVar) && is_array($this->$arrVar))
            $data = $this->$arrVar;
        else if (is_array($arrVar))
            $data = $arrVar;

        $buff = '';
        foreach ($data as $x)
            $buff .= str_repeat(' ', $lpad) . $x . PHP_EOL;

        return $buff;
    }

    private function renderMetaTags(int $lPad = 0) : string
    {
        $buff = '';
        foreach ($this->metaTags as $mt)
        {
            $content = '';
            foreach ($mt as $k => $v)
            {
                if (is_array($v))
                    $v = implode(', ', $v);

                $content .= sprintf(' %s="%s"', $k, Util::htmlEscape($v));
            }

            $buff .= str_repeat(' ', $lPad) . '<meta'.$content.'>'.PHP_EOL;
        }

        return $buff;
    }


    // load jsGlobals
    private function renderGlobalVars(int $lpad = 0) : string
    {
        $buff = '';

        if ($this->guideRating)
            $buff .= str_repeat(' ', $lpad).sprintf(self::GUIDE_RATING_TPL, ...$this->guideRating);

        foreach ($this->jsGlobals as [$jsVar, $data, $extraData])
        {
            $buff .= str_repeat(' ', $lpad).'var _ = '.$jsVar.';';

            foreach ($data as $key => $var)
                $buff .= ' _['.(is_numeric($key) ? $key : "'".$key."'")."]=".Util::toJavaScript($var).';';

            $buff .= PHP_EOL;

            if (isset($this->gPageInfo['type']) && isset($this->gPageInfo['typeId']) && isset($extraData[$this->gPageInfo['typeId']]))
            {
                $buff .= PHP_EOL;
                foreach ($extraData[$this->gPageInfo['typeId']] as $k => $v)
                    if ($v)
                        $buff .= str_repeat(' ', $lpad).'_['.$this->gPageInfo['typeId'].'].'.$k.' = '.Util::toJavaScript($v).';'.PHP_EOL;
                $buff .= PHP_EOL;
            }
        }

        return $buff;
    }

    private function renderSeriesItem(int $idx, array $list, bool $unordered, int $lpad = 0) : string
    {
        if ($unordered)
            $result = '<tr><th style="padding-left:13px;"></th><td><div>';
        else
            $result = '<tr><th>'.($idx + 1).'.</th><td><div>';

        $end = array_key_last($list);
        foreach ($list as $k => $i)                         // itemItr
        {
            $wrap = match ($i['side'])
            {
                SIDE_ALLIANCE => '<span class="icon-alliance-padded">%s</span>',
                SIDE_HORDE    => '<span class="icon-horde">%s</span>',
                default       => '%s'
            };

            if ($i['typeId'] == $this->typeId)
                $result .= sprintf($wrap, '<b>'.$i['name'].'</b>');
            else
                $result .= sprintf($wrap, '<a href="?'.$i['typeStr'].'='.$i['typeId'].'">'.$i['name'].'</a>');

            if ($end != $k)
                $result .= '<br />';

        }

        return str_repeat(' ', $lpad) . $result . '</div></td></tr>' . PHP_EOL;
    }

    private function renderFilter(int $lpad = 0) : string
    {
        $result = [];

        // it's worth noting, that this only works on non-cached page calls. Luckily Profiler pages are not cached.
        if ($this->context instanceof \Aowow\IProfilerList)
        {
            $result[] = "pr_setRegionRealm(\$WH.ge('fi').firstChild, '".$this->region."', '".$this->realm."');";

            if (!empty($this->filter->values['ra']))
                $result[] = "pr_onChangeRace();";
        }

        if ($this->filter->fiInit)                          // str: filter template (and init html form)
            $result[] = "fi_init('".$this->filter->fiInit."');";
        else if ($this->filter->fiType)                     // str: filter template (set without init)
            $result[] = "var fi_type = '".$this->filter->fiType."'";

        if ($this->filter->fiSetCriteria)                   // arr:criteria, arr:signs, arr:values
            $result[] = 'fi_setCriteria('.mb_substr(Util::toJSON($this->filter->fiSetCriteria), 1, -1).");";

        /*
            nt: don't try to match provided weights on predefined weight sets (preselects preset from opt list and ..?)
            ids: weights are encoded as ids, not by their js name and need conversion before use
            stealth: the ub-selector (items filter) will not visually change (so what..?)
        */
        if ($this->filter->fiSetWeights)                    // arr:weights, bool:nt[0], bool:ids[1], bool:stealth[1]
            $result[] = 'fi_setWeights('.Util::toJSON(array_values($this->filter->fiSetWeights)).', 0, 1, 1);';

        if ($this->filter->fiExtraCols)                     // arr:extraCols
            $result[] = 'fi_extraCols = '.Util::toJSON(array_values(array_unique($this->filter->fiExtraCols))).";";

        return str_repeat(' ', $lpad).'<script type="text/javascript">//<![CDATA['.PHP_EOL.
                   $this->renderArray($result, $lpad + 4).
               str_repeat(' ', $lpad).'//]]></script>'.PHP_EOL;
    }

    private function makeOptionsList(array $data, mixed $selectedIdx = null, int $lpad = 0, ?callable $callback = null) : string
    {
        $callback ??= fn(&$v, &$k) => $v;                   // default callback: skip empty descriptors
        $options = '';

        foreach ($data as $idx => $str)
        {
            $extraAttributes = [];
            if (!$callback($str, $idx, $extraAttributes))
                continue;

            if ($idx === '' || !$str)
                continue;

            $options .= str_repeat(' ', max(0, $lpad)).'<option';

            foreach ($extraAttributes as $k => $v)
                $options .= ' '.$k.'="'.$v.'"';

            if (is_array($selectedIdx) && in_array($idx, $selectedIdx))
                $options .= ' selected="selected"';
            else if (!is_null($selectedIdx) && $selectedIdx == $idx)
                $options .= ' selected="selected"';

            $options .= ' value="'.$idx.'">'.$str.'</option>'.($lpad < 0 ? '' : PHP_EOL);
        }

        return $options;
    }

    private function makeRadiosList(string $name, array $data, mixed $selectedIdx = null, int $lpad = 0, ?callable $callback = null) : string
    {
        $callback ??= fn(&$v, &$k) => $v;                   // default callback: skip empty descriptors
        $options = '';

        foreach ($data as $idx => [$title, $id])
        {
            $extraAttributes = [];
            if (!$callback($title, $idx, $extraAttributes))
                continue;

            if ($id === '' || !$title)
                continue;

            $options .= str_repeat(' ', max(0, $lpad)).'<input type="radio" name="'.$name.'" value="'.$idx.'" id="'.$name.'-'.$id.'"';

            if (!is_null($selectedIdx) && $selectedIdx == $idx)
                $options .= ' checked="checked"';

            $options .= '/><label for="'.$name.'-'.$id.'"';

            foreach ($extraAttributes as $k => $v)
                $options .= ' '.$k.'="'.$v.'"';

            $options .= '>'.$title.'</label>'.($lpad < 0 ? '' : PHP_EOL);
        }

        return $options;
    }

    // SEO identity only. Keep the routing pair first: index.php dispatches on it.
    // Return a URL, not HTML; callers must escape it for their output context.
    public static function buildQuery(array $add = [], array $remove = [], ?string $host = null) : string
    {
        parse_str($_SERVER['QUERY_STRING'] ?? '', $queryVars);
        return self::canonicalUrl($queryVars, $add, $remove, $host);
    }

    // Explicit identities share the request canonical policy without changing
    // request globals. Sitemaps always supply a locale, independent of cookies.
    public static function canonicalUrl(array $queryVars, array $add = [], array $remove = [], ?string $host = null) : string
    {
        $route = array_key_first($queryVars) ?? 'home';
        $value = $queryVars[$route] ?? '';

        // Do not let array-shaped input reach strlen() or become URL syntax.
        if (!is_string($value))
            $value = '';

        foreach ($remove as $r)
            unset($queryVars[$r]);

        foreach ($add as $k => $v)
            $queryVars[$k] = $v;

        $value = $queryVars[$route] ?? $value;

        $host ??= Cfg::get('HOST_URL');

        $params = [];
        // These modifiers change content in the current endpoints. Pagination
        // belongs here only once the corresponding HTML endpoint implements it.
        $allowed = match ($route)
        {
            'profiles'            => ['filter', 'roster'],
            'guide'               => match ($value)
            {
                'edit'      => ['id', 'rev'],
                'changelog' => ['id'],
                default     => ['rev']
            },
            'sitemap'             => ['page'],
            default               => in_array($route, self::SEO_FILTER_ROUTES, true) ? ['filter'] : []
        };
        foreach ($allowed as $key)
            if (isset($queryVars[$key]) && is_scalar($queryVars[$key]) && (string)$queryVars[$key] !== '')
                $params[$key] = (string)$queryVars[$key];
        if (self::supportsPagination((string)$route) && isset($queryVars['page']) && is_scalar($queryVars['page']) &&
            filter_var($queryVars['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]))
            $params['page'] = (string)(int)$queryVars['page'];

        // A saved language preference can render a bare URL in another locale.
        // Make that identity explicit, and retain explicit supported locales.
        $locale = $queryVars['locale'] ?? null;
        $loc = \Aowow\Locale::tryFromRequest($locale) ?? Lang::getLocale();
        if ($loc != \Aowow\Locale::EN)
            $params['locale'] = (string)$loc->value;

        ksort($params, SORT_STRING);

        if (!in_array($route, $remove, true))
        {
            $value = $queryVars[$route] ?? $value;
            $routePair = rawurlencode((string)$route);
            if (is_scalar($value) && (string)$value !== '')
                $routePair .= '='.rawurlencode((string)$value);
        }
        else
            $routePair = 'home';

        if ($routePair === 'home' && !$params)
            return $host;

        $suffix = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return rtrim($host, '/').'/?'.$routePair.($suffix !== '' ? '&'.$suffix : '');
    }

    public static function supportsPagination(string $route) : bool
    {
        return in_array($route, self::SEO_LIST_ROUTES, true);
    }

    private function serverTooltip(bool $buff = false) : string
    {
        require_once __DIR__.'/servercontent.class.php';
        $data = $this->jsGlobals[$this->type][2][$this->typeId] ?? [];
        $key = ($buff ? 'buff' : 'tooltip').'_'.$this->locale->json();
        return \Aowow\ServerContent::safeHtml(is_string($data[$key] ?? null) ? $data[$key] : '');
    }

    /** The visible trail and its structured data share the same labels and links. */
    private function serverBreadcrumbs() : array
    {
        require_once __DIR__.'/servercontent.class.php';
        $route = explode('=', $this->pageTemplate['pageName'] ?? '')[0];
        $lists = ['item' => 'items', 'spell' => 'spells', 'quest' => 'quests', 'npc' => 'npcs',
            'object' => 'objects', 'zone' => 'zones', 'guide' => 'guides', 'itemset' => 'itemsets',
            'class' => 'classes', 'race' => 'races', 'skill' => 'skills', 'faction' => 'factions',
            'achievement' => 'achievements', 'currency' => 'currencies', 'event' => 'events',
            'pet' => 'pets', 'sound' => 'sounds', 'icon' => 'icons', 'title' => 'titles',
            'emote' => 'emotes', 'enchantment' => 'enchantments', 'areatrigger' => 'areatriggers', 'mail' => 'mails'];
        $parent = $lists[$route] ?? (self::supportsPagination($route) ? $route : null);
        if (!$parent)
            return [];

        $label = is_callable([Lang::class, 'game']) ? Lang::game($parent) : ucfirst($parent);
        $current = $this->gPageInfo['name'] ?? html_entity_decode(strip_tags($this->title[0] ?? $this->h1 ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return [
            ['name' => \Aowow\ServerContent::label('home'), 'url' => \Aowow\ServerContent::url('home')],
            ['name' => is_string($label) ? $label : ucfirst($parent), 'url' => \Aowow\ServerContent::url($parent)],
            ['name' => (string)$current, 'url' => $this->seoUrl()]
        ];
    }

    private function buildStructuredData() : array
    {
        if (!$this->isIndexable())
            return [];

        $url = $this->seoUrl();
        $siteUrl = rtrim(Cfg::get('HOST_URL'), '/').'/';
        $siteName = (string)Cfg::get('NAME');
        $locale = $this->seo['locale'] ?? $this->locale;
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $route = array_key_first($query) ?? 'home';
        $publisher = ['@type' => 'Organization', '@id' => $siteUrl.'#publisher', 'name' => $siteName, 'url' => $siteUrl];
        $website = ['@type' => 'WebSite', '@id' => $siteUrl.'#website', 'name' => $siteName, 'url' => $siteUrl,
            'publisher' => ['@id' => $publisher['@id']]];
        $name = $this->gPageInfo['name'] ?? html_entity_decode(strip_tags($this->h1 ?: ($this->title[0] ?? $siteName)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($route === 'home')
            $name = $siteName;
        $page = ['@type' => in_array($route, self::SEO_LIST_ROUTES, true) ? 'CollectionPage' : 'WebPage',
            '@id' => $url.'#webpage', 'url' => $url, 'name' => (string)$name, 'inLanguage' => $locale->hreflang('-'),
            'isPartOf' => ['@id' => $website['@id']], 'publisher' => ['@id' => $publisher['@id']]];
        if ($tag = array_find($this->metaTags ?? [], fn($tag) => ($tag['name'] ?? '') === 'description'))
            if (is_string($tag['content']) && $tag['content'] !== '')
                $page['description'] = $tag['content'];

        $graph = [$publisher, $website];
        $items = [];
        foreach ($this->serverBreadcrumbs() as $crumb)
        {
            $itemUrl = str_starts_with($crumb['url'], '?') ? $siteUrl.$crumb['url'] : $crumb['url'];
            // Normalize the English home alias and repeated list-root links.
            if ($itemUrl === $siteUrl.'?home')
                $itemUrl = $siteUrl;
            if ($items && end($items)['item'] === $itemUrl)
                continue;
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $crumb['name'], 'item' => $itemUrl];
        }
        if (count($items) >= 2)
        {
            $page['breadcrumb'] = ['@id' => $url.'#breadcrumb'];
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url.'#breadcrumb', 'itemListElement' => $items];
        }

        // A selected, public guide body is the only article identity we claim.
        if ($route === 'guide' && ($this->seo['index'] ?? false) === true && trim((string)$this->article) !== '')
        {
            $article = ['@type' => 'Article', '@id' => $url.'#article', 'url' => $url, 'headline' => $page['name'],
                'inLanguage' => $page['inLanguage'], 'mainEntityOfPage' => ['@id' => $page['@id']],
                'publisher' => ['@id' => $publisher['@id']]];
            if (isset($page['description']))
                $article['description'] = $page['description'];
            if (is_string($this->seo['author'] ?? null) && $this->seo['author'] !== '')
                $article['author'] = ['@type' => 'Person', 'name' => $this->seo['author'],
                    'url' => $siteUrl.'?user='.rawurlencode($this->seo['author'])];
            $page['mainEntity'] = ['@id' => $article['@id']];
            $graph[] = $article;
        }
        $graph[] = $page;
        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private function seoUrl(?\Aowow\Locale $locale = null) : string
    {
        $add = [];
        $seo = $this->seo ?? [];
        if (isset($seo['guide']))
            $add['guide'] = $seo['guide'];
        if ($loc = $locale ?? ($seo['locale'] ?? null))
            $add['locale'] = $loc->value;

        return self::buildQuery(add: $add, remove: isset($seo['guide']) ? ['rev'] : []);
    }

    private function isIndexable() : bool
    {
        if (($this->seo['index'] ?? null) === false)
            return false;

        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $route = array_key_first($query) ?? 'home';
        $value = $query[$route] ?? '';
        if (!is_string($value))
            return false;

        $public = [...self::SEO_DATABASE_ROUTES, 'home', 'aboutus', 'contactus', 'faq',
            'help', 'whats-new', 'talent', 'petcalc', 'compare', 'maps', 'profiler',
            'searchplugins', 'searchbox', 'tooltips', 'guides', 'guide'];
        if (!in_array($route, $public, true))
            return false;

        // Guides need a resolved public revision; a raw guide route proves nothing.
        if ($route === 'guide' && (($this->seo['index'] ?? false) !== true || array_key_exists('rev', $query)))
            return false;

        if (in_array($route, self::SEO_FILTER_ROUTES, true) && array_key_exists('filter', $query) && $query['filter'] !== '')
        {
            $key = $route.($value !== '' ? '='.$value : '');
            if (!is_string($query['filter']) || !in_array($query['filter'], self::SEO_REVIEWED_FILTERS[$key] ?? [], true))
                return false;
        }

        // Empty database/guide lists are navigable but provide no indexable result.
        if (in_array($route, self::SEO_LIST_ROUTES, true) && $this->lvTabs instanceof \Aowow\Tabs)
        {
            $hasList = false;
            foreach ($this->lvTabs->iterate() as $tab)
                if ($tab instanceof \Aowow\Listview)
                {
                    if ($tab->hasData())
                        return true;
                    $hasList = true;
                }
            if ($hasList)
                return false;
        }

        return true;
    }

    private function seoAlternates() : array
    {
        if (!$this->isIndexable())
            return [];

        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
        $route = array_key_first($query) ?? 'home';
        // Individual guides have one authored language and no translation mapping;
        // guide lists contain different records in each locale, not translations.
        if ((in_array($route, self::SEO_FILTER_ROUTES, true) && isset($query['filter']) && $query['filter'] !== '') ||
            ($route !== 'home' && !in_array($route, self::SEO_DATABASE_ROUTES, true)))
            return [];

        $result = ['x-default' => $this->seoUrl(\Aowow\Locale::getFallback())];
        foreach (\Aowow\Locale::cases() as $locale)
            if ($locale->validate())
                $result[$locale->hreflang('-')] = $this->seoUrl($locale);

        return $result;
    }

    // unordered stuff

    private function prepareScripts() : void
    {
        $this->js = $this->css = [];

        foreach ($this->scripts as [$type, $str, $flags, $tpl])
        {
            $app = [];

            if (($flags & SC_FLAG_APPEND_LOCALE) && $this->locale != \Aowow\Locale::EN)
                $app[] = 'lang='.$this->locale->domain();

            // append anti-cache timestamp
            if (!($flags & SC_FLAG_NO_TIMESTAMP))
                if ($type == SC_JS_FILE || $type == SC_CSS_FILE)
                    $app[] = filemtime('static/'.$str) ?: 0;

            if ($app)
                $appendix = '?'.implode('&', $app);

            if ($type == SC_JS_FILE || $type == SC_CSS_FILE)
                $str = Cfg::get('STATIC_URL').'/'.$str;

            if ($flags & SC_FLAG_PREFIX)
            {
                if ($type == SC_JS_FILE || $type == SC_JS_STRING)
                    array_unshift($this->js, sprintf($tpl, $str, $appendix ?? ''));
                else
                    array_unshift($this->css, sprintf($tpl, $str, $appendix ?? ''));
            }
            else
            {
                if ($type == SC_JS_FILE || $type == SC_JS_STRING)
                    array_push($this->js, sprintf($tpl, $str, $appendix ?? ''));
                else
                    array_push($this->css, sprintf($tpl, $str, $appendix ?? ''));
            }
        }

        if ($data = array_unique($this->dataLoader))
        {
            $args = array(
                'data'   => implode('.', $data),
                'locale' => $this->locale->value,
                't'      => $_SESSION['dataKey']
            );

            array_push($this->js, '<script type="text/javascript" src="'.Cfg::get('HOST_URL').'/?'.http_build_query($args).'"></script>');
        }
    }

    // refresh vars that shouldn't be cached
    private function update() : void
    {
        // These values depend on the current request, not the request that
        // populated a shared page cache. Keep all SEO consumers in agreement.
        $url = $this->seoUrl();
        $metaTags = $this->metaTags ?? [];
        $locale = $this->seo['locale'] ?? $this->locale;
        $alternates = $this->seoAlternates();
        $metaTags = array_values(array_filter($metaTags, fn($tag) => ($tag['property'] ?? '') !== 'og:locale:alternate'));
        foreach (\Aowow\Locale::cases() as $other)
            if ($other !== $locale && isset($alternates[$other->hreflang('-')]))
                $metaTags[] = ['property' => 'og:locale:alternate', 'content' => $other->hreflang()];
        if (!$this->isIndexable())
        {
            $metaTags = array_values(array_filter($metaTags, fn($tag) => ($tag['name'] ?? '') !== 'robots'));
            $metaTags[] = ['name' => 'robots', 'content' => 'noindex, follow'];
            $this->ldIntangible = [];
        }
        foreach ($metaTags as &$tag)
        {
            if (($tag['property'] ?? '') === 'og:title')
                $tag['content'] = $this->documentTitle();
            else if (($tag['property'] ?? '') === 'og:url')
                $tag['content'] = $url;
            else if (($tag['property'] ?? '') === 'og:locale')
                $tag['content'] = $locale->hreflang();
            else if (($tag['name'] ?? '') === 'viewport')
                $tag['content'] = 'width=device-width, initial-scale=1';
            else if (($tag['name'] ?? '') === 'language')
                $tag['content'] = $locale->domain();
        }
        unset($tag);
        $this->metaTags = $metaTags;

        if ($ld = $this->ldIntangible)
        {
            $ld['url'] = $url;
            if (isset($ld['mainEntityOfPage']['@id']))
                $ld['mainEntityOfPage']['@id'] = $url;
            $this->ldIntangible = $ld;
        }

        $this->structuredData = $this->buildStructuredData();

        // not set, but should be
        if (!isset($_COOKIE['consent']) && $this->hasAnalytics)
        {
            $this->addScript(SC_CSS_FILE, 'css/consent.css', SC_FLAG_NOCACHE);
            $this->addScript(SC_JS_FILE,  'js/consent.js', SC_FLAG_NOCACHE);

            $this->consentFooter = true;
        }
        else
            $this->consentFooter = false;

        // analytics + consent
        // not set or declined
        if (empty($_COOKIE['consent']))
            $this->hasAnalytics = false;

        // js + css
        $this->prepareScripts();

        // db profiling
        if (Cfg::get('DEBUG') >= LOG_LEVEL_INFO && User::isInGroup(U_GROUP_DEV | U_GROUP_ADMIN))
            $this->dbProfiles = \Aowow\DB::getProfiles();
    }

    public function setListviewError() : void
    {
        if (!$this->lvTabs)
            return;

        foreach ($this->lvTabs->iterate() as $lv)
            if ($lv instanceof \Aowow\Listview)
                $lv->setError(true);
    }

    // pre-serialization: if a var is relevant it was stored in $rawData
    public function __sleep() : array
    {
        $this->context  = null;                             // unlink from TemplateResponse
        $this->pageData = [];                               // clear modified data

        unset(                                              // must be recreated on __wakeup
            $this->gStaticUrl,
            $this->gHost,
            $this->hasAnalytics,
            $this->gServerTime,
            $this->gUser,
            $this->gFavorites
        );

        if ($this->lvTabs)                                  // do not store lvErrors in cache
            foreach ($this->lvTabs->iterate() as $lv)
                if ($lv instanceof \Aowow\Listview)
                    $lv->setError(false);

        // clear out scripts flagged as non-caching
        $this->scripts = array_filter($this->scripts, fn($x) => !($x[2] & SC_FLAG_NOCACHE));

        $vars = [];
        foreach ($this as $k => $_)
            $vars[] = $k;

        return $vars;
    }

    public function __wakeup() : void
    {
        $this->gStaticUrl   = Cfg::get('STATIC_URL');
        $this->gHost        = Cfg::get('HOST_URL');
        $this->hasAnalytics = !!Cfg::get('GTAG_MEASUREMENT_ID');
        $this->gServerTime  = sprintf("new Date('%s')", date(Util::$dateFormatInternal));
        $this->gUser        = Util::toJSON(User::getUserGlobal());
        $this->gFavorites   = Util::toJSON(User::getFavorites());
    }

    public function __set(string $var, mixed $value) : void
    {
        $this->pageData[$var] = $value;
    }

    public function __get(string $var) : mixed
    {
        // modified data exists
        if (isset($this->pageData[$var]))
            return $this->pageData[$var];

        if (!isset($this->rawData[$var]))
        {
            if (!$this->context)
                return null;

            if (!isset(get_object_vars($this->context)[$var]))
                return null;

            $this->rawData[$var] = $this->context->$var;
        }

        if ($hooks = $this->getDisplayHooks($var))
        {
            if (is_object($this->rawData[$var]))            // is frontend component
                $this->pageData[$var] = clone $this->rawData[$var];
            else
                $this->pageData[$var] = $this->rawData[$var];

            foreach ($hooks as [$fn, $extraParam])
                $fn($this, $this->pageData[$var], $extraParam);
        }

        return $this->pageData[$var] ?? $this->rawData[$var];
    }
}
