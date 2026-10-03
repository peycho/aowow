<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Deployment-owned navigation links, read from config/config.php rather than the database. */
final class ExternalLinks
{
    private const array DEFAULTS = [
        'forum'    => ['enabled' => true, 'url' => 'http://forums.battle.net'],
        'blog'     => ['enabled' => true, 'url' => 'http://worldpress.com'],
        'irc'      => ['enabled' => true, 'url' => 'http://webchat.quakenet.org/'],
        'facebook' => ['enabled' => true, 'url' => 'http://www.facebook.com'],
        'twitter'  => ['enabled' => true, 'url' => 'http://twitter.com'],
        'github'   => ['enabled' => true, 'url' => 'https://github.com/Sarjuuk/aowow']
    ];

    private static array $urls = [];

    public static function defaults() : array
    {
        return self::DEFAULTS;
    }

    // Missing entries retain legacy behavior; disabled, empty or malformed entries never become links.
    public static function load(mixed $config) : void
    {
        self::$urls = [];
        foreach (self::DEFAULTS as $key => $default)
        {
            $entry = is_array($config) ? (array_key_exists($key, $config) ? $config[$key] : $default) : null;
            $entry = is_array($entry) ? array_replace($default, $entry) : [];
            $url = $entry['url'] ?? null;
            $enabled = $entry['enabled'] ?? false;
            self::$urls[$key] = ($enabled === true && self::validUrl($url)) ? $url : null;
        }
    }

    public static function urls() : array
    {
        return self::$urls;
    }

    public static function url(string $key) : ?string
    {
        return self::$urls[$key] ?? null;
    }

    // Allow only absolute web URLs, without credentials, control bytes or ambiguous backslashes.
    private static function validUrl(mixed $url) : bool
    {
        if (!is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL))
            return false;

        $parts = parse_url($url);
        return $parts && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) &&
            !isset($parts['user']) && !isset($parts['pass']);
    }
}
