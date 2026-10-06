<?php

namespace Aowow;

if (!defined('AOWOW_REVISION') || !CLI)
    die('illegal access');

require_once 'includes/setup/schemavalidator.class.php';

CLISetup::registerUtility(new class extends UtilityScript
{
    public int $optGroup = CLISetup::OPT_GRP_UTIL;

    public const string COMMAND = 'validate-schema';
    public const string DESCRIPTION = 'Read-only comparison of application table structure with the initial SQL schema.';
    public const array REQUIRED_DB = [DB_AOWOW];

    public function run(array &$args) : bool
    {
        // This mode must never silently select another command or accept mutation flags.
        global $argv;
        for ($i = 1; $i < count($argv); ++$i)
        {
            $arg = $argv[$i];
            if (in_array($arg, ['--validate-schema', '--debug', '--help', '-h'], true) || str_starts_with($arg, '--log=')) continue;
            if ($arg === '--log' && isset($argv[$i + 1])) { ++$i; continue; }
            CLI::write('[validate-schema] use this command alone, optionally with --debug, --log or --help.', CLI::LOG_ERROR);
            return false;
        }
        CLI::write('[validate-schema] comparing with '.SchemaValidator::REFERENCE);
        try
        {
            $report = SchemaValidator::inspect(DB::Aowow());
        }
        catch (\Throwable $error)
        {
            CLI::debug('[validate-schema] metadata comparison failed', $error);
            CLI::write('[validate-schema] comparison failed (code '.(int)$error->getCode().'); check schema metadata access and the reference file.', CLI::LOG_ERROR);
            return false;
        }
        $grouped = [];
        foreach ($report['issues'] as [$table, $object, $problem])
        {
            $grouped[$table][$problem] = ($grouped[$table][$problem] ?? 0) + 1;
            if (CLISetup::getOpt('debug'))
                // Identifiers are schema metadata but can contain arbitrary/control characters.
                CLI::write('[validate-schema] '.preg_replace('/[^a-zA-Z0-9_ .\-]/', '?', $table.'.'.$object).': '.$problem, CLI::LOG_WARN);
        }
        foreach ($grouped as $table => $problems)
        {
            $summary = [];
            foreach ($problems as $problem => $count) $summary[] = $problem.'='.$count;
            CLI::write('[validate-schema] '.preg_replace('/[^a-zA-Z0-9_\-]/', '?', $table).': '.array_sum($problems).' attribute differences ('.implode(', ', $summary).').', CLI::LOG_WARN);
        }
        if ($report['generated'])
        {
            CLI::write('[validate-schema] '.count($report['generated']).' declared DBC-copy tables recognized; absent from initial SQL, so their structures are not compared.', CLI::LOG_INFO);
            foreach ($report['generated'] as $table => $dbc)
                CLI::debug('[validate-schema] generated table '.preg_replace('/[^a-zA-Z0-9_ .\-]/', '?', $table.' from '.$dbc.'.dbc').' (not compared)');
        }
        $success = !$report['issues'];
        CLI::write('[validate-schema] '.$report['checked'].'/'.$report['expected'].' reference tables compared; '.count($report['issues']).' differences.', $success ? CLI::LOG_OK : CLI::LOG_ERROR);
        if (!$success)
            CLI::write('[validate-schema] This is initial-schema drift, not a count of failed migrations. Review with --debug; no repairs were applied.', CLI::LOG_INFO);
        return $success;
    }

    public function writeCLIHelp() : bool
    {
        CLI::write('  usage: php aowow --validate-schema [--debug] [--log=/path/to/log]', -1, false);
        CLI::write('  Compare all application tables with '.SchemaValidator::REFERENCE.'. No SQL is applied and maintenance is unchanged.', -1, false);
        CLI::write('  Reports columns, indexes, foreign keys, engine, charset/collation and unknown extra tables. Declared DBC-copy tables absent from initial SQL are recognized but not compared.', -1, false);
        CLI::write('  Default output groups differences by table; --debug includes individual attribute details. Exit 0 means a match; exit 1 means differences or failure.', -1, false);
        CLI::write('  Checks structure only, not data, migration state or generator completeness. Customizations can produce expected differences.', -1, false);
        return true;
    }
});
