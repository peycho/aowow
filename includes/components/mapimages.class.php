<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class MapImages
{
    public static function exists(int $zoneId, Locale $locale) : bool
    {
        return self::findLocale($zoneId, $locale) !== null;
    }

    public static function findLocale(int $zoneId, Locale $locale) : ?Locale
    {
        foreach ($locale === Locale::EN ? [$locale] : [$locale, Locale::EN] as $candidate)
        {
            $base = 'static/images/wow/maps/'.$candidate->json().'/original/'.$zoneId;
            foreach (['', '-0', '-1'] as $floor)
                if (is_file($base.$floor.'.jpg'))
                    return $candidate;

            foreach (glob($base.'-*.jpg') ?: [] as $file)
                if (preg_match('~^'.preg_quote($base, '~').'-\d+\.jpg$~D', $file) && is_file($file))
                    return $candidate;
        }

        return null;
    }
}
