// Shared checks execute the actual markup implementation in Node and the browser.
function checkCodeqlMarkup(check) {
    const value = `A & B "one" "two" 'three' <four> \\ $& $'`;
    check(Markup._safeQuotes(value) === `A &amp; B &quot;one&quot; &quot;two&quot; &#39;three&#39; &lt;four&gt; \\ $&amp; $&#39;`,
        'HTML attributes encode every metacharacter and preserve literal replacement tokens');
    const allow = Markup.allow;
    try {
        Markup.allow = Markup.CLASS_STAFF;
        check(Markup._addGlobalAttributes({title: value}) === ' title="' + Markup._safeQuotes(value) + '"',
            'Staff titles use attribute encoding');
        Markup.allow = Markup.CLASS_USER;
        check(Markup._addGlobalAttributes({title: value}) === '', 'User markup cannot add staff attributes');
    }
    finally { Markup.allow = allow; }

    for (const link of ['https://wowhead.com/item=1', 'https://sub.wowhead.com/item=1',
        'HTTPS://WOWHEAD.COM.:8443/item=1', 'https://unrelated.test@wowhead.com/item=1',
        '?item=1', '/subdir/?item=1', '#section'])
        check(Markup.IsLinkAllowed(link), 'Allowed parsed hostname or local reference: ' + link);
    for (const link of ['https://wowhead.com.evil.test/', 'https://wowheadXcom/',
        'https://notwowhead.com/', 'https://wowhead.com@evil.test/', 'https://evil.test/?wowhead.com',
        'https://wowhead.com%2eevil.test/', 'https://wоwhead.com/', 'https:/wowhead.com/',
        'https://', 'javascript:alert(1)', 'https://[invalid]/'])
        check(!Markup.IsLinkAllowed(link), 'Rejected deceptive or malformed URL: ' + link);
    const local = new URL(location.href).hostname.replace(/\.+$/, '');
    check(Markup.IsLinkAllowed('https://' + local + '/'), 'Current hostname remains allowed');
    const html = text => Markup.toHtml(text, {mode: Markup.MODE_COMMENT, allow: Markup.CLASS_PENDING});
    check(!html('https://wowhead.com.evil.test/item=1').includes('<a '), 'Pending autolinks enforce the hostname boundary');
    for (const domain of ['torhead.com', 'guildfans.com', 'sc2ranks.com', 'tankspot.com', 'be-imba.hu',
        'vindictusdb.com', 'worldoflogs.com', 'torchlightarmory.com', 'wow-heroes.com', 'elitistjerks.com', 'wowinsider.com']) {
        check(!Markup.IsLinkAllowed('https://' + domain + '/'), 'Obsolete or unverified domain is not allowed: ' + domain);
        check(!Markup.IsLinkAllowed('https://sub.' + domain + '/'), 'Removed domain subdomains are not allowed: ' + domain);
        check(!html('https://' + domain + '/').includes('<a '), 'Pending autolinks exclude removed domain: ' + domain);
    }
    check(Markup.toHtml('[url=https://example.test/reference]Authored[/url]',
        {mode: Markup.MODE_COMMENT, allow: Markup.CLASS_USER}).includes('<a '),
        'Explicit authored links keep their separate permission policy');
    check(Markup.removeTags('&lt;button&gt;') === '&lt;button&gt;', 'Text extraction decodes original entities once');
    check(Markup.removeTags('<button>') === '<button>', 'Text extraction preserves literal angle brackets as text');
    check(Markup.fromHtml('&amp;#160;') === '&#160;', 'HTML conversion does not recursively decode numeric whitespace');
    check(Markup.fromHtml('A&nbsp;B&#160;C') === 'A B C', 'Original whitespace entities still convert');
    check(Markup.fromHtml('<b>Bold</b><br><i>Italic</i>') === '[b]Bold[/b]\n[i]Italic[/i]', 'Supported HTML formatting remains markup');
    for (const source of ['<!-- first\nsecond -->Visible', '<STYLE>hidden</STYLE>Visible',
        '<sty<style>hidden</style>le>hidden again</style>Visible', '<!<!-- inner -->-- hidden -->Visible'])
        check(Markup.fromHtml(source) === 'Visible', 'HTML conversion removes complete comments/styles: ' + source);
    check(typeof Markup._safeJsString === 'undefined', 'Unused JavaScript-string helper is retired');
    for (const [id, source] of Object.entries(g_condition_sources)) {
        const description = source[1];
        const targets = [...description.matchAll(/\$T([^:]*):([^;]*);/g)];
        check(targets.length <= 1, 'Condition description has at most one targeting expression: ' + id);
        for (const expression of targets) for (const branch of expression.slice(1))
            check((branch.match(/%/g) || []).length <= 1, 'Targeting branch has at most one substitution: ' + id);
    }
}

async function checkCodeqlBrowser(check) {
    checkCodeqlMarkup(check);
    window.codeqlMarker = 0;
    const probe = document.createElement('button');
    probe.setAttribute('onclick', 'window.codeqlMarker++');
    probe.click();
    check(window.codeqlMarker === 1, 'Fixture permits inline handlers; CSP cannot mask these assertions');
    window.codeqlMarker = 0;

    const host = document.createElement('div');
    document.body.appendChild(host);
    const allow = Markup.allow;
    const ajax = window.Ajax;
    const originalUrl = location.href;
    try {
        Markup.allow = Markup.CLASS_STAFF;
        const title = '" onfocus="window.codeqlMarker++" data-extra="x & < > \' "';
        host.innerHTML = '<span' + Markup._addGlobalAttributes({title}) + '>Title</span>';
        check(host.firstChild.getAttribute('title') === title, 'Quoted title round-trips as one attribute');
        check(host.firstChild.attributes.length === 1, 'Title cannot create event handlers or extra attributes');
        host.firstChild.dispatchEvent(new Event('focus'));

        const text = '<button onclick="window.codeqlMarker++">Literal name</button>';
        const td = document.createElement('td');
        Listview.templates.changelog.columns[0].compute({tooltip: '<div><b>' + Markup._safeQuotes(text) + '</b></div>'}, td);
        check(td.textContent === text && !td.firstElementChild, 'Changelog name remains text after entity decoding');

        // Text extraction is also consumed by an HTML attribute in the legacy video tag.
        const videos = g_videos, uid = Markup.uid, thumbnail = vi_thumbnails[1], site = vi_siteurls[1];
        const caption = 'Caption " onfocus="window.codeqlMarker++" data-extra="value';
        try {
            g_videos = {1: {id: 1, videoType: 1, videoId: 'fixture', hasCaption: true, caption}};
            Markup.uid = 'codeql-video';
            vi_thumbnails[1] = g_staticUrl + '/images/ui/misc/selection-h.gif?id=$1';
            vi_siteurls[1] = '?video=$1';
            host.innerHTML = Markup.tags.video.toHtml({id: 1});
            const image = host.querySelector('img[alt]');
            check(image.getAttribute('alt') === caption, 'Video caption round-trips as an alt value');
            check(!image.hasAttribute('onfocus') && !image.hasAttribute('data-extra'), 'Extracted video text cannot create attributes');
            image.dispatchEvent(new Event('focus'));
        }
        finally {
            g_videos = videos;
            Markup.uid = uid;
            vi_thumbnails[1] = thumbnail;
            vi_siteurls[1] = site;
        }

        history.replaceState(null, '', '?profile=eu.realm.Name&literal=$&$%27&label=%22%3Cbutton%3E');
        const expectedLink = '?' + location.search.slice(1);
        window.Ajax = function(url, options) { options.onSuccess({responseText: '[1,[3,0,1,0,0]]'}, {}); };
        pr_updateStatus('profile', host, 1, false, function() {});
        check(host.querySelector('a[rel="np"]').getAttribute('href') === expectedLink,
            'Profile status preserves query delimiters and replacement metacharacters');
        check(!host.querySelector('button, img, [onfocus]'), 'Profile status creates no supplied HTML');

        for (const value of [0, 81, -1, 12.5, '12x', 'Infinity', null, true, '<button>'])
            check(Summary.normalizeLevel(value) === 80, 'Invalid comparison level defaults to 80');
        for (const value of [1, 80, '12', ' 60 '])
            check(Summary.normalizeLevel(value) === Number(value), 'Valid comparison level is an integer');
        const comparisonHost = document.createElement('div');
        comparisonHost.id = 'codeql-comparison';
        host.appendChild(comparisonHost);
        const gets = $WH.g_getGets, cookie = window.g_getWowheadCookie;
        try {
            for (const source of ['query', 'cookie', 'option', 'template']) {
                $WH.g_getGets = () => source === 'query' ? {l: '81'} : {};
                window.g_getWowheadCookie = () => source === 'cookie' ? 'bad' : null;
                const originalLevel = Summary.templates.compare.level;
                if (source === 'template') Summary.templates.compare.level = '<button>';
                try {
                    const summary = new Summary({id: comparisonHost.id, template: 'compare', groups: [], weights: [],
                        level: source === 'option' ? 12.5 : undefined, initialize() {}});
                    check(summary.level === 80, 'Constructor normalizes level from ' + source);
                }
                finally { Summary.templates.compare.level = originalLevel; }
            }
        }
        finally {
            $WH.g_getGets = gets;
            window.g_getWowheadCookie = cookie;
            delete g_summaries[comparisonHost.id];
        }
        check($WH.sprintf('one $1 two', "$& $' $` $$") === "one $& $' $` $$ two", 'Interpolation values remain literal');
        check(window.codeqlMarker === 0, 'Supplied content executed no handler');
    }
    finally {
        Markup.allow = allow;
        window.Ajax = ajax;
        history.replaceState(null, '', originalUrl);
        host.remove();
        delete window.codeqlMarker;
    }
}
