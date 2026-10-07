<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */
?>
    <title><?=$this->concat('title', ' - '); ?></title>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?=\Aowow\Csrf::token();?>">
    <script>var g_csrfRoutes = <?=json_encode(\Aowow\Csrf::POST_ROUTES, JSON_HEX_TAG);?>;
        var g_csrfAdminActions = <?=json_encode(\Aowow\Csrf::ADMIN_ACTIONS, JSON_HEX_TAG);?>;</script>
    <script src="<?=$this->gStaticUrl;?>/js/csrf.js?v=<?=AOWOW_REVISION;?>"></script>
    <script src="<?=$this->gStaticUrl;?>/js/password-policy.js?v=<?=AOWOW_REVISION;?>"></script>
<?=$this->renderMetaTags(4);?>
    <link rel="canonical" href="<?=PageTemplate::buildQuery(); ?>">
    <link rel="alternate" hreflang="x-default" href="<?=PageTemplate::buildQuery(add: ['locale' => Lang::getLocale()->getFallback()->value]); ?>">
<?php
foreach ($this->locale::cases() as $l):
    if ($l->validate()):
        echo '    <link rel="alternate" hreflang="'.$l->hreflang('-').'" href="'.PageTemplate::buildQuery(add: ['locale' => $l->value]).'">'.PHP_EOL;
    endif;
endforeach;
?>
    <link rel="SHORTCUT ICON" href="<?=$this->gStaticUrl; ?>/images/logos/favicon.ico" />
<?php if (\Aowow\Cfg::get('SEARCHPLUGINS_ENABLE')): ?>
    <link rel="search" type="application/opensearchdescription+xml" href="<?=$this->gStaticUrl; ?>/download/searchplugins/aowow.xml" title="<?=Lang::main('search');?>" />
<?php endif; ?>
<?php
if ($this->ldIntangible):
    echo '    <script type="application/ld+json">'.$this->json($this->ldIntangible).'</script>'.PHP_EOL;
endif;
if ($this->headIcons):
    echo '    <link rel="image_src" href="'.$this->gStaticUrl.'/images/wow/icons/large/'.$this->escJS($this->headIcons[0]).'.jpg">'.PHP_EOL;
endif;
echo $this->renderArray('css', 4);
?>
    <script type="text/javascript">
        var g_serverTime = <?=$this->gServerTime; ?>;
        var g_staticUrl = "<?=$this->gStaticUrl; ?>";
        var g_soundsEnabled = <?=\Aowow\Cfg::get('SOUNDS_ENABLE') ? 'true' : 'false';?>;
        var g_host = "<?=$this->gHost; ?>";
<?php
if ($this->gDataKey):
        echo "        var g_dataKey = '".$_SESSION['dataKey']."'".PHP_EOL;
endif;
?>
    </script>

<?=$this->renderArray('js', 4); ?>
    <script src="<?=$this->gStaticUrl;?>/js/external-links.js?v=<?=AOWOW_REVISION;?>.4"></script>
    <script type="text/javascript">
        var g_externalLinks = <?=\Aowow\Util::toJSON(\Aowow\ExternalLinks::urls());?>;
        g_applyExternalLinks(mn_community, g_externalLinks);
        var g_profilerEnabled = <?=\Aowow\Cfg::get('PROFILER_ENABLE') ? 'true' : 'false';?>;
        g_applyProfilerMenus(mn_tools, mn_more, g_profilerEnabled);
        var g_searchpluginsEnabled = <?=\Aowow\Cfg::get('SEARCHPLUGINS_ENABLE') ? 'true' : 'false';?>;
        var g_searchboxEnabled = <?=\Aowow\Cfg::get('SEARCHBOX_ENABLE') ? 'true' : 'false';?>;
        g_applyGoodiesMenus(mn_more, g_searchpluginsEnabled, g_searchboxEnabled);
        var g_missingScreenshotsEnabled = <?=\Aowow\Cfg::get('MISSING_SCREENSHOTS_ENABLE') ? 'true' : 'false';?>;
        g_applyMissingScreenshotsMenu(mn_tools, g_missingScreenshotsEnabled);
        g_applySoundsMenu(mn_database, g_soundsEnabled);
        var g_user = <?=$this->gUser; ?>;
        var g_feedbackEnabled = <?=\Aowow\Cfg::get('FEEDBACK_ENABLE') ? 'true' : 'false';?>;
<?php
if ($this->gFavorites):
    echo '        g_favorites = '.$this->gFavorites.';'.PHP_EOL;
endif;
?>
    </script>

<?php
$turnstile = \Aowow\Turnstile::clientConfig();
if ($turnstile['actions']):
?>
    <script>var g_turnstile = <?=\Aowow\Util::toJSON($turnstile);?>;</script>
    <script src="<?=$this->gStaticUrl;?>/js/turnstile.js?v=<?=AOWOW_REVISION;?>.1"></script>
<?php endif; ?>

<?php if ($this->hasAnalytics): ?>
    <script>
        $WH.Track.gaInit();
    </script>

<?php
endif;

if ($this->rss):
?>

    <link rel="alternate" type="application/rss+xml" title="<?=$this->concat('title', ' - '); ?>" href="<?=$this->rss; ?>"/>

<?php
endif;
?>
