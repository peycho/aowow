<?php

// Synthetic legacy schema/data only; real kernel, CLI, migration corpus, configuration and sync.
use Aowow\{DB, DibiConnection, SchemaValidator, SqlUpdate};

if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_legacy' ||
    (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
    fwrite(STDERR, "Use only AOWOW_TEST_DATABASE=aowow_security_test_legacy on an isolated localhost fixture.\n");
    exit(1);
}
$root = dirname(__DIR__);
define('AOWOW_REVISION', 69); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
require $root.'/includes/defines.php';
require $root.'/includes/libs/autoload.php';
require $root.'/includes/components/errorlog.class.php';
require $root.'/includes/database.php';
require $root.'/includes/setup/cli.class.php';
require $root.'/includes/setup/sqlupdate.class.php';
require $root.'/includes/setup/schemavalidator.class.php';
$options = ['driver'=>'mysqli', 'host'=>'127.0.0.1', 'port'=>(int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306),
    'username'=>'root', 'password'=>'', 'database'=>'aowow_security_test_legacy', 'substitutes'=>[''=>'aowow_'], 'charset'=>'utf8mb4'];
$db = new DibiConnection($options);
$db->onEvent[] = DB::errorLogger(...);
$db->query("SET SESSION sql_mode = ''");
// MySQL 8.4 defaults reject the historical nonunique FK before its archived repair.
// MariaDB and older MySQL accept this schema; adjust only this disposable fixture connection.
if ($db->query("SHOW VARIABLES LIKE 'restrict_fk_on_non_standard_key'")->fetch())
    $db->query('SET SESSION restrict_fk_on_non_standard_key=OFF');
$checks = 0;
function check(bool $ok, string $label) : void {
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
}
function clean(string $dir) : void {
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->isDot()) continue;
        if ($file->isDir() && !$file->isLink()) clean($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($dir);
}
function seed(string $table, array $values) : void {
    global $db;
    foreach ($db->query('SHOW COLUMNS FROM %n', 'aowow_'.$table)->fetchAll() as $column) {
        if (array_key_exists($column->Field, $values) || $column->Null === 'YES' || $column->Default !== null || str_contains($column->Extra, 'auto_increment')) continue;
        $values[$column->Field] = preg_match('/int|float|double|decimal/', $column->Type) ? 0 : '';
    }
    $db->query('INSERT INTO %n', 'aowow_'.$table, $values);
}
function resetLegacy(string $engine = 'MyISAM') : void {
    global $db, $root;
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $table) $db->query('DROP TABLE %n', $table);
    foreach (SqlUpdate::statements(file_get_contents($root.'/tests/fixtures/legacy-1711739612.sql')) as $statement) $db->nativeQuery($statement);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query('ALTER TABLE ::dbversion ENGINE=%sql', $engine);
    $db->query("INSERT INTO ::dbversion VALUES (1711739612, 1, 'power', NULL)");
    $db->query("UPDATE ::config SET value='0' WHERE `key`='maintenance'");
    $db->query("UPDATE ::config SET value='1' WHERE `key`='locales'");
    seed('account', ['id'=>1001, 'user'=>'fixture-login', 'displayName'=>'Fixture User', 'passHash'=>'FIXTURE_HASH', 'email'=>'fixture@example.test', 'avatar'=>'fixture_icon', 'description'=>'Synthetic profile текст']);
    seed('comments', ['id'=>1001, 'type'=>3, 'typeId'=>29254, 'userId'=>1001, 'body'=>'Synthetic comment [b]текст[/b]']);
    seed('articles', ['type'=>3, 'typeId'=>29254, 'locale'=>0, 'article'=>'Synthetic custom article текст', 'url'=>null]);
    seed('screenshots', ['id'=>1001, 'type'=>3, 'typeId'=>29254, 'userIdOwner'=>1001, 'status'=>100, 'caption'=>str_repeat('т',250)]);
    seed('videos', ['id'=>1001, 'type'=>3, 'typeId'=>29254, 'userIdOwner'=>1001, 'status'=>100, 'videoId'=>'fixture123', 'caption'=>str_repeat('Synthetic video текст ',100)]);
    seed('account_favorites', ['userId'=>1001, 'type'=>3, 'typeId'=>29254]);
    seed('account_cookies', ['userId'=>1001, 'name'=>'fixture_preference', 'data'=>'Synthetic preference текст']);
    seed('profiler_profiles', ['id'=>1001, 'realm'=>1, 'realmGUID'=>1001, 'user'=>1001, 'class'=>3, 'cuFlags'=>2, 'lastupdated'=>123]);
    $db->query('CREATE TABLE aowow_fixture_generators (phase varchar(8), tasks text) ENGINE=InnoDB');
}
function community() : array {
    global $db;
    return [
        (array)$db->query('SELECT id, passHash, email, description FROM ::account WHERE id=1001')->fetch(),
        (array)$db->query('SELECT id, type, typeId, userId, body FROM ::comments WHERE id=1001')->fetch(),
        (array)$db->query('SELECT type, typeId, locale, article FROM ::articles WHERE type=3 AND typeId=29254')->fetch(),
        (array)$db->query('SELECT id, type, typeId, userIdOwner, status, caption FROM ::screenshots WHERE id=1001')->fetch(),
        (array)$db->query('SELECT id, type, typeId, userIdOwner, videoId, status, caption FROM ::videos WHERE id=1001')->fetch(),
        (array)$db->query('SELECT * FROM ::account_favorites WHERE userId=1001')->fetch(),
        (array)$db->query('SELECT * FROM ::account_cookies WHERE userId=1001')->fetch(),
    ];
}
function spellProjection() : array {
    global $db, $root;
    // Use the shipped Wrath DBC schema and the actual generator SELECT/positional INSERT.
    $formats=parse_ini_file($root.'/setup/tools/dbc/12340.ini', true, INI_SCANNER_RAW);
    foreach (['spell','spellcasttimes','spellrunecost','spellduration','spellradius'] as $name) {
        $columns=[]; $row=[];
        foreach ($formats[$name] as $field=>$type) {
            if (str_starts_with($field,'UNUSED')) continue;
            if ($type==='LOC') foreach ([0,2,3,4,6,8] as $locale) {
                $columns[]='`'.$field.'_loc'.$locale.'` TEXT NULL';
                $row[$field.'_loc'.$locale]=null;
            }
            else {
                $columns[]='`'.$field.'` '.($type==='f'?'FLOAT':($type==='s'?'TEXT NULL':'BIGINT NOT NULL DEFAULT 0'));
                $row[$field]=$type==='s'?null:0;
            }
        }
        $db->query('CREATE TABLE %n (%sql)', 'dbc_'.$name, implode(', ', $columns));
        if ($name==='spell') {
            $row['id']=90002; $row['name_loc0']='Synthetic Wrath spell';
            $db->query('INSERT INTO dbc_spell', $row);
        }
    }
    $script=file_get_contents($root.'/setup/tools/sqlgen/spell.ss.php');
    preg_match('/\$baseQry = \'(.*?)\';/s', $script, $match);
    check(isset($match[1]), 'actual spell projection is available');
    $rows=$db->selectAssoc($match[1], 0, 10);
    check(count($rows ?? [])===1 && $rows[0]['rank_loc0']===null && $rows[0]['name_loc2']===null, 'valid Wrath strings preserve NULL for empty/unavailable text');
    return $rows[0];
}
function invoke(string $cwd, string $mode = 'success', array $arguments = ['--update', '--debug', '--datasrc=/fixture/extraction/']) : array {
    $process = proc_open([PHP_BINARY, $cwd.'/aowow', ...$arguments], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes, $cwd,
        array_merge(getenv(), ['AOWOW_LEGACY_MODE'=>$mode]), ['bypass_shell'=>true]);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $out.$err];
}
$temp = sys_get_temp_dir().'/aowow-legacy-'.bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    $pending = SqlUpdate::pendingFiles(1711739612, 1, $root.'/setup/sql/updates');
    $archived = array_values(array_filter($pending, fn($file) => basename(dirname($file[0])) === 'v2.0'));
    $current = array_values(array_filter($pending, fn($file) => dirname($file[0]) === $root.'/setup/sql/updates'));
    check(count($archived) === 53, 'exact legacy archive boundary contains 53 migrations');
    check(count(array_filter($current, fn($file) => [$file[1], $file[2]] <= [1791028800, 1])) === 88, 'reviewed target contains 88 current migrations');
    check(count($pending) === count($archived) + count($current), 'older archive contains no pending legacy migrations');
    check(basename($pending[0][0]) === '1713730806_01.sql', 'legacy starts after its confirmed marker');
    check(basename($archived[52][0]) === '1758578400_17.sql', 'last archived part is included');
    check(basename($current[0][0]) === '1759504522_01.sql', 'current migration boundary follows the archive');
    foreach (array_slice($pending, 1) as $i => $file) check([$pending[$i][1], $pending[$i][2]] < [$file[1], $file[2]], 'complete corpus has strict chronological ordering');
    $latest = end($pending);
    check(SqlUpdate::pendingFiles($latest[1], $latest[2], $root.'/setup/sql/updates') === [], 'already-current database skips every archive');

    // A deliberately interleaved archive checks sorting by metadata, not directory path.
    $ordering = $temp.'/ordering'; mkdir($ordering); mkdir($ordering.'/v2.0'); mkdir($ordering.'/v1.2');
    file_put_contents($ordering.'/v1.2/1711739612_01.sql', 'INVALID old SQL');
    file_put_contents($ordering.'/v2.0/1711739612_02.sql', 'SELECT 1');
    file_put_contents($ordering.'/1711739612_03.sql', 'SELECT 2');
    file_put_contents($ordering.'/v2.0/1711739613_01.sql', 'SELECT 3');
    $order = SqlUpdate::pendingFiles(1711739612, 1, $ordering);
    check(array_map(fn($file) => basename($file[0]), $order) === ['1711739612_02.sql', '1711739612_03.sql', '1711739613_01.sql'], 'same-date archive/current parts interleave correctly');
    file_put_contents($ordering.'/1711739613_01.sql', 'SELECT 4');
    try { SqlUpdate::pendingFiles(1711739612, 1, $ordering); check(false, 'duplicate must fail'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'Duplicate SQL migration identifier.', 'duplicate pending migration fails before execution'); }

    $cli = $temp.'/checkout'; mkdir($cli);
    foreach (['includes', 'config', 'setup', 'setup/tools', 'setup/tools/clisetup', 'setup/sql', 'static', 'static/uploads', 'static/uploads/screenshots', 'static/uploads/screenshots/normal'] as $dir) mkdir($cli.'/'.$dir);
    copy($root.'/aowow', $cli.'/aowow'); copy($root.'/includes/kernel.php', $cli.'/includes/kernel.php');
    foreach (glob($root.'/includes/*') as $path) if (basename($path) !== 'kernel.php') symlink($path, $cli.'/includes/'.basename($path));
    symlink($root.'/localization', $cli.'/localization');
    symlink($root.'/setup/setup.php', $cli.'/setup/setup.php');
    foreach (['setupScript.class.php', 'utilityScript.class.php', 'CLISetup.class.php', 'dbcreader.class.php'] as $file) symlink($root.'/setup/tools/'.$file, $cli.'/setup/tools/'.$file);
    foreach (['update', 'sync', 'validate-schema'] as $file) symlink($root.'/setup/tools/clisetup/'.$file.'.us.php', $cli.'/setup/tools/clisetup/'.$file.'.us.php');
    symlink($root.'/setup/sql/updates', $cli.'/setup/sql/updates');
    $config = $options + ['db'=>$options['database'], 'prefix'=>'aowow_'];
    file_put_contents($cli.'/config/config.php', '<?php $AoWoWconf = '.var_export(['aowow'=>$config, 'world'=>$config], true).';');
    file_put_contents($cli.'/static/uploads/screenshots/normal/1001.jpg', 'Synthetic upload bytes');
    $generators = <<<'CODE'
<?php
namespace Aowow;
CLISetup::registerUtility(new class('PHASE') extends UtilityScript {
    public const string COMMAND = 'PHASE';
    public int $optGroup = CLISetup::OPT_GRP_UTIL;
    public string $command;
    public function __construct($phase) {
        $this->command=$phase;
        if (CLISetup::$srcDir!=='/fixture/extraction/' || !CLISetup::$locales) throw new \RuntimeException('Generator constructed before CLI initialization');
    }
    public function run(array &$args) : bool {
        $phase=$this->command; $db=DB::Aowow();
        // These values are unavailable in the pre-migration configuration bootstrap.
        if (Cfg::get('DEFAULT_CHARSET', false, true)[3] !== 'UTF-8') throw new \RuntimeException('Configuration not reloaded');
        if (Cfg::get('MAINTENANCE') !== 1) throw new \RuntimeException('Maintenance not held');
        $other=new DibiConnection($db->getConfig());
        try { $lease=SqlUpdate::acquire($other); SqlUpdate::release($other,$lease); return false; } catch (\Throwable) {}
        $todo=$phase==='sql'?'doSql':'doBuild'; $done=$phase==='sql'?'doneSql':'doneBuild';
        if (in_array('power', $args[$todo], true)) throw new \RuntimeException('Obsolete generator survived');
        $db->query('INSERT INTO aowow_fixture_generators VALUES (%s, %s)', $phase, implode(' ', $args[$todo]));
        if (getenv('AOWOW_LEGACY_MODE')===$phase.'-throw') throw new \RuntimeException('SECRET_SENTINEL');
        $args[$done]=getenv('AOWOW_LEGACY_MODE')===$phase.'-partial'?array_slice($args[$todo],0,1):$args[$todo];
        return true;
    }
});
CODE;
    foreach (['sql', 'build'] as $phase)
        file_put_contents($cli.'/setup/tools/clisetup/fixture-'.$phase.'.us.php', str_replace('PHASE', $phase, $generators));
    // Load the entire real registry on the old schema without executing generators.
    // Their constructors depend on CLI locales/data paths being initialized first.
    $registry=$temp.'/registry'; mkdir($registry); mkdir($registry.'/config');
    copy($root.'/aowow',$registry.'/aowow'); copy($cli.'/config/config.php',$registry.'/config/config.php');
    $capture=$temp.'/registered.json';
    file_put_contents($registry.'/config/config.php', "\n".'register_shutdown_function(static function () {
        $registered=[];
        foreach (\\Aowow\\CLISetup::getSubScripts() as $name=>[$phase]) $registered[$phase][]=$name;
        file_put_contents('.var_export($capture,true).', json_encode($registered, JSON_THROW_ON_ERROR));
    });', FILE_APPEND);
    foreach (['includes','setup','localization'] as $dir) symlink($root.'/'.$dir,$registry.'/'.$dir);
    resetLegacy(); $before=community();
    [$status,$output]=invoke($registry,'success',['--update','--help','--datasrc=/fixture/extraction/']);
    check($status===0 && str_contains($output,'date/part order'), 'complete real utility/generator registry loads against legacy configuration');
    $registered=json_decode(file_get_contents($capture),true,512,JSON_THROW_ON_ERROR);
    check((int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612 && community()===$before &&
        (int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===0, 'legacy help performs no migration or maintenance change');
    foreach (['MyISAM', 'InnoDB'] as $engine) {
        foreach (['email', 'email-case', 'name', 'name-case', 'name-space', 'name-accent'] as $conflict) {
            resetLegacy($engine); $before=community();
            seed('account', ['id'=>1002, 'user'=>'fixture-second', 'displayName'=>match($conflict) {
                'name'=>'Fixture User', 'name-case'=>'fixture user', 'name-space'=>'Fixture User ', 'name-accent'=>'Fíxture Usér', default=>'Fixture Second'
            }, 'email'=>match($conflict) {
                'email'=>'fixture@example.test', 'email-case'=>'FIXTURE@example.test', default=>'second@example.test'
            }]);
            $accounts=$db->query('SELECT * FROM ::account ORDER BY id')->fetchAll();
            [$status,$output]=invoke($cli);
            check($status===1 && str_contains($output,'Legacy account uniqueness conflict in '.(str_starts_with($conflict,'email')?'email':'displayName')), 'legacy account conflicts stop at an actionable preflight');
            check(!str_contains($output,'fixture@example.test') && !str_contains($output,'Fixture User') && !str_contains($output,'second@example.test'), 'preflight reports no account values');
            check($db->query('SELECT * FROM ::account ORDER BY id')->fetchAll()==$accounts && community()===$before, 'duplicate-account preflight preserves every account and community record');
            check((int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612 &&
                $db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()==='power' &&
                $db->query('SELECT build FROM ::dbversion')->fetchSingle()===null &&
                (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===0, 'account preflight applies no migrations or task acknowledgements');
            check(!$db->query("SHOW COLUMNS FROM ::config LIKE 'default'")->fetch() &&
                (int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===0, 'account preflight prevents schema and generator changes');
            check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'account conflict retains maintenance');
        }
    }
    foreach (['MyISAM', 'InnoDB'] as $engine) {
        resetLegacy($engine); $before=community();
        $longSpellText=str_repeat('Synthetic текст ',5000);
        seed('spell', ['id'=>90001, 'description_loc0'=>$longSpellText]);
        // The legacy positional layout gets four aura fields through archived/current SQL.
        // Reproduce the reported NULL failure independently of that later layout change.
        $legacySpell=(array)$db->query('SELECT * FROM ::spell WHERE id=90001')->fetch();
        $legacySpell['id']=90003; $legacySpell['rank_loc0']=null;
        $dbErrors=DB::errorCount();
        check($db->qry('INSERT INTO ::spell VALUES %l', $legacySpell)===null && DB::errorCount()===$dbErrors+1, 'exact legacy required text rejects the DBC empty-string representation');
        seed('account', ['id'=>1002, 'user'=>'fixture-blank-one', 'displayName'=>'Fixture Blank One', 'email'=>'']);
        seed('account', ['id'=>1003, 'user'=>'fixture-blank-two', 'displayName'=>'Fixture Blank Two', 'email'=>'']);
        check(!$db->query("SHOW COLUMNS FROM ::config LIKE 'default'")->fetch(), 'exact legacy config has no default column');
        [$status,$output]=invoke($cli);
        check($status===0, $engine.' legacy entrypoint succeeds: '.$output);
        check(!str_contains($output, 'SECRET_SENTINEL'), 'legacy diagnostics stay redacted');
        check(strtoupper($db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_dbversion'")->fetchSingle())==='INNODB', 'version metadata prepared for accounting');
        check(community()===$before, 'representative community content and upload owner/IDs survive every migration');
        $textColumns=$db->query("SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_spell' AND COLUMN_NAME REGEXP '^(name|rank|description|buff)_loc[0-9]+$'")->fetchPairs();
        check(count($textColumns)===24 && array_unique(array_values($textColumns))===['YES'], 'every localized spell string accepts valid empty DBC strings');
        check($db->query("SELECT COLUMN_TYPE, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_spell' AND COLUMN_NAME='name_loc0'")->fetchPairs()===['varchar(115)'=>'utf8mb4_unicode_ci'], 'ordinary spell width and collation converge with the canonical schema');
        check((int)$db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_spell' AND COLUMN_NAME='name_loc8'")->fetchSingle()>=184, 'short legacy locale width is widened for current extracted strings');
        $projection=spellProjection();
        $projection['description_loc0']=$longSpellText;
        $required=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_spell' AND IS_NULLABLE='NO'")->fetchPairs();
        $unexpectedNulls=array_intersect(array_keys(array_filter($projection,fn($value)=>$value===null)),array_values($required));
        check(!$unexpectedNulls, 'spell projection has unexpected required NULL columns: '.implode(', ',$unexpectedNulls));
        $dbErrors=DB::errorCount();
        check($db->qry('INSERT INTO ::spell VALUES %l', $projection)!==null && $db->getAffectedRows()===1 && DB::errorCount()===$dbErrors, 'real Wrath spell projection inserts successfully after complete migration corpus');
        check($db->query('SELECT description_loc0 FROM ::spell WHERE id=90002')->fetchSingle()===$longSpellText, 'migrated spell storage retains text exceeding TEXT capacity');
        check((array)$db->query('SELECT rank_loc0, name_loc2, buff_loc0 FROM ::spell WHERE id=90002')->fetch() === ['rank_loc0'=>null, 'name_loc2'=>null, 'buff_loc0'=>null], 'empty localized spell values survive insertion');
        check((string)$db->query('SELECT login FROM ::account WHERE id=1001')->fetchSingle()==='fixture-login', 'account login survives rename');
        check((string)$db->query('SELECT username FROM ::account WHERE id=1001')->fetchSingle()==='Fixture User', 'display name survives rename');
        check((string)$db->query('SELECT wowicon FROM ::account WHERE id=1001')->fetchSingle()==='fixture_icon', 'account image reference survives rename');
        check((int)$db->query('SELECT COUNT(*) FROM ::account WHERE id IN (1002,1003) AND email IS NULL')->fetchSingle()===2, 'multiple blank emails are valid and become NULL through the account migration');
        check((int)$db->query('SELECT stub FROM ::profiler_profiles WHERE id=1001')->fetchSingle()===1 &&
            (int)$db->query('SELECT cuFlags FROM ::profiler_profiles WHERE id=1001')->fetchSingle()===2 &&
            (int)$db->query('SELECT lastupdated FROM ::profiler_profiles WHERE id=1001')->fetchSingle()===0, 'hunter refresh uses existing cuFlags and retains unrelated bits');
        foreach (['creature','items','objects','quests','spell'] as $table) {
            $indexes=$db->query('SHOW INDEX FROM %n', 'aowow_'.$table)->fetchAll();
            $names=array_values(array_map(fn($index)=>$index->Column_name, array_filter($indexes, fn($index)=>$index->Index_type==='BTREE' && str_starts_with($index->Key_name,'idx_name'))));
            sort($names);
            check($names===['name_loc0','name_loc2','name_loc3','name_loc4','name_loc6','name_loc8'], 'complete index sequence retains final localized lookup indexes');
            $search=$db->query('SHOW INDEX FROM %n', 'aowow_'.$table.'_search')->fetchAll();
            check(count(array_filter($search, fn($index)=>$index->Index_type==='FULLTEXT'))>0, 'complete index sequence builds final search-table FULLTEXT indexes');
        }
        check(file_get_contents($cli.'/static/uploads/screenshots/normal/1001.jpg')==='Synthetic upload bytes', 'approved screenshot upload reference and file remain untouched');
        $version=(array)$db->query('SELECT * FROM ::dbversion')->fetch();
        check([(int)$version['date'],(int)$version['part']]===[$latest[1],$latest[2]], 'complete corpus reaches latest marker');
        check($version['sql']==='' && $version['build']==='', 'only verified completed generators clear pending work');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===0, 'complete legacy upgrade restores previous maintenance');
        $journal=$db->query('SELECT * FROM ::sql_update_journal ORDER BY date, part')->fetchAll();
        check(count($journal)===count($pending), 'every pending archive/current file is journaled exactly once');
        foreach ($journal as $i=>$entry) {
            check([(int)$entry->date,(int)$entry->part]===[$pending[$i][1],$pending[$i][2]], 'journal follows complete ordered corpus');
            check($entry->status==='applied' && $entry->checksum===hash_file('sha256',$pending[$i][0]) && $entry->statements>0, 'durable journal retains source checksum and statement progress');
        }
        check((int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===2, 'SQL and build generators both complete');
        $audit = SchemaValidator::inspect($db, $root.'/setup/sql/01-db_structure.sql');
        $reference = SchemaValidator::parse(file_get_contents($root.'/setup/sql/01-db_structure.sql'));
        $referenceIssues = array_values(array_filter($audit['issues'], fn($issue) => isset($reference[$issue[0]])));
        check($audit['checked'] === 108 && !$referenceIssues, 'Complete legacy upgrade converges with all fresh-install reference definitions: '.json_encode($referenceIssues));
        check(!in_array(['aowow_account', 'column extId', 'nullable differs'], $audit['issues'], true), 'Account default/nullability drift is repaired by the explicit migration');
        check(community() === $before && (int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle() === 0, 'Auditing the migrated legacy fixture preserves community data and restored maintenance');
        foreach ($db->query('SELECT phase, tasks FROM aowow_fixture_generators')->fetchAll() as $call) {
            $missing=array_diff(explode(' ', $call->tasks), $registered[$call->phase]);
            check(!$missing, 'fixture corpus has unresolved generator identifiers: '.implode(', ', $missing));
        }
        [$status,$output]=invoke($cli);
        check($status===0 && count($db->query('SELECT * FROM ::sql_update_journal')->fetchAll())===count($pending), 'already-current entrypoint does not replay archived SQL');
        check((int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===2, 'already-current entrypoint does not regenerate completed work');
        if ($engine==='MyISAM') {
            $db->query('ALTER TABLE ::dbversion ENGINE=MyISAM');
            [$status,$output]=invoke($cli);
            check($status===0 && strtoupper($db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_dbversion'")->fetchSingle())==='INNODB', 'already-current metadata conversion is safe without pending SQL');
            check((int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===count($pending) &&
                (int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===2 && community()===$before, 'metadata-only preparation preserves journal, completed work and community');
        }
    }
    // Reproduce a prior run that applied SQL and cleared tasks despite failed spell inserts.
    // Only the new migration runs; this also checks data retention across that ALTER itself.
    $repair=$temp.'/repair'; mkdir($repair);
    copy($root.'/setup/sql/updates/1791244800_01.sql', $repair.'/1791244800_01.sql');
    resetLegacy(); $before=community();
    $longSpellText=str_repeat('Synthetic текст ',5000);
    seed('spell', ['id'=>90001, 'description_loc0'=>$longSpellText]);
    $db->query('ALTER TABLE ::spell MODIFY name_loc0 varchar(512) COLLATE utf8mb4_bin NOT NULL');
    $db->query("UPDATE ::dbversion SET date=1791142907, part=1, `sql`='', build='talenticons'");
    $concatLimit=(int)$db->query('SELECT @@SESSION.group_concat_max_len')->fetchSingle();
    $repairVersion=SqlUpdate::apply($db,$repair);
    $repairSql=preg_split('/\s+/',trim($repairVersion['sql']));
    $repairBuild=preg_split('/\s+/',trim($repairVersion['build']));
    check($db->query('SELECT description_loc0 FROM ::spell WHERE id=90001')->fetchSingle()===$longSpellText && community()===$before, 'repair ALTER preserves preexisting long spell text and community records');
    check($db->query("SELECT COLUMN_TYPE, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_spell' AND COLUMN_NAME='name_loc0'")->fetchPairs()===['varchar(512)'=>'utf8mb4_bin'], 'earlier spell repair preserves intentional custom width and collation');
    check(!array_diff(['spell','items','stats','itemset','source','search'],$repairSql), 'repair schedules data generation even when prior SQL tasks were cleared');
    check(!array_diff(['talenticons','enchants','globaljs','tooltips','profiler'],$repairBuild), 'repair retains pending builds and schedules affected output');
    check((int)$db->query('SELECT @@SESSION.group_concat_max_len')->fetchSingle()===$concatLimit, 'repair restores the session aggregation limit');
    check(count($db->query('SELECT * FROM ::sql_update_journal')->fetchAll())===1, 'repair journals only its own migration on an already-migrated marker');
    foreach (['sql-throw', 'sql-partial', 'build-throw'] as $mode) {
        resetLegacy(); $before=community();
        [$status,$output]=invoke($cli,$mode);
        check($status===1 && !str_contains($output,'SECRET_SENTINEL'), $mode.' fails safely');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'generator failure retains maintenance');
        $version=(array)$db->query('SELECT * FROM ::dbversion')->fetch();
        check($version['build']!=='' && ($mode==='build-throw' || $version['sql']!==''), 'incomplete generator tasks remain queued');
        check(!preg_match('/\bpower\b/', (string)$version['sql']), 'actual archive removed obsolete power task');
        check(community()===$before, 'generator failure preserves community data');
        [$status,$output]=invoke($cli);
        check($status===0 && (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===count($pending), 'retry completes pending work without SQL replay');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'retry preserves already-enabled maintenance');
    }
    resetLegacy(); $before=community();
    $lease=SqlUpdate::acquire($db);
    try {
        [$status,$output]=invoke($cli);
        check($status===1 && (int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===0, 'losing concurrent command does not change maintenance');
        check(strtoupper($db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='aowow_dbversion'")->fetchSingle())==='MYISAM', 'loser cannot convert metadata outside the lock');
        check((int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612 && community()===$before, 'losing concurrent command leaves version and community intact');
    } finally { SqlUpdate::release($db,$lease); }
    // Interrupted SQL remains blocked even when engine preparation had completed earlier.
    $db->query('ALTER TABLE ::dbversion ENGINE=InnoDB');
    $db->query(SqlUpdate::JOURNAL_DDL);
    $db->query("INSERT INTO ::sql_update_journal VALUES (1713730806,1,REPEAT('a',64),'running',1)");
    [$status,$output]=invoke($cli);
    check($status===1 && (int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612, 'unfinished journal blocks blind legacy replay');
    check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'interrupted migration retains maintenance');
    check(community()===$before, 'interruption refusal preserves community rows');

    // Exercise real CLI startup, SQL failure and configuration reload failure before
    // a legacy schema has acquired `default`, using separate disposable migrations.
    unlink($cli.'/setup/sql/updates');
    mkdir($cli.'/setup/sql/updates');
    $migration=$cli.'/setup/sql/updates/1713730806_01.sql';
    resetLegacy(); $before=community();
    file_put_contents($migration, 'CREATE TABLE aowow_partial (id int); SELECT SECRET_SENTINEL FROM aowow_missing;');
    [$status,$output]=invoke($cli);
    check($status===1 && !str_contains($output,'SECRET_SENTINEL'), 'legacy SQL failure returns safely');
    check((int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612 &&
        $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='running' &&
        (int)$db->query('SELECT statements FROM ::sql_update_journal')->fetchSingle()===1, 'legacy failure retains durable progress without advancing marker');
    check($db->query('SELECT checksum FROM ::sql_update_journal')->fetchSingle()===hash_file('sha256',$migration), 'failed legacy SQL retains exact checksum');
    check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1 && community()===$before, 'SQL failure retains maintenance and community records');
    file_put_contents($migration, 'DROP TABLE aowow_partial;');
    [$status,$output]=invoke($cli);
    check($status===1 && $db->query("SHOW TABLES LIKE 'aowow_partial'")->fetchSingle(), 'edited partial SQL still cannot replay');
    check((int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===0, 'SQL failure never invokes generators');

    resetLegacy(); $before=community();
    file_put_contents($migration, 'CREATE TABLE aowow_config_checkpoint (id int);');
    [$status,$output]=invoke($cli);
    check($status===1 && (int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1713730806 &&
        $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='applied', 'configuration reload failure keeps already-applied metadata');
    check((int)$db->query('SELECT COUNT(*) FROM aowow_fixture_generators')->fetchSingle()===0 &&
        $db->query('SELECT `sql` FROM ::dbversion')->fetchSingle()==='power', 'configuration reload failure leaves generators and pending work untouched');
    check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1 && community()===$before, 'configuration reload failure retains maintenance and content');

    resetLegacy(); $before=community();
    file_put_contents($migration, 'CREATE TABLE aowow_interrupted (id int); SELECT SLEEP(3); DROP TABLE aowow_interrupted;');
    $worker=proc_open([PHP_BINARY,$cli.'/aowow','--update','--datasrc=/fixture/extraction/'],
        [0=>['pipe','r'],1=>['file',$temp.'/crash.log','w'],2=>['file',$temp.'/crash.log','a']],$pipes,$cli,getenv(),['bypass_shell'=>true]);
    fclose($pipes[0]);
    try {
        $ready=false; $deadline=microtime(true)+5;
        do {
            if ($db->query("SHOW TABLES LIKE 'aowow_sql_update_journal'")->fetchSingle())
                $ready=(int)$db->query('SELECT statements FROM ::sql_update_journal')->fetchSingle()===1;
            if (!$ready) usleep(20000);
        } while (!$ready && microtime(true)<$deadline);
        check($ready,'legacy worker reached durable DDL checkpoint');
    } finally { proc_terminate($worker,9); proc_close($worker); }
    $deadline=microtime(true)+6;
    do {
        $lease=null;
        try { $lease=SqlUpdate::acquire($db); } catch (RuntimeException) {}
        if ($lease===null) usleep(20000);
    } while ($lease===null && microtime(true)<$deadline);
    check($lease!==null,'terminated legacy worker eventually releases its database lease');
    SqlUpdate::release($db,$lease);
    [$status,$output]=invoke($cli);
    check($status===1 && (int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1711739612 &&
        $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='running', 'hard-interrupted legacy migration refuses automatic replay');
    check($db->query("SHOW TABLES LIKE 'aowow_interrupted'")->fetchSingle() && community()===$before &&
        (int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1, 'hard interruption preserves durable DDL, content and maintenance');
    echo 'PASS: '.$checks.' legacy kernel/config/engine/corpus/community/lock checks; '.count($archived).' archived + '.count($current)." current migrations\n";
} finally { clean($temp); }
