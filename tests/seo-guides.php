<?php

// Real response routing lifecycle, request filters, guide/article selection,
// metadata, head rendering and cache trait over disposable loopback HTTP.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : mixed {
            return match ($key) {
                'NAME' => 'Fixture database', 'HOST_URL' => 'https://fixture.example',
                'STATIC_URL' => 'https://static.fixture.example', 'LOCALES' => 0x15D,
                'CACHE_MODE' => 1, 'CACHE_DECAY' => 600, default => 0
            };
        }
        public static function applyToString(string $text, bool $formatNumbers = true) : string { return $text; }
    }
    class User {
        public static int $id = 0;
        public static int $groups = 0;
        public static function isInGroup(int $mask) : bool { return !$mask || (bool)(self::$groups & $mask); }
        public static function isLoggedIn() : bool { return self::$id > 0; }
        public static function getUserGlobal() : array { return ['id' => self::$id]; }
        public static function getFavorites() : array { return []; }
        public static function canWriteGuide() : bool { return self::$id > 0; }
        public static function canUpvote() : bool { return false; }
        public static function canDownvote() : bool { return false; }
    }
    class CacheEnvelope {
        public static function enabled() : bool { throw new \RuntimeException('Guide request consulted a shared page/tooltip cache'); }
    }
    class DB {
        public static function Aowow() : self { return new self; }
        public function selectCell(string $query, mixed ...$args) : mixed {
            if (str_contains($query, 'MAX(`rev`)')) return 3;
            if (str_contains($query, 'FROM ::guides WHERE `url`')) return $args[0] === 'guide-slug' ? 1 : null;
            return null;
        }
        public function selectRow(string $query, mixed ...$args) : array {
            if (!str_contains($query, 'FROM ::articles')) return [];
            // Every guide read must name an exact revision and its guide ID.
            if (!str_contains($query, '`rev` = %i') || $args[0] !== Type::GUIDE || $args[1] !== 1)
                throw new \RuntimeException('Unbounded/cross-guide article lookup');
            $rev = $args[2];
            if (($_GET['missing'] ?? '') === '1' && $rev === 2) return [];
            $body = [1 => 'HISTORICAL_BODY', 2 => 'APPROVED_BODY', 3 => 'SECRET_DRAFT_BODY'][$rev] ?? null;
            return $body === null ? [] : ['article' => $body, 'locale' => 2, 'editAccess' => 0];
        }
    }
    class GuideList {
        public static int $contribute = 0;
        public bool $error;
        public function __construct(array $conditions) { $this->error = $conditions[0][1] !== 1; }
        public function getField(string $field, bool $raw = false) : mixed {
            $status = (int)($_GET['status'] ?? 3);
            return match ($field) {
                'status' => $status, 'rev' => (int)($_GET['published'] ?? 2),
                'url' => 'guide-slug', 'name', 'title', 'description' => $status === 3 ? 'Published name' : 'SECRET_EDIT_METADATA',
                'category' => 2, 'author' => 'Fixture author', 'userId' => 1,
                'cuFlags' => GUIDE_CU_NO_QUICKFACTS | GUIDE_CU_NO_RATING,
                default => 0
            };
        }
        public function canBeViewed() : bool { return $this->getField('status') === GuideMgr::STATUS_APPROVED || $this->getField('rev') > 0; }
        public function userCanView() : bool { return User::$id === 1 || User::isInGroup(U_GROUP_STAFF); }
        public function canBeReported() : bool { return true; }
        public function renderTooltip() : string { return 'Public tooltip'; }
    }
    class Turnstile { public static function clientConfig() : array { return ['actions' => []]; } }
    class ExternalLinks { public static function urls() : array { return []; } }
}
namespace {
    define('AOWOW_REVISION', 75); define('CLI', false);
    chdir(dirname(__DIR__));
    foreach (['includes/defines.php', 'includes/locale.class.php', 'includes/type.class.php', 'includes/utilities.php',
        'includes/game/uitext.class.php', 'includes/components/guidemgr.class.php',
        'includes/components/SmartAI/SmartAI.class.php', 'includes/components/SmartAI/SmartEvent.class.php',
        'includes/components/SmartAI/SmartAction.class.php', 'includes/components/SmartAI/SmartTarget.class.php',
        'localization/lang.class.php', 'includes/components/csrf.class.php', 'includes/components/jsexpression.class.php',
        'includes/components/frontend/markup.class.php', 'includes/components/frontend/tabs.class.php',
        'includes/components/pagetemplate.class.php', 'includes/components/response/baseresponse.class.php',
        'includes/components/response/templateresponse.class.php', 'includes/components/response/textresponse.class.php',
        'includes/components/frontend/tooltip.class.php', 'endpoints/guide/guide.php', 'endpoints/guide/guide_power.php'] as $file)
        require $file;
}
namespace Aowow {
    class FixtureGuideResponse extends GuideBaseResponse {
        protected function sumSQLStats() : void {}
        protected function display(bool $withError = false) : void {
            foreach ((new \ReflectionProperty(TemplateResponse::class, 'header'))->getValue($this) as $header)
                header(...$header);
            // Generated bundles are deployment inputs, unrelated to this head fixture.
            (new \ReflectionProperty(Template\PageTemplate::class, 'scripts'))->setValue($this->result, []);
            (new \ReflectionMethod(Template\PageTemplate::class, 'update'))->invoke($this->result);
            ob_start();
            (new \ReflectionMethod(Template\PageTemplate::class, 'brick'))->invoke($this->result, 'head');
            $head = ob_get_clean();
            echo json_encode(['head' => $head, 'article' => (string)($this->article ?? ''), 'revision' => $this->guideRevision,
                'name' => $this->gPageInfo['name'] ?? '', 'seo' => $this->seo], JSON_THROW_ON_ERROR);
        }
    }
}
namespace {
    use Aowow\{FixtureGuideResponse, GuideMgr, Lang, Locale, User};
    if (PHP_SAPI === 'cli-server') {
        set_error_handler(static function (int $code, string $message) : bool {
            if ($code & (E_DEPRECATED | E_USER_DEPRECATED)) return true;
            throw new ErrorException($message, 0, $code);
        });
        Lang::load(Locale::tryFromRequest($_GET['locale'] ?? null) ?? Locale::EN);
        User::$id = match ($_GET['actor'] ?? '') { 'owner' => 1, 'staff' => 2, 'other' => 3, default => 0 };
        User::$groups = ($_GET['actor'] ?? '') === 'staff' ? U_GROUP_ADMIN : 0;
        if (isset($_GET['power']))
            (new Aowow\GuidePowerResponse($_GET['guide']))->process();
        else
            (new FixtureGuideResponse($_GET['guide']))->process();
        return;
    }
    $checks = 0;
    function check(bool $ok, string $label) : void { global $checks; ++$checks; if (!$ok) throw new RuntimeException($label); }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $log = tempnam(sys_get_temp_dir(), 'aowow-seo-guide-');
    $process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes);
    fclose($pipes[0]);
    try {
        $ready = false;
        for ($i = 0; $i < 100; ++$i) {
            $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
            if ($connection) { fclose($connection); $ready = true; break; }
            usleep(20000);
        }
        check($ready, 'loopback HTTP fixture starts');
        function requestGuide(array $params) : array {
            global $address;
            $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
            $body = file_get_contents('http://'.$address.'/?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986), false, $context);
            preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
            return [(int)$match[1], $body, $http_response_header];
        }
        foreach ([0, 2, 3, 4, 6, 8] as $locale) {
            foreach ([GuideMgr::STATUS_APPROVED, GuideMgr::STATUS_DRAFT, GuideMgr::STATUS_REVIEW, GuideMgr::STATUS_REJECTED, GuideMgr::STATUS_ARCHIVED] as $status) {
                foreach (['guest', 'owner', 'staff', 'other'] as $actor) {
                    [$code, $body] = requestGuide(['guide' => '1', 'locale' => $locale, 'status' => $status, 'actor' => $actor]);
                    check($code === 200, 'public approved revision remains viewable');
                    $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
                    check(str_contains(json_encode($data['article']), 'APPROVED_BODY') && !str_contains($body, 'SECRET_'), 'pending edits never replace published body or metadata');
                    check($data['revision'] === 2, 'selected public revision is exact');
                    check(str_contains($data['head'], 'name="robots"') === ($status === GuideMgr::STATUS_ARCHIVED), 'archived guides are readable and noindex');
                    if ($status !== GuideMgr::STATUS_ARCHIVED)
                        check(str_contains($data['head'], 'href="https://fixture.example/?guide=guide-slug&amp;locale=2"'), 'numeric guide alias resolves to authored-language slug canonical');
                    check(!str_contains($data['head'], 'hreflang='), 'guide does not advertise nonexistent translations');
                }
            }
            foreach ([GuideMgr::STATUS_DRAFT, GuideMgr::STATUS_REVIEW, GuideMgr::STATUS_REJECTED, GuideMgr::STATUS_ARCHIVED] as $status) {
                foreach (['guest', 'owner', 'staff', 'other'] as $actor) {
                    [$code, $body] = requestGuide(['guide' => 'guide-slug', 'published' => 0, 'status' => $status, 'actor' => $actor, 'locale' => $locale]);
                    $authorized = in_array($actor, ['owner', 'staff'], true);
                    check($code === ($authorized ? 200 : 302), 'unpublished guide is available only to owner/staff');
                    check(str_contains($body, 'SECRET_DRAFT_BODY') === $authorized, 'draft body never leaks to another visitor');
                    if ($authorized) check(str_contains($body, 'noindex, follow') && !str_contains($body, 'application/ld+json'), 'owner/staff draft has noindex and no structured data');
                }
            }
        }
        foreach (['guest', 'owner', 'staff', 'other'] as $actor) {
            foreach ([1, 2, 3, 99, -1, 0, 'bad', ['3']] as $rev) {
                [$code, $body] = requestGuide(['guide' => 'guide-slug', 'actor' => $actor, 'rev' => $rev]);
                $valid = is_int($rev) && $rev >= 1 && $rev <= 3 && ($rev === 2 || in_array($actor, ['owner', 'staff'], true));
                check($code === ($valid ? 200 : 404), 'historical/preview revision access and validation');
                if ($valid) check(str_contains($body, 'noindex, follow') && !str_contains($body, 'application/ld+json'), 'every explicit revision is excluded from indexing');
                if (!$valid) check(!str_contains($body, 'SECRET_DRAFT_BODY') && !str_contains($body, 'HISTORICAL_BODY'), 'denied revision has no private content');
            }
            [$code, $body] = requestGuide(['guide' => 'guide-slug', 'actor' => $actor, 'missing' => 1]);
            check($code === 404 && !str_contains($body, 'SECRET_DRAFT_BODY'), 'missing approved article never falls back to latest');
        }
        foreach ([GuideMgr::STATUS_DRAFT, GuideMgr::STATUS_REVIEW, GuideMgr::STATUS_APPROVED] as $status) {
            [$code, $body] = requestGuide(['guide' => '1', 'power' => '', 'status' => $status, 'published' => 0]);
            check($code === 200 && !str_contains($body, 'SECRET_'), 'tooltips do not disclose unpublished guide metadata');
        }
        check(!str_contains(file_get_contents($log), 'Fatal error'), 'HTTP requests completed without fixture/runtime failures');
        echo "PASS: $checks guide publication, revision, locale, cache and HTTP checks\n";
    }
    catch (Throwable $error) {
        fwrite(STDERR, file_get_contents($log));
        throw $error;
    }
    finally { proc_terminate($process); proc_close($process); unlink($log); }
}
