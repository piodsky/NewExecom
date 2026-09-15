<?php
/* Office assistant — the animated avatar and its panel.
 *
 * A BLOCK comment, not a run of // lines, and that is load-bearing: PHP leaves
 * PHP mode at a closing tag even inside a // comment. The include example
 * below contains one, so as // lines it closed the tag right there and the
 * rest of this header was emitted onto the page as HTML — on every screen the
 * assistant appears on. Inside /* … *​/ a closing tag is just characters.
 * Keep this a block comment.
 *
 * Included at the END of <body>, like profile_modal.php and for the same
 * reason: it is fixed-position and overlays the page, and anything with a
 * z-index that lives inside .top-navbar gets trapped under Bootstrap's modal
 * backdrop (see the stacking-context note in CLAUDE.md).
 *
 * Included on every authenticated screen in the module. To add it to a new
 * one, put
 *
 *     <?php include 'assistant.php'; ?>
 *
 * immediately before </body> — it carries its own CSS and JS links and needs
 * nothing else from the host page.
 *
 * Deliberately NOT on: login.php (nobody is signed in yet, and
 * api/assistant.php needs a session), po_verify.php (a public page for
 * auditors scanning a printed QR — the app's chrome has no business on it), or
 * purchase_order_print.php (a print target; furniture must not end up on
 * paper).
 *
 * ── REPLACING THE CHARACTER ────────────────────────────────────────────────
 * The avatar is one inline SVG, below, and nothing outside it knows what it
 * looks like. To swap in your own, keep these class names on the equivalent
 * parts and every animation carries over untouched:
 *
 *     .av-body    the whole figure          (breathes)
 *     .av-head    head group                (nods, tilts, turns)
 *     .av-eye     one per eye               (blinks — scaled about its centre)
 *     .av-pupil   one per eye               (looks around)
 *     .av-brow    one per eyebrow           (rises when thinking)
 *     .av-mouth   mouth path                (opens while speaking)
 *     .av-smile   the smiling mouth path    (shown when greeting)
 *     .av-arm     the waving arm group      (waves)
 *
 * EVERY ONE of those must sit inside a static parent group that positions it,
 * with the class on an inner group that only transforms. A CSS transform
 * REPLACES an SVG element's transform attribute rather than adding to it, so
 * a class on the positioned element itself sends the part to the origin of the
 * drawing the moment it animates. That is the single easiest thing to get
 * wrong here.
 *
 * A Lottie file would also work and would let a designer author the character
 * directly — but it needs the ~300KB lottie-web runtime vendored into
 * vendor/js/, and this repo is deliberately CDN-free and dependency-light. For
 * a bust that blinks, nods and waves, hand-written SVG is smaller, has no
 * runtime, and can be restyled from CSS variables. Reach for Lottie only if
 * you want full-body character animation.
 */
?>
<link rel="stylesheet" href="<?= asset('css/assistant.css') ?>">

<div class="pa" id="paRoot" data-state="idle">

    <!-- Collapsed: a floating avatar button. aria-expanded is what tells a
         screen reader which of the two states this is. -->
    <button type="button" class="pa-launcher" id="paLauncher"
            aria-expanded="false" aria-controls="paPanel" aria-label="Open the office assistant">
        <span class="pa-launcher-avatar" id="paLauncherAvatar"></span>
        <span class="pa-launcher-badge" id="paBadge" aria-hidden="true"></span>
    </button>

    <!-- Expanded: the panel. Kept in the DOM rather than built on demand so the
         avatar's idle animation is already running when it opens. -->
    <section class="pa-panel" id="paPanel" role="dialog" aria-label="Office assistant" aria-hidden="true">

        <header class="pa-head">
            <div class="pa-avatar" id="paAvatar"><!-- avatar injected here --></div>
            <div class="pa-who">
                <div class="pa-name"><?= e(APP_NAME) ?> Assistant</div>
                <div class="pa-status" id="paStatus">Online</div>
            </div>
            <button type="button" class="pa-min" id="paMinimise" aria-label="Minimise the assistant">
                <i class="fas fa-minus"></i>
            </button>
        </header>

        <!-- The assistant's current line, shown large under the avatar. It is
             the speech bubble proper; the log below is the history. -->
        <div class="pa-bubble" id="paBubble" aria-live="polite">
            <span class="pa-bubble-text" id="paBubbleText"></span>
            <span class="pa-typing" id="paTyping" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>

        <div class="pa-log" id="paLog" role="log" aria-label="Conversation"></div>

        <div class="pa-chips" id="paChips"></div>

        <form class="pa-input" id="paForm" autocomplete="off">
            <input type="text" id="paText" maxlength="500" placeholder="Ask me something…"
                   aria-label="Message the assistant">
            <button type="submit" id="paSend" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
        </form>
    </section>
</div>

<!-- ── The character ──────────────────────────────────────────────────────────
     Held in a <template> and cloned into both the launcher and the panel, so
     one definition drives both and they can never drift apart.

     Built around a neck pivot at (60,84): the head and everything on it is
     drawn relative to that point, which is what lets a nod be a plain rotation
     with no transform-origin arithmetic — CSS transform-origin on an SVG
     element defaults to its own local origin. -->
<template id="paAvatarTemplate">
<svg class="av" viewBox="0 0 120 124" role="img" aria-label="Assistant avatar" focusable="false">
    <defs>
        <linearGradient id="avRing" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#3b82f6"/><stop offset="1" stop-color="#1e3a8a"/>
        </linearGradient>
        <linearGradient id="avShirt" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#f8fafc"/><stop offset="1" stop-color="#e2e8f0"/>
        </linearGradient>
        <clipPath id="avClip"><circle cx="60" cy="60" r="52"/></clipPath>
    </defs>

    <circle class="av-ring" cx="60" cy="60" r="52" fill="url(#avRing)"/>
    <circle cx="60" cy="60" r="47" fill="#eef4ff"/>

    <g clip-path="url(#avClip)">
    <g class="av-body">
        <!-- Shoulders: a suit jacket over a collared shirt. Professional
             rather than costumed — this is an office system, and a character
             in a mascot outfit would undercut everything else on the page. -->
        <path d="M18 124c0-19 13-30 27-34l15-4 15 4c14 4 27 15 27 34z" fill="#1e3a8a"/>
        <path d="M45 90l15 13 15-13 7 3-13 31H51L38 93z" fill="url(#avShirt)"/>
        <path d="M60 103l-8-9 8-4 8 4z" fill="#2563eb"/>
        <path d="M60 103l4 6-4 17-4-17z" fill="#2563eb"/>

        <g transform="translate(60,84)">
            <g class="av-head">
                <rect x="-6" y="-14" width="12" height="14" rx="4" fill="#e8b48c"/>
                <ellipse cx="0" cy="-30" rx="23" ry="26" fill="#f2c6a0"/>
                <!-- Ears, then hair over the crown. -->
                <ellipse cx="-23" cy="-30" rx="3.4" ry="5" fill="#e8b48c"/>
                <ellipse cx="23" cy="-30" rx="3.4" ry="5" fill="#e8b48c"/>
                <path class="av-hair" d="M-23-36c0-24 10-27 23-27s23 3 23 27c-2-8-6-13-11-15-7 4-16 4-24 1-5 3-9 7-11 14z" fill="#3f2d23"/>
                <path d="M-12-49c8 3 17 3 24-1-3-4-8-6-13-6-4 0-8 2-11 7z" fill="#4d382c"/>

                <!-- Eyes. The class is on an INNER group so the blink scales
                     about the eye's own centre; on the positioned group it
                     would collapse the eye toward the top-left of the SVG. -->
                <g transform="translate(-8.5,-32)"><g class="av-eye">
                    <ellipse cx="0" cy="0" rx="4.6" ry="5" fill="#fff"/>
                    <g class="av-pupil"><circle cx="0" cy=".4" r="2.6" fill="#243b53"/>
                        <circle cx="1" cy="-.8" r=".9" fill="#fff" opacity=".9"/></g>
                </g></g>
                <g transform="translate(8.5,-32)"><g class="av-eye">
                    <ellipse cx="0" cy="0" rx="4.6" ry="5" fill="#fff"/>
                    <g class="av-pupil"><circle cx="0" cy=".4" r="2.6" fill="#243b53"/>
                        <circle cx="1" cy="-.8" r=".9" fill="#fff" opacity=".9"/></g>
                </g></g>

                <g transform="translate(-8.5,-40)"><g class="av-brow">
                    <path d="M-5 0q5-3.4 10 0" stroke="#3f2d23" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                </g></g>
                <g transform="translate(8.5,-40)"><g class="av-brow">
                    <path d="M-5 0q5-3.4 10 0" stroke="#3f2d23" stroke-width="1.8" fill="none" stroke-linecap="round"/>
                </g></g>

                <!-- Two mouths, cross-faded rather than one path morphing:
                     SVG path interpolation in CSS needs identical node counts,
                     and a smile and an open mouth do not have them. -->
                <g transform="translate(0,-20)">
                    <path class="av-smile" d="M-7 0q7 6 14 0" stroke="#a4553a" stroke-width="2" fill="none" stroke-linecap="round"/>
                    <g class="av-mouth"><ellipse cx="0" cy="1.5" rx="4.6" ry="3.4" fill="#a4553a"/></g>
                </g>
                <ellipse cx="-14" cy="-24" rx="3.6" ry="2.4" fill="#ef8a8a" opacity=".35"/>
                <ellipse cx="14" cy="-24" rx="3.6" ry="2.4" fill="#ef8a8a" opacity=".35"/>
            </g>
        </g>

        <!-- The waving arm, pivoting at the shoulder. -->
        <g transform="translate(96,106)"><g class="av-arm">
            <rect x="-4.6" y="-34" width="9.2" height="36" rx="4.6" fill="#1e3a8a"/>
            <circle cx="0" cy="-38" r="7" fill="#f2c6a0"/>
            <path d="M-4-42.5v-4M0-43.5v-5M4-42.5v-4" stroke="#f2c6a0" stroke-width="2.6" stroke-linecap="round"/>
        </g></g>
    </g>
    </g>
</svg>
</template>

<script src="<?= asset('js/assistant.js') ?>"></script>
