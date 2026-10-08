<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

/** Optional form protection. Keys are deployment configuration, never database settings. */
final class Turnstile
{
    public const array ACTIONS = ['registration', 'login', 'password_recovery', 'username_recovery', 'resend', 'feedback'];
    public const int CONNECT_MS = 2000;
    public const int TOTAL_MS = 5000;
    public const int MAX_BYTES = 8192;

    public static function enabled(string $action) : bool
    {
        return in_array($action, self::ACTIONS, true) && (bool)Cfg::get('TURNSTILE_'.strtoupper($action).'_ENABLE');
    }

    private static function key(string $constant) : string
    {
        $key = defined($constant) ? constant($constant) : null;
        return is_string($key) && preg_match('/^[a-zA-Z0-9_-]{1,256}$/D', $key) ? $key : '';
    }

    public static function clientConfig() : array
    {
        $actions = array_values(array_filter(self::ACTIONS, fn($a) => self::enabled($a) && ($a !== 'feedback' || Cfg::get('FEEDBACK_ENABLE'))));
        return ['actions' => $actions, 'siteKey' => $actions ? self::key('AOWOW_TURNSTILE_SITE_KEY') : '', 'error' => Lang::main('captchaError')];
    }

    public static function verify(string $action, #[\SensitiveParameter] mixed $token) : bool
    {
        if (!in_array($action, self::ACTIONS, true))
            return false;
        if (!self::enabled($action))
            return true;                                   // no network work for disabled forms
        if (!is_string($token) || $token === '' || strlen($token) > 2048 || preg_match('/[\x00-\x20\x7f]/', $token))
            return false;

        $secret = self::key('AOWOW_TURNSTILE_SECRET_KEY');
        $url = Cfg::get('HOST_URL');
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (!$secret || !self::key('AOWOW_TURNSTILE_SITE_KEY') || !is_string($host) || $host === '')
            return false;

        // Commit a durable reservation before any network work, including rejected/replayed tokens.
        if (!TurnstileBudget::reserve())
            return false;

        $result = self::request($secret, $token);
        return ($result['success'] ?? null) === true && ($result['action'] ?? null) === $action &&
            is_string($result['hostname'] ?? null) && strtolower($result['hostname']) === strtolower($host);
    }

    private static function request(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $token) : ?array
    {
        if (!extension_loaded('curl') || !(curl_version()['features'] & CURL_VERSION_ASYNCHDNS))
            return null;
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        if (!$curl) return null;
        $body = '';
        $headers = 0;
        try
        {
            if (!curl_setopt_array($curl, [
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_MS,
                CURLOPT_TIMEOUT_MS => self::TOTAL_MS,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $data) use (&$headers) : int {
                    $headers += strlen($data);
                    return $headers <= 16384 ? strlen($data) : 0;
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $data) use (&$body) : int {
                    if (strlen($body) + strlen($data) > self::MAX_BYTES) return 0;
                    $body .= $data;
                    return strlen($data);
                }
            ])) return null;
            if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200)
                return null;
            $result = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
            return is_array($result) ? $result : null;
        }
        catch (\Throwable) { return null; }                // verification failures are form errors, not runtime errors
        finally { unset($curl); }
    }
}
