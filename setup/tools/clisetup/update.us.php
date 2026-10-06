<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


/*********************************/
/* automaticly apply sql-updates */
/*********************************/

CLISetup::registerUtility(new class extends UtilityScript
{
    public array  $argvOpts   = ['u'];
    public int    $optGroup   = CLISetup::OPT_GRP_SETUP;
    public string $followupFn = 'sync';

    public const string COMMAND     = 'update';
    public const string DESCRIPTION = 'Apply new sql updates fetched from Github and run --sync as needed.';

    public const array  REQUIRED_DB = [DB_AOWOW];

    public const int    LOCK_SITE   = CLISetup::LOCK_RESTORE;

    // args: null, null, sqlToDo, buildToDo
    public function run(array &$args) : bool
    {
        CLI::write('[update] checking for sql updates...');
        $version = SqlUpdate::apply(DB::Aowow());
        Cfg::load(true);                                    // migrated defaults/flags must be loaded before generator follow-ups
        foreach (['sql' => 'doSql', 'build' => 'doBuild'] as $column => $argument)
            $args[$argument] = trim((string)$version[$column]) ? array_values(array_unique(explode(' ', trim(preg_replace('/[^a-z_\-]+/i', ' ', $version[$column]))))) : [];

        if ($args['doSql'])
            CLI::write('[update] SQL scripts scheduled: '.implode(', ', $args['doSql']));
        if ($args['doBuild'])
        {
            CLISetup::setOpt('force', true);
            CLI::write('[update] Build scripts scheduled: '.implode(', ', $args['doBuild']));
        }
        return true;
    }

    public function writeCLIHelp() : bool
    {
        CLI::write('  usage: php aowow --update', -1, false);
        CLI::write();
        CLI::write('  Applies pending SQL in /setup/sql/updates and its version archives in date/part order, then completes scheduled SQL and image/dataset builds.', -1, false);
        CLI::write('  Use this after fetching the latest rev. from Github.', -1, false);

        CLI::write();
        CLI::write();

        return true;
    }
});

?>
