<?php

// Real bounded cURL/TLS to a disposable loopback Siteverify service; no real keys or application DB.
namespace Aowow {
    class Cfg {
        public static array $flags = [];
        public static string $host = 'https://example.test';
        public static function get(string $key) : mixed {
            return self::$flags[$key] ?? match ($key) {
                'HOST_URL' => self::$host, 'FEEDBACK_ENABLE' => 1, 'ACC_AUTH_MODE' => AUTH_MODE_SELF, default => 0
            };
        }
    }
    class Lang {
        public static function main(string $key) : string { return $key; }
        public static function account(mixed ...$args) : string { return 'account-error'; }
    }
    class User {
        public static string $ip = '127.0.0.1';
        public static function authenticate(mixed ...$args) : never { throw new \RuntimeException('AUTH_REACHED'); }
    }
    class DB {
        public static function Aowow() : self { return new self; }
        public function selectCell(mixed ...$args) : never { throw new \RuntimeException('DB_REACHED'); }
        public function selectRow(mixed ...$args) : never { throw new \RuntimeException('DB_REACHED'); }
    }
    class Report {
        public const int MODE_GENERAL = 0, ERR_LIMIT = 8, ERR_INVALID_CAPTCHA = 1;
        private int $mode;
        public static function canCreateContent() : bool { return true; }
        public function __construct(int $mode, mixed ...$args) { $this->mode = $mode; }
        // Persistence is a sentinel here; real Report and feedback admission execute in SQL fixtures.
        public function create(mixed ...$args) : bool {
            if ($this->mode === 0 && !Turnstile::verify('feedback', $args[6] ?? null)) return false;
            throw new \RuntimeException('REPORT_REACHED');
        }
        public function getError() : int { return self::ERR_INVALID_CAPTCHA; }
    }
    // Transport/handler tests spy on admission; real transactions have a separate SQL fixture.
    class TurnstileBudget {
        public static int $calls = 0;
        public static bool $allowed = true;
        public static function reserve() : bool { ++self::$calls; return self::$allowed; }
    }
    class Fixture {
        public static int $calls = 0;
        public static array $options = [];
        public static bool $badCert = false, $dns = true;
    }
    function curl_version() : array {
        $v = \curl_version(); if (!Fixture::$dns) $v['features'] &= ~CURL_VERSION_ASYNCHDNS; return $v;
    }
    function curl_init(string $url) : \CurlHandle|false {
        if ($url !== 'https://challenges.cloudflare.com/turnstile/v0/siteverify') throw new \RuntimeException('Unexpected origin');
        Fixture::$calls++;
        return \curl_init('https://localhost:'.getenv('AOWOW_TEST_TURNSTILE_PORT').'/siteverify');
    }
    function curl_setopt_array(\CurlHandle $handle, array $options) : bool {
        Fixture::$options = $options;
        $options[CURLOPT_CAINFO] = Fixture::$badCert ? '/nonexistent-fixture-ca' : getenv('AOWOW_TEST_TURNSTILE_CA');
        $options[CURLOPT_PROXY] = '';
        return \curl_setopt_array($handle, $options);
    }
}

namespace {
    use Aowow\{Cfg, Fixture, Turnstile, TurnstileBudget};
    define('AOWOW_REVISION', 71); define('CLI', true);
    require __DIR__.'/../includes/defines.php';
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/components/turnstile.class.php';
    foreach (Turnstile::ACTIONS as $action) Cfg::$flags['TURNSTILE_'.strtoupper($action).'_ENABLE'] = 1;
    $variant = $argv[1] ?? '';
    define('AOWOW_TURNSTILE_SITE_KEY', $variant === '--missing-site' ? '' : 'SITE_FIXTURE');
    define('AOWOW_TURNSTILE_SECRET_KEY', $variant === '--missing-secret' ? '' : ($variant === '--invalid-secret' ? ['invalid'] : 'SECRET_FIXTURE'));
    if (in_array($variant, ['--missing-site', '--missing-secret', '--invalid-secret'], true)) {
        exit(!Turnstile::verify('login', 'valid-token') && !Fixture::$calls && !TurnstileBudget::$calls ? 0 : 1);
    }
    if ($variant === '--server') {
        $ctx = stream_context_create(['ssl'=>['local_cert'=>$argv[2], 'local_pk'=>$argv[3]]]);
        $server = stream_socket_server('tls://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
        if (!$server) exit(2);
        file_put_contents($argv[4], substr(strrchr(stream_socket_get_name($server, false), ':'), 1));
        $used = [];
        while (true) {
            $client = @stream_socket_accept($server, 20);
            if (!$client) continue;                        // a rejected TLS handshake must not stop the fixture
            $length = 0;
            while (($line = fgets($client)) !== false && trim($line) !== '') {
                if (stripos($line, 'Content-Length:') === 0) $length = (int)trim(substr($line, 15));
            }
            $data = ''; while (strlen($data) < $length && !feof($client)) $data .= fread($client, $length - strlen($data));
            parse_str($data, $post); $token = $post['response'] ?? '';
            $action = substr($token, strpos($token, '-') + 1);
            $result = ['success'=>true, 'hostname'=>'example.test', 'action'=>$action];
            $status = 200; $extra = ''; $body = null;
            if (($post['secret'] ?? '') !== 'SECRET_FIXTURE') $result['success'] = false;
            if (str_starts_with($token, 'once-')) { $result['success'] = !isset($used[$token]); $used[$token] = true; }
            switch ($token) {
                case 'expired': case 'replayed': $result = ['success'=>false, 'error-codes'=>['timeout-or-duplicate']]; break;
                case 'wrong-host': $result['hostname'] = 'other.test'; $result['action'] = 'login'; break;
                case 'wrong-action': $result['action'] = 'registration'; break;
                case 'truthy': $result['success'] = 1; $result['action'] = 'login'; break;
                case 'missing-host': unset($result['hostname']); break;
                case 'invalid-json': $body = '{invalid'; break;
                case 'scalar-json': $body = 'true'; break;
                case 'oversize': $body = str_repeat('x', 8193); break;
                case 'huge-header': $extra = 'X-Huge: '.str_repeat('x', 17000)."\r\n"; break;
                case 'redirect': $status = 302; $extra = "Location: https://localhost/SECRET_REDIRECT\r\n"; break;
                case 'unavailable': $status = 503; break;
                case 'slow': sleep(7); break;
            }
            $body ??= json_encode($result);
            @fwrite($client, "HTTP/1.1 $status Fixture\r\nContent-Type: application/json\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n".$extra."\r\n".$body);
            fclose($client);
        }
        exit;
    }

    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
    $root = sys_get_temp_dir().'/aowow-turnstile-'.bin2hex(random_bytes(8)); mkdir($root, 0700);
    $process = null;
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException('Unexpected fixture warning', 0, $level); });
    try {
        foreach (['--missing-site', '--missing-secret', '--invalid-secret'] as $case) {
            $child = proc_open([PHP_BINARY, __FILE__, $case], [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);
            foreach ($pipes as $pipe) fclose($pipe);
            check(proc_close($child) === 0, 'Enabled forms reject missing/malformed keys without network work');
        }
        $key = openssl_pkey_new(['private_key_bits'=>2048]);
        $csr = openssl_csr_new(['commonName'=>'localhost'], $key, ['digest_alg'=>'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg'=>'sha256']);
        openssl_x509_export_to_file($cert, $root.'/ca.pem'); openssl_pkey_export_to_file($key, $root.'/key.pem');
        $process = proc_open([PHP_BINARY, __FILE__, '--server', $root.'/ca.pem', $root.'/key.pem', $root.'/ready'],
            [['pipe','r'], ['file',$root.'/server.log','a'], ['file',$root.'/server.log','a']], $pipes); fclose($pipes[0]);
        for ($i=0; $i<200 && !is_file($root.'/ready'); $i++) usleep(10000);
        check(is_file($root.'/ready'), 'Disposable TLS verifier ready');
        putenv('AOWOW_TEST_TURNSTILE_PORT='.trim(file_get_contents($root.'/ready'))); putenv('AOWOW_TEST_TURNSTILE_CA='.$root.'/ca.pem');
        foreach (Turnstile::ACTIONS as $action) {
            check(Turnstile::verify($action, 'v-'.$action), 'Valid token for its form action');
            Cfg::$flags['TURNSTILE_'.strtoupper($action).'_ENABLE'] = 0;
            $calls = Fixture::$calls;
            check(Turnstile::verify($action, null) && Fixture::$calls === $calls, 'Disabled form has no outbound verification');
            Cfg::$flags['TURNSTILE_'.strtoupper($action).'_ENABLE'] = 1;
        }
        $options = Fixture::$options;
        parse_str($options[CURLOPT_POSTFIELDS], $sent);
        check($sent === ['secret'=>'SECRET_FIXTURE', 'response'=>'v-feedback'], 'Siteverify receives only secret and token, not account or email values');
        check($options[CURLOPT_PROTOCOLS] === CURLPROTO_HTTPS && !$options[CURLOPT_FOLLOWLOCATION] && $options[CURLOPT_POST], 'Fixed HTTPS POST never follows redirects');
        check($options[CURLOPT_SSL_VERIFYPEER] && $options[CURLOPT_SSL_VERIFYHOST] === 2 && $options[CURLOPT_TIMEOUT_MS] === 5000, 'TLS verification and bounded deadline retained');
        $calls = Fixture::$calls;
        foreach ([null, [], false, '', "bad\n", str_repeat('x', 2049)] as $token) check(!Turnstile::verify('login', $token), 'Malformed token denied');
        check(!Turnstile::verify('unknown', 'token') && Fixture::$calls === $calls, 'Malformed inputs never contact Cloudflare');
        foreach (['expired','replayed','wrong-host','wrong-action','truthy','missing-host','invalid-json','scalar-json','oversize','huge-header','redirect','unavailable'] as $token)
            check(!Turnstile::verify('login', $token), 'Untrusted verifier response denied: '.$token);
        check(Turnstile::verify('login', 'once-login') && !Turnstile::verify('login', 'once-login'), 'Consumed token cannot be replayed');
        Fixture::$badCert = true; check(!Turnstile::verify('login', 'v-login'), 'Certificate failures deny submission'); Fixture::$badCert = false;
        Fixture::$dns = false; $calls = Fixture::$calls; check(!Turnstile::verify('login', 'v-login') && Fixture::$calls === $calls, 'Unbounded DNS transport denied'); Fixture::$dns = true;
        Cfg::$host = ''; check(!Turnstile::verify('login', 'v-login'), 'Missing configured hostname denied'); Cfg::$host = 'https://EXAMPLE.TEST:8080';
        check(Turnstile::verify('login', 'v-login'), 'Hostname match ignores case and origin port');
        check(!str_contains(json_encode(Turnstile::clientConfig()), 'SECRET_FIXTURE'), 'Client config cannot contain secret');
        $network = Fixture::$calls; $budget = TurnstileBudget::$calls;
        foreach ([null, [], false, '', "bad\n", str_repeat('x', 2049)] as $token)
            check(!Turnstile::verify('login', $token), 'Malformed token denied before reservation');
        check(TurnstileBudget::$calls === $budget && Fixture::$calls === $network, 'Malformed tokens use no database budget or network');
        TurnstileBudget::$allowed = false;
        foreach (Turnstile::ACTIONS as $action)
            check(!Turnstile::verify($action, 'v-'.$action) && Fixture::$calls === $network, 'Budget denial makes no network call: '.$action);
        TurnstileBudget::$allowed = true;
        check(Turnstile::verify('login', 'v-login'), 'Normal verification still works after admission recovers');

        require __DIR__.'/../includes/components/response/baseresponse.class.php';
        require __DIR__.'/../includes/components/response/textresponse.class.php';
        require __DIR__.'/turnstile-handlers.php';
        $start = microtime(true);
        check(!Turnstile::verify('login', 'slow') && microtime(true)-$start < 6.5, 'Slow verifier fails within its deadline');
        echo "PASS: $checks Turnstile TLS/token/action/hostname/form-boundary checks\n";
    } finally {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
        foreach (glob($root.'/*') as $file) unlink($file); rmdir($root);
    }
}
