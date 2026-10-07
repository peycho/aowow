<?php

namespace Aowow;

// Minimal registry/position boundary; the taxi generator and database calls are real.
if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_reconciliation' ||
    (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
    fwrite(STDERR, "Taxi fixture requires the guarded disposable reconciliation database.\n"); exit(1);
}
$root = dirname(__DIR__, 2);
define('AOWOW_REVISION', 69); define('CLI', true);
require $root.'/includes/defines.php';
require $root.'/includes/libs/autoload.php';
require $root.'/includes/components/errorlog.class.php';
require $root.'/includes/database.php';
class SetupScript {}
class CLISetup
{
    public const int ARGV_PARAM = 1;
    public static object $generator;
    public static function registerSetup(string $kind, object $generator) : void { self::$generator = $generator; }
}
class WorldPosition
{
    public static function toZonePos(int $map, float $x, float $y) : array { return []; }
}
$config = ['driver'=>'mysqli', 'host'=>'127.0.0.1', 'port'=>(int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306),
    'username'=>'root', 'password'=>'', 'db'=>'aowow_security_test_reconciliation', 'prefix'=>'aowow_'];
DB::load(DB_AOWOW, $config); DB::load(DB_WORLD, $config);
$app = DB::Aowow(); $world = DB::World();
$app->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
$world->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
$app->query('CREATE TEMPORARY TABLE dbc_taxipath (id int, startNodeId int, endNodeId int)');
$app->query('INSERT INTO dbc_taxipath VALUES (1,301,302),(2,302,301)');
// MySQL cannot reopen a temporary table on both sides of the generator's UNION.
$app->query('CREATE TABLE dbc_taxinodes (id int, mapId int, posX float, posY float,
    name_loc0 varchar(50), name_loc2 varchar(50), name_loc3 varchar(50), name_loc4 varchar(50), name_loc6 varchar(50), name_loc8 varchar(50))');
$app->query("INSERT INTO dbc_taxinodes VALUES (301,0,0,0,'Fixture Quest','Quête','Quest','任务','Misión','Задание'),
    (302,0,10,10,'Fixture Flight','Vol','Flight','飞行','Vuelo','Полёт')");
$app->query('CREATE TABLE dbc_worldmaparea (mapId int, areaId int, `left` float, `right` float, top float, bottom float)');
$app->query('INSERT INTO dbc_worldmaparea VALUES (0,0,100,-100,100,-100)');
$app->query('CREATE TEMPORARY TABLE dbc_worldmaptransforms (sourceMapId int, targetMapId int, offsetX float, offsetY float, minX float, maxX float, minY float, maxY float)');
$app->query('CREATE TEMPORARY TABLE dbc_factiontemplate (id int, enemyFactionId1 int, enemyFactionId2 int, enemyFactionId3 int, enemyFactionId4 int, hostileMask int)');
$app->query('INSERT INTO dbc_factiontemplate VALUES (1,0,0,0,0,0)');
$world->query('CREATE TEMPORARY TABLE creature_template (entry int, faction int, npcflag int)');
$world->query('CREATE TEMPORARY TABLE creature (id int, map int, position_x float, position_y float, npcflag int)');
$world->query('INSERT INTO creature_template VALUES (9001,1,%i)', NPC_FLAG_FLIGHT_MASTER);
$world->query('INSERT INTO creature VALUES (9001,0,10,10,0)');
require $root.'/setup/tools/sqlgen/taxi.ss.php';
if (!CLISetup::$generator->generate() || DB::errorCount() !== 0) throw new \RuntimeException('Taxi generator failed.');
$app->query('DROP TABLE dbc_taxinodes'); $app->query('DROP TABLE dbc_worldmaparea');
echo json_encode([
    'nodes'=>$app->query('SELECT id, type, typeId, name_loc8 FROM ::taxinodes ORDER BY id')->fetchAll(),
    'visible'=>$app->query('SELECT id FROM ::taxinodes WHERE type <> 0 AND typeId <> 0 ORDER BY id')->fetchPairs(),
    'paths'=>(int)$app->query('SELECT COUNT(*) FROM ::taxipath')->fetchSingle(),
], JSON_THROW_ON_ERROR);
