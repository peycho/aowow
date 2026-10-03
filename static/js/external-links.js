/* Apply deployment URLs to localized menu entries before PageTemplate builds navigation. */
function g_applyExternalLinks(menu, links) {
    var keys = {3: 'forum', 7: 'blog', 4: 'irc', 6: 'facebook', 5: 'twitter', 12: 'discord'};
    for (var i = menu.length - 1; i >= 0; --i) {
        var key = keys[menu[i][0]];
        if (!key)
            continue;
        if (typeof links[key] === 'string' && links[key])
            menu[i][2] = links[key];
        else
            menu.splice(i, 1);
    }

    // Remove section headings left without entries (for example, when all social links are disabled).
    for (var i = menu.length - 1; i >= 0; --i)
        if (menu[i][0] == null && (i === menu.length - 1 || menu[i + 1][0] == null))
            menu.splice(i, 1);
}
