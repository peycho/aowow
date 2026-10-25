<?php

// Real front-controller redirects and CSRF policy, with a disposable synthetic kernel.
// No application configuration, retained database, external HTTP or session is used.
namespace {
    use Aowow\RequestUrl;

    require __DIR__.'/../includes/components/requesturl.class.php';

    $checks = 0;
    function check(bool $ok, string $label) : void
    {
        global $checks;
        ++$checks;
        if (!$ok)
            throw new RuntimeException($label);
    }

    $entities = ['npc' => 28692, 'object' => 3948, 'item' => 19019, 'itemset' => 1,
        'quest' => 7786, 'spell' => 133, 'zone' => 4395, 'faction' => 529, 'pet' => 1,
        'achievement' => 1, 'title' => 1, 'event' => 1, 'class' => 1, 'race' => 1,
        'skill' => 164, 'currency' => 1, 'sound' => 1, 'icon' => 1, 'emote' => 1,
        'enchantment' => 1, 'mail' => 1];
    foreach ($entities as $route => $id)
        foreach (['GET', 'HEAD'] as $method)
            foreach ([0, 2, 3, 4, 6, 8] as $locale)
            {
                $target = RequestUrl::localeFirstRedirect($method, 'locale='.$locale.'&'.$route.'='.$id);
                check($target === '?'.$route.'='.$id.'&locale='.$locale, 'public entity and locale identity survives reordering');
                check(RequestUrl::localeFirstRedirect($method, substr($target, 1)) === null, 'redirect target cannot loop');
            }

    foreach ([
        'locale=03&object=3948' => '?object=3948&locale=03',
        'locale=99&object=3948' => '?object=3948&locale=99', // Normal loader decides enabled language/fallback.
        'locale=3&utm_source=one+two&object=3948&gclid=x%2By' => '?object=3948&locale=3&utm_source=one+two&gclid=x%2By',
        'locale=3&object=3948&utm_content=%E2%98%83%26x%3D1%23part' => '?object=3948&locale=3&utm_content=%E2%98%83%26x%3D1%23part',
        'locale=3&fbclid=abc&object=3948&msclkid=def&utm_campaign=a=b' => '?object=3948&locale=3&fbclid=abc&msclkid=def&utm_campaign=a=b',
    ] as $query => $expected)
        check(RequestUrl::localeFirstRedirect('GET', $query) === $expected, 'preserve numeric locale and original tracking encoding');

    $unchanged = [
        '', 'locale=3', 'locale=3&', 'object=3948&locale=3', 'utm_source=x&locale=3&object=3948',
        'locale=3&object=3948&locale=2', 'locale=3&object=3948&object=1',
        'locale=3&object=3948&item=19019', 'locale=3&object=3948&utm_source=a&utm_source=b',
        'locale[]=3&object=3948', 'locale=3&object[]=3948', 'locale=3&object=3948&locale[]=2',
        'locale=3&%6fbject=3948', '%6cocale=3&object=3948', 'locale=3&object=3948&%6cocale=2',
        'locale=3&object=3948&object.foo=1', 'locale=3&object=3948&object%00=x',
        'locale=3&object=3948&Object=1', 'locale=3&Object=3948',
        'locale=abc&object=3948', 'locale=-1&object=3948', 'locale=123&object=3948',
        'locale=3&object=0', 'locale=3&object=-1', 'locale=3&object=03948',
        'locale=3&object=1e3', 'locale=3&object=3948/slug', 'locale=3&object=12345678901',
        'locale=3&object=https://elsewhere.example', 'locale=3&object=', 'locale=3&object',
        'locale=3&object=3948&power', 'locale=3&object=3948&json=1',
        'locale=3&object=3948&xml', 'locale=3&object=3948&rss', 'locale=3&object=3948&playlist',
        'locale=3&object=3948&opensearch', 'locale=3&object=3948&new', 'locale=3&object=3948&action=delete',
        'locale=3&object=3948&refresh=filecache', 'locale=3&object=3948&rev=1',
        'locale=3&object=3948&filter=x', 'locale=3&object=3948&page=2',
        'locale=3&account=signout', 'locale=3&admin=siteconfig&action=update',
        'locale=3&guide=1', 'locale=3&guide=edit', 'locale=3&profile=1',
        'locale=3&areatrigger=1', 'locale=3&items=2.1', 'locale=3&search=test',
        'locale=3;object=3948', 'locale=3&object=3948;utm_source=x',
        "locale=3&object=3948&utm_source=\r\nLocation: https://elsewhere.example",
        'locale=3&object=3948&utm_source=%0d%0aLocation%3Aevil',
        'locale=3&object=3948&utm_source=%00', 'locale=3&object=3948&utm_source=%7F',
        'locale=3&object=3948&utm_source=%5c', 'locale=3&object=3948&utm_source=%GG',
        'locale=3&object=3948&utm_source=%', 'locale=3&object=3948&utm_source=foo#bar',
        'locale=3&object=3948&utm_source=foo\\bar', 'locale=3&object=3948&utm_source=two words',
        'locale=3&object=3948&utm_source='.str_repeat('x', 4096),
        'locale=3&object=3948&'.implode('&', array_map(fn($n) => 'utm_'.$n.'=x', range(1, 64)))
    ];
    foreach ($unchanged as $query)
        check(RequestUrl::localeFirstRedirect('GET', $query) === null, 'malformed, ambiguous or non-public request is not reinterpreted: '.$query);
    foreach (['POST', 'PUT', 'DELETE', 'OPTIONS', 'TRACE', '', 'get'] as $method)
        check(RequestUrl::localeFirstRedirect($method, 'locale=3&object=3948') === null, 'only GET and HEAD may redirect');

    $directory = sys_get_temp_dir().'/aowow-request-routing-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $process = null;
    try
    {
        // The actual index.php must redirect before this kernel is reached. Other
        // requests exercise the real CSRF gate, then stop before any route handler.
        $kernel = <<<'PHP'
<?php
namespace Aowow;
define('CLI', false);
define('AOWOW_REVISION', 75);
header('X-Fixture-Bootstrap: reached');
require __DIR__.'/components/csrf.class.php';
$_SESSION = ['csrfToken' => str_repeat('a', 64), 'csrfIdentity' => ':0'];
Csrf::assertRequest();
http_response_code(204);
exit;
PHP;
        foreach (['', '/db'] as $prefix)
        {
            $root = $directory.$prefix;
            mkdir($root.'/includes/components', 0700, true);
            copy(__DIR__.'/../index.php', $root.'/index.php');
            copy(__DIR__.'/../includes/components/requesturl.class.php', $root.'/includes/components/requesturl.class.php');
            copy(__DIR__.'/../includes/components/csrf.class.php', $root.'/includes/components/csrf.class.php');
            file_put_contents($root.'/includes/kernel.php', $kernel);
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$socket)
            throw new RuntimeException($error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', $directory],
            [['pipe', 'r'], ['file', $directory.'/http.log', 'a'], ['file', $directory.'/http.log', 'a']], $pipes);
        if (!is_resource($process))
            throw new RuntimeException('Cannot start loopback HTTP fixture');
        fclose($pipes[0]);
        $ready = false;
        for ($attempt = 0; $attempt < 100; ++$attempt)
        {
            $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
            if ($connection)
            {
                fclose($connection);
                $ready = true;
                break;
            }
            usleep(20000);
        }
        check($ready, 'loopback front controller starts');

        function request(string $path, string $method = 'GET', string $headers = '') : array
        {
            global $address;
            $context = stream_context_create(['http' => ['method' => $method, 'header' => $headers,
                'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
            $body = file_get_contents('http://'.$address.$path, false, $context);
            preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
            $response = [];
            foreach (array_slice($http_response_header, 1) as $line)
                if (str_contains($line, ':'))
                {
                    [$key, $value] = explode(':', $line, 2);
                    $response[strtolower($key)] = trim($value);
                }
            return [(int)$match[1], $response, $body];
        }

        foreach (['/', '/index.php', '/db/', '/db/index.php'] as $path)
            foreach (['GET', 'HEAD'] as $method)
                foreach ([0, 2, 3, 4, 6, 8] as $locale)
                {
                    [$status, $headers, $body] = request($path.'?locale='.$locale.'&object=3948', $method,
                        "Host: untrusted.example\r\nX-Forwarded-Host: elsewhere.example\r\n");
                    $target = '?object=3948&locale='.$locale;
                    check($status === 301 && ($headers['location'] ?? '') === $target, 'real front controller redirects permanently without trusting host headers');
                    check(!isset($headers['x-fixture-bootstrap']) && !isset($headers['set-cookie']), 'redirect precedes kernel and session startup');
                    check($body === '', 'redirect has no generated page body');
                    [$status, $headers] = request($path.$target, $method);
                    check($status === 204 && isset($headers['x-fixture-bootstrap']) && !isset($headers['location']), 'following relative target preserves installation path and reaches bootstrap without looping');
                }

        [$status, $headers] = request('/?locale=3&utm_source=two+words&object=3948&gclid=x%2By');
        check($status === 301 && $headers['location'] === '?object=3948&locale=3&utm_source=two+words&gclid=x%2By', 'HTTP redirect preserves passive tracking bytes');
        foreach ($unchanged as $query)
        {
            if (preg_match('/[\x00-\x20\x7f#]/', $query))
                continue; // Unit checks cover strings that cannot be sent as a raw HTTP request target.
            [$status, $headers] = request('/?'.$query);
            check(isset($headers['x-fixture-bootstrap']) && !isset($headers['location']), 'unmatched HTTP requests retain application handling');
        }
        foreach (['locale=3', 'locale=3&object=3948&power', 'locale=3&object=3948&item=19019'] as $query)
        {
            [$status, $headers] = request('/?'.$query);
            check($status === 405 && ($headers['allow'] ?? '') === 'POST', 'unmatched locale GET retains language-action POST requirement');
        }
        [$status, $headers] = request('/?locale=3&object=3948', 'POST');
        check($status === 403 && !isset($headers['location']), 'POST cannot bypass CSRF through the early redirect');
        foreach (['locale=3', 'locale=3&object=3948'] as $query)
        {
            [$status, $headers] = request('/?'.$query, 'POST', 'X-CSRF-Token: '.str_repeat('a', 64)."\r\n");
            check($status === 204 && isset($headers['x-fixture-bootstrap']) && !isset($headers['location']), 'protected language POST retains its original route');
        }
        foreach (['PUT', 'DELETE', 'OPTIONS'] as $method)
        {
            [$status, $headers] = request('/?locale=3&object=3948', $method);
            check($status === 405 && !isset($headers['location']), 'other methods retain rejection');
        }

        echo "PASS: $checks locale-first policy, real front-controller HTTP and CSRF checks\n";
    }
    finally
    {
        if (is_resource($process))
        {
            proc_terminate($process);
            proc_close($process);
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry)
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($directory);
    }
}
