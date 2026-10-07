<?php

namespace Aowow {
    class Cfg {
        public static bool $profiler = false;
        public static function get(string $key) : mixed {
            return match ($key) {
                'PROFILER_ENABLE' => self::$profiler,
                'HOST_URL' => 'https://example.com', 'STATIC_URL' => '/static', default => 0
            };
        }
    }
    class Lang {
        public static function getLocale() : Locale { return Locale::EN; }
        public static function main(string $key) : string { return $key; }
    }
    class User {
        public static function isPremium() : bool { return false; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
}

namespace {
    use Aowow\ExternalLinks;
    use Aowow\Template\PageTemplate;

    define('AOWOW_REVISION', 68);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/locale.class.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/components/csrf.class.php';
    require __DIR__.'/../includes/components/externallinks.class.php';
    require __DIR__.'/../includes/components/pagetemplate.class.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $condition, string $message) : void {
        global $checks;
        ++$checks;
        if (!$condition) throw new RuntimeException($message);
    }

    ExternalLinks::load([]);
    foreach (ExternalLinks::defaults() as $key => $entry)
        check(ExternalLinks::url($key) === ($entry['enabled'] ? $entry['url'] : null), 'Missing configuration retains '.$key);

    ExternalLinks::load(['discord' => ['enabled' => true, 'url' => 'https://discord.gg/test-invite']]);
    check(ExternalLinks::url('discord') === 'https://discord.gg/test-invite', 'Discord accepts an enabled invite URL');
    ExternalLinks::load(['discord' => ['enabled' => false, 'url' => 'https://discord.gg/test-invite']]);
    check(ExternalLinks::url('discord') === null, 'Discord can be disabled while retaining its URL');
    ExternalLinks::load(['discord' => ['enabled' => true, 'url' => '']]);
    check(ExternalLinks::url('discord') === null, 'Discord with an empty URL stays hidden');

    ExternalLinks::load(['facebook' => ['url' => 'https://example.com/our-page'], 'twitter' => ['enabled' => false]]);
    check(ExternalLinks::url('facebook') === 'https://example.com/our-page', 'Custom URL with default enabled status');
    check(ExternalLinks::url('twitter') === null, 'Disabled link is omitted');
    check(ExternalLinks::url('unknown') === null, 'Unknown link is omitted');
    foreach (['', 'javascript:alert(1)', 'ftp://example.com/', '//example.com/', '/relative', "https://example.com/\n", 'https://user:pass@example.com/', 'https://example.com/\\evil', null, [], 123] as $url) {
        ExternalLinks::load(['facebook' => ['url' => $url]]);
        check(ExternalLinks::url('facebook') === null, 'Invalid external URL is hidden');
    }
    foreach ([null, false, 'wrong', ['enabled' => 'false'], ['enabled' => null], ['enabled' => 1]] as $entry) {
        ExternalLinks::load(['facebook' => $entry]);
        check(ExternalLinks::url('facebook') === null, 'Malformed link configuration is hidden');
    }

    // Render real templates with a reusable cached PageTemplate and synthetic site/user metadata.
    $_GET = $_SESSION = [];
    $root = dirname(__DIR__);
    $reflection = new ReflectionClass(PageTemplate::class);
    $page = $reflection->newInstanceWithoutConstructor();
    foreach (['locale' => Aowow\Locale::EN, 'user' => Aowow\User::class, 'template' => 'home', 'context' => null,
              'rawData' => ['title' => ['Fixture'], 'metaTags' => [], 'jsGlobals' => [],
                            'featuredBox' => ['markup' => '', 'extended' => false, 'boxBG' => '', 'overlays' => []]]] as $key => $value)
        $reflection->getProperty($key)->setValue($page, $value);
    $page->__wakeup();
    $cached = serialize($page);
    $render = function (string $file) : string {
        ob_start();
        try {
            include $file;
            return ob_get_contents();
        }
        finally { ob_end_clean(); }
    };
    $scratch = sys_get_temp_dir().'/aowow-external-links-'.bin2hex(random_bytes(6));
    mkdir($scratch.'/template/bricks', 0700, true);
    foreach (['head', 'announcement', 'headerMenu', 'pageTemplate'] as $brick)
        file_put_contents($scratch.'/template/bricks/'.$brick.'.tpl.php', '');
    $cwd = getcwd();
    try {
        chdir($scratch);
        $url = 'https://example.com/?a=1&b=%22';
        ExternalLinks::load(['github' => ['url' => $url], 'facebook' => ['url' => 'https://example.com/</script>']]);
        $page = unserialize($cached);
        $head = $render->call($page, $root.'/template/bricks/head.tpl.php');
        check(!str_contains($head, 'https://example.com/</script>'), 'Config URLs cannot close inline scripts');
        preg_match('/var g_externalLinks = (.+);/', $head, $match);
        check(json_decode($match[1], true, 512, JSON_THROW_ON_ERROR) === ExternalLinks::urls(), 'Head emits current configured URLs');
        check(str_contains($head, 'g_applyExternalLinks(mn_community, g_externalLinks)'), 'Head applies configuration before body navigation');
        check(str_contains($head, '/js/external-links.js?v='.AOWOW_REVISION.'.1'), 'Updated navigation script bypasses the prior revision-only browser cache');
        check(str_contains($head, 'var g_profilerEnabled = false;') && str_contains($head, 'g_applyProfilerMenus(mn_tools, mn_more, g_profilerEnabled)'), 'Cached page hides profiler navigation using the current server configuration');
        Aowow\Cfg::$profiler = true;
        $page = unserialize($cached);
        $enabledHead = $render->call($page, $root.'/template/bricks/head.tpl.php');
        check(str_contains($enabledHead, 'var g_profilerEnabled = true;'), 'Same cached page reflects profiler enablement without a rebuild or database discovery');
        Aowow\Cfg::$profiler = false;
        $home = $render->call($page, $root.'/template/pages/home.tpl.php');
        check(str_contains($home, 'href="'.htmlspecialchars($url, ENT_QUOTES | ENT_HTML5).'"'), 'Homepage GitHub URL is HTML escaped');

        ExternalLinks::load(['github' => ['enabled' => false], 'facebook' => ['enabled' => false]]);
        $page = unserialize($cached);
        $home = $render->call($page, $root.'/template/pages/home.tpl.php');
        check(!str_contains($home, '>Github</a>') && !str_contains($home, '</a>||'), 'Disabled GitHub link and its separator are removed from cached template');
        $head = $render->call($page, $root.'/template/bricks/head.tpl.php');
        preg_match('/var g_externalLinks = (.+);/', $head, $match);
        check(json_decode($match[1], true)['facebook'] === null, 'Cached head reads current enabled status');
    }
    finally {
        chdir($cwd);
        foreach (glob($scratch.'/template/bricks/*') as $file) unlink($file);
        rmdir($scratch.'/template/bricks'); rmdir($scratch.'/template'); rmdir($scratch);
    }
    echo "PASS: $checks external link configuration/template checks\n";
}
