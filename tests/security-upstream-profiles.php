<?php

// Real user/profile-global methods; synthetic already-loaded character rows only.
namespace Aowow {
    class Cfg {
        public static bool $enabled = true;
        public static function get(string $key) : mixed { return $key === 'PROFILER_ENABLE' && self::$enabled; }
    }
    class Profiler {
        public static function getRealms() : array { return [1 => ['name'=>'Fixture Realm', 'region'=>'eu']]; }
        public static function urlize(string $name) : string { return strtolower(str_replace(' ', '-', $name)); }
    }
}
namespace {
    define('AOWOW_REVISION', 76); define('CLI', true);
    foreach (['defines.php', 'type.class.php', 'utilities.php', 'components/dbtypelist.class.php', 'components/filter.class.php', 'dbtypes/profile.class.php', 'user.class.php'] as $file)
        require __DIR__.'/../includes/'.$file;
}
namespace Aowow {
    class FixtureProfiles extends LocalProfileList {
        public function __construct() {}
        public function getFoundIDs() : array { return [1, 2]; }
        public function &iterate() : \Generator {
            foreach ([0, 1] as $custom) {
                $this->curTpl = ['id'=>$custom + 1, 'name'=>$custom ? 'Custom profile' : 'Real character', 'custom'=>$custom,
                    'realm'=>1, 'race'=>1, 'class'=>1, 'level'=>80, 'gender'=>0, 'cuFlags'=>0];
                yield $custom + 1 => $this->curTpl;
            }
        }
        public function getField(string $field, bool $localized = false, bool $silent = false) : mixed { return $this->curTpl[$field] ?? 0; }
    }
}
namespace {
    use Aowow\{Cfg, FixtureProfiles, User};
    set_error_handler(static function(int $code, string $message) : never { throw new ErrorException($message, 0, $code); });
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    User::$id = 1;
    (new ReflectionProperty(User::class, 'profiles'))->setValue(null, new FixtureProfiles);
    check(array_column(User::getCharacters(), 'name') === ['Real character'], 'Character globals contain the real character only');
    check(array_column(User::getProfiles(), 'name') === ['Custom profile'], 'Profile globals contain the custom profile only');
    check(User::getCharacters()[0]['realm'] === 'fixture-realm' && !isset(User::getProfiles()[0]['realm']), 'Character and custom globals retain their distinct shape');
    User::$id = 0;
    check(User::getCharacters() === [] && User::getProfiles() === [], 'Anonymous users receive no linked profile globals');
    User::$id = 1; Cfg::$enabled = false;
    check(User::getCharacters() === [] && User::getProfiles() === [], 'Disabled profiler returns no linked globals');
    echo "PASS: $checks upstream linked-profile checks\n";
}
