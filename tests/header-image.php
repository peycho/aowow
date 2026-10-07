<?php

// Real shared header/home templates with synthetic configuration; no runtime database.
namespace Aowow {
    class Cfg {
        public const int FLAG_TYPE_STRING = 8, FLAG_OPT_LIST = 16, FLAG_BITMASK = 32, FLAG_TYPE_BOOL = 4,
                         FLAG_TYPE_INT = 1, FLAG_TYPE_FLOAT = 2, FLAG_PHP = 64, FLAG_PERSISTENT = 128;
        public static array $values = [];
        public static function get(string $key) : mixed { return self::$values[strtolower($key)] ?? 0; }
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
    class TemplateResponse { protected const int TAB_STAFF = 4; }
}

namespace {
    use Aowow\{Cfg, Locale, User};
    use Aowow\Template\PageTemplate;
    define('AOWOW_REVISION', 69);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/locale.class.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/components/externallinks.class.php';
    require __DIR__.'/../includes/components/pagetemplate.class.php';
    require __DIR__.'/../endpoints/admin/siteconfig.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $condition, string $label) : void {
        global $checks;
        ++$checks;
        if (!$condition) throw new RuntimeException($label);
    }
    $root = dirname(__DIR__);
    Cfg::$values = ['static_url'=>'/static', 'host_url'=>'https://site.example.test', 'name_short'=>'Fixture & site',
                    'header_image_enable'=>0, 'header_image_url'=>'', 'header_image_link'=>''];
    $reflect = new ReflectionClass(PageTemplate::class);
    $page = $reflect->newInstanceWithoutConstructor();
    foreach (['locale'=>Locale::EN, 'user'=>User::class, 'template'=>'items', 'context'=>null,
              'rawData'=>['title'=>['Fixture'], 'jsGlobals'=>[], 'featuredBox'=>['markup'=>'', 'extended'=>false, 'boxBG'=>'', 'overlays'=>[]]]] as $key=>$value)
        $reflect->getProperty($key)->setValue($page, $value);
    $page->__wakeup();
    $cached = serialize($page);
    $render = function (string $path) : string {
        ob_start();
        try { include $path; return ob_get_contents(); }
        finally { ob_end_clean(); }
    };
    $scratch = sys_get_temp_dir().'/aowow-header-image-'.bin2hex(random_bytes(6));
    mkdir($scratch.'/template/bricks', 0700, true);
    foreach (['head','headerMenu','announcement','pageTemplate'] as $brick)
        file_put_contents($scratch.'/template/bricks/'.$brick.'.tpl.php', '');
    copy($root.'/template/bricks/headerImage.tpl.php', $scratch.'/template/bricks/headerImage.tpl.php');
    $cwd = getcwd();
    try {
        chdir($scratch);
        $header = fn() => $render->call(unserialize($cached), $root.'/template/bricks/header.tpl.php');
        check(!str_contains($header(), 'header-image'), 'Disabled default emits no image or link');
        Cfg::$values['header_image_enable'] = 1;
        check(!str_contains($header(), 'header-image'), 'Enabled with empty URLs emits no placeholder');
        Cfg::$values['header_image_url'] = 'https://images.example.test/site.gif?a=1&b="quoted"';
        Cfg::$values['header_image_link'] = 'https://example.test/?a=1&b=2';
        $html = $header();
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $link = $xpath->query('//a[@class="header-image"]')->item(0);
        $image = $xpath->query('//a[@class="header-image"]/img')->item(0);
        check($link !== null && $image !== null, 'Standard header renders one linked image');
        check($xpath->query('//a[@class="header-image"]')->length === 1, 'Only one header image is emitted');
        check($link->getAttribute('href') === Cfg::$values['header_image_link'] && $image->getAttribute('src') === Cfg::$values['header_image_url'], 'Escaped URLs round trip without changing query strings');
        check(str_contains($html, '&amp;') && str_contains($html, '&quot;'), 'HTML attribute metacharacters are escaped');
        check($link->getAttribute('target') === '_blank' && $link->getAttribute('rel') === 'noopener noreferrer', 'New tab cannot access opener');
        check(!$image->hasAttribute('width') && !$image->hasAttribute('height'), 'Image dimensions come from CSS without standard advertising-size attributes');
        check($image->getAttribute('alt') === 'Fixture & site', 'Image has escaped alternative text');
        check(!preg_match('/(?:class|id)="[^"]*(?:banner|advert|ad-slot)/i', $html), 'Placement uses neutral markup names');
        check(!str_contains($render->call(unserialize($cached), $root.'/template/pages/home.tpl.php'), 'header-image'), 'Homepage excludes image even when enabled');
        foreach (['header_image_url','header_image_link'] as $key) {
            $saved = Cfg::$values[$key];
            foreach (['', null, [], 'javascript:alert(1)', 'data:image/png;base64,AAAA', 'ftp://example.test/image',
                      '//example.test/image', '/image.png', 'https://user:pass@example.test/',
                      "https://example.test/\nimage", 'https://example.test/\\image',
                      'https://example.test/ image', '" onerror="alert(1)'] as $invalid) {
                Cfg::$values[$key] = $invalid;
                check(!str_contains($header(), 'header-image'), 'Invalid '.$key.' suppresses the placement');
            }
            Cfg::$values[$key] = $saved;
        }
        Cfg::$values['header_image_url'] = 'http://images.example.test/site.png';
        Cfg::$values['header_image_link'] = 'http://example.test/';
        check(str_contains($header(), 'src="http://images.example.test/site.png"'), 'Absolute HTTP URLs remain supported');
        Cfg::$values['header_image_url'] = 'https://images.example.test/changed.png';
        check(str_contains($header(), 'changed.png'), 'Cached template reads edited URL at render time');
        Cfg::$values['header_image_enable'] = 0;
        check(!str_contains($header(), 'header-image'), 'Cached template respects disabling immediately');
        Cfg::$values['header_image_enable'] = 1;
        check(str_contains($header(), 'changed.png'), 'Re-enabling retains configured URLs');

        // Site settings must not turn URL quotes into attributes in the administrator form.
        $admin = (new ReflectionClass(Aowow\AdminSiteconfigResponse::class))->newInstanceWithoutConstructor();
        $row = (new ReflectionMethod($admin, 'buildRow'))->invoke($admin, 'header_image_url',
            'https://example.test/" autofocus onfocus="alert(1)', 136, '', 'Image URL');
        $dom->loadHTML($row, LIBXML_NOERROR | LIBXML_NOWARNING);
        $input = (new DOMXPath($dom))->query('//input')->item(0);
        check(!$input->hasAttribute('autofocus') && !$input->hasAttribute('onfocus'), 'Settings input escapes URL attribute injection');
        if (in_array('--browser', $argv, true)) {
            $css = file_get_contents($root.'/static/css/aowow.css');
            $fixture = str_replace('</head>', '<style>'.$css.'</style></head>', $html);
            // Avoid external requests in the local browser fixture, retaining the real rendered element.
            $fixture = preg_replace('/<img src="[^"]*"/', '<img src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'468\' height=\'60\'%3E%3Crect width=\'468\' height=\'60\' fill=\'%232b5270\'/%3E%3C/svg%3E"', $fixture);
            echo $fixture, <<<'HTML'
</div></div></div>
<pre id="result">PENDING</pre>
<script>
window.addEventListener('load', function () {
    var placement = document.querySelector('.header-image').getBoundingClientRect();
    var image = document.querySelector('.header-image img').getBoundingClientRect();
    var header = document.querySelector('.header').getBoundingClientRect();
    var logo = document.querySelector('.header-logo').getBoundingClientRect();
    var valid = image.width === 468 && image.height === 60 && placement.right === header.right &&
                placement.top - header.top === 22 && placement.left >= logo.right && placement.bottom <= header.bottom;
    document.querySelector('#result').textContent = valid ? 'PASS: 6 header image layout checks' : 'FAIL: header image layout';
});
</script>
</body></html>
HTML;
        }
        else
            echo 'PASS: '.$checks." header image/template/cache/URL/settings checks\n";
    }
    finally {
        chdir($cwd);
        foreach (glob($scratch.'/template/bricks/*') as $path) unlink($path);
        rmdir($scratch.'/template/bricks'); rmdir($scratch.'/template'); rmdir($scratch);
    }
}
