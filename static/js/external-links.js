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

/* Use the existing server-side profiler switch for every localized navigation menu. */
function g_applyProfilerMenus(tools, more, enabled) {
    if (enabled === true)
        return;
    for (var i = tools.length - 1; i >= 0; --i)
        if (tools[i][0] === 5)
            tools.splice(i, 1);
    for (var i = 0; i < more.length; ++i) {
        if (more[i][0] !== 13 || !Array.isArray(more[i][3]))
            continue;
        var help = more[i][3];
        for (var j = help.length - 1; j >= 0; --j)
            if (help[j][0] === 6)
                help.splice(j, 1);
    }
}

/* Keep goodies navigation in sync with the independent server-side page switches. */
function g_applyGoodiesMenus(more, searchpluginsEnabled, searchboxEnabled) {
    for (var i = more.length - 1; i >= 0; --i)
        if ((more[i][0] === 8 && searchpluginsEnabled !== true) ||
            (more[i][0] === 16 && searchboxEnabled !== true))
            more.splice(i, 1);
}

/* Gate the public missing-screenshots utility without removing other contribution tools. */
function g_applyMissingScreenshotsMenu(tools, enabled) {
    if (enabled === true)
        return;
    for (var i = 0; i < tools.length; ++i) {
        if (tools[i][0] !== 8 || !Array.isArray(tools[i][3]))
            continue;
        var utilities = tools[i][3];
        for (var j = utilities.length - 1; j >= 0; --j)
            if (utilities[j][0] === 13)
                utilities.splice(j, 1);
    }
}

/* Sounds are optional site functionality, including their playlist submenu. */
function g_applySoundsMenu(database, enabled) {
    if (enabled === true)
        return;
    for (var i = database.length - 1; i >= 0; --i)
        if (database[i][0] === 19)
            database.splice(i, 1);
}
