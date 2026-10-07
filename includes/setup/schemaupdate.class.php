<?php

namespace Aowow;

if (!defined('AOWOW_REVISION') || !CLI)
    die('illegal access');

require_once __DIR__.'/schemavalidator.class.php';

/** Safety checks for the explicit, journaled schema reconciliation migration. */
final class SchemaUpdate
{
    public const int DATE = 1791331200;

    public static function targets(string $sql) : array
    {
        $targets = [];
        foreach (SqlUpdate::statements($sql) as $statement)
        {
            if (!preg_match('/^ALTER TABLE `(aowow_[a-z0-9_]+)`\s+(.*)$/sD', $statement, $match)) continue;
            $body = preg_replace('/^\s*MODIFY COLUMN (?=`)/m', ' ', $match[2]);
            $ddl = 'CREATE TABLE `'.$match[1].'` ('.$body.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
            if (isset($targets[$match[1]])) throw new \RuntimeException('Duplicate reconciliation table.');
            $targets += SchemaValidator::parse($ddl);
        }
        if (!$targets || !isset($targets['aowow_spell'])) throw new \RuntimeException('Incomplete reconciliation definitions.');
        $index = SchemaValidator::parse('CREATE TABLE `aowow_spell` (`name_loc4` varchar(115), KEY `idx_name4` (`name_loc4`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
        $targets['aowow_spell']['indexes'] = $index['aowow_spell']['indexes'];
        return $targets;
    }

    private static function structure(DibiConnection $db, string $table) : array
    {
        $row = array_values((array)$db->query('SHOW CREATE TABLE %n', $table)->fetch());
        return SchemaValidator::parse($row[1])[$table];
    }

    private static function refuse(string $table, string $column, string $reason) : never
    {
        // Only trusted migration identifiers/reasons are printed, never row values or SQL.
        CLI::write('[update] schema reconciliation blocked at '.$table.'.'.$column.': '.$reason.'. Resolve this on the restored copy; no statements from this migration were applied.', CLI::LOG_ERROR);
        throw new \RuntimeException('Schema reconciliation preflight failed.');
    }

    /** Check all tables before the first ALTER, even when normal connections use implicit defaults. */
    public static function preflight(DibiConnection $db, string $sql) : void
    {
        foreach (self::targets($sql) as $table => $target)
        {
            $actual = self::structure($db, $table);
            foreach (['engine', 'charset', 'collation'] as $attribute)
                if ($target[$attribute] !== $actual[$attribute]) self::refuse($table, 'table', 'unexpected '.$attribute);
            foreach ($target['indexes'] as $name => $definition)
                if (isset($actual['indexes'][$name]) && $actual['indexes'][$name] !== $definition)
                    self::refuse($table, $name, 'unexpected index definition');
            $checks = [];
            foreach ($target['columns'] as $name => $definition)
            {
                if (!isset($actual['columns'][$name])) self::refuse($table, $name, 'missing column');
                $old = $actual['columns'][$name];
                if ($old['generated'] || $definition['generated'] || $old['auto increment'] !== $definition['auto increment'])
                    self::refuse($table, $name, 'unsupported generated or auto-increment conversion');
                $field = '`'.$name.'`';
                if (!$definition['nullable'] && $old['nullable']) $checks[] = [$name, 'NULL values in a required column', $field.' IS NULL'];
                [$type, $size] = $definition['type'];
                $integerBits = ['tinyint'=>8, 'smallint'=>16, 'mediumint'=>24, 'int'=>32, 'bigint'=>64];
                if (isset($integerBits[$type]))
                {
                    // Scripted taxi flights deliberately use zero. Older numeric tables and
                    // the initial ENUM schema both store the same ordinals, consumed by maps.
                    $taxiType = $table === 'aowow_taxinodes' && $name === 'type';
                    if ($taxiType && $old['type'][0] === 'enum')
                    {
                        $labels = array_column(array_filter($old['type'][1], fn($token) => $token[0] === 'string'), 1);
                        if ($labels !== ['NPC', 'GOBJECT']) self::refuse($table, $name, 'unsupported taxi enum label mapping');
                    }
                    else if (!isset($integerBits[$old['type'][0]])) self::refuse($table, $name, 'unsupported numeric conversion');
                    if ($taxiType) $checks[] = [$name, 'invalid taxi type codes', '('.$field.' + 0) < 0 OR ('.$field.' + 0) > 2'];
                    if ($old['type'] === $definition['type'] && $old['unsigned'] === $definition['unsigned']) continue;
                    $bits = $integerBits[$type];
                    $minimum = $definition['unsigned'] ? '0' : ($bits === 64 ? '-9223372036854775808' : (string)-(2 ** ($bits - 1)));
                    $maximum = $bits === 64 ? ($definition['unsigned'] ? '18446744073709551615' : '9223372036854775807') : (string)(2 ** ($bits - ($definition['unsigned'] ? 0 : 1)) - 1);
                    $checks[] = [$name, 'values outside the target numeric range', $field.' < '.$minimum.' OR '.$field.' > '.$maximum];
                }
                else if ($type === 'enum')
                {
                    $labels = array_column(array_filter($size, fn($token) => $token[0] === 'string'), 1);
                    if ($old['type'][0] === 'enum')
                    {
                        $oldLabels = array_column(array_filter($old['type'][1], fn($token) => $token[0] === 'string'), 1);
                        if (array_map('strtolower', $oldLabels) !== array_map('strtolower', $labels))
                            self::refuse($table, $name, 'unsupported enum label mapping');
                        $checks[] = [$name, 'invalid enum values', '('.$field.' + 0) = 0'];
                    }
                    else if (isset($integerBits[$old['type'][0]]))
                        $checks[] = [$name, 'invalid legacy enum codes', $field.' < 1 OR '.$field.' > '.count($labels)];
                    else self::refuse($table, $name, 'unsupported enum conversion');
                }
                else if (in_array($type, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true))
                {
                    if (!in_array($old['type'][0], ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], true) ||
                        $old['charset'] !== $definition['charset'] || $old['collation'] !== $definition['collation'])
                        self::refuse($table, $name, 'unsupported text or character-set conversion');
                    if ($old['type'] === $definition['type']) continue;
                    $predicate = in_array($type, ['char', 'varchar'], true) ? 'CHAR_LENGTH('.$field.') > '.(int)$size[0][1] :
                        'OCTET_LENGTH('.$field.') > '.(['tinytext'=>255, 'text'=>65535, 'mediumtext'=>16777215, 'longtext'=>4294967295][$type]);
                    $checks[] = [$name, 'text exceeds the target capacity', $predicate];
                }
                else if ($old['type'] !== $definition['type'] || $old['unsigned'] !== $definition['unsigned'])
                    self::refuse($table, $name, 'unsupported type conversion');
            }
            if (!$checks) continue;
            $expressions = [];
            foreach ($checks as $i => [, , $predicate]) $expressions[] = 'MAX('.$predicate.') AS `c'.$i.'`';
            $row = (array)$db->query('SELECT '.implode(', ', $expressions).' FROM %n', $table)->fetch();
            foreach ($checks as $i => [$column, $reason])
                if ((int)$row['c'.$i]) self::refuse($table, $column, $reason);
        }
        CLI::write('[update] schema reconciliation data preflight passed', CLI::LOG_OK);
    }

    public static function verify(DibiConnection $db, string $sql) : void
    {
        foreach (self::targets($sql) as $table => $target)
        {
            $actual = self::structure($db, $table);
            foreach (['engine', 'charset', 'collation'] as $attribute)
                if ($actual[$attribute] !== $target[$attribute])
                    throw new \RuntimeException('Schema reconciliation table verification failed.');
            foreach (['columns', 'indexes'] as $kind)
                foreach ($target[$kind] as $name => $definition)
                    if (($actual[$kind][$name] ?? null) !== $definition)
                        throw new \RuntimeException('Schema reconciliation verification failed.');
        }
    }
}
