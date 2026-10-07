<?php
    namespace Aowow\Template;

    if (\Aowow\Turnstile::enabled($turnstileAction ?? '')):
?>
    <div data-turnstile-action="<?=$turnstileAction;?>" style="text-align: center; margin: 12px 0"></div>
<?php endif; ?>
