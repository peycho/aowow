<?php

// Exercise the actual talent dataset generator with synthetic database/spell boundaries.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : int { return 0; }
    }
    class User {
        public static function isInGroup(int $group) : bool { return false; }
    }
    class Lang {
        public static Locale $locale = Locale::EN;
        public static function load(Locale $locale) : void { self::$locale = $locale; }
        public static function getLocale() : Locale { return self::$locale; }
    }
    class CLISetup {
        public const int ARGV_PARAM = 1;
        public static array $locales = [], $files = [];
        public static SetupScript $script;
        public static function registerSetup(string $command, SetupScript $script) : void { self::$script = $script; }
        public static function writeFile(string $file, string $contents) : bool { self::$files[$file] = $contents; return true; }
    }
    class SpellList {
        public const string PAYLOAD = '$(globalThis.aowowSecurityMarker=1)</script><script>globalThis.aowowSecurityMarker=2</script><!-- Български 中文';
        public function __construct(array $conditions) {}
        public function getProfilerMods() : array {
            // Same explicit callback type returned by the real weapon-restricted profiler modifiers.
            return [101 => ['mlecritstrkpct' => [1, 'functionOf', new JsExpression('function() { var j, w = _inventory.getInventory()[16]; if (!w[0] || !g_items[w[0]]) { return 0; } j = g_items[w[0]].jsonequip; return (j.classs == 2 && (1 & (1 << j.subclass))) ? 5 : 0; }')]]];
        }
        public function getEntry(int $spell) : bool { return true; }
        public function parseText() : array { return [self::PAYLOAD]; }
        public function getTalentHeadForCurrent() : string { return self::PAYLOAD; }
    }
    class DB {
        public static function Aowow() : self { return new self; }
        public function selectCol(string $query, mixed ...$args) : array {
            if (str_contains($query, 'categoryEnumID')) return [1 => 0];
            if (str_contains($query, 'dbc_creaturefamily')) return [1 => 'ability_fixture'];
            return [101];
        }
        public function selectAssoc(string $query, mixed ...$args) : array {
            $names = array_fill_keys(['name_loc0', 'name_loc2', 'name_loc3', 'name_loc4', 'name_loc6', 'name_loc8'], SpellList::PAYLOAD);
            if (str_contains($query, 'FROM dbc_talenttab'))
                return [$names + ['id' => 1, 'creatureFamilyMask' => 1]];
            return [$names + ['tId' => 1, 'maxRank' => 1, 'column' => 0, 'row' => 0, 'rank1' => 101,
                'talentSpell' => 1, 'reqTalent' => 0, 'iconString' => 'ability_fixture', 'petCategory1' => 1, 'petCategory2' => 0]];
        }
    }
}

namespace {
    use Aowow\{ChrClass, CLISetup, Locale, SpellList};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/locale.class.php';
    require __DIR__.'/../includes/game/chrclass.class.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/components/jsexpression.class.php';
    require __DIR__.'/../setup/tools/setupScript.class.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }
    CLISetup::$locales = [Locale::EN, Locale::DE, Locale::FR, Locale::ES, Locale::RU, Locale::CN];
    require __DIR__.'/../setup/tools/filegen/talentcalc.ss.php';
    check(CLISetup::$script->generate(), 'Actual talent generator succeeds without encoding warnings');
    check(count(CLISetup::$files) === count(CLISetup::$locales) * (count(ChrClass::cases()) + 1), 'All class and pet datasets generated');
    foreach (CLISetup::$locales as $locale) {
        foreach (ChrClass::cases() as $class) {
            $file = 'datasets/'.$locale->json().'/talents-'.$class->value;
            $source = CLISetup::$files[$file];
            check(str_starts_with($source, '$WowheadTalentCalculator.registerClass('.$class->value.', ['), 'Complete class registration: '.$file);
            check(str_contains($source, '"functionOf",function()'), 'Profiler callbacks remain functions: '.$file);
            check(!preg_match('~</script|<!--~i', $source), 'Talent text cannot escape scripts: '.$file);
        }
        $source = CLISetup::$files['datasets/'.$locale->json().'/pet-talents'];
        check(str_contains($source, 'var g_pet_talents = ['), 'Complete pet dataset');
        check(!preg_match('~</script|<!--~i', $source), 'Pet text cannot escape scripts');
    }
    if (in_array('--fixtures', $argv, true))
        echo json_encode(['files' => CLISetup::$files, 'payload' => SpellList::PAYLOAD], JSON_THROW_ON_ERROR);
    else
        echo "PASS: $checks talent dataset generation and security checks\n";
}
