/* Mobile layout enhancement: original tables/maps remain intact and scrollable. */
(function (root) {
    'use strict';
    var narrow = root.matchMedia('(max-width: 979px)');
    var phone = root.matchMedia('(max-width: 600px)');
    var wrappers = [], facts = null, scheduled = false, observer;
    var labels = { en: 'Scrollable content', fr: 'Contenu défilant', de: 'Verschiebbarer Inhalt', zh: '可滚动内容', es: 'Contenido desplazable', ru: 'Прокручиваемое содержимое' };
    function enhance() {
        if (observer) observer.disconnect();
        if (!narrow.matches) {
            wrappers.forEach(function (wrapper) { if (wrapper.parentNode) wrapper.replaceWith.apply(wrapper, wrapper.childNodes); });
            wrappers = [];
            document.querySelectorAll('[data-responsive-scroll]').forEach(function (element) {
                element.getAttribute('data-responsive-scroll').split(' ').forEach(function (attribute) { element.removeAttribute(attribute); });
                element.removeAttribute('data-responsive-scroll');
            });
        } else {
            document.querySelectorAll('.main-contents table.listview-mode-default, .text table.grid, .text > table:not(.infobox), .text .mapper').forEach(function (table) {
                if (table.closest('.responsive-scroll, #mapper, #mapper-generic') || table.parentElement.closest('table')) return;
                var wrapper = document.createElement('div');
                wrapper.className = 'responsive-scroll';
                table.before(wrapper); wrapper.appendChild(table); wrappers.push(wrapper);
            });
            document.querySelectorAll('.responsive-scroll, #mapper, #mapper-generic, #talent-itself, #petcalc-itself, #tc-classes, #pc-classes, #tc-itself, #pc-itself, .text > .wowhead-tooltip, #fi').forEach(function (element) {
                if (element.scrollWidth <= element.clientWidth + 1 || element.hasAttribute('tabindex')) return;
                var added = ['tabindex'];
                element.setAttribute('tabindex', '0');
                if (!element.hasAttribute('role')) { element.setAttribute('role', 'region'); added.push('role'); }
                if (!element.hasAttribute('aria-label')) {
                    element.setAttribute('aria-label', labels[(document.documentElement.lang || 'en').slice(0, 2)] || labels.en);
                    added.push('aria-label');
                }
                element.setAttribute('data-responsive-scroll', added.join(' '));
            });
        }
        if (phone.matches && !facts) {
            var box = document.querySelector('.main-contents > .infobox');
            var heading = document.querySelector('.main-contents > .text > h1');
            if (box && heading) {
                facts = { box: box, parent: box.parentNode, next: box.nextSibling };
                heading.after(box);
            }
        } else if (!phone.matches && facts) {
            facts.parent.insertBefore(facts.box, facts.next && facts.next.parentNode === facts.parent ? facts.next : null);
            facts = null;
        }
        if (narrow.matches && observer)
            document.querySelectorAll('.main-contents, .home-wrapper').forEach(function (element) { observer.observe(element, { childList: true, subtree: true }); });
    }
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        root.requestAnimationFrame(function () { scheduled = false; enhance(); });
    }
    function init() {
        observer = new MutationObserver(schedule);
        enhance();
        narrow.addEventListener('change', schedule);
        phone.addEventListener('change', schedule);
        root.addEventListener('load', schedule);
    }
    // Reuse the existing menu API for touch. The first tap opens a submenu;
    // a second tap on the same link may follow its existing destination.
    var touched = null, touchLink = null;
    document.addEventListener('pointerdown', function (event) {
        touchLink = event.pointerType === 'touch' ? event.target.closest('a') : null;
        if (event.pointerType === 'touch' && narrow.matches && !event.target.closest('.menu, .menu-buttons, #toptabs')) {
            touched = null;
            if (root.Menu) root.Menu.hide();
        }
    });
    document.addEventListener('click', function (event) {
        if (!narrow.matches || !root.Menu || !root.jQuery) return;
        var link = event.target.closest('.menu-buttons a, #toptabs a, .menu a');
        if (!link || (event.pointerType !== 'touch' && touchLink !== link)) return;
        var menu = root.jQuery(link).data('menu');
        var item = root.jQuery(link).data('menuItem');
        if (!menu && item) menu = item[3];
        if (!menu || touched === link) return;
        touched = link;
        root.Menu.show(menu, link);
        event.preventDefault();
        event.stopPropagation();
    }, true);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})(window);
