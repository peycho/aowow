// These checks concern URL-property assignments, not a promise that every URL is a sanitizer.
function checkCodeqlUrls(check) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const safeLocal = (anchor, prefix, label) => {
        check(anchor && anchor.hasAttribute('href'), label + ': actual anchor has a destination');
        const url = new URL(anchor.href, location.href);
        check(url.origin === location.origin && anchor.getAttribute('href').startsWith(prefix), label);
        check(!anchor.querySelector('button, img, [onfocus]'), 'URL input cannot become link contents');
    };
    try {
        const profile = {region: 'eu', realm: 'fixture', name: 'Name', guild: 'Guild &lt;button&gt; "quoted"', id: 1};
        const template = Listview.templates.profile;
        const name = template.columns.find(column => column.id === 'name');
        const guild = template.columns.find(column => column.id === 'guild');
        const row = document.createElement('tr'), cell = document.createElement('td');
        name.compute.call(template, profile, cell, row);
        safeLocal(cell.querySelector('a[href^="?guild="]'), '?guild=eu.fixture.', 'Profile-row guild URL stays local');
        const guildCell = document.createElement('td');
        guild.compute.call(template, profile, guildCell);
        safeLocal(guildCell.firstChild, '?guild=eu.fixture.', 'Guild-column URL stays local');

        const comparison = document.createElement('a');
        comparison.href = Summary.prototype.getLink('1;2', [[], [], []], 60, 0);
        safeLocal(comparison, '?compare=1;2&l=60', 'Comparison keeps its existing URL grammar');
        const icon = Icon.create('inv_misc_questionmark', 1, null, '?item=1&label="<button>"');
        safeLocal(icon.querySelector('a'), '?item=1', 'Icon href is assigned as a URL property');

        for (const [kind, tableId, update] of [['screenshots', 'theScreenshotsList', ssm_UpdateMassLinks],
            ['videos', 'theVideosList', vim_UpdateMassLinks]]) {
            host.innerHTML = '<div id="' + tableId + '"><input type="checkbox" checked value="7"><input type="checkbox" checked value="9"></div>' +
                '<div id="withselected"><b>Selected</b></div><a id="massapprove"></a><a id="massdelete"></a><a id="masssticky"></a>';
            update();
            for (const [id, action] of [['massapprove', 'approve'], ['massdelete', 'delete'], ['masssticky', 'sticky']]) {
                const anchor = host.querySelector('#' + id);
                safeLocal(anchor, '?admin=' + kind + '&action=' + action + '&id=7,9', 'Moderation URL remains local');
                check(anchor.getAttribute('href') === '?admin=' + kind + '&action=' + action + '&id=7,9',
                    'Integer moderation IDs preserve their comma-separated format');
            }
        }

        for (const value of [location.origin + g_staticUrl + '/images/ui/misc/selection-h.gif?label="<button>"',
            'inv_misc_questionmark" onerror="window.codeqlMarker++']) {
            $('#icon').val(value).trigger('keyup');
            const preview = document.getElementById('iconPreview');
            check(preview.children.length === 1 && preview.firstElementChild.tagName === 'IMG', 'Editor creates one image element');
            check(preview.firstElementChild.attributes.length === 1 && !preview.firstElementChild.hasAttribute('onerror'),
                'Editor src values cannot create HTML attributes');
        }
        $('#iconPreview').empty();
    }
    finally { host.remove(); }
}
