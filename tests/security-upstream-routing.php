<?php

// Real front controller through loopback HTTP; synthetic handlers inspect dispatch.
$directory = sys_get_temp_dir().'/aowow-upstream-routing-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$process = null;
$checks = 0;
function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
try {
    mkdir($directory.'/includes/components', 0700, true);
    copy(__DIR__.'/../index.php', $directory.'/index.php');
    copy(__DIR__.'/../includes/components/requesturl.class.php', $directory.'/includes/components/requesturl.class.php');
    file_put_contents($directory.'/includes/kernel.php', <<<'KERNEL'
<?php
namespace Aowow;
define('CLI', false);
class Util { public static function lower(string $text) : string { return strtolower($text); } public static function ucFirst(string $text) : string { return ucfirst($text); } }
class Type { public const int IDX_FILE_STR=1; public static function getIndexFrom(int $index, string $page) : int { return 0; } }
class TemplateResponse {
    public function __construct(private string $param) {}
    public function process() : void { header('Content-Type: application/json'); echo json_encode(['param'=>$this->param, 'get'=>$_GET], JSON_THROW_ON_ERROR); }
}
KERNEL);
    foreach (['profile'=>['profile'=>'ProfileBase', 'save'=>'ProfileSave'], 'arena-team'=>['arena-team'=>'ArenaTeamBase']] as $route=>$files) {
        mkdir($directory.'/endpoints/'.$route, 0700, true);
        foreach ($files as $file=>$class)
            file_put_contents($directory.'/endpoints/'.$route.'/'.$file.'.php', '<?php namespace Aowow; class '.$class.'Response extends TemplateResponse {}');
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', $address, '-t', $directory], [['pipe','r'], ['file',$directory.'/http.log','a'], ['file',$directory.'/http.log','a']], $pipes);
    fclose($pipes[0]);
    for ($attempt=0; $attempt<100; ++$attempt) {
        $socket = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
        if ($socket) { fclose($socket); break; }
        usleep(20000);
    }
    function request(string $query) : array {
        global $address;
        $context = stream_context_create(['http'=>['ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>5]]);
        $body = file_get_contents('http://'.$address.'/index.php?'.$query, false, $context);
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
        $location = null;
        foreach ($http_response_header as $header) if (str_starts_with($header, 'Location: ')) $location = substr($header, 10);
        return [(int)$status[1], $location, $body];
    }
    foreach (['profile[]=1', 'profile[x][y]=1', '123=1', 'pro_file=1', 'profile%2Fbad=1', 'pro.file=1'] as $query) {
        [$status, $location] = request($query);
        check($status === 302 && $location === '.', 'Malformed first routing pair is rejected: '.$query);
    }
    foreach (['Profile=save&items[]=1&items[]=2', '%50rofile=save&items[]=1&items[]=2'] as $query) {
        [$status, $location] = request($query);
        check($status === 302 && $location === '/index.php?profile=save&items[]=1&items[]=2', 'Uppercase/encoded route redirect retains custom-profile arrays');
    }
    [$status, $location] = request('Profile&x=?Profile&items[]=1');
    check($status === 302 && $location === '/index.php?profile&x=?Profile&items[]=1', 'Only the first routing key changes, including valueless routes');
    foreach (['profile=save&items[]=1&items[]=2', 'profile=1&inventory[head][item]=19019', 'arena-team=1&items[]=1'] as $query) {
        [$status, $location, $body] = request($query);
        $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        check($status === 200 && $location === null && $data['get'] !== [], 'Later arrays and hyphenated routes reach their handlers');
        parse_str($query, $expected);
        check($data['get'] === $expected, 'Dispatch preserves the complete profile inventory payload');
    }
    [$status, $location] = request('locale=3&spell=133');
    check($status === 301 && $location === '?spell=133&locale=3', 'Early locale repair still runs before bootstrap');
    echo "PASS: $checks upstream front-controller compatibility checks\n";
}
finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($paths as $path) if ($path->isDir()) rmdir($path->getPathname()); else unlink($path->getPathname());
    rmdir($directory);
}
