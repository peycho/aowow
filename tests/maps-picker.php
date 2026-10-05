<?php

// Execute the actual Maps endpoint/template and shared image detection with synthetic zone rows.
namespace Aowow {
    class Lang {
        public static Locale $locale = Locale::EN;
        public static function getLocale() : Locale { return self::$locale; }
        public static function maps(string $key, array $args = []) : string { return $key; }
    }
    class Cfg { public static function get(string $key) : int { return 0; } }
    class User { public static function isInGroup(int $group) : bool { return false; } }
    class TemplateResponse {
        protected const int TAB_TOOLS = 1, TAB_DATABASE = 0;
        public array $title = [];
        public string $h1 = '';
        public bool $generated = false;
        protected function generate() : void { $this->generated = true; }
    }
    interface ICache {}
    trait TrDetailPage {}
    trait TrCache {}
    class Type { public const int ZONE = 7; }
    class DB {
        public static array $rows = [], $queries = [];
        public static bool $fail = false;
        public static function Aowow() : self { return new self; }
        public function selectAssoc(string $sql, array $categories, int $exclude) : ?array {
            self::$queries[] = [$sql, $categories, $exclude];
            if (self::$fail) return null;
            return array_values(array_filter(self::$rows, fn($row) => in_array($row['category'], $categories) && !$row['parentArea'] && !($row['cuFlags'] & $exclude)));
        }
    }
}

namespace {
    use Aowow\{DB, Lang, Locale, MapImages, MapsBaseResponse, ZoneBaseResponse};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/locale.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/components/mapimages.class.php';
    require $root.'/includes/components/pagetemplate.class.php';
    require $root.'/endpoints/maps/maps.php';
    require $root.'/endpoints/zone/zone.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }
    class MapsTemplateFixture {
        public array $instanceMaps;
        public array $mapLocales;
        private Aowow\Template\PageTemplate $template;
        public function __construct() { $this->template = (new ReflectionClass(Aowow\Template\PageTemplate::class))->newInstanceWithoutConstructor(); }
        private function brick(string $name) : void {}
        private function json(mixed $value) : string { return (new ReflectionMethod($this->template, 'json'))->invoke($this->template, $value); }
        public function render(MapsBaseResponse $page, string $root) : string {
            $this->instanceMaps = $page->instanceMaps;
            $this->mapLocales = $page->mapLocales;
            ob_start();
            include $root.'/template/pages/maps.tpl.php';
            return ob_get_clean();
        }
    }
    $wrathDungeons = [206, 1196, 4100, 4196, 4228, 4264, 4265, 4272, 4277, 4415, 4416, 4494, 4723, 4809, 4813, 4820];
    $wrathRaids = [3456, 4273, 4493, 4500, 4603, 4722, 4812, 4987];
    $temp = sys_get_temp_dir().'/aowow-picker-test-'.bin2hex(random_bytes(8));
    mkdir($temp);
    $previous = getcwd();
    $fixtures = [];
    try {
        chdir($temp);
        $addImage = function (int $id, string $suffix = '', string $locale = 'enus') : void {
            $dir = 'static/images/wow/maps/'.$locale.'/original/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir.$id.$suffix.'.jpg', 'synthetic image availability fixture');
        };
        $addZone = function (int $id, int $category, string $name, int $parent = 0, int $flags = 0) : void {
            DB::$rows[] = ['id' => $id, 'category' => $category, 'parentArea' => $parent, 'cuFlags' => $flags,
                'name_loc0' => $name, 'name_loc2' => '', 'name_loc3' => 'DE '.$name, 'name_loc4' => '', 'name_loc6' => '', 'name_loc8' => ''];
        };
        foreach ($wrathDungeons as $id) { $addZone($id, MAP_TYPE_DUNGEON, 'Wrath dungeon '.$id); $addImage($id, $id === 4723 ? '' : '-1'); }
        foreach ($wrathRaids as $id) { $addZone($id, MAP_TYPE_RAID, 'Wrath raid '.$id); $addImage($id, '-1'); }
        $payload = '$(globalThis.pickerMarker=1)</script><script>globalThis.pickerMarker=2</script><!-- Български 中文';
        foreach ([1176 => $payload, 2557 => 'Dire Maul', 3562 => 'Hellfire Ramparts'] as $id => $name) {
            $addZone($id, MAP_TYPE_DUNGEON, $name); $addImage($id, $id === 2557 ? '-0' : '');
        }
        foreach ([1977 => "Zul'Gurub", 3457 => 'Karazhan', 3959 => 'Black Temple', 4075 => 'Sunwell Plateau'] as $id => $name) {
            $addZone($id, MAP_TYPE_RAID, $name); $addImage($id, match ($id) { 1977 => '', 3457 => '-2', 3959 => '-0', default => '-1' });
        }
        $addZone(3428, MAP_TYPE_RAID, 'Ruins of Ahn\'Qiraj'); // supplied metadata, images not generated yet
        $addZone(9005, MAP_TYPE_DUNGEON, 'Hidden', flags: CUSTOM_EXCLUDE_FOR_LISTVIEW); $addImage(9005);
        $addZone(9006, MAP_TYPE_DUNGEON, 'Subzone', parent: 1176); $addImage(9006);
        $addZone(12, 0, 'Outdoor'); $addImage(12);
        $addZone(9007, MAP_TYPE_DUNGEON, 'Chinese-only image'); $addImage(9007, locale: 'zhcn');
        $addZone(9008, MAP_TYPE_DUNGEON, 'German-only image'); $addImage(9008, '-3', 'dede');
        $addZone(9009, MAP_TYPE_RAID, 'Directory masquerading as image'); mkdir('static/images/wow/maps/enus/original/9009.jpg');
        $addZone(9010, MAP_TYPE_RAID, 'Preview, not a map'); $addImage(9010, '-preview');
        $expectedDungeons = [...$wrathDungeons, 1176, 2557, 3562];
        $expectedRaids = [...$wrathRaids, 1977, 3457, 3959, 4075];
        $images = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('static', FilesystemIterator::SKIP_DOTS)) as $file)
            if ($file->isFile()) $images[] = $file->getPathname();
        $generate = new ReflectionMethod(MapsBaseResponse::class, 'generate');
        $zonePage = (new ReflectionClass(ZoneBaseResponse::class))->newInstanceWithoutConstructor();
        $hasMap = new ReflectionMethod($zonePage, 'hasMap');
        foreach ([Locale::EN, Locale::DE, Locale::FR, Locale::CN] as $locale) {
            Lang::$locale = $locale;
            $page = (new ReflectionClass(MapsBaseResponse::class))->newInstanceWithoutConstructor();
            $generate->invoke($page);
            check($page->generated && $page->title === ['maps'], 'Existing page generation runs');
            $expected = [...$expectedDungeons];
            if ($locale === Locale::DE) $expected[] = 9008;
            if ($locale === Locale::CN) $expected[] = 9007;
            sort($expected);
            $actual = array_keys($page->instanceMaps['dungeons']); sort($actual);
            check($actual === $expected, 'Dungeons include available Classic/TBC/Wrath maps in '.$locale->json());
            $expected = [...$expectedRaids]; sort($expected);
            $actual = array_keys($page->instanceMaps['raids']); sort($actual);
            check($actual === $expected, 'Raids include available Classic/TBC/Wrath maps in '.$locale->json());
            check($page->instanceMaps['dungeons'][1176] === ($locale === Locale::DE ? 'DE ' : '').$payload, 'Localized names fall back to English as needed');
            check($page->mapLocales[1176] === 'enus', 'English-only images use the English image directory');
            if ($locale === Locale::DE) check($page->mapLocales[9008] === 'dede', 'Active-locale images take precedence');
            if ($locale === Locale::CN) check($page->mapLocales[9007] === 'zhcn', 'Available Chinese image locale is retained');
            foreach (DB::$rows as $row) {
                $zonePage->typeId = $row['id'];
                check($hasMap->invoke($zonePage) === MapImages::exists($row['id'], $locale), 'Zone page uses the shared availability check');
            }
            $html = (new MapsTemplateFixture)->render($page, $root);
            check((bool)preg_match('~<script type="text/javascript">(.*?)</script>~s', $html, $match), 'Actual template initializes the picker');
            check(!preg_match('~</script|<!--~i', $match[1]), 'Picker names cannot escape the inline script');
            $fixtures[] = ['locale' => $locale->json(), 'script' => $match[1], 'maps' => $page->instanceMaps, 'mapLocales' => $page->mapLocales, 'images' => $images, 'html' => $html];
        }
        foreach (DB::$queries as [$sql, $categories, $exclude]) {
            check($categories === [MAP_TYPE_DUNGEON, MAP_TYPE_RAID] && $exclude === CUSTOM_EXCLUDE_FOR_LISTVIEW, 'Use existing category and visibility metadata');
            check(str_contains($sql, '`category` IN %in') && str_contains($sql, '`parentArea` = 0') && str_contains($sql, '(`cuFlags` & %i) = 0'), 'Query excludes outdoor, subzone and hidden records');
        }
        Lang::$locale = Locale::EN;
        $addImage(3428);
        $page = (new ReflectionClass(MapsBaseResponse::class))->newInstanceWithoutConstructor(); $generate->invoke($page);
        check(isset($page->instanceMaps['raids'][3428]), 'Newly generated images appear on the next request');
        unlink('static/images/wow/maps/enus/original/1977.jpg');
        $page = (new ReflectionClass(MapsBaseResponse::class))->newInstanceWithoutConstructor(); $generate->invoke($page);
        check(!isset($page->instanceMaps['raids'][1977]), 'Removed images disappear on the next request');
        DB::$rows = [];
        $page = (new ReflectionClass(MapsBaseResponse::class))->newInstanceWithoutConstructor(); $generate->invoke($page);
        check($page->instanceMaps === ['dungeons' => [], 'raids' => []], 'Empty metadata yields empty instance groups');
        $html = (new MapsTemplateFixture)->render($page, $root);
        preg_match('~<script type="text/javascript">(.*?)</script>~s', $html, $match);
        $fixtures[] = ['locale' => 'empty', 'script' => $match[1], 'maps' => $page->instanceMaps, 'mapLocales' => $page->mapLocales, 'images' => $images, 'html' => $html];
        DB::$fail = true;
        $page = (new ReflectionClass(MapsBaseResponse::class))->newInstanceWithoutConstructor(); $generate->invoke($page);
        check($page->instanceMaps === ['dungeons' => [], 'raids' => []], 'Missing metadata does not create broken options');
        if (in_array('--fixtures', $argv, true)) echo json_encode($fixtures, JSON_THROW_ON_ERROR);
        else echo "PASS: $checks map picker metadata/image/template checks\n";
    }
    finally {
        chdir($previous);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($temp);
    }
}
