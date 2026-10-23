<?php
// Database-free initial HTML for the real image renderer.
namespace Aowow {
    class Cfg {
        public static string $static;
        public static function get(string $key) : mixed { return $key === 'STATIC_URL' ? self::$static : null; }
    }
}
namespace {
    use Aowow\{Cfg, ServerContent};
    define('AOWOW_REVISION', 75);
    chdir(dirname(__DIR__, 2));
    require 'includes/components/servercontent.class.php';
    Cfg::$static = ($argv[1] ?? 'https://fixture.example').'/static';
    $image = static fn(string $name, string $attrs = '') : string => ServerContent::markup('[img src="'.Cfg::$static.'/images/logos/'.$name.'.png" '.$attrs.']');
    echo json_encode([
        'near' => $image('home'), 'far' => $image('header'),
        'scaled' => $image('home', 'width=130'),
        'authored' => $image('home', 'width=130 height=70'),
        'invalid' => ServerContent::markup('[img src="'.Cfg::$static.'/images/../config/security.php" alt="Invalid image"]')
    ], JSON_THROW_ON_ERROR);
}
