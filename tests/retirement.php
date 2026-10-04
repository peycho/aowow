<?php

// Exercise the real build templates and account/help handlers with synthetic configuration only.
namespace Aowow {
    class CLI {
        public const int LOG_ERROR = 1, LOG_WARN = 2;
        public static function write(string $message, int $level = 0) : void { throw new \RuntimeException($message); }
    }
    class CLISetup {
        public const int ARGV_PARAM = 1;
        public static array $locales = [], $scripts = [], $files = [];
        public static function registerSetup(string $command, SetupScript $script) : void { self::$scripts[$script->getName()] = $script; }
        public static function writeFile(string $file, string $contents) : bool { self::$files[$file] = $contents; return true; }
    }
    class DB {
        public static array $writes = [];
        public static int|false $result = 1;
        public static function Aowow() : self { return new self; }
        public function qry(string $sql, mixed ...$args) : int|false { self::$writes[] = [$sql, $args]; return self::$result; }
    }
    class User {
        public static int $id = 42;
        public static bool $banned = false;
        public static function isBanned() : bool { return self::$banned; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
    class Lang {
        public static Locale $locale = Locale::EN;
        public static array $strings = [];
        public static function getLocale() : Locale { return self::$locale; }
        public static function main(string $key, string ...$args) : string {
            $value = self::$strings['main'][$key] ?? $key;
            foreach ($args as $arg) $value = $value[$arg];
            return $value;
        }
        public static function account(string ...$args) : string { return 'saved'; }
    }
    // Replace only the article-loading parent; the actual help route and page renderer are exercised.
    class TemplateResponse {
        public const int TAB_MORE = 2;
        public array $title = [];
        public string $h1 = '', $articleUrl = '';
        public function __construct(string $param) {}
        protected function generate() : void {}
        public function generateError() : never { throw new \RuntimeException('Invalid help route'); }
    }
}

namespace {
    use Aowow\{Cfg, CLISetup, DB, Lang, Locale, User};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/locale.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/cfg.class.php';
    require $root.'/includes/components/jsexpression.class.php';
    require $root.'/includes/components/guidemgr.class.php';
    foreach (['SmartAI', 'SmartEvent', 'SmartAction', 'SmartTarget'] as $class)
        require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    require $root.'/setup/tools/setupScript.class.php';
    require $root.'/includes/components/response/baseresponse.class.php';
    require $root.'/includes/components/response/textresponse.class.php';
    require $root.'/endpoints/account/update-general-settings.php';
    require $root.'/endpoints/help/help.php';
    require $root.'/includes/components/csrf.class.php';
    require $root.'/includes/components/pagetemplate.class.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }

    // Use Cfg's actual interpolation and the registered build generators, capturing their outputs.
    $config = ['host_url'=>'http://127.0.0.1', 'static_url'=>'/static', 'debug'=>0,
               'contact_email'=>'fixture@example.test', 'gtag_measurement_id'=>'', 'ua_measurement_key'=>'',
               'rep_req_border_uncommon'=>0, 'rep_req_border_rare'=>0,
               'rep_req_border_epic'=>0, 'rep_req_border_legendary'=>0];
    $store = [];
    foreach ($config as $key => $value)
        $store[$key] = [$value, is_int($value) ? Cfg::FLAG_TYPE_INT : Cfg::FLAG_TYPE_STRING, 0, null, 'fixture'];
    (new ReflectionProperty(Cfg::class, 'store'))->setValue(null, $store);
    CLISetup::$locales = [Locale::EN, Locale::DE, Locale::FR, Locale::ES, Locale::RU, Locale::CN];
    require $root.'/setup/tools/filegen/global-js.ss.php';
    require $root.'/setup/tools/filegen/tooltips.ss.php';
    foreach (CLISetup::$scripts as $name => $script)
        check($script->generate(), 'Actual generator succeeds: '.$name);
    foreach (CLISetup::$files as $file => $source) {
        check(!str_contains($source, '/*setup:'), 'All build substitutions resolved: '.$file);
        check(!preg_match('/\b(?:ModelViewer|SWFObject|swfobject)\b/', $source), 'Generated assets contain no viewer runtime: '.$file);
    }
    if (($argv[1] ?? '') === '--fixtures') {
        echo json_encode(CLISetup::$files, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $account = new ReflectionClass(Aowow\AccountUpdategeneralsettingsResponse::class);
    $expected = $account->getProperty('expectedPOST')->getDefaultValue();
    check(array_keys($expected) === ['idsInLists'], 'Only list preference is accepted');
    foreach ([['idsInLists'=>'on'], [], ['idsInLists'=>'on', 'modelrace'=>'<script>', 'modelgender'=>'99']] as $post) {
        $handler = $account->newInstanceWithoutConstructor();
        (function () use ($post, $expected) { $this->_post = filter_var_array($post, $expected); })->call($handler);
        DB::$writes = []; DB::$result = 0; $_SESSION = [];
        $account->getMethod('generate')->invoke($handler);
        check($_SESSION['msg'] === ['general', true, 'saved'], 'Missing/legacy viewer fields do not prevent save');
        check(DB::$writes === [['UPDATE ::account SET `debug` = %i WHERE `id` = %i', [empty($post['idsInLists']) ? 0 : 1, 42]]], 'Only account list preference is written');
    }
    DB::$result = false; $_SESSION = [];
    $handler = $account->newInstanceWithoutConstructor();
    $account->getProperty('_post')->setValue($handler, ['idsInLists'=>false]);
    $account->getMethod('generate')->invoke($handler);
    check($_SESSION['msg'] === ['general', false, 'intError'], 'Failed account write reports failure');
    User::$banned = true; DB::$writes = []; $_SESSION = [];
    $account->getMethod('generate')->invoke($handler);
    check(!DB::$writes && !$_SESSION, 'Banned accounts cannot change preferences');

    // Render the actual generic text page with isolated bricks and unchanged synthetic article text.
    $scratch = sys_get_temp_dir().'/aowow-retirement-'.bin2hex(random_bytes(6));
    mkdir($scratch.'/template/bricks', 0700, true);
    foreach (['header', 'announcement', 'pageTemplate', 'footer'] as $brick)
        file_put_contents($scratch.'/template/bricks/'.$brick.'.tpl.php', '');
    file_put_contents($scratch.'/template/bricks/markup.tpl.php', '<?php echo $markup;');
    $cwd = getcwd();
    $article = 'Historical article: [modelviewer npc=1]Original label[/modelviewer]';
    try {
        foreach (CLISetup::$locales as $locale) {
            Lang::$locale = $locale;
            require $root.'/localization/locale_'.$locale->json().'.php';
            Lang::$strings = $lang;
            $help = new Aowow\HelpBaseResponse('modelviewer');
            (new ReflectionMethod($help, 'generate'))->invoke($help);
            check($help->articleUrl === 'help=modelviewer', 'Legacy help URL preserved: '.$locale->json());
            check($help->retirementNotice === $lang['main']['modelViewerRetired'] && $help->retirementNotice !== '', 'Localized banner: '.$locale->json());
            $reflection = new ReflectionClass(Aowow\Template\PageTemplate::class);
            $page = $reflection->newInstanceWithoutConstructor();
            foreach (['locale'=>$locale, 'user'=>User::class, 'template'=>'text-page-generic', 'context'=>$help,
                      'rawData'=>['article'=>$article, 'extraText'=>'', 'doResync'=>[null, null], 'inputbox'=>[],
                                  'typeId'=>1, 'redButtons'=>[0=>true, 3=>true, BUTTON_LINKS=>['type'=>3, 'typeId'=>1], BUTTON_COMPARE=>['eqList'=>'1']]]] as $key => $value)
                $reflection->getProperty($key)->setValue($page, $value);
            $page->__wakeup();
            chdir($scratch);
            ob_start();
            (function () use ($root) { include $root.'/template/pages/text-page-generic.tpl.php'; })->call($page);
            $html = ob_get_clean();
            chdir($cwd);
            check(str_contains($html, $article), 'Article text is unchanged: '.$locale->json());
            check(strpos($html, 'role="status"') < strpos($html, $article), 'Banner precedes original article: '.$locale->json());
            ob_start();
            (function () use ($root) { include $root.'/template/bricks/redButtons.tpl.php'; })->call($page);
            $buttons = ob_get_clean();
            check(!str_contains($buttons, 'Wowhead') && !str_contains($buttons, 'view3D'), 'Detail toolbar omits retired button IDs');
            check(str_contains($buttons, 'Links.show(') && str_contains($buttons, 'su_addToSaved('), 'Detail toolbar retains local link/comparison controls');
            $ordinary = new Aowow\HelpBaseResponse('item-comparison');
            (new ReflectionMethod($ordinary, 'generate'))->invoke($ordinary);
            check($ordinary->retirementNotice === '', 'Other help pages have no viewer banner');
        }
    }
    finally {
        chdir($cwd);
        foreach (glob($scratch.'/template/bricks/*') as $file) unlink($file);
        rmdir($scratch.'/template/bricks'); rmdir($scratch.'/template'); rmdir($scratch);
    }
    check(BUTTON_UPGRADE === 1 && BUTTON_COMPARE === 2 && BUTTON_LINKS === 4, 'Remaining red button IDs are stable');
    echo "PASS: $checks retirement build/account/help checks\n";
}
