// Shared assertions run against the real parser in Node and in the browser bundle.
function checkRetirementMarkup(check) {
    const html = text => Markup.toHtml(text, {mode: Markup.MODE_ARTICLE, allow: Markup.CLASS_STAFF});
    for (const tag of ['model', 'modelviewer']) {
        const fallback = html(`[${tag} npc=1 img=https://wowimg.zamimg.com/ignored.jpg label=Example]Old label[/${tag}]`);
        check(fallback.includes('Example') && fallback.includes(LANG.modelviewer_retired), `${tag}: localized fallback`);
        check(!/<(?:script|img|object|embed|a)\b/i.test(fallback), `${tag}: no scripts, thumbnails or viewer links`);
        const unsafe = Markup.tags[tag].toHtml({label: '<img src=x onerror=alert(1)>', _contents: ''})[0];
        check(unsafe.includes('&lt;img') && !unsafe.includes('<img'), `${tag}: labels are escaped`);
        const body = html(`[${tag} npc=1]<b>Old label</b>[/${tag}]`);
        check(body.includes('&lt;b&gt;Old label&lt;/b&gt;') && !body.includes('<b>'), `${tag}: body labels remain escaped text`);
        const nested = html(`[${tag} npc=1][img=https://wowimg.zamimg.com/ignored.jpg]Old label[/${tag}]`);
        check(!nested.includes('<img'), `${tag}: nested thumbnail markup stays inert`);
    }
    g_externalLinks = {forum: 'https://forum.example.test/rules?a=1&b=2'};
    check(html('[forumrules]').includes('href="https://forum.example.test/rules?a=1&amp;b=2"'), 'Forum rules use configured escaped URL');
    g_externalLinks.forum = null;
    check(html('[forumrules]') === LANG.forum_rules, 'Disabled forum rules render plain localized text');
    for (const enabled of [false, true, false]) {
        g_feedbackEnabled = enabled;
        check(html('[feedback]').includes('ContactTool.show') === enabled, 'Feedback markup follows the runtime switch');
        check(html('[feedback mailto=true]').includes('mailto:'), 'Authored direct email links remain available');
    }
    g_feedbackEnabled = true;
    const quote = html('[quote=Blizzard blizzard=true url=https://eu.battle.net/wow/en/forum/topic/123]Original text[/quote]');
    check(quote.includes('https://eu.battle.net/wow/en/forum/topic/123'), 'Original Blizzard post link survives');
    check(!quote.includes('wowhead.com') && !quote.includes('Blue Tracker'), 'No automatic Blue Tracker link');
    check(html('[item=1]').includes('href="?item=1"'), 'Ordinary game markup resolves locally');
    check(html('[item=1 domain=www]').includes('http://www.wowhead.com?item=1'), 'Explicit external source survives');
    for (const [source, domain] of [['live', 'live'], ['ptr', 'ptr'], ['beta', 'mop']])
        check(html(`[db=${source}][item=1]`).includes(`http://${domain}.wowhead.com?item=1`), `Explicit ${source} source survives`);
    check(html('[url=https://www.wowhead.com/item=1]Authored reference[/url]').includes('https://www.wowhead.com/item=1'), 'Authored literal external links survive');
    check(!JSON.stringify(mn_more).includes('?help=modelviewer'), 'Viewer help is absent from menu');
}
