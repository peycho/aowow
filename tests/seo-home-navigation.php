<?php

// Real jQuery readiness, Menu and homepage code; delay readiness beyond window
// load to reproduce the duplicate fallback regardless of network/cache timing.
$root = dirname(__DIR__);
echo '<!doctype html><html><head><meta charset="utf-8"></head><body><pre id="result">PENDING</pre>';
echo '<div id="home-menu"></div><div id="home-search"><form><input></form></div>';
echo '<nav id="server-navigation"><a href="?items">Items</a><a href="?spells">Spells</a></nav>';
echo '<script>window.fixtureErrors=[]; window.addEventListener("error", function(e) { fixtureErrors.push(e.message); }); window.g_staticUrl=""; window.g_host=""; window.g_user={debug:false,roles:0};</script>';
foreach (['static/js/jquery-3.7.0.min.js', 'static/js/basic.js', 'setup/tools/filegen/templates/global.js/0_user.js',
    'static/js/locale_enus.js', 'setup/tools/filegen/templates/global.js/utilities.js',
    'static/js/server-content.js', 'setup/tools/filegen/templates/global.js/menu.js'] as $file)
    echo '<script>{const source=document.createElement("script"); source.textContent='.json_encode(file_get_contents($root.'/'.$file), JSON_HEX_TAG | JSON_THROW_ON_ERROR).';document.head.appendChild(source);}</script>';
echo <<<'JS'
<script>
$.holdReady(true);
// Isolate unrelated search/analytics dependencies; navigation uses the real Menu.
window.mn_path = [[0, 'Database', null, [[1, 'Items', '?items'], [2, 'Spells', '?spells']]]];
window.LiveSearch = { attach: function () {} };
window.g_preventEmptyFormSubmission = function () {};
</script>
JS;
echo '<script>{const source=document.createElement("script"); source.textContent='.json_encode(file_get_contents($root.'/static/js/home.js'), JSON_HEX_TAG | JSON_THROW_ON_ERROR).';document.head.appendChild(source);}</script>';
echo <<<'JS'
<script>
window.addEventListener('load', function () {
    var checks = 0;
    function check(ok, label) { ++checks; if (!ok) throw new Error(label); }
    setTimeout(function () {
        try {
            check(!!document.getElementById('server-navigation'), 'late menu retains fallback after load');
            check(!document.querySelector('#home-menu a'), 'menu has not been initialized yet');
            $(function () {
                try {
                    check(!!document.querySelector('#home-menu .menu-buttons a span.hassubmenu'), 'real homepage menu initialized');
                    check(!document.getElementById('server-navigation'), 'successful late homepage menu removes fallback');
                    check(fixtureErrors.length === 0, 'no browser exceptions: ' + fixtureErrors.join(' | '));
                    document.getElementById('result').textContent = 'PASS: ' + checks + ' late homepage navigation checks';
                } catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
            });
            $.holdReady(false);
        } catch (error) { document.getElementById('result').textContent = 'FAIL: ' + error.message; }
    }, 0);
});
</script></body></html>
JS;
