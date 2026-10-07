<?php
    namespace Aowow\Template;

    /** @var PageTemplate $this */
    $headerImage = $this->headerImage();
    if ($headerImage):
?>
        <a class="header-image" href="<?=$this->escHTML($headerImage['link']); ?>" target="_blank" rel="noopener noreferrer"><img src="<?=$this->escHTML($headerImage['image']); ?>" alt="<?=$this->escHTML((string)$this->cfg('NAME_SHORT')); ?>" /></a>
<?php endif; ?>
