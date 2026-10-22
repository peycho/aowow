<?php
// Render real shared panels without runtime configuration or any database.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed { return $key === 'ACC_ALLOW_REGISTER' ? 1 : 0; }
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
    }
    class User {
        public static bool $member = false;
        public static function canComment() : bool { return self::$member; }
        public static function canUploadScreenshot() : bool { return self::$member; }
        public static function canSuggestVideo() : bool { return self::$member; }
        public static function isLoggedIn() : bool { return self::$member; }
    }
    class Turnstile {
        public static function enabled(string $action) : bool { return false; }
    }
}
namespace {
    use Aowow\{Lang, Locale, User};
    use Aowow\Template\PageTemplate;
    chdir(dirname(__DIR__, 2));
    define('AOWOW_REVISION', 75);
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/game/uitext.class.php', 'includes/components/guidemgr.class.php',
        'includes/components/SmartAI/SmartAI.class.php', 'includes/components/SmartAI/SmartEvent.class.php',
        'includes/components/SmartAI/SmartAction.class.php', 'includes/components/SmartAI/SmartTarget.class.php', 'localization/lang.class.php',
        'includes/components/csrf.class.php', 'includes/components/pagetemplate.class.php'] as $file) require $file;
    set_error_handler(static function (int $code, string $message) : never { throw new ErrorException($message, 0, $code); });
    $reflection = new ReflectionClass(PageTemplate::class);
    $fixtures = [];
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        Lang::load($locale);
        $page = $reflection->newInstanceWithoutConstructor();
        foreach (['locale' => $locale, 'user' => User::class, 'context' => null, 'rawData' => [
            'contribute' => CONTRIBUTE_CO | CONTRIBUTE_SS | CONTRIBUTE_VI, 'type' => 3, 'typeId' => 1,
            'community' => ['coError' => '', 'ssError' => '', 'viError' => '']
        ]] as $property => $value) $reflection->getProperty($property)->setValue($page, $value);
        $render = static function (string $brick, array $variables = []) use ($reflection, $page) : string {
            ob_start();
            try { $reflection->getMethod('brick')->invoke($page, $brick, $variables); return ob_get_contents(); }
            finally { ob_end_clean(); }
        };
        User::$member = false;
        $fixtures[$locale->domain()]['guest'] = $render('contribute');
        User::$member = true;
        $fixtures[$locale->domain()]['member'] = $render('contribute');
        foreach (['signin', 'signup'] as $kind) {
            $fixtures[$locale->domain()][$kind] = '<form action="/" method="post">'.$render('inputbox-form-'.$kind, [
                'head' => 'Account fixture', 'hasRecovery' => true
            ]);
        }
        $fixtures[$locale->domain()]['locale'] = $locale->json();
        $fixtures[$locale->domain()]['id'] = $locale->value;
    }
    echo json_encode($fixtures, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}
