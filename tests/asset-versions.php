<?php

// Exercise the real page asset URLs with deployment-preserved timestamps; no DB.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : string { return $key === 'STATIC_URL' ? '/static' : ''; }
    }
}
namespace {
    use Aowow\{Locale, Template\PageTemplate};
    $options = getopt('', ['fixtures', 'revision:', 'updated']);
    define('AOWOW_REVISION', (int)($options['revision'] ?? 79));
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/locale.class.php';
    require __DIR__.'/../includes/components/pagetemplate.class.php';
    set_error_handler(static function (int $code, string $message) : never { throw new ErrorException($message, 0, $code); });
    $directory = sys_get_temp_dir().'/aowow-assets-'.bin2hex(random_bytes(6));
    $cwd = getcwd(); $checks = 0;
    function check(bool $ok, string $message) : void {
        global $checks; ++$checks;
        if (!$ok) throw new RuntimeException($message);
    }
    mkdir($directory.'/static/js', 0700, true);
    mkdir($directory.'/static/widgets', 0700);
    mkdir($directory.'/static/css', 0700);
    $files = ['js/basic.js', 'widgets/power.js', 'css/basic.css'];
    try {
        foreach ($files as $file) {
            file_put_contents($directory.'/static/'.$file, 'fixture');
            touch($directory.'/static/'.$file, 1700000000 + ($file === 'js/basic.js' && isset($options['updated']) ? 1 : 0));
        }
        chdir($directory);
        $reflection = new ReflectionClass(PageTemplate::class);
        $prepare = $reflection->getMethod('prepareScripts');
        $render = $reflection->getMethod('renderArray');
        $page = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('locale')->setValue($page, Locale::EN);
        $page->addScript(SC_JS_FILE, 'js/basic.js');
        $page->addScript(SC_JS_FILE, 'widgets/power.js');
        $page->addScript(SC_CSS_FILE, 'css/basic.css');
        $prepare->invoke($page);
        $js = $render->invoke($page, 'js'); $css = $render->invoke($page, 'css');
        if (isset($options['fixtures'])) {
            echo json_encode(['js' => $js, 'css' => $css], JSON_THROW_ON_ERROR);
            return;
        }
        check(substr_count($js, '?v='.AOWOW_REVISION.'&1700000000') === 2, 'Shipped and generated JavaScript share the release revision');
        check(str_contains($css, '?v='.AOWOW_REVISION.'&1700000000'), 'Stylesheets receive the same release revision');
        $page->addScript(SC_JS_FILE, 'js/basic.js', SC_FLAG_NO_TIMESTAMP);
        $page->addScript(SC_JS_STRING, 'window.fixture = true;');
        $prepare->invoke($page);
        $js = $render->invoke($page, 'js');
        check(str_contains($js, 'src="/static/js/basic.js"></script>'), 'Explicit unversioned assets do not inherit the preceding asset query');
        check(str_contains($js, '<script type="text/javascript">window.fixture = true;</script>'), 'Inline JavaScript remains unchanged');
        $localized = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('locale')->setValue($localized, Locale::DE);
        $localized->addScript(SC_JS_FILE, 'widgets/power.js', SC_FLAG_APPEND_LOCALE | SC_FLAG_PREFIX);
        $prepare->invoke($localized);
        check(str_contains($render->invoke($localized, 'js'), '?lang=de&v='.AOWOW_REVISION.'&1700000000'), 'Locale stays first for tooltip widget domain detection');
        echo "PASS: $checks page asset release-version checks\n";
    }
    finally {
        chdir($cwd);
        foreach ($files as $file) unlink($directory.'/static/'.$file);
        foreach (['js', 'widgets', 'css'] as $dir) rmdir($directory.'/static/'.$dir);
        rmdir($directory.'/static'); rmdir($directory);
    }
}
