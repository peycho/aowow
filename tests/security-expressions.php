<?php

// Real parsers/config reset/spell/filter methods with synthetic DB and localization fixtures.
namespace Aowow {
    class DB {
        public static array $writes = [];
        public static function Aowow() : self { return new self; }
        public function qry(string $sql, mixed ...$args) : bool { self::$writes[] = [$sql, $args]; return true; }
    }
    class Lang {
        public static function main(mixed ...$args) : string { return 'Name'; }
        public static function game(mixed ...$args) : string { return 'Level'; }
        public static function spell(mixed ...$args) : string { return strtoupper((string)end($args)); }
        public static function item(mixed ...$args) : string { return 'Armor'; }
        public static function formatTime(mixed ...$args) : string { return '%s seconds'; }
    }
}

namespace {
    use Aowow\NumericExpression as Expression;
    define('AOWOW_REVISION', 65);
    define('CLI', true);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/type.class.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/cfg.class.php';
    require __DIR__.'/../includes/components/numericexpression.class.php';
    require __DIR__.'/../includes/components/dbtypelist.class.php';
    require __DIR__.'/../includes/components/filter.class.php';
    require __DIR__.'/../includes/dbtypes/spell.class.php';

    $checks = 0;
    function check(bool $ok, string $label) : void {
        global $checks;
        ++$checks;
        if (!$ok) throw new RuntimeException($label);
    }
    function rejected(string $formula, bool $functions = false) : bool {
        try { Expression::evaluate($formula, $functions); return false; }
        catch (InvalidArgumentException|RangeException) { return true; }
    }

    $vectors = [
        ['0', 0], ['-200', -200], ['15 * 60', 900], ['30*24*60*60', 2592000], ['0x15D', 349],
        ['0b101 | 0x10', 21], ['015', 13], ['.5 + 1.5', 2.0], ['1e2/4', 25.0],
        ['2+3*4', 14], ['(2+3)*4', 20], ['7%4', 3], ['2**3**2', 512], ['-2**2', -4],
        ['2*-3', -6], ['8 / -2', -4], ['1+-2', -1], ['~0 & 0xff', 255], ['1 << 3', 8],
        ['16 >> 2', 4], ['2^3', 1], ['1+2<<1', 6], ['2>1', true], ['2>=2', true],
        ['2<1', false], ['2<=2', true], ['2==2.0', true], ['2===2.0', false], ['2!==2.0', true],
        ['2!=1', true], ['!0', true], ['true xor true', false], ['true and false or true', true],
        ['0 && 1/0', false], ['1 || 1/0', true], ['0 ? 1/0 : 5', 5], ['7 ?: 3', 7],
        ['$abs(-4)', 4], ['$ceil(1.2)', 2.0], ['$FLOOR(1.9)', 1.0], ['$min(1,2)', 1],
        ['$MAX(1,2)', 2], ['$gt(2,1)', true], ['$lt(2,1)', false], ['$gte(2,2)', true],
        ['$lte(1,2)', true], ['$eq(1,1)', true], ['$cond(0,4,5)', 5], ['$clamp(9,1,5)', 5],
        ['$clamp(-2,1,5)', 1], ['$clamp(3,1,5)', 3], ['$max($abs(-3),2)*2', 6]
    ];
    foreach ($vectors as [$formula, $expected])
        check(Expression::evaluate($formula, true) == $expected && get_debug_type(Expression::evaluate($formula, true)) === get_debug_type($expected), 'numeric vector '.$formula);

    foreach (['', '08', '1;phpinfo()', 'system("id")', '`id`', '${x}', '$unknown(1)',
        '$abs(1,2)', '$min(1)', '$max(1,2,3)', '$cond(1,2)', '$clamp(1,2,3,4)',
        '$x=1', '1..2', 'new stdClass', '(int)1', '[1]', '1 . 2', 'INF', 'NAN',
        '1/0', '1%0', '1%0.5', '1<<-1', '1e999', '10**999', str_repeat('(', 70).'1'.str_repeat(')', 70),
        str_repeat('1+', 520).'1', str_repeat(' ', 8193).'1'] as $formula)
        check(rejected($formula, true), 'rejected grammar/resource case');
    check(rejected('$abs(1)'), 'config grammar cannot invoke spell functions');
    check(Expression::operation('*', '2.5', '4') === 10.0, 'numeric DB field arithmetic');
    try { Expression::operation('+', '1;phpinfo()', 1); check(false, 'reject DB code operand'); }
    catch (InvalidArgumentException) { check(true, 'reject DB code operand'); }

    // Exercise every shipped non-string numeric default, including disabled flags and duration arithmetic.
    $sql = file_get_contents(__DIR__.'/../setup/sql/02-db_initial_data.sql');
    $rows = explode("\n", explode('INSERT INTO `aowow_config`', $sql, 2)[1], 2)[0];
    preg_match_all("~\\('((?:[^'\\\\]|\\\\.)*)','((?:[^'\\\\]|\\\\.)*)',(NULL|'(?:[^'\\\\]|\\\\.)*'),([0-9]+),([0-9]+),'((?:[^'\\\\]|\\\\.)*)'\\)~", $rows, $matches, PREG_SET_ORDER);
    $defaults = 0;
    foreach ($matches as $m)
        if (!((int)$m[5] & Aowow\Cfg::FLAG_TYPE_STRING) && $m[3] !== 'NULL') {
            $default = substr($m[3], 1, -1);
            check(is_numeric(Expression::evaluate($default)), 'shipped numeric default '.$m[1]);
            ++$defaults;
        }
    check($defaults === 48, 'all shipped numeric defaults inventoried');

    $store = new ReflectionProperty(Aowow\Cfg::class, 'store');
    (new ReflectionProperty(Aowow\Cfg::class, 'isLoaded'))->setValue(null, true);
    foreach ([['15*60', 900], ['0', 0], ['0x15D', 349], ['-50', -50]] as [$default, $expected]) {
        $store->setValue(null, ['fixture' => [9, 129, 0, $default, 'fixture']]);
        Aowow\DB::$writes = [];
        check(Aowow\Cfg::reset('fixture') === '', 'actual reset accepts numeric default');
        check(Aowow\DB::$writes[0][1] === [$expected, 'fixture'], 'reset writes typed numeric value');
    }
    foreach (['file_put_contents("marker","EXECUTED")', '0;phpinfo()', '1/0', null] as $default) {
        $store->setValue(null, ['fixture' => [9, 129, 0, $default, 'fixture']]);
        Aowow\DB::$writes = [];
        check(Aowow\Cfg::reset('fixture') !== '' && !Aowow\DB::$writes, 'invalid reset has no writes');
    }
    $store->setValue(null, ['fixture' => ['old', 136, 0, '', 'fixture']]);
    check(Aowow\Cfg::reset('fixture') === '' && Aowow\DB::$writes[0][1] === ['', 'fixture'], 'empty string default remains valid');
    $store->setValue(null, ['fixture' => [9, 129, 0, '5', 'fixture']]);

    $spell = (new ReflectionClass(Aowow\SpellList::class))->newInstanceWithoutConstructor();
    $evaluate = new ReflectionMethod(Aowow\SpellList::class, 'resolveEvaluation');
    $modifier = new ReflectionMethod(Aowow\SpellList::class, 'modifyFormulaValue');
    foreach ($vectors as [$formula, $expected])
        check($evaluate->invoke($spell, $formula) === (string)$expected, 'actual spell formula '.$formula);
    $ap = '<dfn title="LANG.traits.atkpwr[0]" class="w">ATKPWR</dfn>';
    check($evaluate->invoke($spell, '$AP*2') === '('.$ap.' * 2)', 'character stat tooltip preserved');
    check(str_contains($evaluate->invoke($spell, '$max($AP,2)'), 'MAX</dfn>('.$ap.',2)'), 'symbolic function and stat labels preserved');
    check($evaluate->invoke($spell, '$UNKNOWN+1') === '(&lt;UNK: $UNKNOWN&gt; + 1)', 'unknown stat is readable text');
    check($evaluate->invoke($spell, $ap.'+$SP') === '('.$ap.' + <dfn title="LANG.traits.splpwr[0]" class="w">SPLPWR</dfn>)', 'nested generated labels remain markup');
    check($evaluate->invoke($spell, '<img src=x onerror=alert(1)>$formula') === '(&lt;img src=x onerror=alert(1)&gt;&lt;UNK: $formula&gt;)', 'internal PHP locals cannot be interpolated');
    foreach (['file_put_contents("marker","EXECUTED")', '$AP");file_put_contents("marker","EXECUTED");//',
        '$system("id")', '${file_put_contents("marker","EXECUTED")}', '$AP <script>alert(1)</script>'] as $formula) {
        $result = $evaluate->invoke($spell, $formula);
        check(!str_contains($result, '<script>') && !is_file('marker'), 'spell code is displayed without execution');
    }
    foreach (['+', '-', '*', '/', '%', '^'] as $op)
        check($modifier->invoke(null, 8, $op, 2) === Expression::operation($op, 8, 2), 'spell modifier '.$op);
    check($modifier->invoke(null, 8, '/', 0) === 8, 'invalid divisor keeps display value');
    check($modifier->invoke(null, '0;phpinfo()', '+', 1) === '0;phpinfo()', 'DB modifier does not execute text');
    (new ReflectionProperty(Aowow\SpellList::class, 'interactive'))->setValue($spell, 0);
    check($evaluate->invoke($spell, '$AP*2') === '(ATKPWR * 2)', 'noninteractive tooltip labels preserved');

    // Scaling modifiers must affect the complete value exactly once on PHP and JS.
    $amount = new ReflectionMethod(Aowow\SpellList::class, 'calculateAmountForCurrent');
    $resolve = new ReflectionMethod(Aowow\SpellList::class, 'resolveVariableString');
    $rowProperty = new ReflectionProperty(Aowow\DBTypeList::class, 'curTpl');
    $levelProperty = new ReflectionProperty(Aowow\SpellList::class, 'charLevel');
    $spell->error = false;
    $spell->id = 1;
    $row = ['effect1BasePoints'=>100, 'effect1DieSides'=>0, 'effect1RealPointsPerLevel'=>2,
        'baseLevel'=>1, 'maxLevel'=>80, 'spellLevel'=>1, 'attributes0'=>SPELL_ATTR0_PASSIVE,
        'duration'=>9000, 'effect1Periode'=>3000];
    foreach ([1, 2, 3] as $index)
        foreach (['Id','AuraId','MiscValue'] as $field) $row['effect'.$index.$field] = 0;
    $rowProperty->setValue($spell, $row);
    $levelProperty->setValue($spell, 11);
    (new ReflectionProperty(Aowow\SpellList::class, 'interactive'))->setValue($spell, Aowow\SpellList::INTERACTIVE_FULL);
    $scalingFixtures = [];
    foreach ([[null,null,120], ['+','5',125], ['-','5',115], ['*','3',360], ['/','3',40],
        ['*','0',0], ['/','0',120], ['+','0',120], [';','5',120], ['+','1;phpinfo()',120],
        ['*',str_repeat('9',400),120]] as [$op,$operand,$expected]) {
        $result = $amount->invoke($spell, 1, $op, $operand);
        check($result[0] == $expected && $result[1] == $expected, 'complete spell amount modifier '.($op??'none'));
        $scalingFixtures[] = ['tooltip'=>$result[2].abs($result[0]).'<!--?1:1:80:11-->', 'level'=>11, 'expected'=>$expected];
    }
    foreach (['m','M','s','S','o','O'] as $variable) {
        $result = $resolve->invoke($spell, '+', '5', null, $variable, 1, null);
        check($result[0] == (strtolower($variable)==='o' ? 365 : 125), 'real '.$variable.' caller applies modifier once');
    }
    foreach ([1,3] as $ticks) {
        $result = $amount->invoke($spell, 1, '+', '5', $ticks);
        check($result[0] === 120*$ticks+5, 'periodic total adds constant once');
        $scalingFixtures[] = ['tooltip'=>$result[2].$result[0].'<!--?1:1:80:11-->', 'level'=>21, 'expected'=>140*$ticks+5];
    }
    $row['effect1RealPointsPerLevel'] = 0.125;
    $rowProperty->setValue($spell,$row); $levelProperty->setValue($spell,9);
    $result=$amount->invoke($spell,1,'*','3');
    check($result[0]===303, 'fractional per-level growth keeps its precision');
    $scalingFixtures[]=['tooltip'=>$result[2].$result[0].'<!--?1:1:80:9-->', 'level'=>17, 'expected'=>306];
    $row['effect1RealPointsPerLevel']=0;
    $row['effect1Id']=SPELL_EFFECT_SCHOOL_DAMAGE;
    $row['attributes0']=SPELL_ATTR0_LEVEL_DAMAGE_CALCULATION;
    $rowProperty->setValue($spell,$row);
    $result=$amount->invoke($spell,1,'+','5');
    check($result[0]===105 && $result[2]==='<!--pts1:100:+:5-->', 'NPC scaling retains raw base and validated modifier');
    $scalingFixtures[]=['tooltip'=>$result[2].$result[0].'<!--?1:1:80:1:1:1-->', 'level'=>2, 'expected'=>205];
    // Display rounding cannot change operands used by nested formulas.
    $row['attributes0']=SPELL_ATTR0_PASSIVE;
    $row['effect1Id']=0; $row['effect1AuraId']=0;
    $row['effect1BasePoints']=101; $row['effect1DieSides']=0;
    $row['effect1ValueMultiplier']=1.25; $row['effect1DamageMultiplier']=0.75;
    $row['effect1Periode']=2500;
    $rowProperty->setValue($spell,$row);
    $variables = new ReflectionMethod(Aowow\SpellList::class,'handleVariables');
    $formula = new ReflectionMethod(Aowow\SpellList::class,'resolveFormulaString');
    foreach ([['$e1','1.3'], ['$f1','0.8'], ['$/3;s1','33'], ['$/3;S1','33.7']] as [$text,$expected])
        check($variables->invoke($spell,$text) === $expected, 'Variable display precision: '.$text);
    check($formula->invoke($spell,'$e1*4',2)[0] == 5, 'Formula preserves fractional multiplier before final precision');
    check($formula->invoke($spell,'$t1*2',2)[0] == 5, 'Formula preserves fractional tick interval');
    foreach ([['s',33],['S',33.7]] as [$variable,$expected]) {
        $row['effect1RealPointsPerLevel']=0.125;
        $rowProperty->setValue($spell,$row); $levelProperty->setValue($spell,1);
        $tooltip=$variables->invoke($spell,'$/3;'.$variable.'1').'<!--?1:1:80:1-->';
        $scalingFixtures[]=['tooltip'=>$tooltip,'level'=>1,'expected'=>$expected];
    }
    $row['effect1RealPointsPerLevel']=0;
    $row['effect1Periode']=0; $row['duration']=15000;
    foreach ([SPELL_AURA_MOD_REGEN=>300, SPELL_AURA_MOD_POWER_REGEN=>300, SPELL_AURA_PERIODIC_DAMAGE=>500] as $aura=>$expected) {
        $row['effect1AuraId']=$aura; $row['effect1BasePoints']=100;
        $rowProperty->setValue($spell,$row);
        check($resolve->invoke($spell,null,null,null,'o',1,null)[0] == $expected, 'Zero-period fallback remains aura-specific');
    }
    $castRow=['attributes0'=>SPELL_ATTR0_PASSIVE,'attributes1'=>0,'castTime'=>0,'damageClass'=>SPELL_DAMAGE_CLASS_MAGIC];
    $rowProperty->setValue($spell,$castRow);
    check($spell->createCastTimeForCurrent()==='', 'passive talents omit cast time');
    $castRow['attributes0']=0; $castRow['damageClass']=SPELL_DAMAGE_CLASS_MELEE;
    $rowProperty->setValue($spell,$castRow);
    check($spell->createCastTimeForCurrent()==='INSTANTPHYS','instant physical abilities retain their label');
    $castRow['attributes1']=SPELL_ATTR1_CHANNELED_1;
    $rowProperty->setValue($spell,$castRow);
    check($spell->createCastTimeForCurrent()==='CHANNELED','channeled abilities retain their label');

    if (in_array('--scaling-fixtures',$argv,true)) {
        echo json_encode($scalingFixtures,JSON_THROW_ON_ERROR); exit;
    }

    class FilterFixture extends Aowow\Filter {
        protected static array $genericFilter = [1 => [self::CR_NYI_PH, null]];
        protected function createSQLForValues() : array { return []; }
        public function compare(int $operator, string $value) : array { return $this->createSQLForCriterium(1, $operator, $value); }
    }
    $filter = (new ReflectionClass(FilterFixture::class))->newInstanceWithoutConstructor();
    foreach ([1 => '>', 2 => '>=', 3 => '==', 4 => '<=', 5 => '<', 6 => '!='] as $code => $op)
        foreach (['-2', '0', '1.5', '1e2', '0x10'] as $value) {
            $numeric = $value;
            Aowow\Util::checkNumeric($numeric);
            check($filter->compare($code, $value) === [Expression::operation($op, $numeric, 0) ? 1 : 0], 'actual filter comparison');
        }
    check($filter->compare(99, '2') === [0], 'unknown operator remains false');
    check($filter->compare(1, '1;phpinfo()') === [0], 'filter still rejects nonnumeric text');
    echo "PASS: $checks expression/config/spell/filter checks\n";
}
