<?php

// Run the real map generator, image assembler and zone-page detection in a temporary tree.
namespace Aowow {
    class CLI {
        public const int LOG_ERROR = 1, LOG_WARN = 2, LOG_INFO = 3, LOG_BLANK = 4, LOG_OK = 5;
        public static array $messages = [];
        public static function write(string $message = '', int $level = 0, mixed ...$args) : void { self::$messages[] = [$level, $message]; }
        public static function bold(string $s) : string { return $s; }
        public static function red(string $s) : string { return $s; }
        public static function green(string $s) : string { return $s; }
        public static function yellow(string $s) : string { return $s; }
    }
    class CLISetup {
        public const int ARGV_PARAM = 1, ARGV_OPTIONAL = 2;
        public static string $srcDir;
        public static array $locales = [], $paths = [], $strings = [], $files = [];
        public static SetupScript $script;
        public static function registerSetup(string $command, SetupScript $script) : void { self::$script = $script; }
        public static function getOpt(string ...$names) : mixed {
            return count($names) === 1 ? false : array_fill_keys($names, false);
        }
        public static function fileExists(string &$file) : bool {
            if (!isset(self::$paths[strtolower(rtrim($file, '/'))])) return false;
            $file = self::$paths[strtolower(rtrim($file, '/'))];
            return true;
        }
        public static function filesInPathLocalized(string $path, bool &$success, bool $localized) : array {
            $paths = [];
            foreach (self::$locales as $id => $locale) {
                $file = sprintf($path, $locale->gameDirs()[0].'/');
                if (self::fileExists($file)) $paths[$id] = $file;
                else $success = false;
            }
            return $paths;
        }
        public static function searchGlobalStrings(string $pattern) : \Generator {
            foreach (self::$strings as $id => $lines)
                foreach ($lines as $line)
                    if (preg_match($pattern, $line, $matches)) yield $id => $matches;
        }
        public static function writeFile(string $file, string $contents) : bool { self::$files[$file] = $contents; return true; }
    }
    class DB {
        public static array $areas = [], $floors = [];
        public static function Aowow() : self { return new self; }
        public static function World() : self { return new self; }
        public function selectCell(string $sql, mixed ...$args) : int { return 1; }
        public function selectAssoc(string $sql, mixed ...$args) : array {
            if (str_contains($sql, 'FROM dbc_worldmaparea')) return self::$areas;
            if (str_contains($sql, 'FROM dbc_dungeonmap')) return self::$floors;
            // Nonempty overlay table; this unrelated record has no matching fixture area.
            if (str_contains($sql, 'FROM dbc_worldmapoverlay')) return [99999 => []];
            throw new \RuntimeException('Unexpected fixture query');
        }
    }
    class Lang {
        public static Locale $locale = Locale::EN;
        public static function load(Locale $locale) : void { self::$locale = $locale; }
        public static function getLocale() : Locale { return self::$locale; }
        public static function concat(array $values, ?callable $callback = null) : string { return implode(', ', array_map($callback, $values)); }
        public static function maps(string $key, array $args) : string { return sprintf(self::$locale === Locale::DE ? '%d. Stockwerk' : 'Level %d', ...$args); }
    }
    class Cfg { public static function get(string $key) : int { return 0; } }
    class User { public static function isInGroup(int $group) : bool { return false; } }
    class TemplateResponse { protected const int TAB_DATABASE = 0; }
    interface ICache {}
    trait TrDetailPage {}
    trait TrCache {}
    class Type { public const int ZONE = 7; }
}

namespace {
    use Aowow\{CLI, CLISetup, DB, Lang, Locale, ZoneBaseResponse};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/locale.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/setup/datatypes/primitives.php';
    require $root.'/includes/setup/files/binaryfile.class.php';
    require $root.'/includes/setup/files/blp2file.class.php';
    require $root.'/setup/tools/setupScript.class.php';
    require $root.'/endpoints/zone/zone.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }
    // zone, map, texture, DBC floors, source textures, expected output suffixes
    $fixtures = [
        [1176, 209, 'ZULFARRAK', [], [0], ['']],
        [1977, 309, 'ZULGURUB', [0], [0], ['']],
        [3428, 509, 'RUINSOFAHNQIRAJ', [], [0], ['']],
        [2557, 429, 'DIREMAUL', [3, 1, 2], [0, 1, 2, 3], ['-0', '-1', '-2', '-3']],
        [9001, 9001, 'FIXTURE_MIXED', [5, 0, 2], [0, 2, 5], ['-0', '-2', '-5']],
        [9002, 9002, 'FIXTURE_SINGLE', [1], [1], ['']],
        [9003, 9003, 'FIXTURE_NUMBERED', [2, 1], [1, 2], ['-1', '-2']],
        [9004, 9004, 'FIXTURE_NO_LABELS', [1], [0, 1], ['-0', '-1']],
        [12, 0, 'ELWYNN', [], [0], ['']],
        [3959, 564, 'BLACKTEMPLE', [7, 6, 5, 4, 3, 2, 1], range(0, 7), ['-0', '-1', '-2', '-3', '-4', '-5', '-6', '-7']],
        [4075, 580, 'SUNWELLPLATEAU', [1], [0, 1], ['-0', '-1']],
        [4273, 603, 'ULDUAR', [5, 4, 3, 2, 1], range(0, 5), ['-0', '-1', '-2', '-3', '-4', '-5']],
        [4100, 595, 'COTSTRATHOLME', [1], [0, 1], ['-1', '-2']],
        [4494, 619, 'AHNKAHET', [1], [1, 2], ['-1', '-2']],
        [4395, 571, 'DALARAN', [2, 1], [1, 2], ['-1', '-2']],
        [4722, 649, 'ARGENTTOURNAMENTRAID', [2, 1], [1, 2], ['-1', '-2']],
        [4723, 650, 'ARGENTTOURNAMENTRAID', [1], [1], ['']],
        [0, 1, 'KALIMDOR', [], [0], ['']]
    ];
    $temp = sys_get_temp_dir().'/aowow-map-test-'.bin2hex(random_bytes(8));
    mkdir($temp);
    $previous = getcwd();
    try {
        chdir($temp);
        CLISetup::$srcDir = $temp.'/source/';
        CLISetup::$locales = [Locale::EN->value => Locale::EN, Locale::DE->value => Locale::DE];
        $expectedFiles = [];
        foreach ($fixtures as $idx => [$zone, $map, $name, $floors, $textures, $suffixes]) {
            $wma = $zone ?: 13;
            DB::$areas[$zone ?: -$wma] = ['id' => $wma, 'mapId' => $map, 'areaId' => $zone, 'nameINT' => $name];
            if ($floors) DB::$floors[$zone === 4395 ? -495 : $map] = [implode(' ', $floors), count($floors)];
            foreach (CLISetup::$locales as $id => $locale) {
                $directory = CLISetup::$srcDir.$locale->gameDirs()[0].'/Interface/WorldMap/'.strtolower($name);
                if (!is_dir($directory)) mkdir($directory, 0777, true);
                $tile = imagecreatetruecolor(256, 256);
                foreach ($textures as $floor) {
                    imagefill($tile, 0, 0, imagecolorallocate($tile, 30 + $floor * 20, 80, 150));
                    for ($i = 1; $i <= 12; ++$i) {
                        $prefix = $directory.'/'.strtolower($name).($floor ? $floor.'_' : '').$i;
                        if (!$floor && in_array($zone, [1977, 2557])) {
                            // Minimal valid palette BLP2; exercise the native tile loader too.
                            $color = ((30 + $floor * 20) << 16) | (80 << 8) | 150;
                            $blp = 'BLP2'.pack('VCCCCVV', 1, 1, 0, 8, 0, 256, 256)
                                .pack('V16', 1172, ...array_fill(0, 15, 0))
                                .pack('V16', 65536, ...array_fill(0, 15, 0))
                                .pack('V256', $color, ...array_fill(0, 255, 0)).str_repeat("\0", 65536);
                            file_put_contents($prefix.'.blp', $blp);
                        }
                        else if (!file_exists($prefix.'.png')) imagepng($tile, $prefix.'.png');
                    }
                }
                unset($tile);
                $outZone = $zone ?: -6;
                foreach ($suffixes as $suffix) $expectedFiles[] = 'static/images/wow/maps/'.$locale->json().'/original/'.$outZone.$suffix.'.jpg';
                // Deliberately reverse GlobalStrings order and supply irrelevant labels.
                foreach (array_reverse(range(0, 9)) as $floor)
                    CLISetup::$strings[$id][] = 'DUNGEON_FLOOR_'.$name.$floor.' = "'.$locale->json().' '.$name.' floor '.$floor.'";';
            }
        }
        foreach (CLISetup::$locales as $id => $locale) {
            // Exercise synthesized World and Cosmic maps from prepare().
            foreach (['World' => -1, 'Cosmic' => -4] as $name => $zone) {
                $directory = CLISetup::$srcDir.$locale->gameDirs()[0].'/Interface/WorldMap/'.$name;
                mkdir($directory, 0777, true);
                $tile = imagecreatetruecolor(256, 256);
                for ($i = 1; $i <= 12; ++$i) imagepng($tile, $directory.'/'.$name.$i.'.png');
                unset($tile);
                $expectedFiles[] = 'static/images/wow/maps/'.$locale->json().'/original/'.$zone.'.jpg';
            }
            CLISetup::$strings[$id] = array_values(array_filter(CLISetup::$strings[$id], fn($s) => !str_contains($s, 'FIXTURE_NO_LABELS')));
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CLISetup::$srcDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file)
            CLISetup::$paths[strtolower($file->getPathname())] = $file->getPathname();
        require $root.'/setup/tools/filegen/img-maps.ss.php';
        foreach (CLISetup::$script->getRequiredDirs() as $dir)
            if (!is_dir($dir)) mkdir($dir, 0777, true);
        check(CLISetup::$script->generate(), 'Full generator succeeds with Wrath-compatible rows and supplied textures');
        foreach ($expectedFiles as $file) {
            check(file_exists($file), 'Generated image: '.$file);
            [$width, $height] = getimagesize($file);
            check([$width, $height] === [1002, 668], 'Original image dimensions: '.$file);
        }
        $actualFiles = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('static', FilesystemIterator::SKIP_DOTS)) as $file)
            $actualFiles[] = $file->getPathname();
        sort($actualFiles); sort($expectedFiles);
        check($actualFiles === $expectedFiles, 'No unexpected suffixed, duplicated or missing image outputs');
        $datasets = CLISetup::$files;
        foreach (CLISetup::$locales as $id => $locale) {
            Lang::load($locale);
            $source = $datasets['datasets/'.$locale->json().'/zones'];
            check((bool)preg_match('/Mapper.multiLevelZones = (.+);\n\nvar g_zone_areas = (.+);/s', $source, $matches), 'Complete zones dataset');
            $maps = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
            $labels = json_decode($matches[2], true, flags: JSON_THROW_ON_ERROR);
            foreach ($fixtures as [$zone, $map, $name, $floors, $textures, $suffixes]) {
                if (count($suffixes) === 1) {
                    check(!isset($maps[$zone]) && !isset($labels[$zone]), 'Single map has no floor selector: '.$zone);
                    continue;
                }
                check($maps[$zone] === array_map(fn($suffix) => $zone.$suffix, $suffixes), 'Ordered Mapper filenames: '.$zone);
                $expectedLabels = array_map(fn($suffix) => $locale->json().' '.$name.' floor '.substr($suffix, 1), $suffixes);
                if ($zone === 4494) $expectedLabels[1] = Lang::maps('floorN', [2]);
                if ($zone === 9004) $expectedLabels = [Lang::maps('floorN', [1]), Lang::maps('floorN', [2])];
                check($labels[$zone] === $expectedLabels, 'Floor labels match image order: '.$zone);
            }
            foreach ([0, 1, 2, 3] as $floor) {
                $image = imagecreatefromjpeg('static/images/wow/maps/'.$locale->json().'/original/2557-'.$floor.'.jpg');
                $red = (imagecolorat($image, 128, 128) >> 16) & 255;
                check(abs($red - (30 + $floor * 20)) <= 3, 'Dire Maul image uses the corresponding source floor: '.$floor);
                unset($image);
            }
        }
        check(!array_filter(CLI::$messages, fn($m) => $m[0] === CLI::LOG_ERROR), 'No generator errors');
        $hashes = array_map('hash_file', array_fill(0, count($expectedFiles), 'sha256'), $expectedFiles);
        (new ReflectionMethod(CLISetup::$script, 'buildMaps'))->invoke(CLISetup::$script);
        (new ReflectionMethod(CLISetup::$script, 'buildZonesFile'))->invoke(CLISetup::$script);
        check(CLISetup::$files === $datasets, 'Cached-image rebuild retains filenames and label order');
        check($hashes === array_map('hash_file', array_fill(0, count($expectedFiles), 'sha256'), $expectedFiles), 'Existing images are skipped without --force');

        $page = (new ReflectionClass(ZoneBaseResponse::class))->newInstanceWithoutConstructor();
        $hasMap = new ReflectionMethod($page, 'hasMap');
        foreach ([Locale::EN, Locale::DE, Locale::FR] as $locale) {
            Lang::load($locale);
            foreach ($fixtures as [$zone]) {
                $page->typeId = $zone ?: -6;
                check($hasMap->invoke($page), 'Map page detects generated image with locale/fallback: '.$page->typeId);
            }
            $page->typeId = 999999;
            check(!$hasMap->invoke($page), 'Missing map stays absent');
        }
        // Base-only suffixed image detection must work even without floor 1.
        unlink('static/images/wow/maps/enus/original/9004-1.jpg');
        Lang::load(Locale::FR); $page->typeId = 9004;
        check($hasMap->invoke($page), 'Map page finds English -0 image without -1');

        if (in_array('--fixtures', $argv, true))
            echo json_encode(['files' => $datasets, 'images' => $expectedFiles], JSON_THROW_ON_ERROR);
        else
            echo "PASS: $checks map generation and zone-page checks\n";
    }
    finally {
        chdir($previous);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file)
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($temp);
    }
}
