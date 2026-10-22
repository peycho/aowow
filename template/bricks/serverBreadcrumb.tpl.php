<?php
    namespace Aowow\Template;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    use \Aowow\ServerContent as Content;
    $route = explode('=', $this->pageTemplate['pageName'] ?? '')[0];
    $lists = ['item' => 'items', 'spell' => 'spells', 'quest' => 'quests', 'npc' => 'npcs',
        'object' => 'objects', 'zone' => 'zones', 'guide' => 'guides', 'itemset' => 'itemsets',
        'class' => 'classes', 'race' => 'races', 'skill' => 'skills', 'faction' => 'factions',
        'achievement' => 'achievements', 'currency' => 'currencies', 'event' => 'events',
        'pet' => 'pets', 'sound' => 'sounds', 'icon' => 'icons', 'title' => 'titles',
        'emote' => 'emotes', 'enchantment' => 'enchantments', 'areatrigger' => 'areatriggers', 'mail' => 'mails'];
    $parent = $lists[$route] ?? (PageTemplate::supportsPagination($route) ? $route : null);
    if ($parent):
        $label = is_callable([\Aowow\Lang::class, 'game']) ? \Aowow\Lang::game($parent) : ucfirst($parent);
        $current = $this->gPageInfo['name'] ?? strip_tags(html_entity_decode($this->title[0] ?? $this->h1 ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
?>
    <nav id="server-breadcrumb" class="server-breadcrumb" aria-label="<?=Content::label('navigation'); ?>">
        <a href="<?=Content::text(Content::url('home')); ?>"><?=Content::label('home'); ?></a> ›
        <a href="<?=Content::text(Content::url($parent)); ?>"><?=Content::text(is_string($label) ? $label : ucfirst($parent)); ?></a>
        <span aria-current="page"> › <?=Content::text((string)$current); ?></span>
    </nav>
<?php endif; ?>
