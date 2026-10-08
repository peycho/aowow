<?php

// Synthetic loopback SMTP only. No application credentials, external email or database.
namespace Aowow {
    class Cfg {
        public static function applyToString(string $text, bool $formatNumbers=true) : string { return $text; }
        public static function get(string $key) : mixed {
            return match ($key) {
                'DEBUG'=>(int)(getenv('AOWOW_SMTP_TEST_DEBUG') ?: 0),
                'NAME','NAME_SHORT'=>'Fixture ✨', 'CONTACT_EMAIL'=>'contact@example.test',
                'HOST_URL','STATIC_URL'=>'https://site.example.test/', 'LOCALES'=>0x15D, default=>0
            };
        }
    }
    class User {
        public static Locale $preferedLoc;
        public static function isInGroup(int $mask) : bool { return true; }
    }
    function mail(string $recipient, string $subject, string $body, string $headers) : bool {
        $GLOBALS['native'][]=[$recipient,$subject,$body,$headers];
        return !(getenv('AOWOW_SMTP_TEST_NATIVE_FAIL') ?: false);
    }
}
namespace {
    define('AOWOW_REVISION',69); define('CLI',true);
    $root=dirname(__DIR__); chdir($root);
    if (($argv[1] ?? '')==='--server') {
        [$unused,$unused,$fixture,$mode]=$argv;
        $ctx=stream_context_create(['ssl'=>['local_cert'=>$fixture.'/ca.pem','local_pk'=>$fixture.'/key.pem']]);
        $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$error,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,$ctx);
        if (!$server) exit(1);
        file_put_contents($fixture.'/ready',substr(strrchr(stream_socket_get_name($server,false),':'),1));
        $client=stream_socket_accept($server,15);
        $capture=['commands'=>[], 'secure'=>false, 'auth'=>null, 'data'=>''];
        if (!$client) exit(1);
        stream_set_timeout($client,4);
        try {
            if ($mode==='ssl') {
                if (!@stream_socket_enable_crypto($client,true,STREAM_CRYPTO_METHOD_TLS_SERVER)) exit(0);
                $capture['secure']=true;
            }
            if ($mode==='slow') sleep(4);
            @fwrite($client,"220 fixture ESMTP\r\n");
            while (($line=fgets($client))!==false) {
                $command=rtrim($line,"\r\n"); $capture['commands'][]=$command;
                if (str_starts_with($command,'EHLO') || str_starts_with($command,'HELO')) {
                    @fwrite($client,"250-fixture\r\n".($mode==='no-tls'?'':"250-STARTTLS\r\n")."250 AUTH LOGIN PLAIN\r\n");
                } elseif ($command==='STARTTLS') {
                    if ($mode==='no-tls') { @fwrite($client,"502 no TLS\r\n"); continue; }
                    @fwrite($client,"220 start TLS\r\n");
                    if (!@stream_socket_enable_crypto($client,true,STREAM_CRYPTO_METHOD_TLS_SERVER)) break;
                    $capture['secure']=true;
                } elseif ($command==='AUTH LOGIN') {
                    @fwrite($client,"334 VXNlcm5hbWU6\r\n"); $username=base64_decode(trim(fgets($client)),true);
                    @fwrite($client,"334 UGFzc3dvcmQ6\r\n"); $password=base64_decode(trim(fgets($client)),true);
                    $capture['auth']=[$username,$password,$capture['secure']];
                    @fwrite($client,$mode==='authfail'?"535 SECRET_PROVIDER_RESPONSE\r\n":"235 authenticated\r\n");
                } elseif (str_starts_with($command,'MAIL FROM:')) {
                    @fwrite($client,"250 sender accepted\r\n");
                } elseif (str_starts_with($command,'RCPT TO:')) {
                    @fwrite($client,$mode==='rcptfail'?"550 SECRET_PROVIDER_RESPONSE\r\n":"250 recipient accepted\r\n");
                } elseif ($command==='DATA') {
                    @fwrite($client,"354 send message\r\n");
                    while (($line=fgets($client))!==false && $line!==".\r\n") $capture['data'].=$line;
                    @fwrite($client,$mode==='datafail'?"554 SECRET_PROVIDER_RESPONSE\r\n":"250 message accepted\r\n");
                } elseif ($command==='QUIT') {
                    @fwrite($client,"221 bye\r\n"); break;
                } elseif ($command==='RSET') {
                    @fwrite($client,"250 reset\r\n");
                } else @fwrite($client,"502 unsupported\r\n");
            }
        } finally {
            file_put_contents($fixture.'/capture',json_encode($capture,JSON_THROW_ON_ERROR));
            fclose($client); fclose($server);
        }
        exit;
    }

    require $root.'/includes/defines.php';
    require $root.'/includes/libs/autoload.php';
    require $root.'/includes/locale.class.php';
    require $root.'/localization/lang.class.php';
    require $root.'/localization/datetime.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/game/uitext.class.php';
    require $root.'/includes/components/guidemgr.class.php';
    foreach (['SmartAI','SmartEvent','SmartAction','SmartTarget'] as $class)
        require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    set_error_handler(function (int $code,string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
        throw new ErrorException($message,0,$code);
    });
    if (($argv[1] ?? '')==='--send') {
        if (($config=getenv('AOWOW_SMTP_TEST_CONFIG'))!==false) define('AOWOW_MAIL',json_decode($config,true,flags:JSON_THROW_ON_ERROR));
        $locale=Aowow\Locale::from((int)(getenv('AOWOW_SMTP_TEST_LOCALE') ?: 0));
        Aowow\Lang::load($locale); Aowow\User::$preferedLoc=$locale; $GLOBALS['native']=[];
        $success=Aowow\Util::sendMail('recipient@example.test','activate-account',['TOKEN_SENTINEL'],3600);
        echo json_encode(['success'=>$success,'native'=>$GLOBALS['native'],'notes'=>Aowow\Util::getNotes()],JSON_THROW_ON_ERROR);
        exit;
    }
    $checks=0; $workers=[]; $fixture=sys_get_temp_dir().'/aowow-smtp-'.bin2hex(random_bytes(8)); mkdir($fixture,0700);
    function check(bool $ok,string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    function send(mixed $config, array $extra=[], bool $defined=true) : array {
        $env=getenv(); unset($env['AOWOW_SMTP_TEST_CONFIG'],$env['AOWOW_SMTP_TEST_DEBUG'],$env['AOWOW_SMTP_TEST_NATIVE_FAIL'],$env['AOWOW_SMTP_TEST_LOCALE']);
        if ($defined) $env['AOWOW_SMTP_TEST_CONFIG']=json_encode($config,JSON_THROW_ON_ERROR);
        $env=array_replace($env,$extra);
        $start=microtime(true);
        $process=proc_open([PHP_BINARY,__FILE__,'--send'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
        fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process)===0,'Mail worker exits without an uncaught error');
        return [json_decode($out,true,flags:JSON_THROW_ON_ERROR),$err,microtime(true)-$start];
    }
    function relay(string $mode, array $overrides=[]) : array {
        global $fixture,$workers;
        foreach (['ready','capture'] as $file) if (is_file($fixture.'/'.$file)) unlink($fixture.'/'.$file);
        $server=proc_open([PHP_BINARY,__FILE__,'--server',$fixture,$mode],[['pipe','r'],['file',$fixture.'/server.log','a'],['file',$fixture.'/server.log','a']],$pipes);
        fclose($pipes[0]); $workers[]=$server;
        $deadline=microtime(true)+3;
        while (!is_file($fixture.'/ready') && microtime(true)<$deadline) usleep(10000);
        check(is_file($fixture.'/ready'),'Loopback SMTP fixture ready');
        $config=['transport'=>'smtp','host'=>'localhost','port'=>(int)file_get_contents($fixture.'/ready'),
            'encryption'=>$mode==='ssl'?'ssl':'tls','auth'=>true,'username'=>'USER_SENTINEL','password'=>'PASSWORD_SENTINEL',
            'auth_type'=>'LOGIN','timeout'=>1,'command_timeout'=>1,'ca_file'=>$fixture.'/ca.pem'];
        [$result,$err,$elapsed]=send(array_replace($config,$overrides));
        $deadline=microtime(true)+6;
        while (!is_file($fixture.'/capture') && microtime(true)<$deadline) usleep(10000);
        check(is_file($fixture.'/capture'),'SMTP capture completed');
        $capture=json_decode(file_get_contents($fixture.'/capture'),true,flags:JSON_THROW_ON_ERROR);
        check(!$result['native'],'SMTP never falls back to native mail');
        check(!str_contains($err,'PASSWORD_SENTINEL') && !str_contains($err,'USER_SENTINEL') &&
            !str_contains($err,'TOKEN_SENTINEL') && !str_contains($err,'SECRET_PROVIDER_RESPONSE') &&
            !str_contains($err,'recipient@example.test'),'Failure diagnostics omit secrets and message/provider data');
        return [$result,$capture,$err,$elapsed];
    }
    try {
        $key=openssl_pkey_new(['private_key_bits'=>2048]);
        $csr=openssl_csr_new(['commonName'=>'localhost'],$key,['digest_alg'=>'sha256']);
        $cert=openssl_csr_sign($csr,null,$key,1,['digest_alg'=>'sha256']);
        openssl_x509_export_to_file($cert,$fixture.'/ca.pem'); openssl_pkey_export_to_file($key,$fixture.'/key.pem');
        foreach ([null,[],['transport'=>'mail']] as $index=>$config) {
            [$result,$err]=send($config,[], $index!==0);
            check($result['success'] && count($result['native'])===1 && $err==='','Missing/default/explicit native mail remains compatible');
        }
        [$result]=send(['transport'=>'mail'],['AOWOW_SMTP_TEST_NATIVE_FAIL'=>'1']);
        check(!$result['success'],'Native mail failure remains a form failure');
        foreach ([0,2,3,4,6,8] as $locale) {
            [$result]=send(['transport'=>'mail'],['AOWOW_SMTP_TEST_LOCALE'=>(string)$locale]);
            check($result['success'] && str_contains($result['native'][0][2],'TOKEN_SENTINEL') && str_contains($result['native'][0][2],'https://site.example.test/'),'Localized templates retain tokens and site URL');
        }
        [$result]=send(['transport'=>'disabled']);
        check(!$result['success'] && !$result['native'],'Disabled transport sends nothing and reports failure');
        [$result]=send(['transport'=>'smtp','host'=>'invalid'],['AOWOW_SMTP_TEST_DEBUG'=>(string)LOG_LEVEL_INFO]);
        check($result['success'] && !$result['native'] && str_contains(json_encode($result['notes']),'TOKEN_SENTINEL'),'Existing Info debug preview bypasses SMTP');
        foreach ([false,['transport'=>'unknown'],['transport'=>'smtp'],
            ['transport'=>'smtp','host'=>'smtp://localhost'],['transport'=>'smtp','host'=>'localhost;other.test'],
            ['transport'=>'smtp','host'=>"localhost\n"],['transport'=>'smtp','host'=>'localhost','port'=>0],
            ['transport'=>'smtp','host'=>'localhost','port'=>'587'],['transport'=>'smtp','host'=>'localhost','encryption'=>'bad'],
            ['transport'=>'smtp','host'=>'localhost','auth'=>true,'encryption'=>'none','username'=>'USER_SENTINEL','password'=>'PASSWORD_SENTINEL'],
            ['transport'=>'smtp','host'=>'localhost','username'=>'USER_SENTINEL','password'=>"PASSWORD_SENTINEL\r\n"],
            ['transport'=>'smtp','host'=>'localhost','auth'=>false,'timeout'=>31],
            ['transport'=>'smtp','host'=>'localhost','auth'=>false,'from_email'=>"mail@example.test\r\nBcc: other@example.test"],
            ['transport'=>'smtp','host'=>'localhost','auth'=>false,'ca_file'=>'/missing/synthetic-ca.pem']] as $config) {
            [$result,$err]=send($config);
            check(!$result['success'] && !$result['native'] && str_contains($err,'configuration') && !str_contains($err,'PASSWORD_SENTINEL'),'Invalid SMTP configuration fails closed without native fallback or secret output');
        }
        foreach (['tls','ssl'] as $mode) {
            [$result,$capture,$err]=relay($mode,['from_email'=>'sender@example.test','from_name'=>'Sender ✨','reply_to'=>'reply@example.test']);
            check($result['success'] && $err==='','Authenticated '.$mode.' delivery succeeds');
            check($capture['auth']===['USER_SENTINEL','PASSWORD_SENTINEL',true],'Authentication only happens after encryption');
            $data=quoted_printable_decode($capture['data']);
            check(str_contains($data,'TOKEN_SENTINEL') && str_contains($data,'https://site.example.test/'),'SMTP retains rendered activation token and URL');
            check(str_contains($data,'From:') && str_contains($data,'sender@example.test') && str_contains($data,'Reply-To:') && str_contains($data,'reply@example.test') && stripos($data,'charset=utf-8')!==false,'SMTP uses sender/reply-to overrides and UTF-8 plain text');
            check(str_contains(mb_decode_mimeheader($capture['data']),'Fixture ✨'),'Non-ASCII subject survives MIME encoding');
            check(count(array_filter($capture['commands'],fn($command)=>$command==='DATA'))===1,'Successful mail is submitted exactly once');
        }
        [$result,$capture]=relay('plain',['encryption'=>'none','auth'=>false]);
        check($result['success'] && $capture['auth']===null && !$capture['secure'],'Explicit unauthenticated local relay works without authentication');
        check(str_contains($capture['data'],'contact@example.test'),'Absent SMTP sender overrides retain CONTACT_EMAIL');
        [$result,$capture]=relay('tls',['auth_type'=>'']);
        check($result['success'] && $capture['auth']===['USER_SENTINEL','PASSWORD_SENTINEL',true],'Default authentication selection works after STARTTLS');
        $reservation=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        $closedPort=(int)substr(strrchr(stream_socket_get_name($reservation,false),':'),1); fclose($reservation);
        [$result,$err,$elapsed]=send(['transport'=>'smtp','host'=>'127.0.0.1','port'=>$closedPort,'auth'=>false,'timeout'=>1,'command_timeout'=>1]);
        check(!$result['success'] && !$result['native'] && str_contains($err,'SMTP') && $elapsed<3,'Refused SMTP connection fails promptly without native fallback');
        foreach (['authfail','rcptfail','datafail','no-tls'] as $mode) {
            [$result,$capture,$err]=relay($mode);
            check(!$result['success'] && str_contains($err,'SMTP'),'Relay failure is safely reported for '.$mode);
            if ($mode==='no-tls') check($capture['auth']===null && $capture['data']==='','Missing STARTTLS never downgrades or sends credentials');
        }
        foreach ([['ca_file'=>''],['host'=>'127.0.0.1']] as $overrides) {
            [$result,$capture]=relay('tls',$overrides);
            check(!$result['success'] && $capture['auth']===null && $capture['data']==='','Untrusted or mismatched TLS certificate is rejected before authentication');
        }
        [$result,$capture,$err,$elapsed]=relay('slow');
        check(!$result['success'] && $elapsed<4,'Slow SMTP greeting is bounded by configured timeouts');
        echo "PASS: $checks SMTP/native/disabled/templates/TLS/auth/failure/timeout checks\n";
    } finally {
        foreach ($workers as $worker) { if (is_resource($worker)) { proc_terminate($worker); proc_close($worker); } }
        foreach (glob($fixture.'/*') as $file) unlink($file);
        rmdir($fixture);
    }
}
