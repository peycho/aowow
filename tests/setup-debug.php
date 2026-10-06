<?php

// Real CLI, option parser, SQL/build runners and sync; replace only DB and generators.
namespace Aowow {
    class Cfg { public static function get(string $key) : int { return 0; } }
    class Lang { public static function concat(array $items) : string { return implode(', ', $items); } }
    class FixtureResult {
        public function __construct(private array $rows) {}
        public function fetchAll() : array { return $this->rows; }
        public function fetchSingle() : mixed { return $this->rows[0]['build'] ?? $this->rows[0]['sql'] ?? null; }
    }
    class DB {
        public static array $pending = ['sql' => '', 'build' => ''];
        public static int $acknowledgements = 0;
        public static int $errors = 0;
        public static function errorCount() : int { return self::$errors; }
        public static function isConnected(int $db) : bool { return true; }
        public static function Aowow() : self { return new self; }
        public function query(string $query, mixed ...$args) : FixtureResult {
            if (str_starts_with($query, 'UPDATE')) {
                self::$pending[$args[0]] = $args[1]; self::$acknowledgements++;
            }
            return new FixtureResult(isset($args[0]) ? [[$args[0] => self::$pending[$args[0]]]] : []);
        }
    }
}

namespace {
    define('AOWOW_REVISION', 69);
    define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/components/errorlog.class.php';
    require $root.'/includes/setup/cli.class.php';
    require $root.'/setup/tools/setupScript.class.php';
    require $root.'/setup/tools/utilityScript.class.php';
    require $root.'/setup/tools/CLISetup.class.php';
}

namespace Aowow {
    class FixtureGenerator extends SetupScript {
        public int $calls = 0;
        public bool $requirements = true, $result = true, $throws = false;
        public bool $databaseError = false, $customResult = true;
        public function applyCustomData() : bool { return $this->customResult; }
        public function __construct(string $name) { $this->info = [$name => [[], 0, 'Synthetic generator']]; }
        public function fulfillRequirements() : bool { return $this->requirements; }
        public function generate() : bool {
            $this->calls++;
            if ($this->databaseError) DB::$errors++;
            if ($this->throws) throw new \RuntimeException('SECRET_EXCEPTION SQL SELECT SECRET_QUERY', 42, new \LogicException('SECRET_CAUSE'));
            return $this->result;
        }
    }
}

namespace {
    use Aowow\{CLI, CLISetup, DB, FixtureGenerator};
    if (in_array('--parser', $argv, true)) {
        CLISetup::evalOpts();
        echo json_encode(['debug' => CLISetup::getOpt('debug')], JSON_THROW_ON_ERROR);
        exit;
    }
    if (!in_array('--worker', $argv, true)) {
        $checks = 0;
        function check(bool $ok, string $message) : void {
            global $checks;
            ++$checks;
            if (!$ok) throw new RuntimeException($message);
        }
        function process(array $args) : array {
            $process = proc_open([PHP_BINARY, __FILE__, ...$args], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($process), $out.$err];
        }
        foreach ([false, true] as $debug) {
            [$code, $out] = process(['--parser', ...($debug ? ['--debug'] : [])]);
            check($code === 0 && json_decode($out, true, 512, JSON_THROW_ON_ERROR)['debug'] === $debug, 'Real parser recognizes opt-in debug flag');
            foreach (['repeat', 'missing', 'requirements', 'false', 'throw', 'log', 'sql-database', 'build-database', 'custom-false'] as $mode) {
                [$code, $out] = process(['--worker', '--mode='.$mode, ...($debug ? ['--debug'] : [])]);
                check($code === 0 && str_contains($out, 'WORKER PASS'), "$mode worker succeeds: $out");
                check(!str_contains($out, 'SECRET_'), "$mode diagnostics exclude secrets even with debug enabled");
                check(str_contains($out, '[debug]') === $debug, "$mode debug output is opt-in");
                if ($debug) {
                    check(str_contains($out, '[run]'), "$mode logs command boundaries");
                    if ($mode === 'missing') check(str_contains($out, 'missing: missing_generator'), 'Sync identifies missing generators');
                    if ($mode === 'requirements') check(str_contains($out, 'requirements failed'), 'Requirement failure is explicit');
                    if ($mode === 'false') check(str_contains($out, 'result=false'), 'Generator false return is explicit');
                    if ($mode === 'throw') {
                        check(str_contains($out, 'RuntimeException (code 42) @ tests/setup-debug.php:'), 'Exception source/type/code are useful');
                        check(str_contains($out, 'caused by LogicException'), 'Exception causes retain metadata');
                    }
                }
            }
        }
        echo "PASS: $checks setup debug/repeated generator/security checks\n";
        exit;
    }

    CLISetup::evalOpts();
    $mode = explode('--mode=', implode(' ', $argv), 2)[1]; $mode = explode(' ', $mode, 2)[0];
    $directory = sys_get_temp_dir().'/aowow-setup-debug-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $cwd = getcwd(); chdir($directory);
    try {
        // Match constructor registration: generators are loaded before their utility is registered.
        $globaljs = new FixtureGenerator('globaljs'); $tooltips = new FixtureGenerator('tooltips');
        $sql = new FixtureGenerator('fixture_sql');
        CLISetup::registerSetup('build', $globaljs); CLISetup::registerSetup('build', $tooltips);
        CLISetup::registerSetup('sql', $sql);
        // Empty fixture cwd avoids registering deployment generators; runners are the actual source.
        require $root.'/setup/tools/clisetup/filegen.us.php';
        require $root.'/setup/tools/clisetup/datagen.us.php';
        require $root.'/setup/tools/clisetup/sync.us.php';
        $utilities = (new ReflectionProperty(CLISetup::class, 'utilScriptRefs'))->getValue();
        $utilities['build']->assignGenerators('build'); $utilities['sql']->assignGenerators('sql');
        CLI::initLogFile($directory.'/debug.log');
        $errors = CLI::errorCount();
        $args = ['doBuild' => ['globaljs', 'tooltips'], 'doneBuild' => []];
        if (!CLISetup::run('build', $args) || $args['doneBuild'] !== ['globaljs', 'tooltips']) throw new RuntimeException('First build failed');
        $args = ['doSql' => ['fixture_sql'], 'doneSql' => []];
        if (!CLISetup::run('sql', $args)) throw new RuntimeException('First SQL generation failed');
        DB::$pending = ['sql' => 'fixture_sql', 'build' => 'globaljs tooltips'];
        if ($mode === 'missing') DB::$pending['build'] .= ' missing_generator';
        if ($mode === 'requirements') $tooltips->requirements = false;
        if ($mode === 'false') $tooltips->result = false;
        if ($mode === 'throw') $tooltips->throws = true;
        if ($mode === 'sql-database') $sql->databaseError = true;
        if ($mode === 'build-database') $tooltips->databaseError = true;
        if ($mode === 'custom-false') $sql->customResult = false;
        $args = ['doSql' => explode(' ', DB::$pending['sql']), 'doBuild' => explode(' ', DB::$pending['build'])];
        $success = CLISetup::run('sync', $args);
        $expected = in_array($mode, ['repeat', 'log'], true);
        if ($success !== $expected) throw new RuntimeException('Sync outcome incorrect');
        $sqlFailed = in_array($mode, ['sql-database', 'custom-false'], true);
        if ($sql->calls !== 2 || $globaljs->calls !== ($sqlFailed ? 1 : 2)) throw new RuntimeException('Registered generators cannot run twice');
        if (DB::$pending['sql'] !== ($sqlFailed ? 'fixture_sql' : '') || DB::$pending['build'] !== ($expected ? '' : ($mode === 'missing' ? 'globaljs tooltips missing_generator' : 'globaljs tooltips')))
            throw new RuntimeException('Pending work not retained/acknowledged correctly');
        if ($sqlFailed && ($args['doneSql'] ?? []) !== []) throw new RuntimeException('Failed SQL generator was acknowledged');
        if ($mode === 'build-database' && in_array('tooltips', $args['doneBuild'] ?? [], true)) throw new RuntimeException('Failed build generator was acknowledged');
        if ($expected && CLI::errorCount() !== $errors) throw new RuntimeException('Debug changes error accounting');
        CLI::debug('synthetic trace', new RuntimeException('SECRET_LOG', 9, new LogicException('SECRET_CAUSE')));
        $log = file_get_contents($directory.'/debug.log');
        if (str_contains($log, 'SECRET_') || str_contains($log, '[debug]') !== (bool)CLISetup::getOpt('debug')) throw new RuntimeException('Log safety or gating failed');
        echo "\nWORKER PASS\n";
    }
    finally {
        chdir($cwd);
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($paths as $path) $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        rmdir($directory);
    }
}
