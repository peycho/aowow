/* Optional form-only Cloudflare verification. Never contains the server secret. */
var AowowTurnstile = (function () {
    'use strict';
    var states = new WeakMap(), pending = new Set(), loading = false;
    function enabled(action) {
        return typeof g_turnstile !== 'undefined' && g_turnstile.actions.indexOf(action) !== -1;
    }
    function clear(state, error) {
        state.input.value = '';
        state.status.textContent = error ? g_turnstile.error : '';
    }
    function render(form) {
        var state = states.get(form);
        if (!state || !form.contains(state.box) || state.id !== null) return;
        try {
            state.id = turnstile.render(state.widget, {
                sitekey: g_turnstile.siteKey, action: state.action, theme: 'auto', 'response-field': false,
                callback: function (token) { state.input.value = token; state.status.textContent = ''; },
                'expired-callback': function () { clear(state, false); },
                'error-callback': function () { clear(state, true); },
                'timeout-callback': function () { clear(state, true); },
                'refresh-expired': 'auto'
            });
        } catch (_) { clear(state, true); }
    }
    function load(form) {
        if (!g_turnstile.siteKey) { clear(states.get(form), true); return; }
        if (typeof turnstile !== 'undefined') { render(form); return; }
        pending.add(form);
        if (loading) return;
        loading = true;
        var script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=aowowTurnstileLoaded';
        script.async = true;
        script.defer = true;
        script.onerror = function () {
            loading = false;
            pending.forEach(function (f) { var state = states.get(f); if (state) clear(state, true); });
            pending.clear();
            script.remove();
        };
        document.head.appendChild(script);
    }
    function remove(form) {
        var state = states.get(form);
        if (!state) return;
        if (state.id !== null && typeof turnstile !== 'undefined') turnstile.remove(state.id);
        state.input.remove();
        state.box.remove();
        states.delete(form);
        pending.delete(form);
    }
    function mount(form, action, box) {
        if (!enabled(action)) return;
        var state = states.get(form);
        if (state && form.contains(state.box)) return;
        remove(form);
        box = box || document.createElement('div');
        box.style.textAlign = 'center';
        box.style.margin = '12px 0';
        var widget = document.createElement('div'), status = document.createElement('div'), input = document.createElement('input');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        input.type = 'hidden'; input.name = 'cf-turnstile-response';
        box.appendChild(widget); box.appendChild(status); form.appendChild(input);
        if (!form.contains(box)) form.appendChild(box);
        state = {action: action, box: box, widget: widget, status: status, input: input, id: null};
        states.set(form, state);
        load(form);
    }
    function token(form, action) {
        if (!enabled(action)) return '';
        mount(form, action);
        var state = states.get(form);
        if (state.id === null) load(form);                 // allow another submit attempt after the API script failed
        if (!state.input.value) clear(state, true);
        return state.input.value;
    }
    function reset(form) {
        var state = states.get(form);
        if (!state) return;
        clear(state, false);
        if (state.id !== null && typeof turnstile !== 'undefined') turnstile.reset(state.id);
    }
    function init() {
        document.querySelectorAll('[data-turnstile-action]').forEach(function (box) {
            var form = box.closest('form'), action = box.getAttribute('data-turnstile-action');
            if (!form || !enabled(action)) return;
            mount(form, action, box);
            form.addEventListener('submit', function (event) {
                if (!token(form, action)) { event.preventDefault(); event.stopImmediatePropagation(); }
            }, true);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
    return {enabled: enabled, mount: mount, token: token, reset: reset, remove: remove,
        loaded: function () { pending.forEach(render); pending.clear(); }};
})();
function aowowTurnstileLoaded() { AowowTurnstile.loaded(); }
