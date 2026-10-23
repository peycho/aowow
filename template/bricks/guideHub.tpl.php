<?php
    namespace Aowow\Template;
    use \Aowow\ServerContent as Content;
    require_once __DIR__.'/../../includes/components/servercontent.class.php';
    if ($this->guideIntro):
?>
                <p id="guide-introduction"><?=Content::text($this->guideIntro); ?></p>
<?php endif; if ($this->guideCategories): ?>
                <nav id="guide-categories" aria-label="<?=Content::text(\Aowow\Lang::guide('browseTopics')); ?>">
                    <ul>
                        <li><a href="<?=Content::text(PageTemplate::canonicalUrl(['guides' => '', 'locale' => $this->locale->value])); ?>"><?=Content::text(\Aowow\Lang::game('guides')); ?></a></li>
<?php foreach ($this->guideCategories as $category): ?>
                        <li><a href="<?=Content::text($category['href']); ?>"><?=Content::text($category['name']); ?></a></li>
<?php endforeach; ?>
                    </ul>
                </nav>
<?php endif; ?>
