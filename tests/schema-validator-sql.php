<?php

// Import/alter only an explicitly guarded disposable database. Never use deployment credentials.
use Aowow\{DibiConnection, SchemaValidator};

if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_schema' ||
    (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
    fwrite(STDERR, "Use only AOWOW_TEST_DATABASE=aowow_security_test_schema on an isolated localhost fixture.\n");
    exit(1);
}
$root = dirname(__DIR__);
define('AOWOW_REVISION', 69); define('CLI', true);
require $root.'/includes/libs/autoload.php';
require $root.'/includes/database.php';
require $root.'/includes/setup/schemavalidator.class.php';
$port = (int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306);
$options = ['driver' => 'mysqli', 'host' => '127.0.0.1', 'port' => $port, 'username' => 'root', 'password' => '',
    'database' => 'aowow_security_test_schema', 'charset' => 'utf8mb4', 'substitutes' => ['' => 'aowow_']];
$db = new DibiConnection($options);
$checks = 0;
function check(bool $ok, string $message) : void {
    global $checks; ++$checks;
    if (!$ok) throw new RuntimeException($message);
}
function clean(string $dir) : void {
    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->isDot()) continue;
        if ($file->isDir() && !$file->isLink()) clean($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($dir);
}
function invoke(string $cwd, array $arguments) : array {
    $process = proc_open([PHP_BINARY, $cwd.'/aowow', ...$arguments], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $cwd);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process), $out.$err];
}
function seed(string $table, array $values) : void {
    global $db;
    foreach ($db->query('SHOW COLUMNS FROM %n', 'aowow_'.$table)->fetchAll() as $column) {
        if (array_key_exists($column->Field, $values) || $column->Null === 'YES' || $column->Default !== null || str_contains($column->Extra, 'auto_increment')) continue;
        $values[$column->Field] = preg_match('/int|float|double|decimal/', $column->Type) ? 0 : '';
    }
    $db->query('INSERT INTO %n', 'aowow_'.$table, $values);
}
function snapshot() : array {
    global $db;
    $out = [];
    foreach (['account', 'comments', 'articles', 'screenshots', 'videos', 'account_favorites', 'config', 'dbversion', 'sql_update_journal', 'errors'] as $table)
        $out[$table] = $db->query('SELECT * FROM %n', 'aowow_'.$table)->fetchAll();
    return $out;
}
$temp = sys_get_temp_dir().'/aowow-schema-sql-'.bin2hex(random_bytes(8)); mkdir($temp, 0700);
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $table) $db->query('DROP TABLE %n', $table);
    $db->query('ALTER DATABASE aowow_security_test_schema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    // MySQL 8.4 rejects historical nonunique FK targets; allow the shipped DDL only in this fixture session.
    if ($db->query("SHOW VARIABLES LIKE 'restrict_fk_on_non_standard_key'")->fetch()) $db->query('SET SESSION restrict_fk_on_non_standard_key=OFF');
    preg_match_all('/^CREATE TABLE .*?^\).*?;/ms', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $matches);
    $mysql = !str_contains($db->query('SELECT VERSION()')->fetchSingle(), 'MariaDB');
    foreach ($matches[0] as $ddl) {
        // MySQL expresses literal TEXT defaults as parenthesized expressions.
        if ($mysql) $ddl = str_replace("text NOT NULL DEFAULT ''", "text NOT NULL DEFAULT ('')", $ddl);
        $db->nativeQuery($ddl);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("CREATE USER IF NOT EXISTS 'aowow_schema_reader'@'%' IDENTIFIED BY ''");
    $db->query('GRANT SELECT ON aowow_security_test_schema.* TO %s@%s', 'aowow_schema_reader', '%');
    $reader = new DibiConnection(array_replace($options, ['username' => 'aowow_schema_reader']));
    $report = SchemaValidator::inspect($reader, $root.'/setup/sql/01-db_structure.sql');
    check($report['expected'] === 108 && $report['checked'] === 108 && !$report['issues'], 'Full fresh schema matches real SHOW CREATE TABLE: '.json_encode($report['issues']));

    seed('account', ['id'=>1001, 'login'=>'fixture', 'username'=>'Fixture User', 'passHash'=>'SECRET_FIXTURE_HASH', 'email'=>'fixture@example.test']);
    seed('comments', ['id'=>1001, 'userId'=>1001, 'body'=>'SECRET_FIXTURE_COMMENT']);
    seed('articles', ['type'=>3, 'typeId'=>29254, 'locale'=>0, 'article'=>'SECRET_FIXTURE_ARTICLE']);
    seed('screenshots', ['id'=>1001, 'userIdOwner'=>1001, 'caption'=>'SECRET_FIXTURE_UPLOAD']);
    seed('videos', ['id'=>1001, 'userIdOwner'=>1001, 'videoId'=>'fixture123', 'caption'=>'SECRET_FIXTURE_VIDEO']);
    seed('account_favorites', ['userId'=>1001, 'type'=>3, 'typeId'=>29254]);
    $db->query("INSERT INTO ::config (`key`, `value`) VALUES ('maintenance', '1')");
    $db->query("INSERT INTO ::dbversion (`date`, part, `sql`, build) VALUES (1711739612, 1, 'power', NULL)");
    $db->query("INSERT INTO ::sql_update_journal VALUES (1711739612, 1, %s, 'applied', 1)", str_repeat('a',64));
    $before = snapshot();
    foreach (['includes', 'setup', 'localization'] as $dir) symlink($root.'/'.$dir, $temp.'/'.$dir);
    mkdir($temp.'/config'); copy($root.'/aowow', $temp.'/aowow');
    $config = ['host'=>'127.0.0.1', 'port'=>$port, 'user'=>'aowow_schema_reader', 'pass'=>'', 'db'=>'aowow_security_test_schema', 'prefix'=>'aowow_'];
    file_put_contents($temp.'/config/config.php', '<?php $AoWoWconf = '.var_export(['aowow'=>$config], true).';');
    [$code, $out] = invoke($temp, ['--validate-schema']);
    check($code === 0 && str_contains($out, '108/108 reference tables compared; 0 differences'), 'Real kernel/entrypoint needs only SELECT and no world DB, config load or extracted inputs: '.$out);
    check(snapshot() == $before, 'Successful validation preserves accounts/content/uploads, config/maintenance, marker, journal and errors');

    // Real legacy incompatibility: no configuration default, and MyISAM version metadata.
    $db->query('ALTER TABLE ::config DROP COLUMN `default`');
    $db->query('ALTER TABLE ::dbversion ENGINE=MyISAM');
    $before = snapshot();
    [$code, $out] = invoke($temp, ['--validate-schema', '--debug']);
    check($code === 1 && str_contains($out, 'column default: missing') && str_contains($out, 'aowow_dbversion.table: engine differs'), 'Legacy config and MyISAM metadata reported without attempting migrations: '.$out);
    check(!str_contains($out, 'SECRET_'), 'CLI diagnostics contain no community values');
    check(snapshot() == $before, 'Failed legacy validation preserves all data and maintenance');
    $db->query('ALTER TABLE ::config ADD COLUMN `default` varchar(255) DEFAULT NULL AFTER value');
    $db->query('ALTER TABLE ::dbversion ENGINE=InnoDB');

    $db->query('ALTER TABLE ::spell MODIFY rank_loc0 varchar(21) NOT NULL');
    $db->query('ALTER TABLE ::items DROP INDEX idx_model, ADD KEY fixture_index (quality)');
    $db->query('ALTER TABLE ::account_favorites DROP FOREIGN KEY FK_acc_favorites');
    $db->query('ALTER TABLE ::account_favorites ADD CONSTRAINT FK_acc_favorites FOREIGN KEY (userId) REFERENCES ::account(id) ON DELETE RESTRICT ON UPDATE CASCADE');
    $db->query('DROP TABLE ::zones_sounds');
    $db->query('CREATE TABLE ::fixture_extra (id int)');
    $db->query('CREATE TABLE unrelated_fixture (id int)');
    $db->query('CREATE TABLE dbc_fixture (id int)');
    $report = SchemaValidator::inspect($reader, $root.'/setup/sql/01-db_structure.sql');
    foreach ([['aowow_spell', 'column rank_loc0', 'nullable differs'], ['aowow_items', 'index idx_model', 'missing'],
        ['aowow_items', 'index fixture_index', 'extra'], ['aowow_account_favorites', 'foreign key FK_acc_favorites', 'delete rule differs'],
        ['aowow_zones_sounds', 'table', 'missing'], ['aowow_fixture_extra', 'table', 'extra (absent from initial schema)']] as $issue)
        check(in_array($issue, $report['issues'], true), 'Detect actual table/column/index/FK drift');
    check(!in_array('unrelated_fixture', array_column($report['issues'],0), true) && !in_array('dbc_fixture', array_column($report['issues'],0), true), 'Other table namespaces are excluded');

    // Prefix translation applies to both table names and FK targets, never to literal defaults.
    $schema = str_replace('`aowow_', '`custom_', file_get_contents($root.'/setup/sql/01-db_structure.sql'));
    // A small reference keeps the real metadata comparison focused; all other custom tables are extras.
    $single = $temp.'/single.sql';
    preg_match('/^CREATE TABLE `custom_account` .*?^\).*?;/ms', $schema, $match);
    file_put_contents($single, str_replace('`custom_', '`aowow_', $match[0]));
    $db->query('CREATE TABLE custom_account LIKE aowow_account');
    preg_match('/^CREATE TABLE `aowow_account_favorites` .*?^\).*?;/ms', file_get_contents($root.'/setup/sql/01-db_structure.sql'), $favorites);
    file_put_contents($single, "\n".$favorites[0], FILE_APPEND);
    $db->nativeQuery(str_replace(['`aowow_', '`FK_acc_favorites`'], ['`custom_', '`custom_fk_favorites`'], $favorites[0]));
    // Constraint names are schema-global on MySQL/MariaDB; rename the fixture's original constraint to free its reference name.
    $db->query('ALTER TABLE aowow_account_favorites DROP FOREIGN KEY FK_acc_favorites');
    $db->query('ALTER TABLE custom_account_favorites DROP FOREIGN KEY custom_fk_favorites');
    $db->query('ALTER TABLE custom_account_favorites ADD CONSTRAINT FK_acc_favorites FOREIGN KEY (userId) REFERENCES custom_account(id) ON DELETE CASCADE ON UPDATE CASCADE');
    $customReader = new DibiConnection(array_replace($options, ['username'=>'aowow_schema_reader', 'substitutes'=>[''=>'custom_']]));
    check(!SchemaValidator::inspect($customReader, $single)['issues'], 'Configured prefix translates tables and foreign-key targets');
    $db->query('DROP TABLE custom_account_favorites');
    $db->query('DROP TABLE custom_account');
    $db->query('CREATE TABLE account LIKE aowow_account');
    $emptyReader = new DibiConnection(array_replace($options, ['username'=>'aowow_schema_reader', 'substitutes'=>[''=>'']]));
    $report = SchemaValidator::inspect($emptyReader, $single);
    check($report['checked'] === 1 && !in_array('account', array_column($report['issues'],0), true), 'Empty prefix correctly targets the table and reports other schema tables as extras');
    $db->query('DROP TABLE account');

    // Reference files are parsed, so even a DROP/INSERT in the reference cannot execute.
    file_put_contents($single, "DROP TABLE aowow_account; INSERT INTO aowow_config VALUES ('unsafe');\n".str_replace('`custom_', '`aowow_', $match[0]));
    $before = snapshot();
    SchemaValidator::inspect($reader, $single);
    check(snapshot() == $before, 'Reference DROP/INSERT statements are never executed');
    [$code, $out] = invoke($temp, ['--validate-schema', '--update']);
    check($code === 1 && !str_contains($out, 'checking for sql updates'), 'Mixed upgrade/validation rejected by real entrypoint');
    check(snapshot() == $before, 'Mixed-option rejection does not change database state');
    [$code, $out] = invoke($temp, ['--validate-schema', '--delete']);
    check($code === 1 && !str_contains($out, 'have been deleted'), 'Delete flag rejected without a misleading deletion notice');
    check(snapshot() == $before, 'Rejected delete flag preserves all database records');
    [$code, $out] = invoke($temp, ['--validate-schema', '--help']);
    check($code === 0 && str_contains($out, 'Exit 0 means a match'), 'Help works on incompatible schema');
    echo 'PASS: '.$checks.' real schema/read-only entrypoint checks ('.($mysql ? 'MySQL' : 'MariaDB').")\n";
} finally { clean($temp); }
