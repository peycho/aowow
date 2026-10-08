<?php
// Real SQL in one explicitly named disposable database. No application configuration or live accounts.
namespace Aowow {
    class User {
        public static int $id = 7, $groups = 0;
        public static bool $banned = false;
        public static string $ip = '127.0.0.1', $agent = 'synthetic-report-agent';
        public static function isLoggedIn() : bool { return self::$id > 0; }
        public static function isBanned() : bool { return self::$banned; }
        public static function isInGroup(int $mask) : bool { return (bool)(self::$groups & $mask); }
    }
    class Cfg {
        public static bool $feedback = true, $profiler = true;
        public static function get(string $key) : mixed {
            return match ($key) { 'FEEDBACK_ENABLE'=>self::$feedback, 'PROFILER_ENABLE'=>self::$profiler, default=>0 };
        }
    }
    class Lang { public static function main(string $key) : string { return $key; } }
    class Turnstile {
        public static int $calls = 0;
        public static bool $valid = true;
        public static function verify(string $action, mixed $token) : bool { ++self::$calls; return self::$valid; }
    }
}
namespace {
    use Aowow\{Cfg, DB, DibiConnection, ContributionBudget, Report, User, Turnstile};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_reports') {
        fwrite(STDERR, "Use only the disposable aowow_security_test_reports database; see tests/README.md.\n"); exit(1);
    }
    define('AOWOW_REVISION', 70); define('CLI', true); define('CLI_HAS_E', false); define('OS_WIN', false);
    $root = dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/libs/autoload.php';
    foreach (['database.php', 'utilities.php', 'components/contributionbudget.class.php', 'components/report.class.php',
              'components/guidemgr.class.php', 'components/communitycontent.class.php', 'components/response/baseresponse.class.php', 'components/response/textresponse.class.php'] as $file)
        require $root.'/includes/'.$file;
    require $root.'/endpoints/contactus/contactus.php';
    require $root.'/endpoints/comment/out-of-date.php';
    require $root.'/endpoints/comment/flag-reply.php';
    $db = new DibiConnection(['driver'=>'mysqli', 'host'=>getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1',
        'port'=>(int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306), 'username'=>'root', 'password'=>'',
        'database'=>'aowow_security_test_reports', 'charset'=>'utf8mb4', 'substitutes'=>[''=>'aowow_']]);
    $db->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
    (new ReflectionProperty(DB::class, 'interfaceCache'))->setValue(null, [DB_AOWOW=>$db]);
    (new ReflectionProperty(DB::class, 'interfaceTimes'))->setValue(null, [DB_AOWOW=>[time()+86400, 86400]]);

    function submit(int $mode, ?int $id, int $reason = 45, array $metadata = []) : array {
        $report = new Report($mode, $reason, $id);
        $ok = $report->create(...array_replace(['desc'=>'Synthetic report', 'userAgent'=>'fixture', 'appName'=>'fixture',
            'pageUrl'=>'https://example.test/?item=1', 'relUrl'=>null, 'email'=>null], $metadata));
        return [$ok, $report->getError()];
    }
    if (($argv[1] ?? '') === '--worker') {
        User::$id = (int)$argv[2];
        echo json_encode(submit(Report::MODE_COMMENT, (int)$argv[3], Report::CO_INACCURATE)); exit;
    }
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function countReports() : int { global $db; return (int)$db->query('SELECT COUNT(*) FROM ::reports WHERE id <> 500')->fetchSingle(); }
    function used(int $owner, string $bucket = 'report') : int {
        global $db; return (int)$db->query('SELECT used FROM ::contribution_budget WHERE owner=%i AND bucket=%s', $owner, $bucket)->fetchSingle();
    }
    function resetFixture() : void {
        global $db;
        $db->onEvent = [];
        $db->query('DELETE FROM ::reports WHERE id <> 500');
        $db->query('DELETE FROM ::contribution_budget');
        User::$id = 7; User::$groups = 0; User::$banned = false; User::$ip = '127.0.0.1';
        Cfg::$feedback = Cfg::$profiler = true; Turnstile::$valid = true;
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $table) $db->query('DROP TABLE %n', $table);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    foreach (['account', 'contribution_budget', 'reports', 'comments', 'screenshots', 'videos', 'guides'] as $table) {
        preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`aowow_'.preg_quote($table, '/').'` \(.*?\) ENGINE=.*?;/s',
            file_get_contents($root.'/setup/sql/01-db_structure.sql'), $match);
        if (!$match) throw new RuntimeException('Missing fixture schema');
        $db->nativeQuery($match[0]);
    }
    // The profile target query needs only this subset; the full application schema has its own parity gate.
    $db->query('CREATE TABLE ::profiler_profiles (id int PRIMARY KEY, realm int, custom int, deleted int) ENGINE=InnoDB');
    foreach ([7, 8, 9, 10, 11] as $id)
        $db->query('INSERT INTO ::account (id,username,passHash,joinDate,userGroups) VALUES (%i,%s,%s,1,%i)',
            $id, 'fixture'.$id, 'not-a-real-password-hash', $id === 10 ? U_GROUP_MODERATOR : 0);
    foreach ([[1,9,0,0], [2,10,U_GROUP_MODERATOR,0], [3,9,0,CC_FLAG_DELETED], [4,7,0,CC_FLAG_DELETED]] as [$id,$owner,$roles,$flags])
        $db->query('INSERT INTO ::comments (id,userId,roles,body,date,flags) VALUES (%i,%i,%i,%s,1,%i)', $id,$owner,$roles,'preserved comment',$flags);
    foreach (['screenshots','videos'] as $table) {
        foreach ([[1,9,CC_FLAG_APPROVED],[2,10,CC_FLAG_APPROVED],[3,9,0],[4,7,0],[5,9,CC_FLAG_APPROVED|CC_FLAG_DELETED]] as [$id,$owner,$flags]) {
            $row=['id'=>$id,'type'=>1,'typeId'=>1,'userIdOwner'=>$owner,'date'=>1,'width'=>10,'height'=>10,'status'=>$flags];
            if ($table === 'videos') $row += ['videoId'=>'fixture','pos'=>0,'url'=>'https://example.test/fixture'];
            $db->query('INSERT INTO %n %v', '::'.$table, $row);
        }
    }
    foreach ([[1,9,3],[2,9,1],[3,9,5],[4,7,1]] as [$id,$owner,$status])
        $db->query('INSERT INTO ::guides (id,userId,status,title) VALUES (%i,%i,%i,%s)', $id,$owner,$status,'preserved guide');
    $db->query('INSERT INTO ::profiler_profiles VALUES (1,1,0,0),(2,1,1,0),(3,1,0,1)');
    $db->query("INSERT INTO ::reports (id,userId,createDate,mode,reason,subject,ip,description,userAgent,appName,url) VALUES (500,9,1,1,16,1,'127.0.0.1','preserved report','fixture','fixture','https://example.test/?item=1')");
    $original = [];
    foreach (['account','comments','screenshots','videos','guides','profiler_profiles'] as $table)
        $original[$table] = $db->query('SELECT * FROM %n ORDER BY id', '::'.$table)->fetchAll();
    $historical = $db->query('SELECT * FROM ::reports WHERE id=500')->fetch();

    $events = 0; $db->onEvent[] = function () use (&$events) { ++$events; };
    foreach (['anonymous','pending','banned'] as $case) {
        User::$id = $case === 'anonymous' ? 0 : 7;
        User::$groups = $case === 'pending' ? U_GROUP_PENDING : 0;
        User::$banned = $case === 'banned';
        foreach ([[1,16],[3,45],[4,60],[5,45],[6,45]] as [$mode,$reason]) {
            $before=$events; check(!submit($mode,1,$reason)[0] && $events===$before, 'Denied identity makes no database/budget/target work: '.$case);
        }
    }
    resetFixture();
    foreach ([[1,16],[3,45],[4,60],[5,45],[6,45]] as [$mode,$reason]) {
        resetFixture(); check(submit($mode,1,$reason)[0], 'Existing visible target accepted for mode '.$mode);
        check(countReports()===1 && used(7)===1 && used(0)===1 && used(7,'bytes-text')>4096, 'Successful report charges durable work and storage');
        check(!submit($mode,999999,$reason)[0] && used(7)===2, 'Nonexistent target rejected and attempt charged');
    }
    resetFixture();
    foreach ([null,0,-1,8388608] as $id) check(!submit(3,$id)[0] && used(7)===0, 'Invalid storage/target ID denied before budget');
    check(!submit(2,1,Report::FO_INACCURATE)[0] && used(7)===0, 'Unsupported forum target never queries nonexistent tables');
    Cfg::$profiler=false; check(!submit(4,1,60)[0] && used(7)===0,'Disabled profiler cannot be reported'); Cfg::$profiler=true;
    check(!submit(4,2,60)[0] && !submit(4,3,60)[0], 'Custom/deleted profile is not a live character target');
    resetFixture();
    check(submit(1,1,Report::CO_SPAM)[0], 'Ordinary comment author can be reported for spam');
    check(!submit(1,2,Report::CO_SPAM)[0], 'Moderator author protection matches dialog');
    check(submit(1,2,Report::CO_INACCURATE)[0], 'Inaccuracy remains reportable for staff content');
    check(submit(3,1,Report::SS_INAPPROPRIATE)[0] && !submit(3,2,Report::SS_INAPPROPRIATE)[0], 'Screenshot reason checks actual author roles');
    foreach ([3,5] as $mode) {
        check(!submit($mode,3)[0] && !submit($mode,5)[0], 'Unpublished/deleted media not exposed to another account');
        check(submit($mode,4)[0], 'Owner can report visible own pending media');
    }
    check(!submit(1,3,16)[0] && submit(1,4,16)[0], 'Deleted comment visibility follows owner policy');
    check(!submit(6,2)[0] && submit(6,3)[0] && submit(6,4)[0], 'Guide draft/archived/owner visibility');
    User::$groups=U_GROUP_MODERATOR;
    check(!submit(3,3)[0], 'Stale moderator session cannot override current account privileges');
    $db->query('UPDATE ::account SET userGroups=%i WHERE id=7',U_GROUP_MODERATOR);
    check(submit(3,3)[0], 'Current moderator can report pending target');
    $db->query('UPDATE ::account SET userGroups=0 WHERE id=7');
    resetFixture();
    foreach ([[U_GROUP_PENDING,0],[0,ACC_STATUS_NEW],[0,ACC_STATUS_DELETED]] as [$groups,$status]) {
        $db->query('UPDATE ::account SET userGroups=%i,status=%i WHERE id=7',$groups,$status);
        check(!submit(3,1)[0], 'Current account state rechecked under lock');
    }
    $db->query('UPDATE ::account SET userGroups=0,status=0 WHERE id=7');
    resetFixture();
    foreach (['userAgent'=>256,'appName'=>33,'pageUrl'=>256,'relUrl'=>256,'email'=>256] as $key=>$size)
        check(!submit(3,1,45,[$key=>str_repeat('a',$size)])[0] && used(7)===0, 'Oversized metadata rejected locally: '.$key);
    check(!submit(3,1,45,['userAgent'=>"bad\nmetadata"])[0] && !submit(3,1,45,['desc'=>str_repeat('界',501)])[0], 'Controls and Unicode description limits enforced');
    check(!submit(3,1,45,['userAgent'=>"\xff"])[0] && !submit(3,1,45,['desc'=>"\xff"])[0] && used(7)===0, 'Malformed UTF-8 rejected without database work');
    check(submit(3,1,45,['desc'=>str_repeat('界',500),'userAgent'=>str_repeat('界',255)])[0], 'Unicode limits match character widths');
    resetFixture();
    check(submit(3,1)[0], 'First content report succeeds');
    check(submit(3,1,46,['pageUrl'=>'https://other.test/?different=1'])===[false,7] && countReports()===1 && used(7)===2, 'Changing reason/URL does not duplicate the content report');
    User::$id=8; check(submit(3,1)[0], 'Another account can independently report the same content');
    resetFixture();
    for ($i=0;$i<25;++$i) check(!submit(3,999999)[0], 'Invalid target still consumes its work allowance');
    check(submit(3,1)===[false,Report::ERR_LIMIT] && countReports()===0 && used(7)===25, 'Account limit denies before report work');
    resetFixture();
    foreach ([7,8] as $id) { User::$id=$id; for($i=0;$i<25;++$i) check(ContributionBudget::reserve('report'), 'Shared peer allowance across accounts'); }
    User::$id=11;
    check(submit(3,1)===[false,Report::ERR_LIMIT] && used(11)===0 && used(0)===50, 'IP limit cannot be evaded by switching accounts');
    resetFixture();
    User::$ip='::1'; check(ContributionBudget::reserve('report'),'IPv6 peer accepted');
    User::$ip='0:0:0:0:0:0:0:1'; check(ContributionBudget::reserve('report'),'Equivalent IPv6 spelling accepted');
    check((int)$db->query("SELECT COUNT(*) FROM ::contribution_budget WHERE bucket LIKE 'report-ip-%'")->fetchSingle()===1, 'Equivalent IPs share one expiring bucket');
    resetFixture();
    foreach ([[0,'report',10000,1],[7,'bytes-text',268435456,0],[0,'bytes-text',4294967296,0]] as [$owner,$bucket,$amount,$expires]) {
        resetFixture(); $db->query('INSERT INTO ::contribution_budget VALUES (%i,%s,%i,%i)',$owner,$bucket,$amount,$expires ? time()+3600 : 0);
        check(submit(3,1)===[false,Report::ERR_LIMIT] && countReports()===0, 'Global work or retained-byte capacity blocks reports');
    }
    resetFixture();
    $db->query("INSERT INTO ::contribution_budget VALUES (7,'report',25,UNIX_TIMESTAMP()-1)");
    check(submit(3,1)[0] && used(7)===1, 'Expired report window resets atomically');
    resetFixture();
    User::$ip='invalid peer';
    check(!submit(3,1)[0] && countReports()===0 && used(7)===0, 'Missing trusted peer cannot bypass the IP limit');
    resetFixture();
    $db->query('RENAME TABLE ::contribution_budget TO ::unavailable_budget');
    ob_start(); $failed=submit(3,1); $output=ob_get_clean();
    check(!$failed[0] && $output==='' && countReports()===0, 'Unavailable budget fails closed without exposing SQL');
    $db->query('RENAME TABLE ::unavailable_budget TO ::contribution_budget');
    resetFixture();
    $db->onEvent[]=function(Dibi\Event $event) {
        if(str_starts_with($event->sql,'SELECT 1 FROM aowow_reports') || str_starts_with($event->sql,'SELECT 1 FROM `aowow_reports`'))
            throw new RuntimeException('PRIVATE_DUPLICATE_SQL_SENTINEL');
    };
    ob_start(); $failed=submit(3,1); $output=ob_get_clean(); $db->onEvent=[];
    check(!$failed[0] && $output==='' && countReports()===0 && used(7)===1, 'Failed duplicate check never proceeds to insert');
    resetFixture();
    $db->nativeQuery("CREATE TRIGGER report_fail BEFORE INSERT ON aowow_reports FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_REPORT_SQL_SENTINEL'");
    ob_start(); $failed=submit(3,1); $output=ob_get_clean();
    check(!$failed[0] && $output==='' && countReports()===0 && used(7)===1, 'Insert failure rolls back report but conservatively retains allowance without raw diagnostics');
    $db->query('DROP TRIGGER report_fail');
    resetFixture();
    $inserted=false;
    $db->onEvent[]=function(Dibi\Event $event) use (&$inserted) {
        if(str_starts_with($event->sql,'INSERT INTO aowow_reports') || str_starts_with($event->sql,'INSERT INTO `aowow_reports`')) $inserted=true;
        if($inserted && $event->sql==='COMMIT') throw new RuntimeException('uncertain acknowledgement');
    };
    check(!submit(3,1)[0], 'Uncertain report commit does not claim success'); $db->onEvent=[];
    check(countReports()===1 && submit(3,1)===[false,7] && countReports()===1, 'Lost acknowledgement cannot blindly replay the committed report');

    // Independent workers test the duplicate and quota boundaries with actual database locks.
    function race(array $targets) : array {
        global $checks;
        $workers=[];
        foreach($targets as $target) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker','7',(string)$target],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
            fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $results=[];
        foreach($workers as [$process,$pipes]) {
            $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            check(proc_close($process)===0,'Report worker completed without errors: '.$error);
            $results[]=json_decode($out,true,flags:JSON_THROW_ON_ERROR);
        }
        return $results;
    }
    resetFixture();$results=race(array_fill(0,8,1));
    check(count(array_filter($results,fn($r)=>$r[0]))===1 && countReports()===1 && used(7)===8, 'Concurrent same-target reports insert exactly once and charge every attempt');
    resetFixture();
    for($id=100;$id<108;++$id) $db->query("INSERT INTO ::comments (id,userId,roles,body,date) VALUES (%i,9,0,'race target',1)",$id);
    $db->query("INSERT INTO ::contribution_budget VALUES (7,'report',24,UNIX_TIMESTAMP()+3600)");
    $results=race(range(100,107));
    check(count(array_filter($results,fn($r)=>$r[0]))===1 && countReports()===1 && used(7)===25, 'Concurrent reports cannot exceed the account final slot');
    $db->query('DELETE FROM ::comments WHERE id>=100');
    resetFixture();
    User::$id=0;
    check(submit(0,0,1)[0] && used(0)===0, 'Anonymous general feedback remains independent of content quotas');
    Cfg::$feedback=false; check(!submit(0,0,1)[0], 'Disabled general feedback still denied');
    User::$id=7; check(submit(3,1)[0], 'Disabling general feedback preserves authenticated content reporting');

    // Actual responder methods retain feedback CAPTCHA while enforcing content identity and quota messages.
    function endpoint(int $mode, int $reason=45, array $overrides=[]) : mixed {
        $response=(new ReflectionClass(Aowow\ContactusBaseResponse::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($response,'_post'))->setValue($response,array_replace(['mode'=>$mode,'reason'=>$reason,'id'=>1,'desc'=>'Synthetic endpoint report',
            'ua'=>'fixture','appname'=>'fixture','page'=>'https://example.test/','relatedurl'=>null,'email'=>null],$overrides));
        (new ReflectionMethod($response,'generate'))->invoke($response);
        return (new ReflectionProperty($response,'result'))->getValue($response);
    }
    resetFixture();User::$id=0; $calls=Turnstile::$calls;
    check(endpoint(3)==='intError' && countReports()===0 && Turnstile::$calls===$calls, 'Anonymous content endpoint denied without verifier or report work');
    Turnstile::$valid=false; check(endpoint(0,1)==='captchaError' && countReports()===0,'General feedback CAPTCHA still enforced');
    resetFixture();
    set_error_handler(fn(int $level, string $message) => throw new ErrorException('Unexpected report warning',0,$level));
    try {
        foreach ([['desc'=>null],['desc'=>false],['id'=>null],['id'=>false],['ua'=>false],['page'=>[]],['mode'=>[]],['reason'=>null]] as $invalid)
            check(in_array(endpoint(3,45,$invalid),[3,4],true) && used(7)===0 && countReports()===0, 'Malformed filtered input rejected without warnings or database work');
        check(!submit(3,1,999)[0] && !submit(-1,1,45)[0] && used(7)===0,'Invalid report contexts are ordinary rejections');
    } finally { restore_error_handler(); }
    resetFixture();check(endpoint(3)===0,'Authenticated endpoint succeeds without adding content CAPTCHA');
    $db->query("UPDATE ::contribution_budget SET used=25 WHERE owner=7 AND bucket='report'");
    check(endpoint(3)==='contributionLimit','Quota exhaustion returns the existing localized limit message');

    // Other consumers of Report::create must treat normal denials as form failures, not maintenance-triggering warnings.
    class FlagReplyProbe extends Aowow\CommentFlagreplyResponse {
        public function generate404(?string $out=null) : never { throw new RuntimeException($out ?? ''); }
    }
    function commentEndpoint(string $class, array $post) : mixed {
        $response=(new ReflectionClass($class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($response,'_post'))->setValue($response,$post);
        try { (new ReflectionMethod($response,'generate'))->invoke($response); }
        catch (RuntimeException $e) { return $e->getMessage(); }
        return (new ReflectionProperty($response,'result'))->getValue($response);
    }
    resetFixture();
    set_error_handler(fn(int $level, string $message) => throw new ErrorException('Unexpected comment-report warning',0,$level));
    try {
        foreach ([Aowow\CommentOutofdateResponse::class,FlagReplyProbe::class] as $class) {
            resetFixture();
            $result=commentEndpoint($class,['id'=>1,'remove'=>null,'reason'=>'Synthetic outdated report']);
            check($result===($class===FlagReplyProbe::class ? null : 'ok') && countReports()===1 && used(7)===1,'Comment report consumer preserves successful reporting below threshold');
            $db->query("UPDATE ::contribution_budget SET used=25 WHERE owner=7 AND bucket='report'");
            check(commentEndpoint($class,['id'=>1,'remove'=>null,'reason'=>'Synthetic outdated report'])==='contributionLimit' && countReports()===1,'Comment report quota denial is a normal localized failure');
            resetFixture();User::$groups=U_GROUP_PENDING;
            check(commentEndpoint($class,['id'=>1,'remove'=>null,'reason'=>'Synthetic outdated report'])==='intError' && countReports()===0 && used(7)===0,'Pending account denied by comment report consumer');
        }
    } finally { restore_error_handler(); }

    foreach($original as $table=>$rows) check($db->query('SELECT * FROM %n ORDER BY id','::'.$table)->fetchAll()==$rows,'Existing community/owner rows preserved: '.$table);
    check($db->query('SELECT * FROM ::reports WHERE id=500')->fetch()==$historical,'Historical moderation report remains unchanged');
    echo "PASS: $checks report identity/target/metadata/quota/concurrency/rollback/community checks\n";
}
