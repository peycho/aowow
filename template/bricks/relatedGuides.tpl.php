<?php
    namespace Aowow\Template;
    use \Aowow\ServerContent as Content;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    if ($links = $this->relatedGuides):
?>
            <section class="text clear" id="related-guides">
                <h2><?=Content::text(\Aowow\Lang::guide('relatedGuides')); ?></h2>
                <ul>
<?php foreach ($links as $link): ?>
                    <li><a href="<?=Content::text($link['href']); ?>"><?=Content::text($link['title']); ?></a></li>
<?php endforeach; ?>
                </ul>
            </section>
<?php endif; ?>
