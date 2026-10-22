<?php
    namespace Aowow\Template;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    use \Aowow\ServerContent as Content;
?>
    <nav id="server-navigation" class="server-navigation" aria-label="<?=Content::label('navigation'); ?>">
        <a href="<?=Content::text(Content::url('home')); ?>"><?=Content::label('home'); ?></a>
<?php foreach (['items', 'spells', 'quests', 'npcs', 'objects', 'zones', 'guides'] as $route):
    $label = is_callable([\Aowow\Lang::class, 'game']) ? \Aowow\Lang::game($route) : ucfirst($route);
?>
        <a href="<?=Content::text(Content::url($route)); ?>"><?=Content::text(is_string($label) ? $label : ucfirst($route)); ?></a>
<?php endforeach; ?>
    </nav>
