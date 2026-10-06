<?php

namespace Aowow;

if (!defined('AOWOW_REVISION') || !CLI)
    die('illegal access');

/** Compare trusted first-install DDL with SHOW CREATE TABLE; never execute reference SQL. */
final class SchemaValidator
{
    public const string REFERENCE = 'setup/sql/01-db_structure.sql';

    public static function inspect(DibiConnection $db, string $reference = self::REFERENCE) : array
    {
        $sql = file_get_contents($reference);
        if ($sql === false)
            throw new \RuntimeException('Cannot read the initial schema.');
        $defaults = (array)$db->query('SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()')->fetch();
        $expected = self::parse($sql, true, $defaults);
        $prefix = (string)($db->getConfig('substitutes')[''] ?? '');
        $tables = $db->query('SELECT TABLE_NAME, TABLE_TYPE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAssoc('TABLE_NAME');
        $issues = [];
        $generated = [];
        $knownGenerated = self::generatedTables();
        $checked = 0;
        $names = [];
        foreach ($expected as $name => $schema)
        {
            $target = $prefix.substr($name, strlen('aowow_'));
            $names[$target] = true;
            if (!isset($tables[$target]))
            {
                $issues[] = [$target, 'table', 'missing'];
                continue;
            }
            if ($tables[$target]['TABLE_TYPE'] !== 'BASE TABLE')
            {
                $issues[] = [$target, 'table', 'expected a base table'];
                continue;
            }
            // References use the configured application prefix, including non-default/empty prefixes.
            foreach ($schema['foreignKeys'] as &$key)
                if (str_starts_with($key['table'], 'aowow_'))
                    $key['table'] = $prefix.substr($key['table'], strlen('aowow_'));
            unset($key);
            $row = array_values((array)$db->query('SHOW CREATE TABLE %n', $target)->fetch());
            if (!isset($row[1]) || !is_string($row[1]))
                throw new \RuntimeException('Cannot read table metadata.');
            try
            {
                $collation = strtolower($tables[$target]['TABLE_COLLATION']);
                $actual = self::parse($row[1], false, ['collation' => $collation, 'charset' => explode('_', $collation)[0]]);
                if (!isset($actual[$target]))
                    throw new \RuntimeException('Unexpected table metadata.');
                foreach (self::differences($schema, $actual[$target]) as [$object, $problem])
                    $issues[] = [$target, $object, $problem];
                ++$checked;
            }
            catch (\RuntimeException)
            {
                // Custom unsupported DDL must fail visibly, never yield a false match or dump SQL.
                $issues[] = [$target, 'table', 'cannot compare unsupported or malformed DDL'];
            }
        }
        foreach ($tables as $name => $_)
            if (str_starts_with($name, $prefix) && !isset($names[$name]))
            {
                $command = substr($name, strlen($prefix));
                if (isset($knownGenerated[$command]) && $tables[$name]['TABLE_TYPE'] === 'BASE TABLE')
                    $generated[$name] = $knownGenerated[$command];
                else
                    $issues[] = [$name, 'table', 'extra (absent from initial schema)'];
            }
        return ['expected' => count($expected), 'checked' => $checked, 'issues' => $issues, 'generated' => $generated];
    }

    /** Discover literal TrDBCcopy declarations without including or constructing generators. */
    public static function generatedTables(string $directory = 'setup/tools/sqlgen', string $formats = 'setup/tools/dbc/12340.ini') : array
    {
        $definitions = parse_ini_file($formats, true, INI_SCANNER_RAW);
        if ($definitions === false) throw new \RuntimeException('Cannot read DBC definitions.');
        $out = [];
        foreach (glob($directory.'/*.ss.php') as $file)
        {
            $source = file_get_contents($file);
            if ($source === false) throw new \RuntimeException('Cannot read generator declaration.');
            $tokens = array_values(array_filter(token_get_all($source, TOKEN_PARSE), fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
            $copy = false; $command = ''; $dbc = ''; $depth = 0; $classes = []; $pendingClass = false;
            foreach ($tokens as $i => $token)
            {
                if ($token === '{')
                {
                    ++$depth;
                    if ($pendingClass) { $classes[] = $depth; $pendingClass = false; }
                }
                else if ($token === '}')
                {
                    if (end($classes) === $depth) array_pop($classes);
                    --$depth;
                }
                if (!is_array($token)) continue;
                if ($token[0] === T_CLASS && ($tokens[$i - 1][0] ?? null) !== T_DOUBLE_COLON) $pendingClass = true;
                if (!in_array($depth, $classes, true)) continue;
                if ($token[0] === T_USE && ($tokens[$i + 1][0] ?? null) === T_STRING &&
                    $tokens[$i + 1][1] === 'TrDBCcopy' && ($tokens[$i + 2] ?? '') === ';') $copy = true;
                if ($token[0] !== T_VARIABLE || ($tokens[$i + 1] ?? '') !== '=') continue;
                if ($token[1] === '$command' && ($tokens[$i + 2][0] ?? null) === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i + 3] ?? '') === ';')
                    $command = self::tokens($tokens[$i + 2][1])[0][1];
                if ($token[1] === '$dbcSourceFiles' && ($tokens[$i + 2] ?? '') === '[' &&
                    ($tokens[$i + 3][0] ?? null) === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i + 4] ?? '') === ']' && ($tokens[$i + 5] ?? '') === ';')
                    $dbc = self::tokens($tokens[$i + 3][1])[0][1];
            }
            if (!$copy || !preg_match('/^[a-z][a-z0-9_]*$/D', $command) || !isset($definitions[$dbc])) continue;
            if (isset($out[$command])) throw new \RuntimeException('Duplicate DBC-copy declaration.');
            $out[$command] = $dbc;
        }
        return $out;
    }

    /** Parse only structure; DROP/SET/INSERT and dump comments are never run. */
    public static function parse(string $sql, bool $initial = true, array $defaults = []) : array
    {
        $tokens = self::tokens($sql);
        $tables = [];
        for ($i = 0, $n = count($tokens); $i < $n; ++$i)
        {
            if ($tokens[$i] !== ['word', 'CREATE'] || ($tokens[$i + 1] ?? null) !== ['word', 'TABLE'])
                continue;
            $i += 2;
            if (($tokens[$i] ?? null) === ['word', 'IF']) $i += 3;
            $name = self::identifier($tokens[$i++] ?? []);
            if ($initial && !str_starts_with($name, 'aowow_'))
                throw new \RuntimeException('Unexpected reference table prefix.');
            if (isset($tables[$name]))
                throw new \RuntimeException('Duplicate reference table.');
            $body = self::group($tokens, $i);
            $options = [];
            while ($i < $n && $tokens[$i][1] !== ';') $options[] = $tokens[$i++];
            $schema = ['engine' => self::option($options, 'ENGINE'),
                'charset' => self::charset(self::option($options, 'CHARSET') ?: ($defaults['charset'] ?? '')),
                'collation' => self::charset(self::option($options, 'COLLATE') ?: ($defaults['collation'] ?? '')),
                'columns' => [], 'indexes' => [], 'foreignKeys' => []];
            if (!$schema['engine'])
                throw new \RuntimeException('Incomplete table options.');
            foreach (self::parts($body) as $part)
            {
                if ($part[0][0] === 'id')
                {
                    $column = array_shift($part)[1];
                    if (isset($schema['columns'][$column])) throw new \RuntimeException('Duplicate column.');
                    $schema['columns'][$column] = self::column($part, $schema);
                }
                else if (in_array($part[0][1], ['PRIMARY', 'UNIQUE', 'FULLTEXT', 'SPATIAL', 'KEY', 'INDEX'], true))
                {
                    [$key, $definition] = self::index($part);
                    if (isset($schema['indexes'][$key])) throw new \RuntimeException('Duplicate index.');
                    $schema['indexes'][$key] = $definition;
                }
                else if ($part[0][1] === 'CONSTRAINT' && ($part[2][1] ?? '') === 'FOREIGN')
                {
                    [$key, $definition] = self::foreignKey($part);
                    if (isset($schema['foreignKeys'][$key])) throw new \RuntimeException('Duplicate foreign key.');
                    $schema['foreignKeys'][$key] = $definition;
                }
                else
                    throw new \RuntimeException('Unsupported table definition.');
            }
            $tables[$name] = $schema;
        }
        if (!$tables) throw new \RuntimeException('No reference tables found.');
        return $tables;
    }

    public static function differences(array $expected, array $actual) : array
    {
        $out = [];
        foreach (['engine', 'charset', 'collation'] as $field)
            if ($expected[$field] !== $actual[$field]) $out[] = ['table', $field.' differs'];
        foreach (['columns' => 'column', 'indexes' => 'index', 'foreignKeys' => 'foreign key'] as $kind => $label)
        {
            foreach ($expected[$kind] as $name => $definition)
            {
                if (!isset($actual[$kind][$name])) $out[] = [$label.' '.$name, 'missing'];
                else foreach ($definition as $attribute => $value)
                    if ($value !== $actual[$kind][$name][$attribute])
                        $out[] = [$label.' '.$name, $attribute.' differs'];
            }
            foreach (array_diff_key($actual[$kind], $expected[$kind]) as $name => $_)
                $out[] = [$label.' '.$name, 'extra'];
        }
        if (array_keys($expected['columns']) !== array_keys($actual['columns']) &&
            !array_diff_key($expected['columns'], $actual['columns']) && !array_diff_key($actual['columns'], $expected['columns']))
            $out[] = ['columns', 'order differs'];
        return $out;
    }

    /** Quote-aware tokens preserve literal commas, parentheses, escapes and case. */
    private static function tokens(string $sql) : array
    {
        $out = [];
        for ($i = 0, $n = strlen($sql); $i < $n;)
        {
            $c = $sql[$i];
            if (ctype_space($c)) { ++$i; continue; }
            if ($c === '#' || ($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 === $n || ord($sql[$i + 2]) <= 32)))
            {
                $i = strpos($sql, "\n", $i) ?: $n;
                continue;
            }
            if (substr($sql, $i, 2) === '/*')
            {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) throw new \RuntimeException('Unterminated DDL comment.');
                if (substr($sql, $i, 3) === '/*!')
                    array_push($out, ...self::tokens(preg_replace('/^\d*\s*/', '', substr($sql, $i + 3, $end - $i - 3))));
                $i = $end + 2;
                continue;
            }
            if ($c === '`' || $c === "'" || $c === '"')
            {
                $quote = $c; $value = ''; $closed = false; ++$i;
                while ($i < $n)
                {
                    $c = $sql[$i++];
                    if ($c === $quote)
                    {
                        if ($i < $n && $sql[$i] === $quote) { $value .= $quote; ++$i; }
                        else { $closed = true; break; }
                    }
                    else if ($c === '\\' && $quote !== '`' && $i < $n)
                    {
                        $c = $sql[$i++];
                        $value .= match ($c) { '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a", default => $c };
                    }
                    else $value .= $c;
                }
                if (!$closed) throw new \RuntimeException('Unterminated DDL quote.');
                $out[] = [$quote === '`' ? 'id' : 'string', $value];
            }
            else if (preg_match('/\G(?:\d+(?:\.\d+)?|[a-zA-Z_$][a-zA-Z0-9_$]*)/', $sql, $m, 0, $i))
            {
                $out[] = ['word', strtoupper($m[0])]; $i += strlen($m[0]);
            }
            else { $out[] = ['symbol', $c]; ++$i; }
        }
        return $out;
    }

    private static function identifier(array $token) : string
    {
        if (($token[0] ?? '') !== 'id') throw new \RuntimeException('Expected quoted DDL identifier.');
        return $token[1];
    }

    private static function group(array $tokens, int &$i) : array
    {
        if (($tokens[$i++] ?? null) !== ['symbol', '(']) throw new \RuntimeException('Expected DDL group.');
        $out = []; $depth = 1;
        while ($i < count($tokens))
        {
            $t = $tokens[$i++];
            if ($t === ['symbol', '(']) ++$depth;
            if ($t === ['symbol', ')'] && --$depth === 0) return $out;
            $out[] = $t;
        }
        throw new \RuntimeException('Unterminated DDL group.');
    }

    private static function parts(array $tokens) : array
    {
        $out = []; $part = []; $depth = 0;
        foreach ($tokens as $t)
        {
            if ($t === ['symbol', '(']) ++$depth;
            if ($t === ['symbol', ')']) --$depth;
            if ($depth < 0) throw new \RuntimeException('Unbalanced DDL group.');
            if ($t === ['symbol', ','] && !$depth) { $out[] = $part; $part = []; }
            else $part[] = $t;
        }
        if ($depth || !$part) throw new \RuntimeException('Incomplete DDL definition.');
        $out[] = $part;
        return $out;
    }

    private static function option(array $tokens, string $name) : string
    {
        foreach ($tokens as $i => $t)
            if ($t === ['word', $name])
                return strtolower($tokens[$i + (($tokens[$i + 1][1] ?? '') === '=' ? 2 : 1)][1] ?? '');
        return '';
    }

    private static function charset(string $value) : string
    {
        return preg_replace('/^utf8(?=_|$)/', 'utf8mb3', strtolower($value));
    }

    private static function column(array $tokens, array $table) : array
    {
        $i = 0; $type = strtolower($tokens[$i++][1]); $size = [];
        if (($tokens[$i] ?? null) === ['symbol', '(']) $size = self::group($tokens, $i);
        $numeric = in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal'], true);
        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) $size = [];
        $out = ['type' => [$type, $size], 'unsigned' => false, 'nullable' => true, 'default' => null,
            'auto increment' => false, 'charset' => null, 'collation' => null, 'generated' => null];
        if (in_array($type, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum', 'set'], true))
        {
            $out['charset'] = $table['charset']; $out['collation'] = $table['collation'];
        }
        $hasDefault = false;
        while ($i < count($tokens))
        {
            $word = $tokens[$i++][1];
            switch ($word)
            {
                case 'UNSIGNED': $out['unsigned'] = true; break;
                case 'NOT':
                    if (($tokens[$i++][1] ?? '') !== 'NULL') throw new \RuntimeException('Unsupported column attribute.');
                    $out['nullable'] = false; break;
                case 'NULL': break;
                case 'AUTO_INCREMENT': $out['auto increment'] = true; break;
                case 'COMMENT': ++$i; break;
                case 'CHARACTER':
                    if (($tokens[$i++][1] ?? '') !== 'SET') throw new \RuntimeException('Unsupported column character set.');
                    $out['charset'] = self::charset($tokens[$i++][1] ?? ''); break;
                case 'COLLATE': $out['collation'] = self::charset($tokens[$i++][1] ?? ''); break;
                case 'GENERATED':
                    if (($tokens[$i][1] ?? '') === 'ALWAYS') ++$i;
                    if (($tokens[$i++][1] ?? '') !== 'AS') throw new \RuntimeException('Unsupported generated column.');
                    $expression = self::group($tokens, $i);
                    while (($expression[0] ?? null) === ['symbol', '('])
                    {
                        $j = 0; $inner = self::group($expression, $j);
                        if ($j !== count($expression)) break;
                        $expression = $inner;
                    }
                    $storage = $tokens[$i++][1] ?? '';
                    if (!in_array($storage, ['STORED', 'PERSISTENT', 'VIRTUAL'], true)) throw new \RuntimeException('Unsupported generated column storage.');
                    $out['generated'] = [$expression, $storage === 'PERSISTENT' ? 'STORED' : $storage];
                    break;
                case 'DEFAULT':
                    $value = ($tokens[$i] ?? null) === ['symbol', '('] ? self::group($tokens, $i) : [$tokens[$i++] ?? []];
                    if ($value === [['symbol', '-']]) $value[] = $tokens[$i++] ?? [];
                    if (count($value) === 1 && $value[0][0] === 'word' && str_starts_with($value[0][1], '_') && ($tokens[$i][0] ?? '') === 'string')
                        $value[] = $tokens[$i++];
                    if (count($value) === 2 && $value[0][0] === 'word' && str_starts_with($value[0][1], '_') && $value[1][0] === 'string')
                    {
                        if (self::charset(substr($value[0][1], 1)) !== $out['charset']) throw new \RuntimeException('Unsupported default character set.');
                        $value = [$value[1]];
                    }
                    if (!$value || count($value) > 2) throw new \RuntimeException('Unsupported column default.');
                    $hasDefault = true;
                    if ($value === [['word', 'NULL']]) $out['default'] = null;
                    else if ($numeric)
                    {
                        $number = implode('', array_column($value, 1));
                        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $number)) throw new \RuntimeException('Unsupported numeric default.');
                        $out['default'] = rtrim(rtrim(str_contains($number, '.') ? $number : $number.'.', '0'), '.');
                        if ($out['default'] === '' || $out['default'] === '-') $out['default'] = '0';
                    }
                    else $out['default'] = $value;
                    break;
                default: throw new \RuntimeException('Unsupported column attribute.');
            }
        }
        if (!$hasDefault && !$out['nullable']) $out['default'] = ['absent'];
        return $out;
    }

    private static function index(array $tokens) : array
    {
        $i = 0; $kind = $tokens[$i++][1];
        $primary = $kind === 'PRIMARY';
        if (in_array($kind, ['PRIMARY', 'UNIQUE', 'FULLTEXT', 'SPATIAL'], true))
            if (!in_array($tokens[$i++][1] ?? '', ['KEY', 'INDEX'], true)) throw new \RuntimeException('Unsupported index.');
        $name = $primary ? 'PRIMARY' : self::identifier($tokens[$i++] ?? []);
        $out = ['unique' => $primary || $kind === 'UNIQUE', 'type' => in_array($kind, ['FULLTEXT', 'SPATIAL'], true) ? $kind : 'BTREE',
            'columns' => self::keyColumns(self::group($tokens, $i)), 'visible' => true];
        while ($i < count($tokens))
        {
            $word = $tokens[$i++][1];
            if ($word === 'USING') $out['type'] = $tokens[$i++][1] ?? '';
            else if ($word === 'VISIBLE') $out['visible'] = true;
            else if ($word === 'INVISIBLE' || $word === 'IGNORED') $out['visible'] = false;
            else if ($word === 'COMMENT') ++$i;
            else throw new \RuntimeException('Unsupported index attribute.');
        }
        return [$name, $out];
    }

    private static function keyColumns(array $tokens) : array
    {
        $out = [];
        foreach (self::parts($tokens) as $part)
        {
            $i = 0; $name = self::identifier($part[$i++] ?? []); $length = null; $order = 'ASC';
            if (($part[$i] ?? null) === ['symbol', '('])
            {
                $size = self::group($part, $i);
                if (count($size) !== 1 || !ctype_digit($size[0][1])) throw new \RuntimeException('Unsupported index prefix.');
                $length = (int)$size[0][1];
            }
            if (isset($part[$i])) $order = $part[$i++][1];
            if ($i !== count($part) || !in_array($order, ['ASC', 'DESC'], true)) throw new \RuntimeException('Unsupported index column.');
            $out[] = [$name, $length, $order];
        }
        return $out;
    }

    private static function foreignKey(array $tokens) : array
    {
        $name = self::identifier($tokens[1]); $i = 4;
        $columns = self::keyColumns(self::group($tokens, $i));
        if (($tokens[$i++][1] ?? '') !== 'REFERENCES') throw new \RuntimeException('Unsupported foreign key.');
        $table = self::identifier($tokens[$i++] ?? []);
        $out = ['columns' => $columns, 'table' => $table, 'references' => self::keyColumns(self::group($tokens, $i)),
            'delete rule' => 'RESTRICT', 'update rule' => 'RESTRICT'];
        while ($i < count($tokens))
        {
            if (($tokens[$i++][1] ?? '') !== 'ON') throw new \RuntimeException('Unsupported foreign key attribute.');
            $event = $tokens[$i++][1] ?? ''; $rule = $tokens[$i++][1] ?? '';
            if ($rule === 'SET' || $rule === 'NO') $rule .= ' '.($tokens[$i++][1] ?? '');
            if (!in_array($event, ['DELETE', 'UPDATE'], true) || !in_array($rule, ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'], true))
                throw new \RuntimeException('Unsupported foreign key rule.');
            $out[strtolower($event).' rule'] = $rule === 'NO ACTION' ? 'RESTRICT' : $rule;
        }
        return [$name, $out];
    }
}
