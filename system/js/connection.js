/* Connection watchdog.
 *
 * Loaded by sidebar.php, so it runs on every protected screen and on none of
 * the public ones — sidebar.php is included by exactly the eleven pages behind
 * requireAuth(), and not by login.php.
 *
 * Three different failures, three different responses:
 *
 *   session gone (HTTP 401)   -> straight to login. The server has already
 *                                refused; there is nothing to preserve and no
 *                                point counting down.
 *   network / server down     -> banner with a countdown, then sign out. The
 *                                countdown is the whole point: office wifi
 *                                drops for a few seconds routinely, and
 *                                signing out on a blip would throw away a
 *                                half-typed contract.
 *   database down (HTTP 503)  -> treated as "down". The network is fine but
 *                                nothing in the app works, so sitting on a
 *                                dead screen helps nobody.
 *
 * One honest limitation. The session cookie is httponly (see config.php), so
 * this cannot clear it from JavaScript — signing out means reaching
 * logout.php, and if the machine is genuinely offline that request cannot go
 * anywhere either. What happens then: the browser shows its own "can't reach
 * this page" error, so the screen full of purchase orders is gone from view,
 * and because location.replace() has already put logout.php in the address
 * bar, the next reload destroys the session for real. The screen is secured
 * immediately; the server-side session ends as soon as the machine can talk
 * to the server again.
 */
(function () {
    'use strict';

    // How often to check while everything is healthy.
    var PING_INTERVAL_MS = 20000;
    // How often to retry once something is wrong — faster, so a brief blip
    // clears the banner quickly instead of leaving it up for a full interval.
    var RETRY_INTERVAL_MS = 4000;
    // How long the connection may stay down before signing out.
    var GRACE_MS = 60000;
    // Anything slower than this counts as unreachable. Without a cap a request
    // on a dying connection can hang for minutes, and the watchdog would sit
    // there thinking all is well.
    var PING_TIMEOUT_MS = 8000;

    var PING_URL   = 'api/ping.php';
    var LOGIN_URL  = 'login.php?expired=1';
    var LOGOUT_URL = 'logout.php';

    var downSince = null;   // ms timestamp of the first failure, or null when up
    var timer     = null;   // the ping timer
    var ticker    = null;   // the 1s countdown repaint
    var finished  = false;  // set once we are on our way out; stops all further work
    var banner    = null;

    // ── Banner ──────────────────────────────────────────────────────────
    // Appended straight to <body> and fixed-position, never nested inside
    // .top-navbar. That element is position:sticky with a z-index, which makes
    // it a stacking context — the same trap documented in CLAUDE.md that
    // leaves a modal stranded under its own backdrop. A high z-index here
    // clears Bootstrap's modal (1055) and backdrop (1050) as well.
    function styles() {
        return '' +
        '#connWatchdog{position:fixed;left:50%;transform:translateX(-50%);top:0;z-index:20000;' +
        'max-width:min(680px,94vw);margin-top:12px;border-radius:12px;padding:.7rem 1rem;' +
        'font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.75rem;' +
        'box-shadow:0 10px 30px rgba(15,23,42,.25);border:1px solid transparent;' +
        'font-family:inherit;line-height:1.35;}' +
        '#connWatchdog.is-down{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}' +
        '#connWatchdog.is-back{background:#f0fdf4;border-color:#bbf7d0;color:#15803d;}' +
        '#connWatchdog .cw-msg{flex:1 1 auto;}' +
        '#connWatchdog .cw-sub{display:block;font-weight:500;opacity:.85;font-size:.78rem;margin-top:.15rem;}' +
        '#connWatchdog button{border:1px solid currentColor;background:transparent;color:inherit;' +
        'border-radius:20px;padding:.22rem .7rem;font-size:.76rem;font-weight:700;cursor:pointer;' +
        'white-space:nowrap;}' +
        '#connWatchdog button:hover{background:rgba(0,0,0,.06);}' +
        '@media print{#connWatchdog{display:none!important;}}';
    }

    function ensureBanner() {
        if (banner) return banner;
        var css = document.createElement('style');
        css.textContent = styles();
        document.head.appendChild(css);

        banner = document.createElement('div');
        banner.id = 'connWatchdog';
        banner.setAttribute('role', 'status');
        // Announced politely rather than assertively: the countdown repaints
        // every second, and an assertive region would interrupt a screen
        // reader on every tick.
        banner.setAttribute('aria-live', 'polite');
        banner.innerHTML =
            '<span class="cw-msg"></span>' +
            '<button type="button" class="cw-retry">Retry now</button>';
        banner.querySelector('.cw-retry').addEventListener('click', function () { check(true); });
        document.body.appendChild(banner);
        return banner;
    }

    function hideBanner() {
        if (banner) banner.style.display = 'none';
    }

    function showDown(secondsLeft) {
        var el = ensureBanner();
        el.style.display = 'flex';
        el.className = 'is-down';
        el.querySelector('.cw-retry').style.display = '';
        el.querySelector('.cw-msg').innerHTML =
            'Connection lost — signing out in ' + secondsLeft + 's' +
            '<span class="cw-sub">Anything not yet saved will be lost. Reconnect to cancel.</span>';
    }

    function showBack() {
        var el = ensureBanner();
        el.style.display = 'flex';
        el.className = 'is-back';
        el.querySelector('.cw-retry').style.display = 'none';
        el.querySelector('.cw-msg').textContent = 'Connection restored.';
        setTimeout(hideBanner, 3000);
    }

    // ── Leaving ─────────────────────────────────────────────────────────
    function leave(url) {
        if (finished) return;
        finished = true;
        clearInterval(timer);
        clearInterval(ticker);
        // replace(), not assign(): the dead screen must not be one Back press
        // away, and it leaves logout.php in the address bar so a later reload
        // finishes the job if this navigation cannot complete right now.
        window.location.replace(url);
    }

    // A 401 means the server has already refused this session. No countdown —
    // there is nothing left to save to, so waiting only delays the inevitable.
    function sessionExpired() { leave(LOGIN_URL); }

    // ── State ───────────────────────────────────────────────────────────
    function markUp() {
        if (finished) return;
        if (downSince !== null) {
            downSince = null;
            clearInterval(ticker);
            ticker = null;
            showBack();
            schedule(PING_INTERVAL_MS);
        }
    }

    function markDown() {
        if (finished || downSince !== null) return;
        downSince = Date.now();
        paintCountdown();
        ticker = setInterval(paintCountdown, 1000);
        schedule(RETRY_INTERVAL_MS);
    }

    function paintCountdown() {
        if (finished || downSince === null) return;
        var left = Math.ceil((GRACE_MS - (Date.now() - downSince)) / 1000);
        if (left <= 0) return leave(LOGOUT_URL);
        showDown(left);
    }

    function schedule(intervalMs) {
        clearInterval(timer);
        timer = setInterval(check, intervalMs);
    }

    // ── The check ───────────────────────────────────────────────────────
    function check(force) {
        if (finished) return;
        // The browser's own verdict is free and instant, so trust it when it
        // says there is no network at all. It is only trusted in that
        // direction — navigator.onLine reporting true means "an interface is
        // up", which says nothing about whether the server can be reached.
        if (navigator.onLine === false) return markDown();

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timedOut = controller ? setTimeout(function () { controller.abort(); }, PING_TIMEOUT_MS) : null;

        // cache:'no-store' as well as the endpoint's own headers — belt and
        // braces, because a cached 200 would be indistinguishable from a
        // healthy server.
        fetch(PING_URL + (force ? '?t=' + Date.now() : ''), {
            cache: 'no-store',
            headers: { 'X-Connection-Watchdog': '1' },
            signal: controller ? controller.signal : undefined
        }).then(function (res) {
            if (timedOut) clearTimeout(timedOut);
            if (res.status === 401) return sessionExpired();
            // 5xx covers both "database unreachable" (503, from ping.php) and
            // "database connection failed" (500, from dbConnectionFailure).
            // Either way the app cannot be used.
            if (!res.ok) return markDown();
            markUp();
        }).catch(function () {
            if (timedOut) clearTimeout(timedOut);
            markDown();
        });
    }

    // ── Wiring ──────────────────────────────────────────────────────────
    // Every other request in the app goes through fetch(), so wrapping it
    // means a save that fails because the network died reports it immediately
    // rather than waiting for the next scheduled ping. Observing only: the
    // response is passed through untouched and its body is never read, which
    // would consume the stream the real caller is about to use.
    var nativeFetch = window.fetch;
    if (typeof nativeFetch === 'function') {
        window.fetch = function () {
            var args = arguments;
            return nativeFetch.apply(this, args).then(function (res) {
                if (!finished && res && res.status === 401) sessionExpired();
                else if (!finished && res && res.ok) markUp();
                return res;
            }, function (err) {
                // A caller aborting its own request is not a network failure.
                if (!finished && !(err && err.name === 'AbortError')) markDown();
                throw err;
            });
        };
    }

    window.addEventListener('offline', function () { markDown(); });
    // Coming back online only means an interface is up — confirm with a real
    // request before clearing the banner.
    window.addEventListener('online', function () { check(true); });

    function start() {
        schedule(PING_INTERVAL_MS);
        check();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
