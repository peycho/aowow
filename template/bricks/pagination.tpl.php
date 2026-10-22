<?php
    namespace Aowow\Template;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    use \Aowow\ServerContent as Content;
    $page = $this->pagination ?? [];
    if (($page['pages'] ?? 0) > 1):
?>
    <nav class="server-pagination" aria-label="<?=Content::label('page'); ?>">
        <span><?=Content::label('results'); ?> <?=($page['page'] - 1) * $page['size'] + 1; ?>–<?=min($page['total'], $page['page'] * $page['size']); ?> / <?=$page['total']; ?></span>
<?php if ($page['page'] > 1): ?>
        <a rel="prev" href="<?=$this->escHTML(PageTemplate::buildQuery(add: ['page' => $page['page'] - 1])); ?>"><?=Content::label('previous'); ?></a>
<?php endif; ?>
        <span aria-current="page"><?=Content::label('page'); ?> <?=$page['page']; ?> / <?=$page['pages']; ?></span>
<?php if ($page['page'] < $page['pages']): ?>
        <a rel="next" href="<?=$this->escHTML(PageTemplate::buildQuery(add: ['page' => $page['page'] + 1])); ?>"><?=Content::label('next'); ?></a>
<?php endif; ?>
    </nav>
<?php endif; ?>
