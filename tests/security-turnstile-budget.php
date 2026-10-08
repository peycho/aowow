<?php
// Dedicated disposable MySQL fixture. Real reservation/verification methods; intercepted provider, no real tokens or keys.
namespace Aowow {
    class User { public static string $ip = '127.0.0.1'; }
    class Cfg {
        public static bool $enabled = true;
        public static function get(string $key) : mixed {
            return $key === 'HOST_URL' ? 'https://example.test' : (str_starts_with($key, 'TURNSTILE_') && self::$enabled);
        }
    }
    class Fixture {
        public static int $network = 0;
        public static string $response = 'invalid';
        public static bool $committed = false, $duringNetwork = false;
    }
    function curl_version() : array { return ['features'=>CURL_VERSION_ASYNCHDNS]; }
    function curl_init(string $url) : \CurlHandle {
        if ($url !== 'https://challenges.cloudflare.com/turnstile/v0/siteverify' || !Fixture::$committed)
            throw new \RuntimeException('Verification preceded durable reservation');
        ++Fixture::$network;
        // Use an independent connection to prove no database locks span verification.
        Fixture::$duringNetwork = true;
        try {
            $observer = new DibiConnection(\fixtureOptions());
            $observer->query('SET SESSION innodb_lock_wait_timeout = 1');
            $observer->query('START TRANSACTION');
            $observer->query("SELECT used FROM ::contribution_budget WHERE owner=0 AND bucket='captcha-global' FOR UPDATE")->fetchSingle();
            $observer->query('ROLLBACK');
        } finally { Fixture::$duringNetwork = false; }
        return \curl_init();
    }
    function curl_setopt_array(\CurlHandle $curl, array $options) : bool {
        if (Fixture::$response === 'throw') throw new \RuntimeException('PRIVATE_VERIFIER_SENTINEL');
        $body = Fixture::$response === 'valid' ? ['success'=>true,'action'=>'login','hostname'=>'example.test'] : ['success'=>false];
        ($options[CURLOPT_WRITEFUNCTION])($curl, json_encode($body));
        return true;
    }
    function curl_exec(\CurlHandle $curl) : bool { return Fixture::$response !== 'outage'; }
    function curl_getinfo(\CurlHandle $curl, int $option) : int { return 200; }
}
namespace {
    use Aowow\{DB, DibiConnection, Cfg, Fixture, User, Turnstile, TurnstileBudget, Retention};
    if (getenv('AOWOW_TEST_DATABASE') !== 'aowow_security_test_turnstile' ||
        (getenv('AOWOW_TEST_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1') {
        fwrite(STDERR, "Use only isolated aowow_security_test_turnstile on 127.0.0.1; see tests/README.md.\n"); exit(1);
    }
    define('AOWOW_REVISION',71); define('CLI',true); define('CLI_HAS_E',false); define('OS_WIN',false);
    define('AOWOW_TURNSTILE_SITE_KEY','SITE_FIXTURE'); define('AOWOW_TURNSTILE_SECRET_KEY','SECRET_FIXTURE');
    $root=dirname(__DIR__);
    require $root.'/includes/defines.php';
    require $root.'/includes/libs/autoload.php';
    foreach (['database.php','components/turnstilebudget.class.php','components/turnstile.class.php','components/retention.class.php'] as $file)
        require $root.'/includes/'.$file;
    function fixtureOptions() : array {
        return ['driver'=>'mysqli','host'=>'127.0.0.1','port'=>(int)(getenv('AOWOW_TEST_DB_PORT') ?: 3306),
            'username'=>'root','password'=>'','database'=>'aowow_security_test_turnstile','charset'=>'utf8mb4','substitutes'=>[''=>'aowow_']];
    }
    $db=new DibiConnection(fixtureOptions());
    $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
    (new ReflectionProperty(DB::class,'interfaceCache'))->setValue(null,[DB_AOWOW=>$db]);
    (new ReflectionProperty(DB::class,'interfaceTimes'))->setValue(null,[DB_AOWOW=>[time()+86400,86400]]);
    $db->onEvent[]=function(Dibi\Event $event) {
        if (!Fixture::$duringNetwork && $event->sql==='COMMIT') Fixture::$committed=true;
    };
    if (($argv[1] ?? '') === '--worker') {
        User::$ip=$argv[2]; Fixture::$committed=false;
        echo json_encode(['verified'=>Turnstile::verify('login','synthetic-token'),'network'=>Fixture::$network]); exit;
    }
    if (($argv[1] ?? '') === '--interrupt') {
        $phase=$argv[2];
        $db->onEvent[]=function(Dibi\Event $event) use ($phase) {
            if (($phase==='after' && $event->sql==='COMMIT') ||
                ($phase==='before' && str_starts_with($event->sql,'UPDATE ') && str_contains($event->sql,'captcha-global'))) {
                echo "READY\n"; flush();
                while (true) usleep(10000);              // Parent kills this process; no provider call can begin.
            }
        };
        Turnstile::verify('login','synthetic-token'); exit(2);
    }
    $checks=0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function resetFixture() : void {
        global $db;
        $db->query("DELETE FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'captcha-%'");
        Fixture::$network=0; Fixture::$response='invalid'; Fixture::$committed=false;
        User::$ip='127.0.0.1'; Cfg::$enabled=true;
    }
    function total(string $bucket='captcha-global') : int {
        global $db; return (int)$db->query('SELECT used FROM ::contribution_budget WHERE owner=0 AND bucket=%s',$bucket)->fetchSingle();
    }
    function peerKey() : string {
        global $db; return (string)$db->query("SELECT bucket FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'captcha-ip-%' LIMIT 1")->fetchSingle();
    }
    function peerCount() : int {
        global $db; return (int)$db->query("SELECT COUNT(*) FROM ::contribution_budget WHERE owner=0 AND bucket LIKE 'captcha-ip-%'")->fetchSingle();
    }
    function verify(string $action='login', mixed $token='synthetic-token') : bool {
        Fixture::$committed=false; return Turnstile::verify($action,$token);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($db->query('SHOW TABLES')->fetchPairs() as $table) $db->query('DROP TABLE %n',$table);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    preg_match('/CREATE TABLE IF NOT EXISTS `aowow_contribution_budget` \(.*?\) ENGINE=.*?;/s',file_get_contents($root.'/setup/sql/01-db_structure.sql'),$schema);
    check(!empty($schema),'Budget reference schema available'); $db->nativeQuery($schema[0]);
    // Existing contribution and storage reservations are outside verification cleanup/accounting.
    $db->query("INSERT INTO ::contribution_budget VALUES (7,'comment',3,UNIX_TIMESTAMP()-1),(0,'bytes-text',1024,0),(0,'report',2,UNIX_TIMESTAMP()+3600)");
    $existing=$db->query("SELECT * FROM ::contribution_budget WHERE bucket NOT LIKE 'captcha-%' ORDER BY owner,bucket")->fetchAll();

    $events=0; $db->onEvent[]=function() use (&$events) { ++$events; };
    Cfg::$enabled=false;
    check(verify('login') && $events===0 && Fixture::$network===0,'Disabled CAPTCHA needs neither budget nor provider');
    Cfg::$enabled=true;
    foreach ([null,[],false,'',"bad\n",str_repeat('x',2049)] as $token)
        check(!verify('login',$token) && $events===0 && Fixture::$network===0,'Malformed token makes no database/network work');
    check(!verify('unknown') && $events===0,'Unknown action makes no budget work');
    foreach (['','invalid','127.0.0.1, 192.0.2.1'] as $ip) {
        User::$ip=$ip; check(!verify() && $events===0 && Fixture::$network===0,'Missing/untrusted peer fails closed before reservation');
    }
    resetFixture();
    for ($i=0;$i<TurnstileBudget::PEER_LIMIT;++$i)
        check(!verify(Turnstile::ACTIONS[$i%count(Turnstile::ACTIONS)]),'Invalid tokens are rejected across shared actions');
    $peer=peerKey();
    check(total()===20 && total($peer)===20 && Fixture::$network===20,'Every invalid token consumes both durable allowances');
    $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()+20 WHERE bucket='captcha-global'");
    $saved=$db->query("SELECT * FROM ::contribution_budget WHERE bucket LIKE 'captcha-%' ORDER BY bucket")->fetchAll();
    Fixture::$response='valid';
    foreach (Turnstile::ACTIONS as $action) check(!verify($action),'Switching action cannot evade the peer limit');
    check(Fixture::$network===20 && $db->query("SELECT * FROM ::contribution_budget WHERE bucket LIKE 'captcha-%' ORDER BY bucket")->fetchAll()==$saved,'Quota denial rolls back partial work and does not extend active windows');
    $db->query('UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket=%s',$peer);
    check(verify() && Fixture::$network===21 && total()===21 && total(peerKey())===1,'Expired peer allowance permits a legitimate retry');
    resetFixture();
    $db->query("INSERT INTO ::contribution_budget VALUES (0,'captcha-global',%i,UNIX_TIMESTAMP()+60)",TurnstileBudget::GLOBAL_LIMIT);
    check(!verify() && peerCount()===0 && Fixture::$network===0,'Global limit blocks before peer admission or provider');
    $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket='captcha-global'");
    Fixture::$response='valid';check(verify() && total()===1,'Expired global allowance resets');
    resetFixture();
    User::$ip='::1';check(!verify(),'IPv6 peer charged'); $peer=peerKey();
    User::$ip='0:0:0:0:0:0:0:1';check(!verify() && peerCount()===1 && total($peer)===2,'Canonical IPv6 spellings share one allowance');
    resetFixture();
    User::$ip='::ffff:127.0.0.1';check(!verify(),'Mapped IPv4 peer charged');$peer=peerKey();
    User::$ip='127.0.0.1';check(!verify() && peerCount()===1 && total($peer)===2,'Mapped IPv4 and IPv4 share allowance');
    check(!str_contains($peer,'127.0.0.1') && strlen($peer)===32,'No raw address/token is used as a budget key');

    resetFixture();
    foreach (['outage','throw','invalid'] as $response) {
        Fixture::$response=$response;
        ob_start();$ok=verify();$output=ob_get_clean();
        check(!$ok && $output==='' && total()===Fixture::$network,'Provider failures retain reservations without leaking details');
    }
    Fixture::$response='valid';check(verify() && total()===4,'Valid retries after provider failures retain normal authority');
    resetFixture();
    $db->query('RENAME TABLE ::contribution_budget TO ::missing_budget');
    ob_start();$ok=verify();$output=ob_get_clean();
    check(!$ok && $output==='' && Fixture::$network===0,'Missing budget schema denies without provider/SQL disclosure');
    $db->query('RENAME TABLE ::missing_budget TO ::contribution_budget');
    $db->nativeQuery("CREATE TRIGGER captcha_fail BEFORE UPDATE ON aowow_contribution_budget FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='PRIVATE_SQL_SENTINEL'");
    ob_start();$ok=verify();$output=ob_get_clean();
    check(!$ok && $output==='' && Fixture::$network===0 && total()===0 && peerCount()===0,'SQL failure rolls back both budgets and makes no provider call');
    $db->query('DROP TRIGGER captcha_fail');
    $ack=function(Dibi\Event $event) { if ($event->sql==='COMMIT') throw new RuntimeException('uncertain acknowledgement'); };
    $db->onEvent[]=$ack;
    check(!verify() && Fixture::$network===0,'Lost commit acknowledgment denies verification');array_pop($db->onEvent);
    check(total()===1 && total(peerKey())===1,'Lost acknowledgment conservatively retains atomic reservations');

    foreach (['before','after'] as $phase) {
        resetFixture();
        $process=proc_open([PHP_BINARY,__FILE__,'--interrupt',$phase],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        fclose($pipes[0]);stream_set_blocking($pipes[1],false);$ready='';
        for($i=0;$i<300 && !str_contains($ready,"READY\n");++$i) { $ready.=stream_get_contents($pipes[1]);usleep(10000); }
        $killed=proc_terminate($process,9);
        fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($process);
        check(str_contains($ready,"READY\n") && $killed && $error==='','Hard interruption reached '.$phase.'-commit boundary');
        check(total()===($phase==='before' ? 0 : 1) && peerCount()===($phase==='before' ? 0 : 1),'Interruption rolls back uncommitted work and preserves committed charges');
        Fixture::$response='valid';check(verify() && total()===($phase==='before' ? 1 : 2),'Interruption releases locks for a legitimate retry');
    }

    // Hard peer-key cap, bounded reclamation, and existing keys at capacity.
    resetFixture();
    $rows=['owner'=>[], 'bucket'=>[], 'used'=>[], 'expires'=>[]];
    for($i=0;$i<TurnstileBudget::MAX_PEERS;++$i) {
        $rows['owner'][]=0; $rows['bucket'][]='captcha-ip-'.str_pad((string)$i,21,'0',STR_PAD_LEFT);
        $rows['used'][]=0; $rows['expires'][]=time()+3600;
    }
    $db->query('INSERT INTO ::contribution_budget %m',$rows);
    check(!verify() && peerCount()===4096 && Fixture::$network===0 && total()===0,'Hard cap prevents new peer keys even without prune');
    $db->query('UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()-1 WHERE bucket LIKE %s LIMIT 10','captcha-ip-%');
    check(!verify() && Fixture::$network===1 && peerCount()===4089,'One request reclaims at most eight expired peer keys before admission');
    check($db->query("SELECT * FROM ::contribution_budget WHERE bucket NOT LIKE 'captcha-%' ORDER BY owner,bucket")->fetchAll()==$existing,'Online cleanup preserves unrelated work and permanent byte reservations');
    $current='captcha-ip-'.substr(hash('sha256',inet_pton(User::$ip)),0,21);
    $db->query("UPDATE ::contribution_budget SET expires=UNIX_TIMESTAMP()+3600 WHERE bucket LIKE 'captcha-ip-%'");
    for($i=0;$i<7;++$i) $db->query('INSERT INTO ::contribution_budget VALUES (0,%s,0,UNIX_TIMESTAMP()+3600)','captcha-ip-extra'.$i);
    check(peerCount()===4096 && !verify() && Fixture::$network===2 && total($current)===2,'Existing peer can retry at key capacity');

    function race(array $peers) : array {
        $children=[];
        foreach($peers as $peer) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker',$peer],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
            fclose($pipes[0]);$children[]=[$process,$pipes];
        }
        $results=[];
        foreach($children as [$process,$pipes]) {
            $out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            check(proc_close($process)===0,'Independent verification worker completed: '.$error);
            $results[]=json_decode($out,true,flags:JSON_THROW_ON_ERROR);
        }
        return $results;
    }
    resetFixture();check(!verify(),'Initial peer allowance created');$peer=peerKey();
    $db->query('UPDATE ::contribution_budget SET used=%i WHERE bucket=%s',TurnstileBudget::PEER_LIMIT-1,$peer);
    $db->query("UPDATE ::contribution_budget SET used=0 WHERE bucket='captcha-global'");
    $results=race(array_fill(0,8,'127.0.0.1'));
    check(array_sum(array_column($results,'network'))===1 && total()===1 && total($peer)===20,'Concurrent workers cannot exceed the final peer slot');
    resetFixture();
    $db->query("INSERT INTO ::contribution_budget VALUES (0,'captcha-global',119,UNIX_TIMESTAMP()+60)");
    $results=race(array_map(fn($i)=>'192.0.2.'.$i,range(1,8)));
    check(array_sum(array_column($results,'network'))===1 && total()===120 && peerCount()===1,'Concurrent distinct peers cannot exceed the global slot');
    resetFixture();
    foreach($rows as &$column) array_pop($column); unset($column);
    $db->query('INSERT INTO ::contribution_budget %m',$rows);
    $results=race(array_map(fn($i)=>'192.0.2.'.$i,range(1,8)));
    check(array_sum(array_column($results,'network'))===1 && peerCount()===4096,'Concurrent distinct peers cannot exceed the final key slot');

    // Existing CLI pruning reclaims verification rows; it does not treat them as permanent storage.
    resetFixture();
    $db->query('CREATE TABLE ::errors (date int) ENGINE=InnoDB');
    $db->query('CREATE TABLE ::account_password_budget (expires int) ENGINE=InnoDB');
    $db->query('CREATE TABLE ::screenshot_uploads (expires int) ENGINE=InnoDB');
    $db->query("INSERT INTO ::contribution_budget VALUES (0,'captcha-global',1,UNIX_TIMESTAMP()-1),(0,'captcha-ip-expired',1,UNIX_TIMESTAMP()-1),(0,'captcha-ip-live',1,UNIX_TIMESTAMP()+3600)");
    check(Retention::database(false)['contribution_budget']===3,'Prune preview includes expired verification rows and ordinary expiring work');
    check(Retention::database(true)['contribution_budget']===3 && total('captcha-ip-live')===1 && total('bytes-text')===1024,'Prune removes only expired work; live/permanent reservations survive');
    echo "PASS: $checks Turnstile SQL/admission/concurrency/provider/failure/key-cap/retention checks\n";
}
