/* ── Office assistant ────────────────────────────────────────────────────────
 * Behaviour only. Everything the character DOES is CSS, keyed off one
 * data-state attribute on the root — this file never touches the drawing.
 * That split is what makes the avatar replaceable: swap the SVG in
 * assistant.php, keep the class names, and none of this changes.
 *
 * States: idle · greeting · thinking · speaking · nodding
 * ──────────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var root = document.getElementById('paRoot');
    if (!root) return;

    var panel    = document.getElementById('paPanel');
    var launcher = document.getElementById('paLauncher');
    var bubble   = document.getElementById('paBubbleText');
    var log      = document.getElementById('paLog');
    var chips    = document.getElementById('paChips');
    var form     = document.getElementById('paForm');
    var input    = document.getElementById('paText');
    var send     = document.getElementById('paSend');
    var status   = document.getElementById('paStatus');

    var API = 'api/assistant.php';
    var stateTimer = null;
    var opener = '';
    var greeted = false;

    /* ── The character ─────────────────────────────────────────────────────── */

    // One <template>, cloned into the launcher and the panel, so the two can
    // never drift apart.
    (function cloneAvatar() {
        var tpl = document.getElementById('paAvatarTemplate');
        if (!tpl) return;
        ['paAvatar', 'paLauncherAvatar'].forEach(function (id, i) {
            var host = document.getElementById(id);
            if (!host) return;
            host.appendChild(tpl.content.cloneNode(true));
            // Offset the second copy's blink so the two avatars on screen are
            // not in lockstep, which reads as a single image duplicated rather
            // than as one character.
            if (i === 1) {
                host.querySelectorAll('.av-eye').forEach(function (el) {
                    el.style.animationDelay = '-2.1s';
                });
            }
        });
    })();

    // The one place state changes. `hold` returns to idle automatically, which
    // is what keeps a one-shot animation (a wave, a nod) from sticking on
    // because some later code path forgot to clear it.
    function setState(name, hold) {
        clearTimeout(stateTimer);
        root.dataset.state = name;
        if (hold) stateTimer = setTimeout(function () { root.dataset.state = 'idle'; }, hold);
    }

    /* ── Speaking ──────────────────────────────────────────────────────────── */

    // Speaking time is derived from the length of the line: a two-word answer
    // held for four seconds looks broken, and a five-line list flashed for one
    // looks like a glitch. ~45ms a character, floored and capped.
    function speakDuration(text) {
        return Math.min(6500, Math.max(1400, String(text).length * 45));
    }

    function say(text, opts) {
        opts = opts || {};
        bubble.textContent = text;
        if (opts.nodFirst) {
            setState('nodding');
            setTimeout(function () { setState('speaking', speakDuration(text)); }, 550);
        } else {
            setState('speaking', speakDuration(text));
        }
    }

    function addMessage(text, who) {
        var el = document.createElement('div');
        el.className = 'pa-msg ' + who;
        el.textContent = text;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
    }

    // A chip is [label, message] — or [label, '', url] for one that navigates
    // rather than asks. The tracking answers need the second kind: "Open this
    // PO" sent as a message would match the same number again and redraw the
    // trail already on screen.
    function renderChips(list) {
        chips.innerHTML = '';
        (list || []).forEach(function (c) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pa-chip';
            b.textContent = c[0];
            b.addEventListener('click', function () {
                if (c[2]) { window.location.href = c[2]; return; }
                submit(c[1]);
            });
            chips.appendChild(b);
        });
    }

    /* ── Open / close ──────────────────────────────────────────────────────── */

    function open() {
        root.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        panel.setAttribute('aria-hidden', 'false');
        // Only on a real pointer: focusing the input on a phone throws the
        // keyboard up over the character the person just opened.
        if (window.matchMedia('(min-width: 768px)').matches) input.focus();
        if (!greeted) { greeted = true; loadGreeting(); }
    }

    function close() {
        root.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
        panel.setAttribute('aria-hidden', 'true');
        launcher.focus();
    }

    launcher.addEventListener('click', open);
    document.getElementById('paMinimise').addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('is-open')) close();
    });

    /* ── Talking to the server ─────────────────────────────────────────────── */

    function loadGreeting() {
        fetch(API + '?action=greeting', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'no greeting');
                opener = d.opener || '';
                // Wave first, then speak. The greeting animation is 2.6s, and
                // the line lands part-way through it rather than after — a
                // character that waves in silence and only then starts talking
                // reads as two separate events.
                setState('greeting');
                bubble.textContent = d.greeting;
                setTimeout(function () {
                    say(d.greeting + (opener ? '\n' + opener : ''));
                    renderChips(d.chips);
                }, 900);
            })
            .catch(function () {
                // A dead endpoint must not leave an empty bubble staring back.
                bubble.textContent = "Hello! I can't reach my notes at the moment, "
                                   + 'but the rest of the dashboard should be fine.';
                setState('idle');
            });
    }

    function submit(text) {
        text = String(text || '').trim();
        if (!text) return;

        addMessage(text, 'me');
        input.value = '';
        send.disabled = true;
        status.textContent = 'Thinking…';
        setState('thinking');

        var started = Date.now();

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) throw new Error(d.message || 'no answer');
                // A local answer can come back in 30ms, which makes the
                // thinking animation flash and vanish — it reads as a glitch
                // rather than as thought. Hold the floor at 600ms. This is
                // presentation, not a fake delay to seem clever: below it, the
                // state change is not perceivable at all.
                var wait = Math.max(0, 600 - (Date.now() - started));
                setTimeout(function () { deliver(d); }, wait);
            })
            .catch(function (err) {
                console.error(err);
                setTimeout(function () {
                    deliver({ text: "Sorry — I couldn't reach the server just then. Try again in a moment?", chips: [] });
                }, 400);
            });
    }

    function deliver(d) {
        status.textContent = 'Online';
        send.disabled = false;
        addMessage(d.text, 'bot');
        say(d.text, { nodFirst: true });
        renderChips(d.chips);

        // A navigation answer moves the page. Long enough to read the "opening
        // X" line first — a screen that changes under you with no warning is
        // disorienting even when it is the thing you asked for.
        if (d.go) setTimeout(function () { window.location.href = d.go; }, 900);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submit(input.value);
    });

    /* ── Idle ──────────────────────────────────────────────────────────────── */
    setState('idle');
})();
