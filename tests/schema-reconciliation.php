<?php

// Structure-only legacy input and synthetic rows, on an explicitly disposable database.
use Aowow\{DibiConnection, SchemaUpdate, SchemaValidator, SqlUpdate};

if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_reconciliation' ||
    (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
    fwrite(STDERR, "Use only AOWOW_TEST_DATABASE=aowow_security_test_reconciliation on an isolated localhost fixture.\n"); exit(1);
}
$root = dirname(__DIR__); define('AOWOW_REVISION',69); define('CLI',true);
define('CLI_HAS_E',false); define('OS_WIN',false);
require $root.'/includes/defines.php';
require $root.'/includes/libs/autoload.php';
require $root.'/includes/components/errorlog.class.php';
require $root.'/includes/database.php';
require $root.'/includes/setup/cli.class.php';
require $root.'/includes/setup/sqlupdate.class.php';
require $root.'/includes/setup/schemaupdate.class.php';
$port = (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306);
$options = ['driver'=>'mysqli','host'=>'127.0.0.1','port'=>$port,'username'=>'root','password'=>'',
    'database'=>'aowow_security_test_reconciliation','charset'=>'utf8mb4','substitutes'=>[''=>'aowow_']];
$db = new DibiConnection($options); $checks=0;
function check(bool $ok, string $message) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($message); }
check($db->query('SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()')->fetchSingle()==='utf8mb4_unicode_ci',
    'Historical reconciliation fixture requires utf8mb4_unicode_ci; initialize disposable databases with bash tests/ci/run.sh sql');
function seed(string $table, array $values) : void {
    global $db;
    foreach ($db->query('SHOW COLUMNS FROM %n','aowow_'.$table)->fetchAll() as $column) {
        if (array_key_exists($column->Field,$values) || $column->Null==='YES' || $column->Default!==null || str_contains($column->Extra,'auto_increment')) continue;
        $values[$column->Field]=preg_match('/int|float|double|decimal/',$column->Type)?0:'';
    }
    $db->query('INSERT INTO %n','aowow_'.$table,$values);
}
function resetSchema(string $file) : void {
    global $db,$root;
    $db->query('SET SESSION sql_mode=%s',''); $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $name) $db->query('DROP TABLE %n',$name);
    if ($db->query("SHOW VARIABLES LIKE 'restrict_fk_on_non_standard_key'")->fetch()) $db->query('SET SESSION restrict_fk_on_non_standard_key=OFF');
    preg_match_all('/^CREATE TABLE .*?^\).*?;/ms',file_get_contents($file),$matches);
    foreach ($matches[0] as $ddl) $db->nativeQuery($ddl);
    preg_match_all('/^INSERT INTO .*;$/m',file_get_contents($root.'/setup/sql/02-db_initial_data.sql'),$seeds);
    foreach ($seeds[0] as $sql) $db->nativeQuery($sql);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("UPDATE ::config SET value='0' WHERE `key`='maintenance'");
    $db->query("UPDATE ::dbversion SET `sql`='', build=''");
}
function structures() : array {
    global $db;
    $out=[];
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $name) $out[$name]=array_values((array)$db->query('SHOW CREATE TABLE %n',$name)->fetch())[1];
    return $out;
}
function snapshot() : array {
    global $db;
    $out=[];
    foreach (['account','account_cookies','account_favorites','account_weightscales','account_weightscale_data','comments','guides','guides_changelog','articles','screenshots','videos','account_profiles','profiler_profiles','reports','spell'] as $name)
        $out[$name]=$db->query('SELECT * FROM %n','aowow_'.$name)->fetchAll();
    // Enum ordinal/ownership/value relationships must survive normalization of label spelling.
    $out['excludes']=$db->query('SELECT userId, type, typeId, mode+0 AS mode FROM ::account_excludes')->fetchAll();
    $out['ratings']=$db->query('SELECT type+0 AS type, entry, userId, value FROM ::user_ratings')->fetchAll();
    $out['taxi']=$db->query('SELECT id, type+0 AS type, typeId, name_loc0 FROM ::taxinodes ORDER BY id')->fetchAll();
    return $out;
}
function invoke(string $dir, array $arguments=['--update']) : array {
    $process=proc_open([PHP_BINARY,$dir.'/aowow',...$arguments],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$dir);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return [proc_close($process),$out];
}
function clean(string $dir) : void { foreach(new DirectoryIterator($dir) as $f) { if($f->isDot())continue; if($f->isDir()&&!$f->isLink())clean($f->getPathname());else unlink($f->getPathname()); } rmdir($dir); }
$temp=sys_get_temp_dir().'/aowow-reconciliation-'.bin2hex(random_bytes(8)); mkdir($temp,0700);
$migration=$root.'/setup/sql/updates/'.SchemaUpdate::DATE.'_01.sql';
$fixture=$root.'/tests/fixtures/schema-before-reconciliation.sql';
try {
    mkdir($temp.'/config'); copy($root.'/aowow',$temp.'/aowow');
    foreach(['includes','setup','localization'] as $name)symlink($root.'/'.$name,$temp.'/'.$name);
    $config=['host'=>'127.0.0.1','port'=>$port,'user'=>'root','pass'=>'','db'=>$options['database'],'prefix'=>'aowow_'];
    file_put_contents($temp.'/config/config.php','<?php $AoWoWconf = '.var_export(['aowow'=>$config,'world'=>$config],true).';');
    mkdir($temp.'/static/uploads/screenshots/normal',0700,true);file_put_contents($temp.'/static/uploads/screenshots/normal/1001.jpg','SYNTHETIC_UPLOAD_BYTES');
    resetSchema($fixture);
    $db->query("UPDATE ::dbversion SET date=1791244800, part=1");
    $before=SchemaValidator::inspect($db,$root.'/setup/sql/01-db_structure.sql');
    check($before['checked']===108 && count($before['issues'])>500,'Historical definitions reproduce broad schema drift against the corrected baseline');
    $targets=SchemaUpdate::targets(file_get_contents($migration));
    check(count($targets)===52 && array_sum(array_map(fn($t)=>count($t['columns']),$targets))===526,'Entire reviewed reconciliation definition is parsed');
    seed('account',['id'=>1001,'extId'=>4242,'login'=>'fixture','username'=>'Fixture User','passHash'=>'SECRET_FIXTURE_HASH','email'=>'fixture@example.test','description'=>'Profile текст','userGroups'=>8,'userPerms'=>3,'curLogin'=>12345,'prevLogin'=>12300,'avatar'=>2]);
    seed('comments',['id'=>1001,'type'=>3,'typeId'=>29254,'userId'=>1001,'body'=>'SECRET_COMMENT текст']);
    seed('guides',['id'=>1001,'userId'=>1001,'name'=>'Fixture guide']);
    seed('guides_changelog',['id'=>1001,'userId'=>1001]);
    seed('articles',['type'=>3,'typeId'=>29254,'locale'=>0,'article'=>'SECRET_CUSTOM_ARTICLE']);
    seed('screenshots',['id'=>1001,'userIdOwner'=>1001,'caption'=>str_repeat('т',250)]);
    seed('videos',['id'=>1001,'userIdOwner'=>1001,'videoId'=>'fixture123','caption'=>str_repeat('т',2000)]);
    seed('account_cookies',['userId'=>1001,'name'=>'fixture','data'=>str_repeat('x',70000)]);
    seed('account_favorites',['userId'=>1001,'type'=>3,'typeId'=>29254]);
    seed('account_weightscales',['id'=>2001,'userId'=>1001,'name'=>'Fixture Unicode','icon'=>str_repeat('中',48)]);
    seed('account_weightscale_data',['id'=>2001,'field'=>'agi','val'=>15]);
    seed('profiler_profiles',['id'=>1001]);seed('account_profiles',['accountId'=>1001,'profileId'=>1001]);
    seed('reports',['id'=>1001,'userId'=>1001,'description'=>'SECRET_REPORT']);
    seed('spell',['id'=>1001,'description_loc0'=>str_repeat('x',80000),'rank_loc4'=>str_repeat('中',22)]);
    foreach([1,2] as $code) {
        seed('account_excludes',['userId'=>1001,'type'=>3,'typeId'=>$code,'mode'=>$code]);
        seed('user_ratings',['userId'=>1001,'entry'=>$code,'type'=>$code,'value'=>1]);
        seed('sounds_files',['id'=>$code,'file'=>'fixture'.$code,'type'=>$code]);
        seed('taxinodes',['id'=>$code,'type'=>$code]);
    }
    seed('taxinodes',['id'=>3,'type'=>0,'typeId'=>0,'name_loc0'=>'Fixture scripted flight']);
    $rows=snapshot();
    // Every unsafe-data condition stops before any ALTER or journal start, retaining maintenance.
    foreach(['numeric','enum','taxi','null','length'] as $failure) {
        $db->query("UPDATE ::config SET value='0' WHERE `key`='maintenance'");
        if($failure==='numeric')seed('races',['id'=>1001,'classMask'=>65536]);
        if($failure==='enum')$db->query('UPDATE ::account_excludes SET mode=0 WHERE typeId=1');
        if($failure==='taxi')$db->query('UPDATE ::taxinodes SET type=3 WHERE id=3');
        if($failure==='null') {
            $db->query('ALTER TABLE ::account_weightscales MODIFY icon varchar(48) DEFAULT NULL');
            seed('account_weightscales',['id'=>1001,'userId'=>1001,'name'=>'fixture','icon'=>null]);
        }
        if($failure==='length') {
            $db->query('ALTER TABLE ::account_weightscales MODIFY icon text NOT NULL');
            seed('account_weightscales',['id'=>1001,'userId'=>1001,'name'=>'fixture','icon'=>str_repeat('x',52)]);
        }
        $ddl=structures();$data=snapshot();
        [$code,$out]=invoke($temp);
        check($code===1 && str_contains($out,'schema reconciliation blocked'),'Real --update refuses unsafe '.$failure.' conversion');
        check(!str_contains($out,'SECRET_') && !str_contains($out,'SELECT MAX('),'Diagnostics omit account/content values and SQL');
        check(structures()===$ddl && snapshot()==$data,'Failed preflight changes neither structures nor community rows');
        check((int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1791244800 && !(int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle(),'Failed preflight neither advances version nor starts an incomplete journal');
        check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===1,'Failed reconciliation retains maintenance');
        if($failure==='numeric')$db->query('DELETE FROM ::races WHERE id=1001');
        if($failure==='enum')$db->query('UPDATE ::account_excludes SET mode=1 WHERE typeId=1');
        if($failure==='taxi')$db->query('UPDATE ::taxinodes SET type=0 WHERE id=3');
        if($failure==='null') { $db->query('DELETE FROM ::account_weightscales WHERE id=1001');$db->query("ALTER TABLE ::account_weightscales MODIFY icon varchar(48) NOT NULL DEFAULT ''"); }
        if($failure==='length') { $db->query('DELETE FROM ::account_weightscales WHERE id=1001');$db->query("ALTER TABLE ::account_weightscales MODIFY icon varchar(48) NOT NULL DEFAULT ''"); }
    }
    $db->query("UPDATE ::config SET value='0' WHERE `key`='maintenance'");
    $other=new DibiConnection($options);$lease=SqlUpdate::acquire($other);
    try { [$code,$out]=invoke($temp);check($code===1,'Concurrent update is refused before reconciliation'); }
    finally { SqlUpdate::release($other,$lease); }
    seed('races',['id'=>1001,'classMask'=>65535,'flags'=>255,'factionId'=>-32768]);
    $db->query('ALTER TABLE ::spell DROP INDEX idx_name4');
    [$code,$out]=invoke($temp);
    check($code===0 && str_contains($out,'schema reconciliation data preflight passed'),'Real --update completes the guarded reconciliation: '.$out);
    $after=SchemaValidator::inspect($db,$root.'/setup/sql/01-db_structure.sql');
    check($after['checked']===108 && !$after['issues'],'All 108 historical application table definitions converge exactly with the corrected initial schema: '.json_encode($after['issues']));
    check(snapshot()==$rows,'Accounts, passwords, community text, ownership, favorites, profiles, ratings and long legacy content survive');
    check(file_get_contents($temp.'/static/uploads/screenshots/normal/1001.jpg')==='SYNTHETIC_UPLOAD_BYTES','Upload files remain unchanged');
    // Execute the generator's actual INSERTs against DBC-shaped empty-string input.
    $db->query('CREATE TEMPORARY TABLE dbc_declinedword (id int, word varchar(127) NULL)');
    $db->query('CREATE TEMPORARY TABLE dbc_declinedwordcases (wordId int, caseIdx int, word varchar(131) NULL)');
    $db->query("INSERT INTO dbc_declinedword VALUES (2001,NULL),(2002,'пример')");
    $db->query("INSERT INTO dbc_declinedwordcases VALUES (2001,1,NULL),(2002,1,'примера')");
    preg_match_all('/qry\(\x27(INSERT INTO ::declinedword(?:cases)? [^\x27]+)\x27\)/',file_get_contents($root.'/setup/tools/sqlgen/declinedword.ss.php'),$wordInserts);
    check(count($wordInserts[1])===2,'Actual declined-word generator INSERTs located');
    $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
    foreach($wordInserts[1] as $insert)$db->query($insert);
    check($db->query('SELECT word FROM ::declinedword WHERE id=2001')->fetchSingle()===null &&
        $db->query('SELECT word FROM ::declinedwordcases WHERE wordId=2001')->fetchSingle()===null &&
        $db->query('SELECT word FROM ::declinedwordcases WHERE wordId=2002')->fetchSingle()==='примера','Valid empty and Unicode DBC strings survive real generator INSERTs in strict mode');
    $db->query('DROP TEMPORARY TABLE dbc_declinedword');$db->query('DROP TEMPORARY TABLE dbc_declinedwordcases');
    $db->query('SET SESSION sql_mode=%s','');
    foreach([['account_excludes','mode',['EXCLUDE','INCLUDE']],['sounds_files','type',['OGG','MP3']],['user_ratings','type',['COMMENT','GUIDE']]] as [$table,$column,$labels])
        check(array_map(fn($row)=>$row[$column],$db->query('SELECT %n FROM %n ORDER BY %n',$column,'aowow_'.$table,$table==='account_excludes'?'typeId':($table==='user_ratings'?'entry':'id'))->fetchAll())===$labels,'Both legacy enum codes preserve their intended labels in '.$table);
    check(array_map(fn($row)=>(int)$row->type,$db->query('SELECT type FROM ::taxinodes ORDER BY id')->fetchAll())===[1,2,0],'Scripted, NPC and object taxi ordinals survive unchanged');
    // A schema created from the older initial ENUM must preserve its numeric meaning too.
    $db->query("ALTER TABLE ::taxinodes MODIFY type enum('NPC','GOBJECT') NOT NULL");
    $db->query('TRUNCATE ::sql_update_journal');$db->query('UPDATE ::dbversion SET date=1791244800');
    [$code,$out]=invoke($temp);
    check($code===0 && snapshot()==$rows,'Older initial taxi ENUM retains ordinals 0, 1 and 2 through strict reconciliation: '.$out);
    $process=proc_open([PHP_BINARY,$root.'/tests/fixtures/taxi-generator.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$root);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $code=proc_close($process);
    check($code===0,'Real taxi generator completes without database errors under strict mode: '.$err);
    $generated=json_decode($out,true,flags:JSON_THROW_ON_ERROR);
    check($generated['nodes']===[['id'=>301,'type'=>0,'typeId'=>0,'name_loc8'=>'Задание'],['id'=>302,'type'=>1,'typeId'=>9001,'name_loc8'=>'Полёт']] &&
        $generated['visible']===[302] && $generated['paths']===1,'Real generator preserves scripted/NPC identities, localized names, map filtering and paths');
    check((int)$db->query("SELECT value FROM ::config WHERE `key`='maintenance'")->fetchSingle()===0,'Successful reconciliation restores prior maintenance');
    $entry=$db->query('SELECT * FROM ::sql_update_journal WHERE date=%i AND part=1',SchemaUpdate::DATE)->fetch();
    check($entry->status==='applied' && $entry->checksum===hash_file('sha256',$migration) && (int)$entry->statements===count(SqlUpdate::statements(file_get_contents($migration))),'All ALTER statements retain durable checksum/progress accounting');
    $journalCount=(int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle();
    [$code,$out]=invoke($temp);check($code===0 && (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===$journalCount,'Already-current rerun performs no replay');

    // A failure after DDL still uses the existing partial-application refusal, even if columns now match.
    $dir=$temp.'/broken';mkdir($dir);$sql=file_get_contents($migration)."\nSELECT nonexistent_fixture_column FROM aowow_account;";
    file_put_contents($dir.'/'.SchemaUpdate::DATE.'_01.sql',$sql);
    $db->query('TRUNCATE ::sql_update_journal');$db->query('UPDATE ::dbversion SET date=1791244800');
    $data=snapshot();$mode=$db->query('SELECT @@SESSION.sql_mode')->fetchSingle();$failed=false;
    try { SqlUpdate::apply($db,$dir); } catch(Throwable){$failed=true;}
    check($failed && $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='running','SQL failure after ALTER retains unfinished journal');
    check(snapshot()==$data && $db->query('SELECT @@SESSION.sql_mode')->fetchSingle()===$mode,'Failed strict execution preserves rows and restores connection SQL mode');
    $failed=false;try{SqlUpdate::apply($db,$dir);}catch(Throwable){$failed=true;}
    check($failed && (int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1791244800,'Incomplete migration cannot be replayed based on matching structures');
    $lease=SqlUpdate::acquire($other);SqlUpdate::release($other,$lease);check(true,'Failure releases the connection-scoped update lease');

    // Verification must catch a completed SQL file that still leaves a mismatching definition.
    foreach (['column'=>'ALTER TABLE aowow_account MODIFY curLogin int unsigned NOT NULL DEFAULT 9',
        'table'=>'ALTER TABLE aowow_achievement DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'] as $kind=>$ddl) {
        $db->query('TRUNCATE ::sql_update_journal');
        $sql=file_get_contents($migration)."\nSET @fixture_ddl = '".$ddl."';\nPREPARE fixture_change FROM @fixture_ddl;\nEXECUTE fixture_change;\nDEALLOCATE PREPARE fixture_change;";
        file_put_contents($dir.'/'.SchemaUpdate::DATE.'_01.sql',$sql);
        $failed=false;try{SqlUpdate::apply($db,$dir);}catch(Throwable){$failed=true;}
        check($failed && $db->query('SELECT status FROM ::sql_update_journal')->fetchSingle()==='running' &&
            (int)$db->query('SELECT date FROM ::dbversion')->fetchSingle()===1791244800,'Post-DDL verification refuses to acknowledge a remaining '.$kind.' mismatch');
        check(snapshot()==$data && $db->query('SELECT @@SESSION.sql_mode')->fetchSingle()===$mode,'Verification failure preserves rows and restores SQL mode');
    }

    resetSchema($root.'/setup/sql/01-db_structure.sql');
    $fresh=SchemaValidator::inspect($db,$root.'/setup/sql/01-db_structure.sql');
    check(!$fresh['issues'],'Fresh install imports natively on this engine and matches the same baseline');
    $flags=$db->query("SELECT `key`, value, `default`, cat, flags FROM ::config WHERE `key` IN ('searchplugins_enable','searchbox_enable') ORDER BY `key`")->fetchAll();
    check(count($flags)===2 && array_reduce($flags,fn($ok,$row)=>$ok && $row->value==='1' && $row->default==='1' && (int)$row->cat===1 && (int)$row->flags===132,true),'Fresh install seeds both goodies as persistent, enabled site booleans');
    check((int)$db->query("SELECT COUNT(*) FROM ::config WHERE `key`='missing_screenshots_enable' AND value='0' AND `default`='0' AND cat=1 AND flags=132")->fetchSingle()===1,'Fresh install seeds missing screenshots as a persistent, disabled site boolean');
    check((int)$db->query("SELECT COUNT(*) FROM ::config WHERE `key`='feedback_enable' AND value='1' AND `default`='1' AND cat=1 AND flags=132")->fetchSingle()===1,'Fresh install seeds Feedback as a persistent, enabled site boolean');
    SqlUpdate::apply($db);
    check(!(int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle(),'Fresh version metadata already includes reconciliation');
    $db->query('UPDATE ::dbversion SET date=1791244800');SqlUpdate::apply($db);
    check(!SchemaValidator::inspect($db,$root.'/setup/sql/01-db_structure.sql')['issues'],'Reconciliation also supports an already-matching schema at the preceding marker');
    $db->query("UPDATE ::config SET value='0' WHERE `key`='searchplugins_enable'");
    $db->query("DELETE FROM ::config WHERE `key`='searchbox_enable'");
    $db->query("UPDATE ::dbversion SET date=1791331200, part=1");
    $db->query('TRUNCATE ::sql_update_journal');
    [$code,$out]=invoke($temp);
    check($code===0,'Real --update installs the goodies settings from the preceding marker: '.$out);
    check($db->query("SELECT value FROM ::config WHERE `key`='searchplugins_enable'")->fetchSingle()==='0' &&
        $db->query("SELECT value FROM ::config WHERE `key`='searchbox_enable'")->fetchSingle()==='1','Settings migration preserves an existing disabled choice and adds missing settings');
    $entry=$db->query('SELECT * FROM ::sql_update_journal WHERE date=1791331200 AND part=2')->fetch();
    check((int)$entry->date===1791331200 && (int)$entry->part===2 && $entry->status==='applied' &&
        $entry->checksum===hash_file('sha256',$root.'/setup/sql/updates/1791331200_02.sql'),'Settings migration uses ordinary durable update accounting');
    [$code,$out]=invoke($temp);
    check($code===0 && (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===3 &&
        $db->query("SELECT value FROM ::config WHERE `key`='searchplugins_enable'")->fetchSingle()==='0','Already-current update retains goodies choices without replay');
    $db->query("DELETE FROM ::config WHERE `key`='missing_screenshots_enable'");
    $db->query('UPDATE ::dbversion SET date=1791331200, part=2');
    $db->query('TRUNCATE ::sql_update_journal');
    [$code,$out]=invoke($temp);
    check($code===0,'Real --update adds the missing-screenshots switch from the preceding marker: '.$out);
    check((int)$db->query("SELECT COUNT(*) FROM ::config WHERE `key`='missing_screenshots_enable' AND value='0' AND `default`='0' AND cat=1 AND flags=132")->fetchSingle()===1,'Settings migration disables missing screenshots by default with correct boolean metadata');
    $entry=$db->query('SELECT * FROM ::sql_update_journal WHERE date=1791331200 AND part=3')->fetch();
    check((int)$entry->date===1791331200 && (int)$entry->part===3 && $entry->status==='applied' &&
        $entry->checksum===hash_file('sha256',$root.'/setup/sql/updates/1791331200_03.sql') && (int)$entry->statements===1,'Missing-screenshots migration uses ordinary durable update accounting');
    $db->query("UPDATE ::config SET value='1' WHERE `key`='missing_screenshots_enable'");
    $db->query('UPDATE ::dbversion SET date=1791331200, part=2');
    $db->query('TRUNCATE ::sql_update_journal');
    [$code,$out]=invoke($temp);
    check($code===0 && $db->query("SELECT value FROM ::config WHERE `key`='missing_screenshots_enable'")->fetchSingle()==='1','Migration preserves an existing explicitly enabled choice');
    [$code,$out]=invoke($temp);
    check($code===0 && (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===2 &&
        $db->query("SELECT value FROM ::config WHERE `key`='missing_screenshots_enable'")->fetchSingle()==='1','Already-current update preserves the switch without replay');
    $db->query("DELETE FROM ::config WHERE `key`='feedback_enable'");
    $db->query('UPDATE ::dbversion SET date=1791331200, part=3');
    $db->query('TRUNCATE ::sql_update_journal');
    [$code,$out]=invoke($temp);
    check($code===0 && (int)$db->query("SELECT COUNT(*) FROM ::config WHERE `key`='feedback_enable' AND value='1' AND `default`='1' AND cat=1 AND flags=132")->fetchSingle()===1,'Real --update installs enabled Feedback with persistent boolean metadata: '.$out);
    $entry=$db->query('SELECT * FROM ::sql_update_journal')->fetch();
    check((int)$entry->part===4 && $entry->status==='applied' && (int)$entry->statements===2 &&
        $entry->checksum===hash_file('sha256',$root.'/setup/sql/updates/1791331200_04.sql'),'Feedback migration retains durable checksummed accounting');
    check(str_contains(file_get_contents($temp.'/static/js/global.js'), 'typeof g_feedbackEnabled') &&
        !$db->query('SELECT build FROM ::dbversion')->fetchSingle(),'Feedback update builds the client guards and clears pending work only after completion');
    $db->query("UPDATE ::config SET value='0' WHERE `key`='feedback_enable'");
    $db->query('UPDATE ::dbversion SET date=1791331200, part=3');
    $db->query('TRUNCATE ::sql_update_journal');
    [$code,$out]=invoke($temp);
    check($code===0 && $db->query("SELECT value FROM ::config WHERE `key`='feedback_enable'")->fetchSingle()==='0','Feedback migration preserves an existing disabled choice');
    [$code,$out]=invoke($temp);
    check($code===0 && (int)$db->query('SELECT COUNT(*) FROM ::sql_update_journal')->fetchSingle()===1 &&
        $db->query("SELECT value FROM ::config WHERE `key`='feedback_enable'")->fetchSingle()==='0','Already-current update preserves Feedback without replay');
    echo 'PASS: '.$checks.' guarded reconciliation/parity/community/enum/concurrency checks ('.$db->query('SELECT VERSION()')->fetchSingle().")\n";
} finally { clean($temp); }
