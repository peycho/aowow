<?php

// Execute the real setup driver and option parser without application databases or assets.
namespace Aowow {
    class CLI {
        public const int LOG_ERROR = 1, LOG_INFO = 2;
        public static array $messages = [];
        public static bool $resume = false;
        public static function write(string $message = '', mixed ...$args) : void { self::$messages[] = $message; }
        public static function bold(string $message) : string { return $message; }
        public static function read(array $questions, ?array &$answers) : bool {
            if (self::$resume && str_contains($questions['x'][0], 'continue setup?')) {
                $answers = ['x' => 'y']; return true;
            }
            return false;
        }
    }
    class SetupFixtureCLI {
        public const int OPT_GRP_SETUP = 0, LOCK_OFF = 0, LOCK_ON = 1;
        public static array $options = [], $scripts = [], $runs = [], $progress = [];
        public static ?string $fail = null;
        public static UtilityScript $setup;
        public static function getOpt(string $name) : bool|string { return self::$options[$name] ?? false; }
        public static function getSubScripts() : \Generator { yield from self::$scripts; }
        public static function registerUtility(UtilityScript $script) : void { self::$setup = $script; }
        public static function writeDir(string $dir) : bool { return is_dir($dir) || mkdir($dir, 0700, true); }
        public static function run(string $command, array &$args) : bool {
            $name = $args['doSql'] ?? $args['doBuild'] ?? $command;
            if (!is_string($name)) $name = $command;
            self::$runs[] = $name;
            self::$progress[$name] = is_file('cache/setup/firstrun') ? file('cache/setup/firstrun') : [];
            return self::$fail !== $name;
        }
    }
}

namespace {
    use Aowow\{CLI, CLISetup, SetupFixtureCLI};
    define('AOWOW_REVISION', 69);
    define('CLI', true);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    if (in_array('--parse-options', $argv, true)) {
        require $root.'/setup/tools/CLISetup.class.php';
        CLISetup::evalOpts();
        echo json_encode(['skip' => CLISetup::getOpt('skip-sounds')], JSON_THROW_ON_ERROR);
        exit;
    }
    class_alias(SetupFixtureCLI::class, 'Aowow\\CLISetup');
    require $root.'/setup/tools/setupScript.class.php';
    require $root.'/setup/tools/utilityScript.class.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });
    $checks = 0;
    function check(bool $ok, string $message) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($message);
    }
    foreach ([[], ['--skip-sounds']] as $options) {
        $process = proc_open([PHP_BINARY, __FILE__, '--parse-options', ...$options], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0 && $err === '', 'Actual option parser succeeds');
        check(json_decode($out, true, 512, JSON_THROW_ON_ERROR)['skip'] === (bool)$options, 'Actual parser recognizes the optional flag');
    }
    $script = new class extends Aowow\SetupScript { public function generate() : bool { return true; } };
    foreach (['spells', 'sounds', 'zones'] as $name) CLISetup::$scripts[$name] = ['sql', $script];
    foreach (['soundfiles', 'talentcalc'] as $name) CLISetup::$scripts[$name] = ['build', $script];
    require $root.'/setup/tools/clisetup/setup.us.php';
    $setup = CLISetup::$setup;
    $steps = (new ReflectionProperty($setup, 'steps'))->getValue($setup);
    $soundsIdx = array_search('sounds', array_column($steps, 1), true);
    $zonesIdx = array_search('zones', array_column($steps, 1), true);
    $soundfilesIdx = array_search('soundfiles', array_column($steps, 1), true);
    $all = ['database', 'configure', 'spells', 'sounds', 'zones', 'soundfiles', 'talentcalc', 'update', 'sync', 'account'];
    $silent = array_values(array_diff($all, ['sounds', 'soundfiles']));
    $directory = sys_get_temp_dir().'/aowow-setup-sounds-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $cwd = getcwd();
    try {
        chdir($directory);
        $args = [];
        check($setup->run($args), 'Default setup succeeds');
        check(CLISetup::$runs === $all, 'Default setup runs sound generators in the original order');
        check(!is_file('cache/setup/firstrun'), 'Completed default setup clears resume state');

        CLISetup::$options = ['skip-sounds' => true]; CLISetup::$runs = [];
        check($setup->run($args), 'Setup without sounds succeeds');
        check(CLISetup::$runs === $silent, 'Only sound data generation and audio copying are skipped');
        check((int)CLISetup::$progress['zones'][1] === $zonesIdx, 'Skipped sound generation advances saved progress');
        check((int)CLISetup::$progress['talentcalc'][1] === $soundfilesIdx + 1, 'Skipped audio copying advances saved progress');
        check(count(array_filter(CLI::$messages, fn($message) => str_contains($message, '(--skip-sounds)'))) === 2, 'Each skipped sound step is logged');
        check(!is_file('cache/setup/firstrun'), 'Completed silent setup clears resume state');

        CLISetup::$runs = []; CLISetup::$fail = 'zones';
        check(!$setup->run($args), 'Unrelated failure still aborts silent setup');
        check((int)file('cache/setup/firstrun')[1] === $zonesIdx, 'Failure preserves the next original step number');
        CLISetup::$runs = []; CLISetup::$fail = null; CLI::$resume = true;
        check($setup->run($args), 'Saved setup resumes with sounds skipped');
        check(CLISetup::$runs === ['zones', 'talentcalc', 'update', 'sync', 'account'], 'Resume neither repeats completed work nor runs audio copying');

        CLISetup::$options['step'] = (string)($soundsIdx + 1); CLISetup::$runs = [];
        check($setup->run($args), 'Explicit --step can start at a skipped sound step');
        check(CLISetup::$runs === ['zones', 'talentcalc', 'update', 'sync', 'account'], 'Explicit step numbers retain their original meaning');
        CLISetup::$options['step'] = (string)($soundfilesIdx + 1); CLISetup::$runs = [];
        check($setup->run($args), 'Explicit --step can start at skipped audio copying');
        check(CLISetup::$runs === ['talentcalc', 'update', 'sync', 'account'], 'Setup continues after skipped audio copying');
        check((new ReflectionProperty($setup, 'steps'))->getValue($setup) === $steps, 'Skip option never changes the step list');
        CLI::$messages = []; $setup->writeCLIHelp();
        check(str_contains(implode("\n", CLI::$messages), '--skip-sounds'), 'Setup help documents the option');
        echo "PASS: $checks setup sound option/parser/progress checks\n";
    }
    finally {
        chdir($cwd);
        if (is_file($directory.'/cache/setup/firstrun')) unlink($directory.'/cache/setup/firstrun');
        if (is_dir($directory.'/cache/setup')) rmdir($directory.'/cache/setup');
        if (is_dir($directory.'/cache')) rmdir($directory.'/cache');
        rmdir($directory);
    }
}
