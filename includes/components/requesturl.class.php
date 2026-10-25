<?php

namespace Aowow;

// Loaded before the kernel: this URL-only policy must not depend on configuration or sessions.
final class RequestUrl
{
    private const array PUBLIC_ENTITIES = [
        'npc', 'object', 'item', 'itemset', 'quest', 'spell', 'zone', 'faction', 'pet',
        'achievement', 'title', 'event', 'class', 'race', 'skill', 'currency', 'sound',
        'icon', 'emote', 'enchantment', 'mail'
    ];

    public static function localeFirstRedirect(string $method, string $query) : ?string
    {
        if (!in_array($method, ['GET', 'HEAD'], true) || strlen($query) > 4096 ||
            // Reject header controls, malformed escapes and ambiguous query separators.
            preg_match('/[\x00-\x20\x7f\\\\#;]|%(?![a-f0-9]{2})|%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $query))
            return null;

        $pairs = explode('&', $query);
        if (count($pairs) < 2 || count($pairs) > 64)
            return null;

        $locale = array_shift($pairs);
        // Availability and fallback remain the normal locale loader's responsibility.
        if (!preg_match('/^locale=[0-9]{1,2}$/D', $locale))
            return null;

        $entity = null;
        $extras = [];
        $seen = ['locale' => true];
        foreach ($pairs as $pair)
        {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (isset($seen[$key]))
                return null;
            $seen[$key] = true;

            if (in_array($key, self::PUBLIC_ENTITIES, true))
            {
                if ($entity !== null || !preg_match('/^[1-9][0-9]{0,9}$/D', $value))
                    return null;
                $entity = $pair;
            }
            // Preserve passive attribution, without moving actions, previews or response selectors.
            else if (preg_match('/^utm_[a-z0-9_]+$/D', $key) || in_array($key, ['gclid', 'fbclid', 'msclkid'], true))
                $extras[] = $pair;
            else
                return null;
        }

        // A query-relative Location retains root/subdirectory/index.php paths and never uses Host.
        return $entity === null ? null : '?'.implode('&', [$entity, $locale, ...$extras]);
    }
}
