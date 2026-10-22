
<?php if ($markup): ?>
    <div id="<?=\Aowow\Util::htmlEscape($markup->getParent()); ?>" class="left"><?=$markup->renderHtml($this->jsGlobals ?? []); ?></div>
    <script type="text/javascript">//<![CDATA[
        <?=$markup;?>
    //]]></script>
    <div class="pad2"></div>

<?php endif; ?>
