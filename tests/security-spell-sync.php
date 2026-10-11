<?php

// Real filter parsing, spell rendering and custom-data trait with synthetic DB/identity/game rows.
namespace Aowow {
    class User {
        public static int $groups = 0;
        public static function isInGroup(int $mask) : bool { return (self::$groups & $mask) !== 0; }
    }
    class Cfg { public static function get(string $key) : int { return 0; } }
    class Lang {
        public static array $data = [];
        private static function text(string $section, array $args) : mixed {
            $value = self::$data[$section];
            foreach ($args as $arg) {
                if (is_array($arg)) return vsprintf($value, $arg);
                $value = $value[$arg] ?? null;
            }
            return $value;
        }
        public static function spell(mixed ...$args) : mixed { return self::text('spell', $args); }
        public static function main(mixed ...$args) : mixed { return self::text('main', $args); }
        public static function game(mixed ...$args) : mixed { return self::text('game', $args); }
        public static function enchantment(mixed ...$args) : mixed { return self::text('enchantment', $args); }
    }
    class DB {
        public const string AND = '%and', OR = '%or';
        public static array $calls = [];
        public static ?array $ids = [7266], $customData = [];
        public static ?int $attributes = 0, $updateResult = 0;
        public static function World() : self { return new self; }
        public static function Aowow() : self { return new self; }
        public function selectCol(string $sql, mixed ...$args) : ?array {
            self::$calls[] = [$sql, $args];
            return str_contains($sql, 'setup_custom_data') ? self::$customData : self::$ids;
        }
        public function selectCell(string $sql, mixed ...$args) : ?int {
            self::$calls[] = [$sql, $args];
            return self::$attributes;
        }
        public function selectRow(string $sql, mixed ...$args) : ?array { return null; }
        public function selectAssoc(string $sql, mixed ...$args) : array { return []; }
        public function qry(string $sql, mixed ...$args) : ?int {
            self::$calls[] = [$sql, $args]; return self::$updateResult;
        }
    }
    class DBTypeList { protected array $curTpl = []; }
    trait listviewHelper {}
    trait sourceHelper {}
    interface ICache {}
    trait TrDetailPage { public ?string $transfer = null; }
    trait TrCache {}
    class TemplateResponse {
        protected const int TAB_DATABASE = 0;
        public string $h1 = 'Synthetic spell';
        public ?string $article = null;
        public function brick(string $name, array $args = []) : void {}
        protected function fmtStaffTip(?string $text, string $tip) : string { return $text ?? ''; }
        protected function extendGlobalIds(int $type, int ...$ids) : void {}
    }
    class GameObjectList {
        public static array $conditions = [];
        public function __construct(array $conditions) { self::$conditions = $conditions; }
        public static function makeLink(int $id) : string { return '<a href="?object='.$id.'">Duel Flag</a>'; }
        public function iterate() : \Generator { yield 21680 => []; }
        public function getField(string $field, bool $localized = false) : mixed {
            return $field === 'displayId' ? 7871 : 'Duel Flag';
        }
    }
}
namespace {
    define('AOWOW_REVISION', 73); define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/type.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/components/guidemgr.class.php';
    foreach (['SmartAI', 'SmartEvent', 'SmartAction', 'SmartTarget'] as $class)
        require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    require $root.'/includes/components/filter.class.php';
    require $root.'/includes/dbtypes/spell.class.php';
    require $root.'/includes/game/chrstatistics.php';
    require $root.'/includes/dbtypes/item.class.php';
    require $root.'/includes/dbtypes/quest.class.php';
    require $root.'/includes/dbtypes/achievement.class.php';
    require $root.'/setup/tools/setupScript.class.php';
    require $root.'/endpoints/spell/spell.php';
    require $root.'/endpoints/enchantment/enchantment.php';
}
namespace Aowow {
    class FixtureSpell extends SpellList {
        public array $fields = [];
        public function __construct() {}
        public function getField(string $field, bool $localized = false) : mixed { return $this->fields[$field] ?? 0; }
        public function canCreateItem() : array { return []; }
        public function canTeachSpell() : array { return []; }
        public function canTriggerSpell() : array { return []; }
        public function isScalableHealingSpell() : bool { return false; }
        public function isScalableDamagingSpell() : bool { return false; }
        public function iterate() : \Generator {
            $this->curTpl = $this->fields;
            yield 7266 => [];
        }
    }
    class FixtureCustomData { use TrCustomData; public function getName() : string { return 'spell'; } }
}
namespace {
    use Aowow\{AchievementListFilter, DB, EnchantmentBaseResponse, FixtureCustomData, FixtureSpell, GameObjectList, ItemListFilter, Lang, QuestListFilter, SpellBaseResponse, SpellListFilter, User, Util};
    $checks = 0; $diagnostics = [];
    set_error_handler(function (int $code, string $message) use (&$diagnostics) : bool {
        if (in_array($code, [E_USER_NOTICE, E_USER_WARNING], true)) { $diagnostics[] = $message; return true; }
        throw new ErrorException($message, 0, $code);
    });
    function check(bool $ok, string $message) : void {
        global $checks; ++$checks;
        if (!$ok) throw new RuntimeException($message);
    }
    function filter(int $id, mixed $bit) : SpellListFilter {
        return new SpellListFilter('cr='.$id.';crs='.$bit.';crv=0');
    }
    foreach ([0, U_GROUP_TESTER, U_GROUP_MOD, U_GROUP_EDITOR, U_GROUP_PREMIUM] as $group) {
        User::$groups = $group;
        foreach ([96, 104, 105, 202] as $id) {
            DB::$calls = [];
            $f = filter($id, 1); $f->getConditions();
            check($f->getSetCriteria($id) === [] && $f->shouldReload, 'Forged staff criteria are removed for nonemployees');
            check(DB::$calls === [], 'Denied filter performs no world query');
        }
    }
    foreach ([U_GROUP_ADMIN, U_GROUP_BUREAU, U_GROUP_DEV] as $group) {
        User::$groups = $group;
        foreach ([1, 12, 26, 32] as $bit) {
            DB::$calls = [];
            $f = filter(202, $bit); $conditions = $f->getConditions();
            check($f->getSetCriteria(202) === [202] && !$f->error, 'Employees retain valid custom-attribute filters');
            check(DB::$calls[0][1] === [1 << ($bit - 1)], 'Bounded bit selector becomes a parameterized integer mask');
            check($conditions === [DB::AND, [DB::AND, ['s.id', [7266]]]], 'World matches become spell ID conditions');
            check($f->buildGETParam() === 'cr=202;crs='.$bit.';crv=0', 'Custom criterion round-trips through URLs');
        }
    }
    User::$groups = U_GROUP_ADMIN;
    foreach ([0, -1, 33, 99999, '1 OR 1=1', '1.5', '1e100', '999999999999999999999'] as $bit) {
        foreach ([96, 104, 105, 202] as $id) {
            DB::$calls = []; $f = filter($id, $bit); $f->getConditions();
            check($f->getSetCriteria($id) === [] && $f->error, 'Invalid bit selector '.$bit.' on criterion '.$id.' is rejected before shifting');
            check(DB::$calls === [], 'Malformed selectors perform no query');
        }
    }
    foreach ([104 => 'targets', 105 => 'stanceMaskNot', 106 => 'spellFamilyFlags1'] as $id => $column) {
        $f = filter($id, 32);
        check($f->getConditions() === [DB::AND, [DB::AND, [[$column, 1 << 31, '&'], 1 << 31]]], 'Legacy staff URLs retain their columns and high bit');
        check($f->buildGETParam() === 'cr='.$id.';crs=32;crv=0', 'Legacy URL round-trip is unchanged');
    }
    $f = filter(202, 1); User::$groups = 0; DB::$calls = [];
    check($f->getConditions() === [DB::AND, [DB::AND, [0]]] && DB::$calls === [], 'Role loss before condition generation denies the callback query');
    User::$groups = U_GROUP_ADMIN;
    foreach ([[], null] as $ids) {
        DB::$ids = $ids;
        check(filter(202, 1)->getConditions() === [DB::AND, [DB::AND, [0]]], 'No matches or failed world reads produce no spell matches');
    }
    DB::$ids = [7266];
    foreach ([[AchievementListFilter::class, 18, 'flags'], [QuestListFilter::class, 42, 'flags'], [ItemListFilter::class, 176, 'flags'], [ItemListFilter::class, 177, 'flagsExtra']] as [$class, $id, $column]) {
        User::$groups = U_GROUP_ADMIN;
        $f = new $class('cr='.$id.';crs=32;crv=0');
        check($f->getConditions() === [DB::AND, [DB::AND, [[$column, 1 << 31, '&'], 1 << 31]]], 'Other staff-flag consumers retain high-bit filtering');
        foreach ([0, 33, '1.5', '1e100'] as $bit) {
            $f = new $class('cr='.$id.';crs='.$bit.';crv=0');
            check($f->getSetCriteria($id) === [] && $f->error, 'Other staff-flag consumers reject malformed positions');
        }
        User::$groups = 0;
        check((new $class('cr='.$id.';crs=1;crv=0'))->getSetCriteria($id) === [], 'Other staff flags remain employee-only');
    }
    $page = (new ReflectionClass(SpellBaseResponse::class))->newInstanceWithoutConstructor();
    $spell = new FixtureSpell;
    (new ReflectionProperty(SpellBaseResponse::class, 'subject'))->setValue($page, $spell);
    $page->typeId = 7266;
    $attributeMethod = new ReflectionMethod($page, 'createAttributesList');
    $effectMethod = new ReflectionMethod($page, 'createEffects');
    foreach (['enus', 'dede', 'eses', 'frfr', 'ruru', 'zhcn'] as $locale) {
        require $root.'/localization/locale_'.$locale.'.php'; Lang::$data = $lang;
        User::$groups = 0;
        $spell->fields = ['attributes0' => SPELL_ATTR0_PASSIVE];
        DB::$attributes = SPELL_ATTR0_CU_CAN_CRIT | SPELL_ATTR0_CU_ROLLING_PERIODIC;
        $attributeMethod->invoke($page);
        check(count($page->attributes) === 4 && $page->attributes[1] === null, 'Known custom attributes follow a single separator in every locale');
        check($page->attributes[2] === Lang::spell('attributesCu', SPELL_ATTR0_CU_CAN_CRIT), 'Custom attribute names come from the active locale');
        ob_start(); (function () use ($root) { include $root.'/template/pages/spell.tpl.php'; })->call($page); $html = ob_get_clean();
        check(str_contains($html, '<h1>Synthetic spell</h1>') && !str_contains($html, 'h1-icon'), 'Spell heading renders without an unused icon offset');
        check(substr_count($html, '<hr ') === 1 && !str_contains($html, '<li></li>'), 'Attribute separator renders valid lists');
        $enchantment = (new ReflectionClass(EnchantmentBaseResponse::class))->newInstanceWithoutConstructor();
        ob_start(); (function () use ($root) { include $root.'/template/pages/enchantment.tpl.php'; })->call($enchantment); $enchantmentHtml = ob_get_clean();
        check(str_contains($enchantmentHtml, '<h1>Synthetic spell</h1>') && !str_contains($enchantmentHtml, 'h1-icon'), 'Enchantment headings render without an unused icon offset');
        DB::$attributes = 1 << 31; $attributeMethod->invoke($page);
        check(count($page->attributes) === 1 && !in_array(null, $page->attributes, true), 'Hidden unknown attributes do not add a stray separator');
        User::$groups = U_GROUP_MOD; $attributeMethod->invoke($page);
        check(str_contains(end($page->attributes), 'Unknown CustomAttribute: 0x80000000'), 'Staff unknown attributes use bounded numeric hex output');
        foreach ([130, 172, 206, 207, 208, 209, 210, 211] as $aura) {
            $spell->fields = ['effect1Id' => SPELL_EFFECT_APPLY_AURA, 'effect1AuraId' => $aura, 'effect1BasePoints' => 59, 'effect1DieSides' => 1];
            $effectMethod->invoke($page);
            check(str_contains($page->effects[1]['footer']['value'], '60%'), 'Speed aura details display a percentage');
        }
        $spell->fields = ['effect1Id' => SPELL_EFFECT_DUEL, 'effect1MiscValue' => 21680];
        $effectMethod->invoke($page);
        check(str_contains($page->effects[1]['name'], '?object=21680'), 'Duel MiscValue links to its gameobject');
        foreach ([2, 3] as $i) { $spell->fields['effect'.$i.'Id'] = 0; $spell->fields['effect'.$i.'MiscValue'] = 0; $spell->fields['effect'.$i.'AuraId'] = 0; }
        $model = $spell->getModelInfo(7266, 1);
        check($model['typeId'] === 21680 && $model['displayId'] === 7871, 'Duel effects use the shared object-model lookup');
    }
    $data = new FixtureCustomData;
    DB::$customData = []; check($data->applyCustomData(), 'Empty custom data is a successful no-op');
    DB::$customData = null; check(!$data->applyCustomData(), 'Failed custom-data lookup is not successful');
    DB::$customData = [7266 => ['name_loc0' => 'SECRET_CUSTOM_VALUE']]; DB::$updateResult = 0;
    check($data->applyCustomData(), 'Zero affected rows remain a successful custom update');
    DB::$updateResult = null;
    check(!$data->applyCustomData(), 'Swallowed update failure returns false');
    check(!str_contains(implode('\n', $diagnostics), 'SECRET_CUSTOM_VALUE'), 'Custom-data diagnostics do not disclose field values');
    check(Util::createNumRange(-120, -60, '-', callback: fn($x) => (string)-$x) === '120-60', 'Renamed duration callback remains callable');
    echo "PASS: $checks upstream spell/filter/custom-data security and compatibility checks\n";
}
