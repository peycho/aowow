<?php
    namespace Aowow\Template;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    use \Aowow\ServerContent as Content;
    $trail = $this->serverBreadcrumbs();
    if ($trail):
?>
    <nav id="server-breadcrumb" class="server-breadcrumb" aria-label="<?=Content::label('navigation'); ?>">
        <a href="<?=Content::text($trail[0]['url']); ?>"><?=Content::text($trail[0]['name']); ?></a> ›
        <a href="<?=Content::text($trail[1]['url']); ?>"><?=Content::text($trail[1]['name']); ?></a>
        <span aria-current="page"> › <?=Content::text($trail[2]['name']); ?></span>
    </nav>
<?php endif; ?>
