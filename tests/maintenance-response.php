<?php

// Real response constructors, maintenance generation, metadata, page template and locale files.
// Only configuration, database reads, identity and the final output transport are fixtures.
namespace Aowow {
    class Cfg {
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
        public static function get(string $key) : mixed {
            return match ($key) {
                'NAME'=>'Fixture database', 'HOST_URL'=>'https://fixture.example',
                'STATIC_URL'=>$GLOBALS['staticUrl'] ?? 'https://static.fixture.example', 'LOCALES'=>0x15D,
                'MAINTENANCE'=>1, default=>0
            };
        }
    }
    class DB {
        public static function Aowow() : self { return new self; }
        public function selectCell(string $query, mixed ...$args) : ?string {
            return str_contains($query, 'seo_descriptions') && ($GLOBALS['manualDescription'] ?? false) ? 'Fixture manual description' : null;
        }
    }
    class User {
        public static function isInGroup(int $mask) : bool { return $mask===0; }
        public static function getUserGlobal() : array { return []; }
        public static function getFavorites() : array { return []; }
    }
}
namespace {
    define('AOWOW_REVISION',69); define('CLI',false);
    $root=dirname(__DIR__); chdir($root);
    require $root.'/includes/defines.php';
    require $root.'/includes/locale.class.php';
    require $root.'/includes/utilities.php';
    require $root.'/includes/game/uitext.class.php';
    require $root.'/includes/components/guidemgr.class.php';
    foreach (['SmartAI','SmartEvent','SmartAction','SmartTarget'] as $class)
        require $root.'/includes/components/SmartAI/'.$class.'.class.php';
    require $root.'/localization/lang.class.php';
    require $root.'/includes/components/csrf.class.php';
    require $root.'/includes/components/pagetemplate.class.php';
    require $root.'/includes/components/response/baseresponse.class.php';
    require $root.'/includes/components/response/templateresponse.class.php';
}
namespace Aowow {
    class FixtureMaintenanceResponse extends TemplateResponse {
        protected function display(bool $withError = false) : void {
            ob_start();
            try { $this->result->render(); $html=ob_get_contents(); }
            finally { ob_end_clean(); }
            if ($GLOBALS['renderOnly'] ?? false) {
                echo str_replace('</body>', <<<'HTML'
<pre id="result" style="display:none">PENDING</pre>
<script>
window.addEventListener('load', function () {
    var logo = document.querySelector('.maintenance-logo');
    var art = document.querySelector('.maintenance-art');
    var title = document.querySelector('h1').getBoundingClientRect();
    var lines = document.querySelectorAll('.maintenance p');
    var lastLine = lines[lines.length - 1].getBoundingClientRect();
    var valid = logo.complete && logo.naturalWidth > 0 && art.complete && art.naturalWidth > 0 &&
                logo.getBoundingClientRect().bottom < title.top && lastLine.bottom < art.getBoundingClientRect().top &&
                document.documentElement.scrollWidth <= window.innerWidth && art.getBoundingClientRect().width <= 640;
    document.querySelector('#result').textContent = valid ? 'PASS: 6 maintenance image and layout checks' : 'FAIL: maintenance layout';
});
</script>
</body>
HTML, $html);
                return;
            }
            echo json_encode([
                'headers'=>(new \ReflectionProperty(TemplateResponse::class,'header'))->getValue($this),
                'template'=>(new \ReflectionProperty(Template\PageTemplate::class,'template'))->getValue($this->result),
                'meta'=>$this->metaTags, 'withError'=>$withError, 'html'=>$html,
            ], JSON_THROW_ON_ERROR);
        }
    }
}
namespace {
    use Aowow\{FixtureMaintenanceResponse,Lang,Locale};
    set_error_handler(static function (int $code, string $message) : bool {
        if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true; // Match the production handler.
        throw new ErrorException($message,0,$code);
    });
    if (($argv[1] ?? '')==='--browser') {
        Lang::load(Locale::EN);
        $GLOBALS['renderOnly']=true;
        $GLOBALS['staticUrl']='file://'.$root.'/static';
        new FixtureMaintenanceResponse();
    }
    if (($argv[1] ?? '')==='--worker') {
        Lang::load(Locale::from((int)$argv[2]));
        $GLOBALS['manualDescription']=$argv[3]==='manual';
        if ($argv[3]==='direct') {
            $response=(new ReflectionClass(FixtureMaintenanceResponse::class))->newInstanceWithoutConstructor();
            $response->generateMaintenance();
        }
        new FixtureMaintenanceResponse(); // Mirrors the constructor reached by exception handling.
        throw new RuntimeException('Maintenance constructor failed to terminate');
    }
    $checks=0;
    function check(bool $ok,string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    foreach ([Locale::EN,Locale::FR,Locale::DE,Locale::CN,Locale::ES,Locale::RU] as $locale) {
        Lang::load($locale);
        foreach (['direct','constructor','manual'] as $mode) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker',(string)$locale->value,$mode],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
            fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            check(proc_close($process)===0 && $err==='', $locale->name.' '.$mode.' renders without warnings or fatal errors: '.$err);
            $response=json_decode($out,true,flags:JSON_THROW_ON_ERROR);
            check($response['template']==='maintenance' && $response['withError'], 'maintenance response uses the intended error template');
            check($response['headers']===[['HTTP/1.0 503 Service Temporarily Unavailable',true,503],['Retry-After: '.(3*HOUR)]], 'maintenance retains HTTP 503 and retry delay');
            $dom=new DOMDocument(); $dom->loadHTML($response['html'],LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath=new DOMXPath($dom);
            check($xpath->query('//main/h1')->item(0)?->textContent==='Maintenance in progress', 'Maintenance has a readable heading');
            check($xpath->query('//img[@class="maintenance-art"]')->item(0)?->getAttribute('src')==='https://static.fixture.example/images/maintenance/archive-repair.webp', 'Maintenance renders the replacement illustration from the configured static origin');
            check($xpath->query('//img[@class="maintenance-logo"]')->length===1 && !str_contains($response['html'],'brbgnomes.jpg'), 'Logo and new artwork are independent content images');
            check(str_contains($response['html'],'Please check back later. Thank you for your patience.') && !str_contains($response['html'],'few minutes'), 'Maintenance copy makes no duration promise');
            check($xpath->query('//meta[@name="viewport"]')->item(0)?->getAttribute('content')==='width=device-width, initial-scale=1', 'Maintenance supports narrow viewports');
            $description=$mode==='manual'?'Fixture manual description':Lang::meta('description','home');
            foreach (['name'=>'description','property'=>'og:description'] as $attribute=>$value) {
                $tags=array_values(array_filter($response['meta'],fn($tag)=>($tag[$attribute] ?? '')===$value));
                check(count($tags)===1 && $tags[0]['content']===$description, 'localized or manual description reaches both metadata tags');
            }
        }
    }
    $dimensions=getimagesize($root.'/static/images/maintenance/archive-repair.webp');
    check($dimensions[0]===1536 && $dimensions[1]===1024 && $dimensions['mime']==='image/webp', 'Replacement asset has the declared dimensions and format');
    echo "PASS: $checks maintenance response/locale/metadata/template checks\n";
}
