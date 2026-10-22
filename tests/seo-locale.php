<?php

// Exercise actual anonymous preference initialization without DB/network access.
namespace Aowow {
    class Cfg {
        public static int $locales = 0x15D;
        public static function get(string $key) : mixed { return $key === 'LOCALES' ? self::$locales : 0; }
    }
}
namespace {
    use Aowow\{Cfg, Locale, User};
    define('AOWOW_REVISION', 75);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/locale.class.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/user.class.php';
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    foreach (['fr-FR', 'de-DE,en;q=0.5', 'zh-CN', 'es-ES', 'ru-RU', ''] as $header) {
        $_SERVER = ['HTTP_ACCEPT_LANGUAGE' => $header];
        $_SESSION = [];
        User::init(); // Missing SAPI peer stops before DB use, after preference setup.
        check(User::$preferedLoc === Locale::EN, 'new anonymous session has stable English default despite browser header');
    }
    foreach ([Locale::EN, Locale::FR, Locale::DE, Locale::CN, Locale::ES, Locale::RU] as $locale) {
        $_SESSION = ['locale' => $locale];
        User::init();
        check(User::$preferedLoc === $locale, 'explicit saved locale remains selected');
    }
    foreach ([Locale::KR, Locale::TW, 'fr', ['2'], 2] as $invalid) {
        $_SESSION = ['locale' => $invalid];
        User::init();
        check(User::$preferedLoc === Locale::EN, 'invalid saved locale falls back safely');
    }
    Cfg::$locales = 1;
    $_SESSION = ['locale' => Locale::FR];
    User::init();
    check(User::$preferedLoc === Locale::EN, 'disabled saved locale falls back');
    Cfg::$locales = Locale::FR->maskBit() | Locale::DE->maskBit();
    $_SESSION = [];
    User::init();
    check(User::$preferedLoc === Locale::FR, 'sites without English retain configured fallback');
    echo "PASS: $checks anonymous and saved-locale initialization checks\n";
}
