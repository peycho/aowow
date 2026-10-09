<?php
// Actual feedback service, endpoint, CAPTCHA and budgets; disposable SQL and intercepted provider only.
namespace Aowow {
    class User {
        public static int $id=0, $groups=0;
        public static string $ip='127.0.0.1', $agent='fixture';
        public static function isLoggedIn() : bool { return self::$id > 0; }
        public static function isBanned() : bool { return false; }
    }
    class Cfg {
        public static bool $feedback=true, $captcha=false;
        public static function get(string $key) : mixed {
            return match ($key) { 'HOST_URL'=>'https://example.test', 'FEEDBACK_ENABLE'=>self::$feedback,
                'TURNSTILE_FEEDBACK_ENABLE'=>self::$captcha, default=>0 };
        }
    }
    class Lang { public static function main(string $key) : string { return $key; } }
    class Fixture { public static int $network=0; public static string $response='valid'; }
    function curl_version() : array { return ['features'=>CURL_VERSION_ASYNCHDNS]; }
    function curl_init(string $url) : \CurlHandle {
        if ($url !== 'https://challenges.cloudflare.com/turnstile/v0/siteverify') throw new \RuntimeException('Unexpected provider');
        ++Fixture::$network;
        // A separate worker can lock both admission rows during outbound verification.
        $observer=new DibiConnection(\fixtureOptions());
        $observer->query('SET SESSION innodb_lock_wait_timeout=1');
        $observer->query('START TRANSACTION');
        foreach (['feedback-global','captcha-global'] as $bucket) {
            $used=$observer->query('SELECT used FROM ::contribution_budget WHERE owner=0 AND bucket=%s FOR UPDATE',$bucket)->fetchSingle();
            if (!$used) throw new \RuntimeException('Admission was not durable before verification');
        }
        $observer->query('ROLLBACK');
        return \curl_init();
    }
    function curl_setopt_array(\CurlHandle $handle, array $options) : bool {
        if (Fixture::$response==='throw') throw new \RuntimeException('PRIVATE_PROVIDER_SENTINEL');
        ($options[CURLOPT_WRITEFUNCTION])($handle, json_encode(Fixture::$response==='valid' ?
            ['success'=>true,'action'=>'feedback','hostname'=>'example.test'] : ['success'=>false]));
        return true;
    }
    function curl_exec(\CurlHandle $handle) : bool { return Fixture::$response!=='outage'; }
    function curl_getinfo(\CurlHandle $handle, int $option) : int { return 200; }
}
namespace {
    use Aowow\{DB,DibiConnection,User,Cfg,Fixture,FeedbackBudget,Report,Turnstile,Retention};
    if (getenv('AOWOW_TEST_DATABASE')!=='aowow_security_test_feedback' ||
        (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1')!=='127.0.0.1') {
        fwrite(STDERR,"Use only isolated aowow_security_test_feedback on 127.0.0.1; see tests/README.md.\n");exit(1);
    }
    define('AOWOW_REVISION',72);define('CLI',true);define('CLI_HAS_E',false);define('OS_WIN',false);
    define('AOWOW_TURNSTILE_SITE_KEY','SITE_FIXTURE');define('AOWOW_TURNSTILE_SECRET_KEY','SECRET_FIXTURE');
    $root=dirname(__DIR__);
    require $root.'/includes/defines.php';require $root.'/includes/libs/autoload.php';
    foreach (['database.php','components/contributionbudget.class.php','components/feedbackbudget.class.php',
        'components/turnstilebudget.class.php','components/turnstile.class.php','components/report.class.php',
        'components/retention.class.php','components/response/baseresponse.class.php','components/response/textresponse.class.php'] as $file)
        require $root.'/includes/'.$file;
    require $root.'/endpoints/contactus/contactus.php';
    function fixtureOptions() : array {
        return ['driver'=>'mysqli','host'=>'127.0.0.1','port'=>(int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306),
            'username'=>'root','password'=>'','database'=>'aowow_security_test_feedback','charset'=>'utf8mb4','substitutes'=>[''=>'aowow_']];
    }
    $db=new DibiConnection(fixtureOptions());$db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
    (new ReflectionProperty(DB::class,'interfaceCache'))->setValue(null,[DB_AOWOW=>$db]);
    (new ReflectionProperty(DB::class,'interfaceTimes'))->setValue(null,[DB_AOWOW=>[time()+86400,86400]]);
    function submit(string $url='https://example.test/?item=1', int $reason=1, int $subject=0, mixed $token='synthetic-token', array $metadata=[]) : array {
        $report=new Report(0,$reason,$subject);
        $ok=$report->create(...array_replace(['desc'=>'Synthetic feedback','userAgent'=>'fixture','appName'=>'fixture',
            'pageUrl'=>$url,'relUrl'=>null,'email'=>null,'captchaToken'=>$token],$metadata));
        return [$ok,$report->getError()];
    }
    if (($argv[1] ?? '')==='--worker') {
        User::$ip=$argv[2];echo json_encode(submit($argv[3]));exit;
    }
    if (($argv[1] ?? '')==='--interrupt') {
        $phase=$argv[2];$commits=0;
        $db->onEvent[]=function(Dibi\Event $event) use ($phase,&$commits) {
            if ($event->sql==='COMMIT') ++$commits;
            if (($phase==='admitted' && $commits===1 && $event->sql==='COMMIT') ||
                ($phase==='inserted' && str_starts_with(str_replace('`','',$event->sql),'INSERT INTO aowow_reports')) ||
                ($phase==='stored' && $commits===3 && $event->sql==='COMMIT')) {
                echo "READY\n";flush();while(true) usleep(10000);
            }
        };
        submit();exit(2);
    }
    $checks=0;
    function check(bool $ok,string $label) : void { global $checks;++$checks;if(!$ok)throw new RuntimeException($label); }
    function used(string $bucket) : int { global $db;return (int)$db->query('SELECT used FROM ::contribution_budget WHERE owner=0 AND bucket=%s',$bucket)->fetchSingle(); }
    function peerCount() : int { global $db;return (int)$db->query("SELECT COUNT(*) FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'feedback-ip-%'")->fetchSingle(); }
    function peerKey() : string { global $db;return (string)$db->query("SELECT bucket FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'feedback-ip-%' LIMIT 1")->fetchSingle(); }
    function countReports() : int { global $db;return (int)$db->query('SELECT COUNT(*) FROM ::reports WHERE id NOT IN (500,501)')->fetchSingle(); }
    function resetFixture() : void {
        global $db;$db->onEvent=[];
        $db->query('DELETE FROM ::reports WHERE id NOT IN (500,501)');
        $db->query("DELETE FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'feedback-%'");
        $db->query("DELETE FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'captcha-%'");
        Cfg::$feedback=true;Cfg::$captcha=false;User::$id=0;User::$ip='127.0.0.1';Fixture::$network=0;Fixture::$response='valid';
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach($db->query('SHOW TABLES')->fetchPairs() as $table)$db->query('DROP TABLE %n',$table);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    foreach(['reports','contribution_budget','account','comments','screenshots','videos','guides','account_favorites'] as $table) {
        preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`aowow_'.preg_quote($table,'/').'` \(.*?\) ENGINE=.*?;/s',file_get_contents($root.'/setup/sql/01-db_structure.sql'),$schema);
        check(!empty($schema),'Reference schema available for '.$table);$db->nativeQuery($schema[0]);
    }
    // Representative existing community records and old general/content reports must remain byte-for-byte intact.
    $db->query("INSERT INTO ::account (id,username,passHash,joinDate) VALUES (7,'fixture','synthetic-hash',1)");
    $db->query("INSERT INTO ::comments (id,userId,roles,body,date) VALUES (1,7,0,'preserved',1)");
    $db->query("INSERT INTO ::guides (id,userId,status,title) VALUES (1,7,3,'preserved')");
    $db->query("INSERT INTO ::screenshots (id,type,typeId,userIdOwner,date,width,height,status) VALUES (1,1,1,7,1,10,10,1)");
    $db->query("INSERT INTO ::videos (id,type,typeId,userIdOwner,date,width,height,status,videoId,pos,url) VALUES (1,1,1,7,1,10,10,1,'fixture',0,'https://example.test/fixture')");
    $db->query("INSERT INTO ::account_favorites (userId,type,typeId) VALUES (7,1,1)");
    foreach([500=>0,501=>1] as $id=>$mode)
        $db->query("INSERT INTO ::reports (id,userId,createDate,mode,reason,subject,ip,description,userAgent,appName,url) VALUES (%i,7,1,%i,1,1,'192.0.2.1','preserved','fixture','fixture','https://example.test/preserved')",$id,$mode);
    $db->query("INSERT INTO ::contribution_budget VALUES (7,'comment',3,UNIX_TIMESTAMP()-1),(0,'bytes-text',1024,0),(0,'report',2,UNIX_TIMESTAMP()+3600)");
    $community=[];
    foreach(['account','comments','guides','screenshots','videos','account_favorites'] as $table)$community[$table]=$db->query('SELECT * FROM %n','::'.$table)->fetchAll();
    $historical=$db->query('SELECT * FROM ::reports WHERE id IN (500,501) ORDER BY id')->fetchAll();
    $initialBytes=FeedbackBudget::ROW_OVERHEAD+strlen('preservedfixturefixturehttps://example.test/preserved192.0.2.1');
    check(submit()[0] && countReports()===1 && used('feedback-stored')===2 && Fixture::$network===0,'Anonymous feedback succeeds with CAPTCHA disabled');
    check(used('feedback-bytes')===$initialBytes+FeedbackBudget::ROW_OVERHEAD+strlen('Synthetic feedbackfixturefixturehttps://example.test/?item=1127.0.0.1'),'Historical bytes and actual inserted metadata charged');
    check(!submit()[0] && countReports()===1 && used('feedback-stored')===2 && used('feedback-global')===2,'Duplicate consumes work but not retained storage');
    for($i=2;$i<FeedbackBudget::PEER_LIMIT;++$i) {
        User::$id=$i%2 ? 7 : 0;
        check(submit('https://example.test/?item='.$i,1+$i%8,$i)[0],'Changed URL/reason/subject and identity consumes shared allowance');
    }
    $savedBytes=used('feedback-bytes');Cfg::$captcha=true;
    check(submit('https://example.test/new')===[false,Report::ERR_LIMIT] && Fixture::$network===0 && used('feedback-bytes')===$savedBytes,'Peer exhaustion denies before verification even with CAPTCHA enabled');
    resetFixture();Cfg::$captcha=true;
    foreach(['invalid','outage','throw'] as $response) {
        Fixture::$response=$response;ob_start();$result=submit();$output=ob_get_clean();
        check($result===[false,Report::ERR_INVALID_CAPTCHA] && $output==='' && countReports()===0,'Provider failure rejected without diagnostics: '.$response);
    }
    check(used('feedback-global')===3 && used('captcha-global')===3 && used('feedback-stored')===1,'Failed CAPTCHA retains request/verification charges and historical baseline only');
    Fixture::$response='valid';check(submit()[0] && used('feedback-global')===4 && countReports()===1,'Legitimate retry retains normal authority');
    resetFixture();Cfg::$captcha=true;
    foreach([null,[],false,'',"bad\n",str_repeat('x',2049)] as $token)
        check(submit(token:$token)===[false,Report::ERR_INVALID_CAPTCHA] && Fixture::$network===0,'Malformed/missing CAPTCHA still consumes feedback attempt');
    check(used('feedback-global')===6,'Local CAPTCHA denial consumes durable feedback work');
    resetFixture();$events=0;$db->onEvent[]=function()use(&$events){++$events;};
    foreach([['desc'=>''],['desc'=>str_repeat('x',501)],['userAgent'=>str_repeat('x',256)],['email'=>"bad\n"],['appName'=>"\xff"]] as $metadata)
        check(!submit(metadata:$metadata)[0] && $events===0,'Invalid local fields rejected without SQL/network work');
    Cfg::$feedback=false;check(!submit()[0] && $events===0,'Disabled feedback avoids reservations');Cfg::$feedback=true;
    foreach(['','invalid','127.0.0.1, 192.0.2.1'] as $ip){User::$ip=$ip;check(!submit()[0] && $events===0,'Unknown/untrusted peer denied before SQL');}
    resetFixture();
    foreach(['::1','0:0:0:0:0:0:0:1'] as $i=>$ip){User::$ip=$ip;check(submit('https://example.test/'.$i)[0],'IPv6 peer accepted');}
    check(peerCount()===1 && used(peerKey())===2,'Canonical IPv6 shares allowance');
    resetFixture();
    foreach(['::ffff:127.0.0.1','127.0.0.1'] as $i=>$ip){User::$ip=$ip;check(submit('https://example.test/'.$i)[0],'IPv4/mapped peer accepted');}
    check(peerCount()===1 && used(peerKey())===2 && strlen(peerKey())===32 && !str_contains(peerKey(),User::$ip),'Mapped IPv4 shares hashed key without raw address');
    $db->query('UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket=%s',peerKey());
    check(submit('https://example.test/retry')[0] && used(peerKey())===1,'Expired peer window permits retry');
    resetFixture();$db->query("INSERT INTO ::contribution_budget VALUES (0,'feedback-global',%i,UNIX_TIMESTAMP()+86400)",FeedbackBudget::GLOBAL_LIMIT);
    Cfg::$captcha=true;check(submit()===[false,Report::ERR_LIMIT] && Fixture::$network===0 && peerCount()===0,'Site-wide exhaustion denies before peer admission and provider');
    $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket='feedback-global'");
    check(submit()[0] && used('feedback-global')===1,'Expired global window permits retry');
    // Permanent capacities include historical feedback and must not reset when request windows expire.
    foreach(['feedback-stored'=>FeedbackBudget::RECORD_LIMIT,'feedback-bytes'=>FeedbackBudget::BYTE_LIMIT] as $bucket=>$limit) {
        resetFixture();check(submit()[0],'Storage seeded');$db->query('UPDATE ::contribution_budget SET used=%i WHERE bucket=%s',$limit,$bucket);
        $saved=$db->query("SELECT * FROM ::contribution_budget WHERE bucket IN ('feedback-stored','feedback-bytes') ORDER BY bucket")->fetchAll();
        Cfg::$captcha=true;
        check(submit('https://example.test/over')===[false,Report::ERR_LIMIT] && countReports()===1 && Fixture::$network===0,'Permanent capacity denies before provider: '.$bucket);
        $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket='feedback-global' OR bucket LIKE 'feedback-ip-%'");
        check(submit('https://example.test/over2')===[false,Report::ERR_LIMIT] && $db->query("SELECT * FROM ::contribution_budget WHERE bucket IN ('feedback-stored','feedback-bytes') ORDER BY bucket")->fetchAll()==$saved,'Storage cap survives expired work windows without partial charges');
    }
    resetFixture();check(submit()[0],'Storage seed for byte boundary');
    $charge=FeedbackBudget::ROW_OVERHEAD+strlen('Synthetic feedbackfixturefixturehttps://example.test/boundary127.0.0.1');
    $db->query("UPDATE ::contribution_budget SET used=%i WHERE bucket='feedback-bytes'",FeedbackBudget::BYTE_LIMIT-$charge);
    check(submit('https://example.test/boundary')[0] && used('feedback-bytes')===FeedbackBudget::BYTE_LIMIT,'Exact byte boundary accepted');
    check(!submit('https://example.test/next')[0],'One further record exceeds byte boundary');
    // A restored inbox already at capacity is seeded once; retries do not rescan it or contact CAPTCHA.
    resetFixture();
    $rows=['userId'=>[],'createDate'=>[],'mode'=>[],'reason'=>[],'subject'=>[],'ip'=>[],
        'description'=>[],'userAgent'=>[],'appName'=>[],'url'=>[]];
    for($i=1;$i<FeedbackBudget::RECORD_LIMIT;++$i) {
        foreach(['userId'=>0,'createDate'=>1,'mode'=>0,'reason'=>1,'subject'=>0,'ip'=>'192.0.2.1',
            'description'=>'historical fixture','userAgent'=>'fixture','appName'=>'','url'=>''] as $key=>$value)$rows[$key][]=$value;
    }
    $db->query('INSERT INTO ::reports %m',$rows);Cfg::$captcha=true;
    $scans=0;$db->onEvent[]=function(Dibi\Event $event)use(&$scans){if(str_contains($event->sql,'COALESCE(SUM('))++$scans;};
    check(submit()===[false,Report::ERR_LIMIT] && used('feedback-stored')===FeedbackBudget::RECORD_LIMIT && Fixture::$network===0,'Existing full inbox initializes durable capacity and rejects before CAPTCHA');
    check(submit()===[false,Report::ERR_LIMIT] && $scans===1 && countReports()===FeedbackBudget::RECORD_LIMIT-1,'Full historical inbox remains untouched and is scanned only once');
    resetFixture();Cfg::$captcha=true;
    $db->nativeQuery("CREATE TRIGGER feedback_budget_fail BEFORE UPDATE ON aowow_contribution_budget FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_SQL_SENTINEL'");
    ob_start();$result=submit();$output=ob_get_clean();
    check(!$result[0] && $output==='' && used('feedback-global')===0 && peerCount()===0 && Fixture::$network===0,'Failed request accounting rolls back and never reaches provider');
    $db->query('DROP TRIGGER feedback_budget_fail');
    resetFixture();check(submit()[0],'Partial ledger fixture seeded');
    $db->query("DELETE FROM ::contribution_budget WHERE bucket='feedback-bytes'");Cfg::$captcha=true;
    check(!submit('https://example.test/missing')[0] && Fixture::$network===0 && used('feedback-stored')===2 && countReports()===1,'Partial permanent ledger fails closed without resetting charges');
    // Missing budget schema, SQL failure and uncertain acknowledgments fail closed without exposing SQL/values.
    resetFixture();Cfg::$captcha=true;$db->query('RENAME TABLE ::contribution_budget TO ::missing_budget');
    ob_start();$result=submit();$output=ob_get_clean();
    check(!$result[0] && $output==='' && Fixture::$network===0 && countReports()===0,'Missing schema denies before provider');
    $db->query('RENAME TABLE ::missing_budget TO ::contribution_budget');Cfg::$captcha=false;
    $db->nativeQuery("CREATE TRIGGER feedback_fail BEFORE INSERT ON aowow_reports FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_SQL_SENTINEL'");
    ob_start();$result=submit();$output=ob_get_clean();
    check(!$result[0] && $output==='' && used('feedback-global')===1 && used('feedback-stored')===1 && countReports()===0,'Insert failure retains work and baseline, rolls back new storage and record');
    $db->query('DROP TRIGGER feedback_fail');
    foreach([1,2,3] as $failCommit) {
        resetFixture();$commits=0;
        $db->onEvent[]=function(Dibi\Event $event)use(&$commits,$failCommit){if($event->sql==='COMMIT' && ++$commits===$failCommit)throw new RuntimeException('Lost acknowledgement');};
        check(!submit()[0],'Uncertain commit denies reported success');$db->onEvent=[];
        check(used('feedback-global')===1 && countReports()===($failCommit===3 ? 1 : 0),'Uncertain admission/storage commit retains durable state');
        check(used('feedback-stored')===($failCommit===1 ? 0 : ($failCommit===2 ? 1 : 2)),'Uncertain storage commit retains corresponding charges');
    }
    foreach(['admitted','inserted','stored'] as $phase) {
        resetFixture();$process=proc_open([PHP_BINARY,__FILE__,'--interrupt',$phase],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]);stream_set_blocking($pipes[1],false);$ready='';
        for($i=0;$i<300 && !str_contains($ready,"READY\n");++$i){$ready.=stream_get_contents($pipes[1]);usleep(10000);}
        $killed=proc_terminate($process,9);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($process);
        check(str_contains($ready,"READY\n") && $killed && $error==='','Interruption reached '.$phase.' boundary');
        check(used('feedback-global')===1 && countReports()===($phase==='stored' ? 1 : 0) && used('feedback-stored')===($phase==='stored' ? 2 : ($phase==='admitted' ? 0 : 1)),'Interruption preserves committed work and only committed storage');
        check(submit('https://example.test/retry')[0],'Interrupted worker releases locks for retry');
    }
    function race(array $jobs) : array {
        $workers=[];
        foreach($jobs as [$ip,$url]){$process=proc_open([PHP_BINARY,__FILE__,'--worker',$ip,$url],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $results=[];
        foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0 && $err==='','Independent SQL worker exited cleanly');$results[]=json_decode($out,true,flags:JSON_THROW_ON_ERROR);}
        return $results;
    }
    resetFixture();check(submit('https://example.test/seed')[0],'Peer seed');
    $db->query('UPDATE ::contribution_budget SET used=%i WHERE bucket=%s',FeedbackBudget::PEER_LIMIT-1,peerKey());
    $jobs=[];for($i=0;$i<8;++$i)$jobs[]=['127.0.0.1','https://example.test/race'.$i];
    $results=race($jobs);check(count(array_filter($results,fn($r)=>$r[0]))===1 && used(peerKey())===FeedbackBudget::PEER_LIMIT && countReports()===2,'Concurrent changed URLs cannot exceed final peer slot');
    resetFixture();$db->query("INSERT INTO ::contribution_budget VALUES (0,'feedback-global',%i,UNIX_TIMESTAMP()+86400)",FeedbackBudget::GLOBAL_LIMIT-1);
    $jobs=[];for($i=0;$i<8;++$i)$jobs[]=['192.0.2.'.($i+1),'https://example.test/race'.$i];
    $results=race($jobs);check(count(array_filter($results,fn($r)=>$r[0]))===1 && used('feedback-global')===FeedbackBudget::GLOBAL_LIMIT && countReports()===1,'Concurrent peers cannot exceed global slot');
    resetFixture();$jobs=array_fill(0,8,['127.0.0.1','https://example.test/same']);$results=race($jobs);
    check(count(array_filter($results,fn($r)=>$r[0]))===1 && countReports()===1 && used('feedback-stored')===2 && used('feedback-global')===8,'Concurrent duplicate feedback inserts once while all attempts count');
    resetFixture();check(submit('https://example.test/seed')[0],'Storage race seed');
    $db->query("UPDATE ::contribution_budget SET used=%i WHERE bucket='feedback-stored'",FeedbackBudget::RECORD_LIMIT-1);
    $results=race($jobs=array_map(fn($i)=>['192.0.2.'.($i+1),'https://example.test/last'.$i],range(0,7)));
    check(count(array_filter($results,fn($r)=>$r[0]))===1 && used('feedback-stored')===FeedbackBudget::RECORD_LIMIT && countReports()===2,'Concurrent peers cannot exceed final storage slot');
    resetFixture();$rows=['owner'=>[],'bucket'=>[],'used'=>[],'expires'=>[]];
    for($i=0;$i<FeedbackBudget::MAX_PEERS;++$i){$rows['owner'][]=0;$rows['bucket'][]='feedback-ip-'.str_pad((string)$i,20,'0',STR_PAD_LEFT);$rows['used'][]=0;$rows['expires'][]=time()+3600;}
    $db->query('INSERT INTO ::contribution_budget %m',$rows);Cfg::$captcha=true;
    check(submit()===[false,Report::ERR_LIMIT] && peerCount()===FeedbackBudget::MAX_PEERS && Fixture::$network===0,'Hard peer-key cap denies before verification without prune');
    $db->query('UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket LIKE %s LIMIT 10','feedback-ip-%');
    check(submit()[0] && peerCount()===FeedbackBudget::MAX_PEERS-FeedbackBudget::RECLAIM+1,'Online cleanup removes at most eight expired peer keys');
    // Existing peers can use their allowance at the key cap; new peers race for one final slot.
    resetFixture();check(submit('https://example.test/existing')[0],'Existing peer seed at key cap');
    foreach($rows as &$column)array_pop($column);unset($column);
    $db->query('INSERT INTO ::contribution_budget %m',$rows);
    check(submit('https://example.test/existing2')[0] && peerCount()===FeedbackBudget::MAX_PEERS,'Existing peer retains allowance at hard key capacity');
    resetFixture();$db->query('INSERT INTO ::contribution_budget %m',$rows);
    $jobs=array_map(fn($i)=>['192.0.2.'.($i+1),'https://example.test/key'.$i],range(0,7));
    $results=race($jobs);
    check(count(array_filter($results,fn($r)=>$r[0]))===1 && peerCount()===FeedbackBudget::MAX_PEERS,'Concurrent new peers cannot exceed final key slot');
    resetFixture();check(submit()[0],'Permanent seed survives retention');$stored=used('feedback-stored');$bytes=used('feedback-bytes');
    $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket='feedback-global' OR bucket LIKE 'feedback-ip-%'");
    $db->query('CREATE TABLE ::errors (date int) ENGINE=InnoDB');
    $db->query('CREATE TABLE ::account_password_budget (expires int) ENGINE=InnoDB');
    $db->query('CREATE TABLE ::screenshot_uploads (expires int) ENGINE=InnoDB');
    Retention::database(true);
    check(used('feedback-stored')===$stored && used('feedback-bytes')===$bytes && countReports()===1,'Prune retains feedback records and permanent charges');
    // Endpoint result contracts remain localized; no warning/maintenance side effect on quota denial.
    function endpoint(array $overrides=[]) : mixed {
        $response=(new ReflectionClass(Aowow\ContactusBaseResponse::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($response,'_post'))->setValue($response,array_replace(['mode'=>0,'reason'=>1,'id'=>0,'desc'=>'Synthetic feedback','page'=>'https://example.test/endpoint'],$overrides));
        (new ReflectionMethod($response,'generate'))->invoke($response);
        return (new ReflectionProperty($response,'result'))->getValue($response);
    }
    resetFixture();$_POST=['cf-turnstile-response'=>'synthetic-token'];Cfg::$captcha=true;
    check(endpoint()===0,'Actual endpoint passes token to protected service');
    Fixture::$response='invalid';check(endpoint(['page'=>'https://example.test/invalid'])==='captchaError','CAPTCHA failure keeps localized endpoint response');
    $db->query('UPDATE ::contribution_budget SET used=%i WHERE bucket=%s',FeedbackBudget::PEER_LIMIT,peerKey());$network=Fixture::$network;
    set_error_handler(fn(int $level,string $message)=>throw new ErrorException('Unexpected feedback warning',0,$level));
    try{check(endpoint()==='contributionLimit' && Fixture::$network===$network,'Quota denial localized without warning or network');}finally{restore_error_handler();}
    resetFixture();
    foreach($community as $table=>$snapshot)check($db->query('SELECT * FROM %n','::'.$table)->fetchAll()==$snapshot,'Community data preserved: '.$table);
    check($db->query('SELECT * FROM ::reports WHERE id IN (500,501) ORDER BY id')->fetchAll()==$historical,'Existing general/content report fields preserved');
    // Prune legitimately expires unrelated transient work; only permanent unrelated accounting must persist.
    check(used('bytes-text')===1024 && used('report')===2,'Independent content quotas/storage preserved');
    echo 'PASS: '.$checks." feedback SQL/handler/CAPTCHA/concurrency/storage/preservation checks\n";
}
