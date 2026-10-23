<?php

// Guarded real-SQL suite: use only the dedicated disposable database documented in tests/README.md.
namespace Aowow {
    class Cfg {
        public static function load() : void {}
        public static function loadForUpdate() : void {}
        public static function get(string $key) : mixed {
            return $key === 'MAINTENANCE' ? (int)DB::Aowow()->query("SELECT value FROM ::config WHERE `key` = 'maintenance'")->fetchSingle() : 1;
        }
        public static function set(string $key, mixed $value) : string {
            // Match the existing nonthrowing config writer: CLISetup must independently verify persistence.
            return DB::Aowow()->qry("UPDATE ::config SET value = %s WHERE `key` = 'maintenance'", $value) === null ? 'internal error' : '';
        }
    }
}
namespace {
    use Aowow\{DB, DibiConnection, SqlUpdate};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_updates') {
        fwrite(STDERR, "Use AOWOW_TEST_DATABASE=aowow_security_test_updates on a disposable fixture.\n");
        exit(1);
    }
    $root = dirname(__DIR__);
    define('AOWOW_REVISION', 66);
    define('CLI', true);
    define('CLI_HAS_E', false);
    define('OS_WIN', false);
    require $root.'/includes/defines.php';
    require $root.'/includes/components/errorlog.class.php';
    require $root.'/includes/locale.class.php';
    $source = getenv('AOWOW_TEST_DIBI_DIR');
    if ($source) {
        foreach (['interfaces', 'exceptions', 'dibi'] as $file) require $source.'/src/Dibi/'.$file.'.php';
        spl_autoload_register(function ($class) use ($source) {
            if (str_starts_with($class, 'Dibi\\')) require $source.'/src/'.str_replace('\\', '/', $class).'.php';
        });
    }
    else require $root.'/includes/libs/autoload.php';
    require $root.'/includes/database.php';
    require $root.'/includes/setup/cli.class.php';
    require $root.'/includes/setup/sqlupdate.class.php';
    $options = ['driver' => 'mysqli', 'host' => getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1', 'port' => (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306),
        'username' => 'root', 'password' => '', 'database' => 'aowow_security_test_updates', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']];
    $db = new DibiConnection($options);
    $db->query("SET SESSION sql_mode = ''");
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db, DB_WORLD => $db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW => [time()+86400, 86400], DB_WORLD => [time()+86400, 86400]]);

    if (getenv('AOWOW_UPDATE_CHILD')) {
        $db->onEvent[] = DB::errorLogger(...);
        if (getenv('AOWOW_UPDATE_MODE') === 'init-error') Aowow\CLI::write('Fixture initialization failure', Aowow\CLI::LOG_ERROR);
        if (getenv('AOWOW_UPDATE_MODE') === 'missing-world')
            (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW => $db]);
        return;
    }                // fixture kernel continues into the real setup entrypoint
    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        $checks++;
        if (!$ok) throw new RuntimeException($label);
    }
    function resetDb() : void {
        global $db, $root;
        $db->onEvent = [];
        foreach ($db->query('SHOW TABLES')->fetchPairs() as $table)
            $db->query('DROP TABLE %n', $table);
        preg_match('/CREATE TABLE `aowow_dbversion` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $m);
        $db->nativeQuery($m[0]);
        $db->query('INSERT INTO ::dbversion (`date`, `part`) VALUES (0, 0)');
        $db->query('CREATE TABLE ::config (`key` varchar(64) PRIMARY KEY, value text) ENGINE=InnoDB');
        $db->query("INSERT INTO ::config VALUES ('maintenance', '0')");
        $db->query('CREATE TABLE ::fixture (id int PRIMARY KEY, value text) ENGINE=InnoDB');
    }
    function failed(callable $work, string $label) : void {
        try { $work(); } catch (Throwable $e) {
            check(!str_contains($e->getMessage(), 'SECRET_SENTINEL'), $label.' safe error');
            return;
        }
        check(false, $label.' must fail');
    }
    function marker() : array {
        global $db;
        return array_map('intval', (array)$db->query('SELECT date, part FROM ::dbversion')->fetch());
    }
    $temp = sys_get_temp_dir().'/aowow-update-'.bin2hex(random_bytes(8));
    mkdir($temp, 0700);
    function files(array $contents) : string {
        global $temp;
        $dir = $temp.'/sql-'.bin2hex(random_bytes(4));
        mkdir($dir, 0700);
        foreach ($contents as $name => $sql) file_put_contents($dir.'/'.$name, $sql);
        return $dir;
    }
    function process(array $argv, string $cwd, array $extra = []) : array {
        $env = array_merge(getenv(), $extra);
        $process = proc_open($argv, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, $cwd, $env, ['bypass_shell' => true]);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $out.$err];
    }
    function cleanup(string $dir) : void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname());
        }
        rmdir($dir);
    }
    try {
        $quoted = <<<'SQL'
-- comment;
SELECT 'a;--b', "c;d", `e;f`; # comment
SELECT 'doubled'';quote'; /* ignored; */ SELECT 'escape\';x'; SELECT 100%3
SQL;
        $split = SqlUpdate::statements($quoted);
        check(count($split) === 4 && $split[0] === "SELECT 'a;--b', \"c;d\", `e;f`" && $split[1] === "SELECT 'doubled'';quote'" && $split[3] === 'SELECT 100%3', 'quoted/comment delimiters and final statement');
        check(SqlUpdate::statements("\xEF\xBB\xBF/*!40101 SET @a = 'x;y' */; SELECT 1--2;") === ["/*!40101 SET @a = 'x;y' */", 'SELECT 1--2'], 'BOM executable comments and arithmetic minus');
        foreach (["SELECT 'bad", 'SELECT 1 /* bad', 'COMMIT;', 'DELIMITER $$', '/*!40101 SET autocommit=0 */;', 'SET @@session.sql_mode="";'] as $sql)
            failed(fn() => SqlUpdate::statements($sql), 'preflight rejection');
        foreach (glob($root.'/setup/sql/updates/*.sql') as $file)
            check(count(SqlUpdate::statements(file_get_contents($file))) > 0, 'shipped migration parses: '.basename($file));

        resetDb();
        $db->query('UPDATE ::dbversion SET date=1700000000, part=0');
        $dir = files(['1700000000_00.sql' => 'INSERT INTO aowow_fixture VALUES (99, "must skip");', '1700000000_01.sql' => "CREATE TABLE aowow_created (id int); INSERT INTO aowow_fixture VALUES (1, 'semi;100%'); UPDATE aowow_fixture SET value='unused' WHERE id=999; SELECT 'ok';", '1700000000_02.sql' => "INSERT INTO aowow_fixture VALUES (2, 'EOF')", '1700000000_03.sql.extra.sql' => 'INVALID SECRET_SENTINEL']);
        SqlUpdate::apply($db, $dir);
        check(marker() === ['date'=>1700000000,'part'=>2], 'sorted same-date parts and invalid names');
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 2, 'raw percent semicolon EOF and zero-affected success');
        check((int)$db->query("SELECT COUNT(*) FROM ::sql_update_journal WHERE status='applied'")->fetchSingle() === 2, 'journal completion');
        check((int)$db->query('SELECT statements FROM ::sql_update_journal WHERE part=1')->fetchSingle() === 4, 'every successful statement counted');
        check($db->query('SELECT checksum FROM ::sql_update_journal WHERE part=1')->fetchSingle() === hash_file('sha256', $dir.'/1700000000_01.sql'), 'exact file checksum');
        SqlUpdate::apply($db, $dir);
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 2, 'completed updates skip without replay');
        $db->query('UPDATE ::dbversion SET part=0');
        failed(fn() => SqlUpdate::apply($db, $dir), 'marker rollback cannot replay applied journal');

        resetDb();
        $dir = files(['1700000000_01.sql'=>'INSERT INTO aowow_fixture VALUES (1, "good");', '1700000000_02.sql'=>'CREATE TABLE aowow_partial (id int); INSERT INTO aowow_fixture VALUES (2, "before"); SELECT SECRET_SENTINEL FROM missing_fixture; INSERT INTO aowow_fixture VALUES (3, "after");', '1700000000_03.sql'=>'INSERT INTO aowow_fixture VALUES (4, "next");']);
        failed(fn() => SqlUpdate::apply($db, $dir), 'SQL failure');
        check(marker() === ['date'=>1700000000,'part'=>1], 'last confirmed marker preserved');
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 2, 'later statement and later file skipped');
        check($db->query('SHOW TABLES LIKE %s', 'aowow_partial')->fetchSingle() === 'aowow_partial', 'partial DDL persists');
        check($db->query('SELECT status FROM ::sql_update_journal WHERE part=2')->fetchSingle() === 'running', 'partial journal persists');
        check((int)$db->query('SELECT statements FROM ::sql_update_journal WHERE part=2')->fetchSingle() === 2, 'only acknowledged progress recorded');
        failed(fn() => SqlUpdate::apply($db, $dir), 'dirty retry blocked');
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 2, 'blocked retry does not mutate');

        foreach (['version', 'journal', 'progress', 'journal-corrupt'] as $failure) {
            resetDb();
            $db->query(SqlUpdate::JOURNAL_DDL);
            $table = $failure === 'version' ? 'aowow_dbversion' : 'aowow_sql_update_journal';
            $when = $failure === 'journal' ? "IF NEW.status = 'applied' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SECRET_SENTINEL'; END IF;" : "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SECRET_SENTINEL';";
            if ($failure === 'journal-corrupt') $when = "IF NEW.status = 'applied' THEN SET NEW.status='bogus'; END IF;";
            $db->nativeQuery('CREATE TRIGGER fixture_fail BEFORE UPDATE ON '.$table.' FOR EACH ROW BEGIN '.$when.' END');
            $dir = files(['1700000000_01.sql'=>'INSERT INTO aowow_fixture VALUES (1, "durable");']);
            failed(fn() => SqlUpdate::apply($db, $dir), $failure.' failure');
            check(marker() === ['date'=>0,'part'=>0], $failure.' marker not advanced');
            check($db->query('SELECT status FROM ::sql_update_journal')->fetchSingle() === 'running', $failure.' journal remains running');
            check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 1, $failure.' previous SQL remains durable');
        }
        resetDb();
        $db->onEvent[] = static function (Dibi\Event $event) {
            if ($event->sql === 'COMMIT') throw new RuntimeException('SECRET_SENTINEL uncertain acknowledgement');
        };
        $dir = files(['1700000000_01.sql'=>'INSERT INTO aowow_fixture VALUES (1, "durable");']);
        failed(fn() => SqlUpdate::apply($db, $dir), 'commit acknowledgement');
        $db->onEvent=[];
        check(marker() === ['date'=>1700000000,'part'=>1] && $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle() === 'applied', 'uncertain commit still has atomic metadata');
        SqlUpdate::apply($db, $dir);
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 1, 'acknowledged metadata prevents duplicate SQL');

        foreach (['missing', 'duplicate', 'myisam-journal', 'bad-journal', 'null-journal', 'mode', 'autocommit', 'syntax', 'empty'] as $failure) {
            resetDb();
            $sql='INSERT INTO aowow_fixture VALUES (1, "should not run");';
            if ($failure === 'missing') $db->query('DELETE FROM ::dbversion');
            if ($failure === 'duplicate') $db->query('INSERT INTO ::dbversion (`date`,`part`) VALUES (0,0)');
            if ($failure === 'myisam-journal') { $db->query(SqlUpdate::JOURNAL_DDL); $db->query('ALTER TABLE ::sql_update_journal ENGINE=MyISAM'); }
            if ($failure === 'bad-journal') $db->query('CREATE TABLE ::sql_update_journal (date int) ENGINE=InnoDB');
            if ($failure === 'null-journal') { $db->query(SqlUpdate::JOURNAL_DDL); $db->query("ALTER TABLE ::sql_update_journal MODIFY status varchar(16) NULL"); $db->query("INSERT INTO ::sql_update_journal VALUES (1,1,'x',NULL,0)"); }
            if ($failure === 'mode') $db->query("SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'");
            if ($failure === 'autocommit') $db->query('SET autocommit=0');
            if ($failure === 'syntax') $sql .= " SELECT 'unfinished";
            if ($failure === 'empty') $sql='-- only comment';
            $dir=files(['1700000000_01.sql'=>$sql]);
            failed(fn() => SqlUpdate::apply($db,$dir), $failure.' preflight');
            check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle() === 0, $failure.' rejected before migration SQL');
            $db->query('ROLLBACK'); $db->query('SET autocommit=1'); $db->query("SET sql_mode=''");
        }

        resetDb();
        $other=new DibiConnection($options);
        $lease=SqlUpdate::acquire($db);
        failed(fn()=>SqlUpdate::acquire($other), 'concurrent connection');
        $db->query('CREATE TABLE ::lock_commit (id int)');
        failed(fn()=>SqlUpdate::acquire($other), 'DDL does not release lock');
        $nested=SqlUpdate::acquire($db); SqlUpdate::release($db,$nested);
        failed(fn()=>SqlUpdate::acquire($other), 'nested release preserves parent lock');
        $connectionId=$db->query('SELECT CONNECTION_ID()')->fetchSingle();
        DB::holdConnection(DB_AOWOW);
        (new ReflectionProperty(DB::class,'interfaceTimes'))->setValue(null,[DB_AOWOW=>[0,86400],DB_WORLD=>[time()+86400,86400]]);
        check(DB::Aowow()->query('SELECT CONNECTION_ID()')->fetchSingle() === $connectionId, 'long build retains lock-owning connection');
        DB::releaseConnection(DB_AOWOW);
        (new ReflectionProperty(DB::class,'interfaceTimes'))->setValue(null,[DB_AOWOW=>[time()+86400,86400],DB_WORLD=>[time()+86400,86400]]);
        SqlUpdate::release($db,$lease);
        $lease=SqlUpdate::acquire($other); SqlUpdate::release($other,$lease);
        check($db->qry('SELECT SECRET_SENTINEL FROM missing_fixture') === null, 'legacy runtime query failure semantics preserved');

        // The fixture substitutes generators only; entrypoint, CLISetup, updater and sync are the real source.
        $cli=$temp.'/checkout'; mkdir($cli,0700);
        foreach (['includes', 'setup', 'setup/tools', 'setup/tools/clisetup', 'setup/sql', 'setup/sql/updates'] as $dir) mkdir($cli.'/'.$dir,0700);
        copy($root.'/aowow',$cli.'/aowow');
        file_put_contents($cli.'/includes/kernel.php', '<?php require '.var_export(__FILE__,true).';');
        symlink($root.'/includes/setup',$cli.'/includes/setup');
        symlink($root.'/setup/setup.php',$cli.'/setup/setup.php');
        foreach (['setupScript.class.php','utilityScript.class.php','CLISetup.class.php','dbcreader.class.php'] as $file) symlink($root.'/setup/tools/'.$file,$cli.'/setup/tools/'.$file);
        foreach (['update','sync','validate-schema'] as $name) symlink($root.'/setup/tools/clisetup/'.$name.'.us.php',$cli.'/setup/tools/clisetup/'.$name.'.us.php');
        $fake=<<<'CODE'
<?php
namespace Aowow;
CLISetup::registerUtility(new class('GENERATOR') extends UtilityScript {
    public int $optGroup = CLISetup::OPT_GRP_UTIL;
    public const string COMMAND='GENERATOR';
    public string $command;
    public function __construct($cmd) { $this->command=$cmd; }
    public function run(array &$args) : bool {
        $cmd=$this->command;
        $key=$cmd==='sql'?'doneSql':'doneBuild';
        $todo=$cmd==='sql'?'doSql':'doBuild';
        DB::Aowow()->query('INSERT INTO ::calls VALUES (%s)', $cmd);
        $mode=getenv('AOWOW_UPDATE_MODE');
        if ($mode === 'restore-ack-fail' && $cmd === 'build') DB::Aowow()->onEvent[] = static function (\Dibi\Event $event) {
            if (str_contains($event->sql, 'UPDATE') && str_contains($event->sql, 'config') && str_contains($event->sql, "'0'"))
                throw new \RuntimeException('SECRET_SENTINEL lost unlock acknowledgement');
        };
        if ($mode===$cmd.'-throw') throw new \RuntimeException('SECRET_SENTINEL');
        if ($mode===$cmd.'-error') { CLI::write('Fixture failure', CLI::LOG_ERROR); return true; }
        if ($mode===$cmd.'-fail') return false;
        if ($mode===$cmd.'-swallowed') DB::Aowow()->qry('INSERT INTO ::calls VALUES (NULL)');
        if ($mode==='ack-fail' && $cmd==='sql') DB::Aowow()->nativeQuery("CREATE TRIGGER fixture_ack BEFORE UPDATE ON aowow_dbversion FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SECRET_SENTINEL'");
        $args[$key]=$mode===$cmd.'-partial'?array_slice($args[$todo],0,1):$args[$todo];
        if ($mode==='lock-probe') {
            $second=new DibiConnection(DB::Aowow()->getConfig());
            try { SqlUpdate::acquire($second); return false; } catch (\Throwable) { }
        }
        return true;
    }
    public function test(?array &$error = []) : bool { return getenv('AOWOW_UPDATE_MODE') !== $this->command.'-test-fail'; }
});
CODE;
        file_put_contents($cli.'/setup/tools/clisetup/fixture-sql.us.php', str_replace('GENERATOR', 'sql', $fake));
        file_put_contents($cli.'/setup/tools/clisetup/fixture-build.us.php', str_replace('GENERATOR', 'build', $fake));
        $php=[PHP_BINARY,'-d','extension='.getenv('AOWOW_TEST_MYSQLI_EXTENSION'),$cli.'/aowow'];
        if (!getenv('AOWOW_TEST_MYSQLI_EXTENSION')) $php=[PHP_BINARY,$cli.'/aowow'];
        foreach (['success','lock-probe','sql-fail','build-fail','sql-throw','sql-error','sql-partial','sql-test-fail','ack-fail','missing-world','sql-swallowed','build-swallowed'] as $mode) {
            resetDb();
            $db->query('CREATE TABLE ::calls (name varchar(16) NOT NULL) ENGINE=InnoDB');
            file_put_contents($cli.'/setup/sql/updates/1700000000_01.sql', "INSERT INTO aowow_fixture VALUES (1, 'once'); UPDATE aowow_dbversion SET `sql`='one two', build='three';");
            [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1','AOWOW_UPDATE_MODE'=>$mode]);
            $ok=in_array($mode,['success','lock-probe']);
            check($code===($ok?0:1), $mode.' real CLI status: '.$output);
            check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===($ok?0:1), $mode.' maintenance outcome');
            check(marker()===['date'=>1700000000,'part'=>1],$mode.' migration completes independently of generation');
            check(!str_contains($output,'SECRET_SENTINEL'), $mode.' redacted exception');
            check((string)$db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()===($ok || in_array($mode,['build-fail','build-swallowed'],true)?'':'one two'), $mode.' pending SQL outcome');
            check((string)$db->query('SELECT build FROM ::dbversion')->fetchSingle()===($ok?'':'three'), $mode.' pending build outcome');
            check((int)$db->query("SELECT COUNT(*) FROM ::calls WHERE name='build'")->fetchSingle()===($ok || in_array($mode,['build-fail','build-swallowed'],true)?1:0), $mode.' build skipped after SQL failure');
            if (!$ok) {
                if ($mode === 'ack-fail') $db->query('DROP TRIGGER fixture_ack');
                [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1','AOWOW_UPDATE_MODE'=>'success']);
                check($code===0 && (int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle()===1, 'retry completes pending work without replaying migration');
                check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1,'retry preserves already-restricted maintenance');
            }
        }
        foreach (['success', 'build-throw'] as $mode) {
            resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16)) ENGINE=InnoDB');
            file_put_contents($cli.'/setup/sql/updates/1700000000_01.sql', "INSERT INTO aowow_fixture VALUES (1, 'once'); UPDATE aowow_dbversion SET `sql`='one two', build='three';");
            [$code,$output]=process([...$php,'--update','--debug'],$cli,['AOWOW_UPDATE_CHILD'=>'1','AOWOW_UPDATE_MODE'=>$mode]);
            $ok=$mode==='success';
            check($code===($ok?0:1), $mode.' debug CLI status: '.$output);
            check(str_contains($output,'[debug]') && str_contains($output,'[sync] build requested: three'), $mode.' debug follows pending generator handoff');
            check(!str_contains($output,'SECRET_SENTINEL'), $mode.' debug excludes exception messages and SQL values');
            check(marker()===['date'=>1700000000,'part'=>1], $mode.' debug preserves migration accounting');
            check((string)$db->query('SELECT build FROM ::dbversion')->fetchSingle()===($ok?'':'three'), $mode.' debug preserves pending build work');
            check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===($ok?0:1), $mode.' debug preserves maintenance');
            if (!$ok) check(str_contains($output,'RuntimeException (code 0) @ '), 'debug shows useful exception metadata');
        }
        resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16))');
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1','AOWOW_UPDATE_MODE'=>'restore-ack-fail']);
        check($code===1 && !str_contains($output,'SECRET_SENTINEL'), 'lost unlock acknowledgement is safe and nonzero');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'uncertain unlock re-enables maintenance');
        resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16))');
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1','AOWOW_UPDATE_MODE'=>'init-error']);
        check($code===1 && marker()===['date'=>0,'part'=>0], 'initialization error prevents command mutations');
        resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16))');
        file_put_contents($cli.'/setup/sql/updates/1700000000_01.sql', "CREATE TABLE aowow_partial (id int); SELECT SECRET_SENTINEL FROM missing_fixture; INSERT INTO aowow_fixture VALUES (1,'later');");
        [$code,$output]=process([...$php,'--update','--debug'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1 && !str_contains($output,'SECRET_SENTINEL') && str_contains($output,'1700000000_01.sql statement 2'),'real CLI failed statement metadata');
        check(str_contains($output,'[debug] [update] failed at 1700000000_01.sql; statement=2'), 'debug identifies failed SQL statement without its contents');
        check(marker()===['date'=>0,'part'=>0] && (int)$db->query('SELECT COUNT(*) FROM ::calls')->fetchSingle()===0,'real CLI failed migration skips sync');
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1 && marker()===['date'=>0,'part'=>0], 'real CLI refuses partial DDL retry');
        [$code,$output]=process([...$php,'--update','--help'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===0 && str_contains($output,'usage:'),'help succeeds without applying updates');
        $lease=SqlUpdate::acquire($db);
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1,'concurrent real CLI fails');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1,'losing concurrent command preserves maintenance');
        SqlUpdate::release($db,$lease);
        resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16))');
        $db->query("UPDATE ::dbversion SET `sql`='one two unrelated', build='three'");
        // Feed named requests through the actual sync utility without replacing the dispatcher.
        require $root.'/setup/tools/utilityScript.class.php';
        require $root.'/setup/tools/CLISetup.class.php';
        require $root.'/setup/tools/clisetup/sync.us.php';
        require $cli.'/setup/tools/clisetup/fixture-sql.us.php';
        require $cli.'/setup/tools/clisetup/fixture-build.us.php';
        $io=['doSql'=>['one'], 'doBuild'=>[]];
        check(Aowow\CLISetup::run('sync',$io), 'manual subset sync succeeds');
        check($db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()==='two unrelated', 'manual sync preserves unrelated pending work');
        check($db->query('SELECT build FROM ::dbversion')->fetchSingle()==='three', 'manual sync preserves unrequested builds');
        $db->nativeQuery("CREATE TRIGGER fixture_mode BEFORE UPDATE ON aowow_config FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SECRET_SENTINEL'");
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1 && !str_contains($output,'SECRET_SENTINEL'), 'maintenance persistence failure is safe and nonzero');
        check((int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle()===0, 'maintenance failure prevents migrations');
        $db->query('DROP TRIGGER fixture_mode');
        resetDb(); $db->query('CREATE TABLE ::calls (name varchar(16))');
        file_put_contents($cli.'/setup/sql/updates/1700000000_01.sql', 'CREATE TABLE aowow_interrupted (id int); SELECT SLEEP(10); INSERT INTO aowow_fixture VALUES (1, "later");');
        $env=array_merge(getenv(),['AOWOW_UPDATE_CHILD'=>'1']);
        $worker=proc_open([...$php,'--update'],[0=>['pipe','r'],1=>['file',$temp.'/crash.log','w'],2=>['file',$temp.'/crash.log','a']],$pipes,$cli,$env,['bypass_shell'=>true]);
        fclose($pipes[0]);
        try {
            $ready=false;
            $deadline=microtime(true)+5;
            do {
                if ($db->query("SHOW TABLES LIKE 'aowow_sql_update_journal'")->fetchSingle())
                    $ready=(int)$db->query('SELECT statements FROM ::sql_update_journal')->fetchSingle()===1;
                if (!$ready) usleep(20000);
            } while (!$ready && microtime(true)<$deadline);
            check($ready,'independent worker reached durable DDL checkpoint');
        }
        finally { proc_terminate($worker,9); proc_close($worker); }
        // The terminated client's server statement may still be sleeping; its named lease remains held until disconnect.
        $deadline=microtime(true)+12;
        do {
            $free=(int)$db->query('SELECT IS_FREE_LOCK(%s)', 'aowow.update.'.substr(hash('sha256','aowow_security_test_updates|aowow_'),0,48))->fetchSingle()===1;
            if (!$free) usleep(20000);
        } while (!$free && microtime(true)<$deadline);
        check($free,'server releases dead worker lease');
        check($db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='running' && marker()===['date'=>0,'part'=>0],'hard interruption preserves journal and version');
        [$code,$output]=process([...$php,'--update'],$cli,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1 && (int)$db->query('SELECT COUNT(*) FROM ::fixture')->fetchSingle()===0,'hard-interrupted migration cannot replay');
        [$code,$output]=process($php,$temp,['AOWOW_UPDATE_CHILD'=>'1']);
        check($code===1,'wrong checkout directory is nonzero');
        file_put_contents($temp.'/missing-extension.php', '<?php namespace Aowow; function extension_loaded(string $name) : bool { return false; }');
        [$code,$output]=process([PHP_BINARY,'-d','auto_prepend_file='.$temp.'/missing-extension.php',$root.'/aowow','--update'],$root);
        check($code!==0,'missing runtime extensions are nonzero');
        // Apply the configuration-only retirement update through the real journaled migration runner.
        $retirement = files(['1791028800_01.sql' => file_get_contents($root.'/setup/sql/updates/1791028800_01.sql')]);
        foreach (['http://www.wowhead.com/forums?board=', 'https://forum.example.test/board=',
                  'http://www.wowhead.com/forums?board= ', 'http://WWW.wowhead.com/forums?board=', '', null] as $value) {
            resetDb();
            if ($value !== null) $db->query("INSERT INTO ::config VALUES ('board_url', %s)", $value);
            $db->query("INSERT INTO ::config VALUES ('other_url', 'https://example.test/')");
            $db->query("UPDATE ::dbversion SET build='existing'");
            $before = [];
            foreach (['articles', 'guides', 'comments', 'account_cookies'] as $table) {
                $db->query('CREATE TABLE %n (id int PRIMARY KEY, content longblob)', '::'.$table);
                $db->query('INSERT INTO %n VALUES (1, %s)', '::'.$table, "Historical [modelviewer] content\0\n[db=ptr] UTF-8 текст");
                $before[$table] = (array)$db->query('SELECT * FROM %n', '::'.$table)->fetch();
            }
            SqlUpdate::apply($db, $retirement);
            $expected = $value === 'http://www.wowhead.com/forums?board=' ? '' : $value;
            check($db->query("SELECT value FROM ::config WHERE `key`='board_url'")->fetchSingle() === $expected, 'retirement clears only exact shipped board URL');
            check($db->query("SELECT value FROM ::config WHERE `key`='other_url'")->fetchSingle() === 'https://example.test/', 'retirement preserves unrelated config');
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'existing globaljs tooltips', 'retirement requests both asset rebuilds');
            foreach ($before as $table => $row)
                check((array)$db->query('SELECT * FROM %n', '::'.$table)->fetch() === $row, 'retirement preserves stored '.$table.' bytes');
            SqlUpdate::apply($db, $retirement);
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'existing globaljs tooltips', 'journal prevents retirement migration replay');
        }
        // Widen existing localized criteria without losing rows or queued work.
        resetDb();
        preg_match('/CREATE TABLE `aowow_achievementcriteria` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $criteria);
        $db->nativeQuery(str_replace('varchar(150)', 'varchar(50)', $criteria[0]));
        $nameColumns = array_map(fn($locale) => 'name_loc'.$locale, [0, 2, 3, 4, 6, 8]);
        $row = array_fill_keys(['id', 'refAchievementId', 'type', 'value1', 'value2', 'value3', 'value4', 'value5', 'value6', 'completionFlags', 'groupFlags', 'timeLimit', 'order'], 0);
        $row['id'] = 1;
        foreach ($nameColumns as $column) $row[$column] = 'Original '.$column;
        $db->query('INSERT INTO ::achievementcriteria', $row);
        $before = (array)$db->query('SELECT * FROM ::achievementcriteria')->fetch();
        $db->query("UPDATE ::dbversion SET `sql`='existing', build='globaljs tooltips'");
        $migration = files(['1791142907_01.sql' => file_get_contents($root.'/setup/sql/updates/1791142907_01.sql')]);
        SqlUpdate::apply($db, $migration);
        check(marker() === ['date'=>1791142907, 'part'=>1], 'criteria migration advances the verified version');
        check((array)$db->query('SELECT * FROM ::achievementcriteria')->fetch() === $before, 'criteria migration preserves existing localized names and row data');
        check($db->query('SELECT `sql` FROM ::dbversion')->fetchSingle() === 'existing achievementcriteria', 'criteria migration queues regeneration alongside existing SQL work');
        check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'globaljs tooltips', 'criteria migration preserves pending asset builds');
        $types = $db->query('SHOW COLUMNS FROM ::achievementcriteria')->fetchPairs('Field', 'Type');
        $db->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        foreach ($nameColumns as $column) {
            check($types[$column] === 'varchar(150)', 'criteria migration widens '.$column);
            $name = $column.' '.str_repeat('Ж', 120);
            $db->query('UPDATE ::achievementcriteria SET %n = %s WHERE id=1', $column, $name);
            check($db->query('SELECT %n FROM ::achievementcriteria WHERE id=1', $column)->fetchSingle() === $name, 'long Unicode criteria survive without truncation in '.$column);
        }
        $db->query("SET SESSION sql_mode = ''");
        SqlUpdate::apply($db, $migration);
        check($db->query('SELECT `sql` FROM ::dbversion')->fetchSingle() === 'existing achievementcriteria', 'criteria migration journal prevents duplicate regeneration requests');

        // New upstream regeneration migrations retain queued tasks and unrelated custom fields.
        resetDb();
        preg_match('/CREATE TABLE `aowow_setup_custom_data` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $custom);
        $db->nativeQuery($custom[0]);
        foreach ([16253,16298] as $entry) {
            $db->query("INSERT INTO ::setup_custom_data VALUES ('spell',%i,'typeCat','-2','old category')",$entry);
            $db->query("INSERT INTO ::setup_custom_data VALUES ('spell',%i,'name_loc0','Custom name','operator override')",$entry);
        }
        $db->query("INSERT INTO ::setup_custom_data VALUES ('spell',1,'typeCat','7','unrelated spell')");
        $db->query("UPDATE ::dbversion SET `sql`='existing', build='globaljs tooltips'");
        $migrations=files(array_combine(
            ['1791805587_01.sql','1791808400_01.sql','1791840861_01.sql','1792413928_01.sql'],
            array_map(fn($name)=>file_get_contents($root.'/setup/sql/updates/'.$name),['1791805587_01.sql','1791808400_01.sql','1791840861_01.sql','1792413928_01.sql'])
        ));
        SqlUpdate::apply($db,$migrations);
        check(marker()===['date'=>1792413928,'part'=>1],'new migrations advance the verified marker in order');
        foreach ([16253,16298] as $entry) {
            check($db->query("SELECT value FROM ::setup_custom_data WHERE command='spell' AND entry=%i AND field='typeCat'",$entry)->fetchSingle()==='0','obsolete talent category override updated');
            check($db->query("SELECT value FROM ::setup_custom_data WHERE command='spell' AND entry=%i AND field='name_loc0'",$entry)->fetchSingle()==='Custom name','unrelated custom field preserved');
        }
        check($db->query("SELECT value FROM ::setup_custom_data WHERE entry=1")->fetchSingle()==='7','unrelated spell category preserved');
        $tasks=(string)$db->query('SELECT `sql` FROM ::dbversion')->fetchSingle();
        foreach (['existing','items','stats','spell','taxi'] as $task) check(in_array($task,explode(' ',$tasks),true),'pending task retained: '.$task);
        check($db->query('SELECT build FROM ::dbversion')->fetchSingle()==='globaljs tooltips','pending client assets preserved');
        $snapshot=$db->query('SELECT * FROM ::setup_custom_data ORDER BY entry,field')->fetchAll();
        SqlUpdate::apply($db,$migrations);
        check($db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()===$tasks,'journal prevents repeated task scheduling');
        check($db->query('SELECT * FROM ::setup_custom_data ORDER BY entry,field')->fetchAll()==$snapshot,'journal prevents repeated custom-data writes');

        // Fresh installs already have the widened schema and retain the fork's pending build.
        resetDb();
        $db->nativeQuery($criteria[0]);
        $types = $db->query('SHOW COLUMNS FROM ::achievementcriteria')->fetchPairs('Field', 'Type');
        foreach ($nameColumns as $column) check($types[$column] === 'varchar(150)', 'fresh criteria schema widens '.$column);
        preg_match('/INSERT INTO `aowow_dbversion` VALUES .*?;/', file_get_contents($root.'/setup/sql/02-db_initial_data.sql'), $seed);
        $db->query('TRUNCATE ::dbversion');
        $db->nativeQuery($seed[0]);
        check(marker()['date'] >= 1792413928, 'fresh schema version includes all imported migrations');
        check(str_contains((string)$db->query('SELECT build FROM ::dbversion')->fetchSingle(), 'globaljs'), 'fresh setup retains the pending globaljs build');

        // The sound setting migration preserves choices and schedules client guards exactly once.
        $soundMigration = files(['1791331200_06.sql' => file_get_contents($root.'/setup/sql/updates/1791331200_06.sql')]);
        foreach ([null, '0', '1'] as $choice) {
            resetDb();
            $db->query('ALTER TABLE ::config ADD `default` text, ADD cat int, ADD flags int, ADD comment text');
            if ($choice !== null) $db->query("INSERT INTO ::config (`key`,value) VALUES ('sounds_enable',%s)", $choice);
            $db->query("UPDATE ::dbversion SET build='existing'");
            SqlUpdate::apply($db,$soundMigration);
            check($db->query("SELECT value FROM ::config WHERE `key`='sounds_enable'")->fetchSingle() === ($choice ?? '1'), 'Sound migration defaults to enabled and preserves choices');
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'existing globaljs', 'Sound migration preserves pending builds');
            check($db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle() === '0', 'Sound migration leaves maintenance unchanged');
            if ($choice === null) check((int)$db->query("SELECT flags FROM ::config WHERE `key`='sounds_enable'")->fetchSingle() === 132, 'Sound setting is persistent boolean');
            SqlUpdate::apply($db,$soundMigration);
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'existing globaljs', 'Journal prevents duplicate sound builds');
        }

        $headerMigration = files(['1791331200_07.sql' => file_get_contents($root.'/setup/sql/updates/1791331200_07.sql')]);
        foreach ([null, '0', '1'] as $choice) {
            resetDb();
            $db->query('ALTER TABLE ::config ADD `default` text, ADD cat int, ADD flags int, ADD comment text');
            if ($choice !== null) {
                $db->query("INSERT INTO ::config (`key`,value) VALUES ('header_image_enable',%s)", $choice);
                $db->query("INSERT INTO ::config (`key`,value) VALUES ('header_image_url','https://images.example.test/custom.gif'), ('header_image_link','https://example.test/destination')");
            }
            $db->query("UPDATE ::dbversion SET `sql`='existing', build='existing'");
            SqlUpdate::apply($db,$headerMigration);
            check($db->query("SELECT value FROM ::config WHERE `key`='header_image_enable'")->fetchSingle() === ($choice ?? '0'), 'Header image migration defaults to disabled and preserves choices');
            check($db->query("SELECT value FROM ::config WHERE `key`='header_image_url'")->fetchSingle() === ($choice === null ? '' : 'https://images.example.test/custom.gif'), 'Header image migration preserves configured image URL');
            check($db->query("SELECT value FROM ::config WHERE `key`='header_image_link'")->fetchSingle() === ($choice === null ? '' : 'https://example.test/destination'), 'Header image migration preserves destination URL');
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'existing' && $db->query('SELECT `sql` FROM ::dbversion')->fetchSingle() === 'existing', 'Header image migration leaves queued work unchanged');
            check($db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle() === '0', 'Header image migration leaves maintenance unchanged');
            if ($choice === null) check((int)$db->query("SELECT COUNT(*) FROM ::config WHERE cat=1 AND ((`key`='header_image_enable' AND flags=132) OR (`key` IN ('header_image_url','header_image_link') AND flags=136))")->fetchSingle() === 3, 'Header image settings have persistent site metadata');
            SqlUpdate::apply($db,$headerMigration);
            check((int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle() === 1, 'Journal prevents header image migration replay');
        }


        // Execute the real item generator's equipment-normalization SQL and
        // bindings. Bags/ammunition have inventory slots without being armor.
        resetDb();
        $db->query('CREATE TABLE ::items (id INT PRIMARY KEY, class INT NOT NULL, classBak INT NOT NULL, subClass INT NOT NULL, subClassBak INT NOT NULL, slot INT NOT NULL, slotBak INT NOT NULL, cuFlags INT NOT NULL, name_loc0 VARCHAR(100)) ENGINE=InnoDB');
        $cases = [];
        foreach (range(0, 8) as $subclass)
            $cases[] = [ITEM_CLASS_CONTAINER, $subclass, INVTYPE_BAG, ITEM_CLASS_CONTAINER];
        foreach ([ITEM_CLASS_AMMUNITION => [2, 3], ITEM_CLASS_QUIVER => [2, 3]] as $class => $subclasses)
            foreach ($subclasses as $subclass)
                $cases[] = [$class, $subclass, $class === ITEM_CLASS_AMMUNITION ? INVTYPE_AMMO : INVTYPE_BAG, $class];
        $cases = array_merge($cases, [
            [ITEM_CLASS_QUIVER, 2, INVTYPE_QUIVER, ITEM_CLASS_QUIVER],
            [ITEM_CLASS_ARMOR, 4, INVTYPE_CHEST, ITEM_CLASS_ARMOR],
            [ITEM_CLASS_WEAPON, 7, INVTYPE_WEAPON, ITEM_CLASS_WEAPON],
            [ITEM_CLASS_CONSUMABLE, 6, INVTYPE_FEET, ITEM_CLASS_CONSUMABLE],
            [ITEM_CLASS_MISC, 0, INVTYPE_HOLDABLE, ITEM_CLASS_ARMOR],
            [ITEM_CLASS_QUEST, 0, INVTYPE_HOLDABLE, ITEM_CLASS_ARMOR],
            [ITEM_CLASS_MISC, 0, INVTYPE_NON_EQUIP, ITEM_CLASS_MISC]
        ]);
        foreach ($cases as $id => [$class, $subclass, $slot])
            $db->query('INSERT INTO ::items VALUES (%i,%i,%i,%i,%i,%i,%i,%i,%s)',
                $id + 1, $class, $class, $subclass, $subclass, $slot, $slot,
                $id % 2 ? CUSTOM_EXCLUDE_FOR_LISTVIEW : 0, 'Fixture item '.($id + 1));
        $itemSource = file_get_contents($root.'/setup/tools/sqlgen/items.ss.php');
        check((bool)preg_match("~'(UPDATE ::items SET `class` = %i, `subclass` = %i WHERE [^']+)',\\s*([A-Z_]+),\\s*([A-Z_]+),\\s*([A-Z_]+),\\s*\\[([A-Z_,\\s]+)\\]~", $itemSource, $normalization), 'real item normalization statement and constant bindings found');
        $excluded = array_map('constant', preg_split('/\s*,\s*/', trim($normalization[5])));
        $db->query($normalization[1], constant($normalization[2]), constant($normalization[3]), constant($normalization[4]), $excluded);
        foreach ($cases as $id => [$class, $subclass, $slot, $expected]) {
            $item = (array)$db->query('SELECT * FROM ::items WHERE id=%i', $id + 1)->fetch();
            check($item['class'] === $expected, 'native item category preserved, miscellaneous equipment normalized: '.($id + 1));
            check($item['subClass'] === ($expected === $class ? $subclass : ITEM_SUBCLASS_MISC_ARMOR), 'native subclass preserved: '.($id + 1));
            check($item['classBak'] === $class && $item['subClassBak'] === $subclass && $item['slot'] === $slot && $item['slotBak'] === $slot,
                'source classification and inventory slots unchanged: '.($id + 1));
            check($item['cuFlags'] === ($id % 2 ? CUSTOM_EXCLUDE_FOR_LISTVIEW : 0) && $item['name_loc0'] === 'Fixture item '.($id + 1), 'visibility and item names unchanged: '.($id + 1));
        }
        foreach ([ITEM_CLASS_CONTAINER => 9, ITEM_CLASS_AMMUNITION => 2, ITEM_CLASS_QUIVER => 3] as $class => $count)
            check((int)$db->query('SELECT COUNT(*) FROM ::items WHERE class=%i', $class)->fetchSingle() === $count, 'category and subclass lists have their native records: '.$class);

        // The normal generator follow-up must continue respecting site overrides.
        preg_match('/CREATE TABLE `aowow_setup_custom_data` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $customSchema);
        $db->nativeQuery($customSchema[0]);
        $db->query("INSERT INTO ::setup_custom_data (command,entry,field,value) VALUES ('items',1,'class','4'),('items',1,'subClass','-5'),('items',2,'cuFlags','7')");
        require_once $root.'/setup/tools/setupScript.class.php';
        $customizer = new class {
            use Aowow\TrCustomData;
            public function getName() : string { return 'items'; }
        };
        check($customizer->applyCustomData(), 'normal custom-data application succeeds after classification');
        check((int)$db->query('SELECT class FROM ::items WHERE id=1')->fetchSingle() === 4 && (int)$db->query('SELECT subClass FROM ::items WHERE id=1')->fetchSingle() === -5, 'explicit custom classification remains authoritative');
        check((int)$db->query('SELECT cuFlags FROM ::items WHERE id=2')->fetchSingle() === 7, 'custom visibility override retained');

        $itemRepair = files(['1792742400_02.sql' => file_get_contents($root.'/setup/sql/updates/1792742400_02.sql')]);
        $itemSnapshot = $db->query('SELECT * FROM ::items ORDER BY id')->fetchAll();
        $customSnapshot = $db->query('SELECT * FROM ::setup_custom_data ORDER BY entry,field')->fetchAll();
        foreach ([null, 'existing'] as $pending) {
            $db->query('DROP TABLE IF EXISTS ::sql_update_journal');
            $db->query('UPDATE ::dbversion SET date=0,part=0,`sql`=%s,build=%s', $pending, 'globaljs');
            SqlUpdate::apply($db, $itemRepair);
            $tasks = trim((string)$db->query('SELECT `sql` FROM ::dbversion')->fetchSingle());
            check($tasks === ($pending ? 'existing items stats' : 'items stats'), 'upgrade queues item and stat regeneration while preserving pending work');
            check($db->query('SELECT build FROM ::dbversion')->fetchSingle() === 'globaljs', 'item repair preserves pending asset builds');
            check($db->query('SELECT * FROM ::items ORDER BY id')->fetchAll() == $itemSnapshot && $db->query('SELECT * FROM ::setup_custom_data ORDER BY entry,field')->fetchAll() == $customSnapshot,
                'migration schedules normal regeneration without replacing item or custom data');
            SqlUpdate::apply($db, $itemRepair);
            check(trim((string)$db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()) === $tasks, 'journal prevents repeated item regeneration scheduling');
        }


        // Import the real bundled articles; check both fresh seeding and the
        // guarded legacy repair without a world database or client extraction.
        $festivalRepair = files(['1792742400_03.sql' => file_get_contents($root.'/setup/sql/updates/1792742400_03.sql')]);
        $festivalEvents = ['lunar-festival' => [7], 'noblegarden' => [9], 'love-is-in-the-air' => [8,24],
            'childrens-week' => [], 'midsummer-fire-festival' => [1], 'brewfest' => [],
            'hallows-end' => [], 'pilgrims-bounty' => [26], 'winter-veil' => [2]];
        foreach (['stock', 'edited', 'pending'] as $state) {
            resetDb();
            foreach (['articles','guides','events'] as $table) {
                preg_match('/CREATE TABLE `aowow_'.preg_quote($table, '/').'` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $definition);
                $db->nativeQuery($definition[0]);
            }
            preg_match_all('/^INSERT INTO `aowow_(?:articles|guides)`.*;$/m', file_get_contents($root.'/setup/sql/03-db_initial_articles.sql'), $inserts);
            check(count($inserts[0]) === 2, 'bundled guide and article seed statements found');
            foreach ($inserts[0] as $sql) $db->nativeQuery($sql);
            $stockArticles = $db->selectAssoc('SELECT * FROM ::articles WHERE type=300 ORDER BY typeId');
            check(count($stockArticles) === 9, 'all nine festival guides are seeded');
            foreach ($db->selectAssoc('SELECT g.url AS guideSlug,a.url AS articleSlug,a.article FROM ::guides g JOIN ::articles a ON a.type=300 AND a.typeId=g.id AND a.locale=g.locale AND a.rev=g.rev') as $row) {
                check($row['guideSlug'] === $row['articleSlug'] && str_starts_with($row['article'], '[menu tab=6 path=6,3,'.$row['guideSlug'].']'), 'fresh guide body matches its own metadata and URL');
                preg_match_all('~\[url=(?:/\?)?\??event=(\d+)\]~', $row['article'], $links);
                check(array_map('intval', $links[1]) === $festivalEvents[$row['guideSlug']], 'fresh guide event links use game-event IDs: '.$row['guideSlug']);
            }
            foreach ([7=>327,9=>181,8=>423,24=>372,1=>341,2=>141,26=>404] as $event => $holiday)
                $db->query('INSERT INTO ::events (id,holidayId,startTime,endTime,occurence,length) VALUES (%i,%i,0,0,0,0)', $event, $holiday);
            // Use a different imported Lunar event ID to prove the upgrade is
            // driven by application event records, not one core's numbering.
            $db->query('UPDATE ::events SET id=107 WHERE holidayId=327');
            $db->query('DELETE FROM ::articles WHERE type=300');
            foreach ($stockArticles as $row) {
                foreach ([7=>'event=327',9=>'/?event=181',8=>'/?event=335',24=>'/?event=372',1=>'event=341',2=>'/?event=141',26=>'event=26'] as $event => $legacy)
                    $row['article'] = str_replace('[url=?event='.$event.']', '[url='.$legacy.']', $row['article']);
                if ($row['url'] === 'midsummer-fire-festival') $row['typeId'] = 4;
                if ($row['url'] === 'childrens-week') $row['typeId'] = 5;
                $db->query('INSERT INTO ::articles %v', $row);
            }
            $legacyPair = $db->selectAssoc('SELECT typeId,url FROM ::articles WHERE type=300 AND typeId IN (4,5) ORDER BY typeId');
            if ($state === 'edited') $db->query("UPDATE ::articles SET article=CONCAT(article,' Custom editorial note') WHERE type=300 AND typeId=4");
            if ($state === 'pending') $db->query("INSERT INTO ::articles (type,typeId,locale,rev,url,article) VALUES (300,4,0,2,'midsummer-fire-festival','SECRET_PENDING_TEXT')");
            $db->query("INSERT INTO ::guides (id,url,title,name,status,rev) VALUES (50,'custom-guide','Custom guide','Custom guide',3,1)");
            $db->query("INSERT INTO ::articles (type,typeId,locale,rev,article) VALUES (300,50,0,1,'[url=event=327]Custom target[/url]'),(3,1,0,1,'[url=event=327]Unrelated article[/url]')");
            $db->query("UPDATE ::articles SET article=CONCAT(article,' [url=?event=3270]Longer ID[/url]') WHERE type=300 AND typeId=1");
            $guidesBefore = $db->selectAssoc('SELECT * FROM ::guides ORDER BY id');
            $db->query("UPDATE ::dbversion SET `sql`='stats',build='existing'");
            SqlUpdate::apply($db, $festivalRepair);
            foreach ($db->selectAssoc('SELECT typeId,url,article FROM ::articles WHERE type=300 AND typeId<10 AND rev=1 ORDER BY typeId') as $row) {
                preg_match_all('~\[url=(?:/\?)?\??event=(\d+)\]~', $row['article'], $links);
                $expected = $row['url'] === 'lunar-festival' ? [107,3270] : $festivalEvents[$row['url']];
                check(array_map('intval', $links[1]) === $expected, 'legacy event links follow imported holiday mapping, preserving longer IDs: '.$row['url']);
                check(!preg_match('~\[url=(?:/\?)?event=~', $row['article']), 'event URL tags have an explicit query-string prefix: '.$row['url']);
            }
            $repairedPair = $db->selectAssoc('SELECT typeId,url FROM ::articles WHERE type=300 AND typeId IN (4,5) AND rev=1 ORDER BY typeId');
            check($state === 'stock' ? $repairedPair === [['typeId'=>4,'url'=>'childrens-week'],['typeId'=>5,'url'=>'midsummer-fire-festival']] : $repairedPair === $legacyPair,
                'only exact bundled article associations are repaired: '.$state);
            if ($state === 'edited') check(str_ends_with($db->selectCell('SELECT article FROM ::articles WHERE type=300 AND typeId=4 AND rev=1'), ' Custom editorial note'), 'customized guide text preserved');
            if ($state === 'pending') check($db->selectCell('SELECT article FROM ::articles WHERE type=300 AND typeId=4 AND rev=2') === 'SECRET_PENDING_TEXT', 'pending guide revision preserved');
            check($db->selectAssoc('SELECT * FROM ::guides ORDER BY id') === $guidesBefore, 'guide ownership, status, approved revision and metadata unchanged');
            check($db->selectCell('SELECT article FROM ::articles WHERE type=300 AND typeId=50') === '[url=event=327]Custom target[/url]' && $db->selectCell('SELECT article FROM ::articles WHERE type=3 AND typeId=1') === '[url=event=327]Unrelated article[/url]', 'unrelated guides and non-guide articles remain unchanged');
            check($db->selectCell('SELECT build FROM ::dbversion') === 'existing guidereferences' && $db->selectCell('SELECT `sql` FROM ::dbversion') === 'stats', 'related-guide rebuild scheduled without losing pending tasks');
            SqlUpdate::apply($db, $festivalRepair);
            check($db->selectCell('SELECT build FROM ::dbversion') === 'existing guidereferences', 'journal prevents repeated guide-reference scheduling');
        }

        // Help is CMS-owned too: refresh the stock English article while
        // retaining edits, other revisions/locales and the surrounding data.
        $markupHelp = files(['1792742400_04.sql' => file_get_contents($root.'/setup/sql/updates/1792742400_04.sql')]);
        foreach (['markup-guide' => 'markup-guide-legacy.txt', 'talent-calculator' => 'talent-help-legacy.json', 'stat-weighting' => 'stat-help-legacy.json', 'tooltips' => 'tooltips-legacy.json'] as $help => $fixture) {
            foreach (['stock', 'edited', 'permissions'] as $state) {
                resetDb();
                preg_match('/CREATE TABLE `aowow_articles` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $definition);
                $db->nativeQuery($definition[0]);
                preg_match('/^INSERT INTO `aowow_articles`.*;$/m', file_get_contents($root.'/setup/sql/03-db_initial_articles.sql'), $insert);
                $db->nativeQuery($insert[0]);
                $url = $help === 'tooltips' ? $help : 'help='.$help;
                $fresh = $db->selectRow('SELECT * FROM ::articles WHERE url=%s AND locale=0 AND rev=0', $url);
                $original = file_get_contents($root.'/tests/fixtures/'.$fixture);
                if (str_ends_with($fixture, '.json')) $original = json_decode($original, true, 512, JSON_THROW_ON_ERROR)['article'];
                $db->query('UPDATE ::articles SET article=%s,editAccess=%i WHERE url=%s AND locale=0 AND rev=0',
                    $original.($state === 'edited' ? ' Custom help text' : ''), $state === 'permissions' ? 4 : 2, $url);
                $db->query('INSERT INTO ::articles (locale,url,rev,editAccess,article) VALUES (2,%s,0,2,%s),(0,%s,1,2,%s)', $url, $original, $url, $original);
                $before = $db->selectRow('SELECT * FROM ::articles WHERE url=%s AND locale=0 AND rev=0', $url);
                $others = $db->selectAssoc('SELECT * FROM ::articles WHERE url IS NULL OR url<>%s OR locale<>0 OR rev<>0 ORDER BY type,typeId,locale,url,rev', $url);
                $db->query("UPDATE ::dbversion SET `sql`='existing',build='globaljs'");
                SqlUpdate::apply($db, $markupHelp);
                $after = $db->selectRow('SELECT * FROM ::articles WHERE url=%s AND locale=0 AND rev=0', $url);
                $expected = $state === 'stock' ? $fresh : $before;
                if ($help !== 'markup-guide' && $state === 'edited') $expected['article'] = $fresh['article'].' Custom help text';
                check($after === $expected, $help.' upgrade matches fresh content and preserves CMS edits/permissions: '.$state);
                check($db->selectAssoc('SELECT * FROM ::articles WHERE url IS NULL OR url<>%s OR locale<>0 OR rev<>0 ORDER BY type,typeId,locale,url,rev', $url) === $others,
                    $help.' preserves other locales, revisions and unrelated articles');
                check($db->selectCell('SELECT `sql` FROM ::dbversion') === 'existing' && $db->selectCell('SELECT build FROM ::dbversion') === 'globaljs', 'help content update leaves pending setup work intact');
                SqlUpdate::apply($db, $markupHelp);
                check($db->selectRow('SELECT * FROM ::articles WHERE url=%s AND locale=0 AND rev=0', $url) === $after, $help.' journal replay preserves content');
            }
        }

        resetDb();
        preg_match('/CREATE TABLE `aowow_articles` \(.*?\) ENGINE=.*?;/s', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $definition);
        $db->nativeQuery($definition[0]);
        foreach ([
            0 => '[li]Feedback [url=?forums&board=1]forum[/url][/li]',
            2 => '[li][url=?forums&board=1]Forum[/url] de feedback[/li]',
            3 => '[li][url=?forums&board=1]Forum[/url] für Rückmeldungen[/li]',
            6 => '[li]Foro del [url=?forums&board=1]feedback[/url].[/li]'
        ] as $locale => $body)
            $db->query("INSERT INTO ::articles (locale,url,article) VALUES (%i,'page-not-found',%s)", $locale, 'Before'.$body.'After');
        $db->query("INSERT INTO ::articles (locale,url,rev,article) VALUES (0,'page-not-found',1,'[url=?forums&board=1]Pending[/url]'),(8,'page-not-found',0,'[url=?forums&board=1]Custom translation[/url]'),(0,'custom-page',0,'[url=?forums&board=1]Custom link[/url]')");
        $deletion = 'Any comment that does not abide by the same [forumrules] will be deleted by a moderator.';
        $db->query("INSERT INTO ::articles (locale,url,rev,article) VALUES (0,'help=commenting-and-you',0,%s),(2,'help=commenting-and-you',0,%s),(0,'help=commenting-and-you',1,%s)", 'Before'.$deletion.'After', $deletion, $deletion);
        SqlUpdate::apply($db, $markupHelp);
        foreach ([0,2,3,6] as $locale)
            check($db->selectCell("SELECT article FROM ::articles WHERE url='page-not-found' AND locale=%i AND rev=0", $locale) === 'BeforeAfter', 'obsolete forum entry removed without losing surrounding localized text: '.$locale);
        check($db->selectCell("SELECT article FROM ::articles WHERE url='page-not-found' AND locale=0 AND rev=1") === '[url=?forums&board=1]Pending[/url]', 'forum cleanup preserves other revisions');
        check($db->selectCell("SELECT article FROM ::articles WHERE url='page-not-found' AND locale=8") === '[url=?forums&board=1]Custom translation[/url]', 'forum cleanup preserves an unrelated translation');
        check($db->selectCell("SELECT article FROM ::articles WHERE url='custom-page'") === '[url=?forums&board=1]Custom link[/url]', 'forum cleanup leaves custom pages untouched');
        check($db->selectCell("SELECT article FROM ::articles WHERE url='help=commenting-and-you' AND locale=0 AND rev=0") === 'BeforeComments that do not follow the guidelines on this page may be deleted by a moderator.After', 'comment deletion uses local guidelines and preserves surrounding custom text');
        check($db->selectCell("SELECT article FROM ::articles WHERE url='help=commenting-and-you' AND locale=2") === $deletion, 'comment-deletion correction preserves translations');
        check($db->selectCell("SELECT article FROM ::articles WHERE url='help=commenting-and-you' AND locale=0 AND rev=1") === $deletion, 'comment-deletion correction preserves other revisions');

        echo "\n$checks update checks passed.\n";
    }
    finally { cleanup($temp); }
}
