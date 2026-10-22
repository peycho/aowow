/* Initial HTML stays available until its interactive replacement has succeeded. */
(function (root) {
    'use strict';
    function navigation() {
        var navigation = document.getElementById('server-navigation');
        var interactive = document.querySelector('#toptabs a, #home-menu a');
        if (navigation && interactive) navigation.remove();
        var breadcrumb = document.getElementById('server-breadcrumb');
        if (breadcrumb && document.querySelector('.breadcrumb a')) breadcrumb.remove();
    }
    function lists(id) {
        var fallback = document.getElementById(id);
        if (!fallback) return;
        fallback.querySelectorAll('[data-listview]').forEach(function (section) {
            var key = section.getAttribute('data-listview');
            var view = root.g_listviews && root.g_listviews[key];
            if (!view || !view.container) return;
            if (view.container.firstElementChild) section.remove();
            else if (view.tabs) {
                // Lazy tabs already have hidden containers. Keep their HTML inside
                // the tab until that particular list finishes initializing.
                var afterCreate = view.onAfterCreate;
                view.onAfterCreate = function () {
                    if (afterCreate) afterCreate.apply(this, arguments);
                    section.remove();
                };
                view.container.appendChild(section);
                if (view.tabs.selectedTab !== view.tabIndex) view.container.style.display = 'none';
            }
        });
        if (!fallback.querySelector('[data-listview]')) fallback.remove();
    }
    root.AowowServerContent = { navigation: navigation, lists: lists };
    document.addEventListener('DOMContentLoaded', navigation);
    root.addEventListener('load', navigation);
})(window);
