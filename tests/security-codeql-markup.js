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
    check(Markup.toHtml('[url=https://example.test/reference]Authored[/url]',
        {mode: Markup.MODE_COMMENT, allow: Markup.CLASS_USER}).includes('<a '),
        'Explicit authored links keep their separate permission policy');
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
