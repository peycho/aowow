<?php

// Real parser, CLI initialization/dispatcher and command; synthetic read-only metadata.
namespace Aowow {
    class Cfg {
        public static function load() : void { throw new \RuntimeException('Schema check loaded configuration'); }
        public static function get(string $key) : mixed { throw new \RuntimeException('Schema check read configuration'); }
    }
    class DB {
        public static function isConnected(int $index) : bool { return $index === DB_AOWOW; }
        public static function errorCount() : int { return 0; }
        public static function Aowow() : DibiConnection { return new DibiConnection; }
        public static function holdConnection(int $index) : never { throw new \RuntimeException('Schema check took a mutation lease'); }
    }
    class SchemaResult {
        public function __construct(private array $data) {}
        public function fetch() : array { return $this->data; }
        public function fetchAssoc(string $key) : array { return $this->data; }
    }
    class DibiConnection {
        public static string $mode = 'match';
        public static int $queries = 0;
        public function getConfig(string $key) : array { return ['' => 'aowow_']; }
        public function query(string $sql, mixed ...$args) : SchemaResult {
            ++self::$queries;
            if (self::$mode === 'denied') throw new \RuntimeException('SECRET_DATABASE_VALUE raw SQL', 1142);
            if (str_contains($sql, 'information_schema.SCHEMATA'))
                return new SchemaResult(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
            $source = file_get_contents(SchemaValidator::REFERENCE);
            preg_match_all('/^CREATE TABLE .*?^\).*?;/ms', $source, $matches);
            $tables = [];
            foreach ($matches[0] as $ddl) {
                $name = array_key_first(SchemaValidator::parse($ddl));
                $tables[$name] = ['TABLE_TYPE' => 'BASE TABLE', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci', 'ddl' => $ddl];
            }
            if (self::$mode === 'legacy') $tables['aowow_config']['ddl'] = preg_replace('/^  `default`[^\n]*\n/m', '', $tables['aowow_config']['ddl']);
            if (self::$mode === 'generated') {
                foreach (SchemaValidator::generatedTables() as $command => $dbc)
                    $tables['aowow_'.$command] ??= ['TABLE_TYPE'=>'BASE TABLE', 'TABLE_COLLATION'=>'utf8mb4_unicode_ci'];
            }
            if ($sql === 'SELECT TABLE_NAME, TABLE_TYPE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
                return new SchemaResult($tables);
            if ($sql === 'SHOW CREATE TABLE %n' && isset($tables[$args[0]]))
                return new SchemaResult([$args[0], $tables[$args[0]]['ddl']]);
            throw new \RuntimeException('Validator attempted a non-metadata query');
        }
    }
}

namespace {
    use Aowow\{CLI, CLISetup, DibiConnection, SchemaValidator};
    define('AOWOW_REVISION', 69); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    $root = dirname(__DIR__); chdir($root);
    require $root.'/includes/defines.php';
    require $root.'/includes/setup/schemavalidator.class.php';
    if (in_array('--fixture-worker', $argv, true)) {
        foreach ($argv as $arg) if (str_starts_with($arg, '--fixture-mode=')) DibiConnection::$mode = substr($arg, 15);
        $argv = array_values(array_filter($argv, fn($arg) => $arg !== '--fixture-worker' && !str_starts_with($arg, '--fixture-mode=')));
        require $root.'/includes/utilities.php';
        require $root.'/includes/components/errorlog.class.php';
        require $root.'/includes/setup/cli.class.php';
        require $root.'/setup/tools/setupScript.class.php';
        require $root.'/setup/tools/utilityScript.class.php';
        require $root.'/setup/tools/CLISetup.class.php';
        require $root.'/setup/tools/clisetup/update.us.php';
        require $root.'/setup/tools/clisetup/validate-schema.us.php';
        CLISetup::init(); CLISetup::loadScripts();
        if (CLISetup::getOpt('help')) { CLISetup::writeCLIHelp(); exit(CLI::errorCount() ? 1 : 0); }
        exit(CLISetup::runInitial() && CLI::errorCount() === 0 ? 0 : 1);
    }
    $checks = 0;
    function check(bool $ok, string $message) : void {
        global $checks; ++$checks;
        if (!$ok) throw new RuntimeException($message);
    }
    function table(string $sql) : array { return array_values(SchemaValidator::parse($sql))[0]; }
    function worker(string $mode, array $options = []) : array {
        $process = proc_open([PHP_BINARY, __FILE__, '--validate-schema', ...$options, '--fixture-worker', '--fixture-mode='.$mode], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process), $out.$err];
    }
    $reference = SchemaValidator::parse(file_get_contents(SchemaValidator::REFERENCE));
    check(count($reference) === 108, 'Complete initial schema is parsed');
    $copies = SchemaValidator::generatedTables();
    check(count($copies) === 18 && $copies['soundemitters'] === 'soundemitters' && $copies['dungeonmap'] === 'dungeonmap', 'DBC-copy tables discovered from literal generator declarations');
    $directory = sys_get_temp_dir().'/aowow-schema-declarations-'.bin2hex(random_bytes(6)); mkdir($directory,0700);
    try {
        file_put_contents($directory.'/real.ss.php', '<?php namespace Aowow; class Fixture { use TrDBCcopy; protected string $command="fixture_copy"; protected array $dbcSourceFiles=["dungeonmap"]; } throw new \\RuntimeException("Generator must never execute");');
        file_put_contents($directory.'/comment.ss.php', '<?php namespace Aowow; /* use TrDBCcopy; */ class Fixture { protected string $command="fake_comment"; protected array $dbcSourceFiles=["dungeonmap"]; }');
        file_put_contents($directory.'/import.ss.php', '<?php namespace Aowow; use TrDBCcopy; class Fixture { protected string $command="fake_import"; protected array $dbcSourceFiles=["dungeonmap"]; }');
        file_put_contents($directory.'/unknown.ss.php', '<?php namespace Aowow; class Fixture { use TrDBCcopy; protected string $command="unknown_copy"; protected array $dbcSourceFiles=["nonexistent_dbc"]; }');
        check(SchemaValidator::generatedTables($directory) === ['fixture_copy'=>'dungeonmap'], 'Comments, namespace imports and unknown formats cannot classify arbitrary extra tables; generator code never executes');
    } finally {
        foreach (glob($directory.'/*') as $file) unlink($file);
        rmdir($directory);
    }
    foreach ($reference as $name => $definition)
        check(SchemaValidator::differences($definition, $definition) === [], $name.' compares equally');
    $ddl = <<<'SQL'
CREATE TABLE `aowow_fixture` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(64) NOT NULL DEFAULT 'comma, quote'' and ); -- text',
 `balance` float(8,2) NOT NULL DEFAULT 0.00,
 `state` enum('UPPER','lower,()') NOT NULL,
 `optional` int DEFAULT NULL,
 `computed` tinyint(1) GENERATED ALWAYS AS (`id` >= 1) STORED,
 PRIMARY KEY (`id`),
 UNIQUE KEY `name` (`name`(20)) USING BTREE,
 CONSTRAINT `FK_fixture` FOREIGN KEY (`id`) REFERENCES `aowow_account` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPACT COMMENT='ignored';
SQL;
    $expected = table($ddl);
    $variant = str_replace(['int(10)', 'tinyint(1)', 'DEFAULT 0.00', 'DEFAULT NULL', 'utf8 ', 'utf8_unicode_ci', ' (`id` >= 1)', 'USING BTREE', 'ON DELETE RESTRICT', "COMMENT='ignored'"],
        ['int', 'tinyint', "DEFAULT '0'", '', 'utf8mb3 ', 'utf8mb3_unicode_ci', ' ((`id` >= 1))', '', 'ON DELETE NO ACTION', 'AUTO_INCREMENT=981'], $ddl);
    check(SchemaValidator::differences($expected, table($variant)) === [], 'Widths, quoted numeric defaults, nullable defaults, utf8 aliases, FK rules and generated-expression wrappers normalize');
    $literal = "CREATE TABLE `aowow_literal` (`description` text NOT NULL DEFAULT '', `count` int NOT NULL DEFAULT -1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    foreach (["DEFAULT (_utf8mb4'')", "DEFAULT _utf8mb4''", "DEFAULT ('')"] as $default)
        check(SchemaValidator::differences(table($literal), table(str_replace(["DEFAULT ''", 'DEFAULT -1'], [$default, 'DEFAULT (-1)'], $literal))) === [], 'MySQL literal/charset introducers and negative parenthesized defaults normalize');
    foreach ([
        ['varchar(64)', 'varchar(65)', 'type differs'],
        ['unsigned NOT NULL AUTO_INCREMENT', 'NOT NULL AUTO_INCREMENT', 'unsigned differs'],
        ['varchar(64) NOT NULL', 'varchar(64)', 'nullable differs'],
        ["DEFAULT 'comma, quote'' and ); -- text'", "DEFAULT 'SECRET_DEFAULT_VALUE'", 'default differs'],
        ['AUTO_INCREMENT,', ',', 'auto increment differs'],
        ['`name`(20)', '`name`(21)', 'columns differs'],
        ['UNIQUE KEY', 'KEY', 'unique differs'],
        ['USING BTREE', 'USING HASH', 'type differs'],
        ['USING BTREE', '/*!80000 INVISIBLE */', 'visible differs'],
        ['`id` >= 1', '`id` >= 2', 'generated differs'],
        ['ON UPDATE CASCADE', 'ON UPDATE SET NULL', 'update rule differs'],
        ['ENGINE=InnoDB', 'ENGINE=MyISAM', 'engine differs'],
        ['utf8_unicode_ci', 'utf8_general_ci', 'collation differs'],
        ["enum('UPPER'", "enum('upper'", 'type differs'],
    ] as [$from, $to, $problem]) {
        $issues = SchemaValidator::differences($expected, table(str_replace($from, $to, $ddl)));
        check(in_array($problem, array_column($issues, 1), true), 'Detect '.$problem);
        check(!str_contains(json_encode($issues), 'SECRET_DEFAULT_VALUE'), 'Differences never print defaults');
    }
    $changed = $expected; unset($changed['columns']['name']); $changed['columns']['extra'] = $expected['columns']['name'];
    unset($changed['indexes']['name'], $changed['foreignKeys']['FK_fixture']);
    $issues = SchemaValidator::differences($expected, $changed);
    foreach ([['column name', 'missing'], ['column extra', 'extra'], ['index name', 'missing'], ['foreign key FK_fixture', 'missing']] as $issue)
        check(in_array($issue, $issues, true), 'Detect missing/extra structure');
    $changed = $expected; $changed['columns'] = array_reverse($changed['columns'], true);
    check(in_array(['columns', 'order differs'], SchemaValidator::differences($expected, $changed), true), 'Detect reordered columns');
    foreach ([str_replace('int(10)', 'int(10) ZEROFILL', $ddl), str_replace('ENGINE=InnoDB', '', $ddl), substr($ddl, 0, 50), $ddl.$ddl] as $bad) {
        $rejected = false;
        try { table($bad); } catch (RuntimeException) { $rejected = true; }
        check($rejected, 'Malformed/unsupported DDL rejected');
    }
    foreach ([false, true] as $debug) {
        foreach (['match' => 0, 'generated' => 0, 'legacy' => 1, 'denied' => 1] as $mode => $expectedExit) {
            [$code, $out] = worker($mode, $debug ? ['--debug'] : []);
            check($code === $expectedExit, "$mode command exit ($code): $out");
            check(!str_contains($out, 'SECRET_'), 'Failure diagnostics exclude values and raw SQL');
            if ($mode === 'match') check(str_contains($out, '108/108 reference tables compared; 0 differences'), 'All tables inspected without config/generators');
            if ($mode === 'legacy') {
                check(str_contains($out, 'aowow_config: 1 attribute differences') && str_contains($out, 'initial-schema drift'), 'Default output gives compact table totals without implying migration failure');
                check(str_contains($out, 'column default: missing') === $debug, 'Debug output retains individual missing-column findings');
            }
            if ($mode === 'generated') check(str_contains($out, '18 declared DBC-copy tables recognized') && !str_contains($out, 'extra (absent'), 'Completed setup DBC-copy tables are identified as unvalidated inputs rather than unexplained extras');
        }
    }
    foreach ([['--update'], ['--sql=spell'], ['--build=globaljs'], ['--force'], ['--delete'], ['--datasrc=/fixture/']] as $options) {
        [$code, $out] = worker('match', $options);
        check($code === 1 && !str_contains($out, 'reference tables compared'), 'Mixed mutation/irrelevant options fail before comparison');
    }
    [$code, $out] = worker('denied', ['--help']);
    check($code === 0 && str_contains($out, 'usage: php aowow --validate-schema'), 'Contextual help needs no metadata access');
    $log = tempnam(sys_get_temp_dir(), 'aowow-schema-log-');
    unlink($log); // CLI logging chooses a numbered sibling when the requested file exists.
    try {
        [$code, $out] = worker('match', ['--log='.$log]);
        check($code === 0 && str_contains(file_get_contents($log), '108/108'), 'CLI log contains the validation summary');
    } finally { unlink($log); }
    echo "PASS: $checks schema parser/read-only CLI checks\n";
}
