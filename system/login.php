<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link rel="stylesheet" href="vendor/css/all.min.css">

<style>
  *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { height: 100%; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
    color: #1c1e21;
    background: #fff;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
  }
  .main-split { display: flex; flex: 1 1 auto; min-height: 0; }

  /* ═══════════════ LOADING SCREEN ═══════════════ */
  .loading-screen {
    --ls-bg: #dbeafe;
    --ls-grid-line: rgba(37,99,235,0.055);
    --ls-glow-color: rgba(37,99,235,0.08);
    --ls-corner: rgba(37,99,235,0.38);
    --ls-logo-bg: rgba(37,99,235,0.05);
    --ls-logo-border: rgba(37,99,235,0.12);
    --ls-logo-shadow: rgba(37,99,235,0.2);
    --ls-text-primary: #0f172a;
    --ls-text-secondary: rgba(30,58,138,0.65);
    --ls-text-muted: rgba(71,85,105,0.7);
    --ls-text-accent: #1d4ed8;
    --ls-text-footer: rgba(71,85,105,0.55);
    --ls-track-bg: rgba(0,0,0,0.08);
    --ls-accent-conn: rgba(37,99,235,0.65);
    --ls-pill-inactive: rgba(30,64,175,0.45);
    --ls-pill-loaded: #1d4ed8;
    --ls-title-shadow: 0 0 40px rgba(37,99,235,0.25), 0 0 80px rgba(37,99,235,0.1);
    --ls-ring-inner-border: rgba(37,99,235,0.2);
    --ls-ring-inner-shadow: 0 0 18px rgba(37,99,235,0.14), inset 0 0 18px rgba(37,99,235,0.05);
  }
  .loading-screen {
    position: fixed; inset: 0; z-index: 9999;
    background: var(--ls-bg);
    display: flex; align-items: center; justify-content: center;
    transition: opacity 1s ease, visibility 1s ease;
    overflow: hidden;
  }
  .loading-screen.fade-out { opacity: 0; visibility: hidden; }

  #lsParticles { position: absolute; inset: 0; z-index: 0; pointer-events: none; }

  .ls-grid {
    position: absolute; inset: 0; z-index: 1; pointer-events: none;
    background-image: linear-gradient(var(--ls-grid-line) 1px, transparent 1px), linear-gradient(90deg, var(--ls-grid-line) 1px, transparent 1px);
    background-size: 48px 48px;
  }
  .ls-glow {
    position: absolute; inset: 0; z-index: 1; pointer-events: none;
    background: radial-gradient(ellipse 680px 560px at 50% 48%, var(--ls-glow-color) 0%, transparent 72%);
  }
  .ls-scan {
    position: absolute; left: 0; right: 0; height: 2px; z-index: 3; pointer-events: none;
    background: linear-gradient(90deg, transparent 0%, rgba(37,99,235,0.35) 30%, rgba(147,197,253,0.55) 50%, rgba(37,99,235,0.35) 70%, transparent 100%);
    animation: lsScanDown 7s linear infinite;
  }
  @keyframes lsScanDown { 0% { top: -2px; } 100% { top: 100%; } }

  .ls-corner {
    position: absolute; z-index: 5; width: 34px; height: 34px;
    border-color: var(--ls-corner); border-style: solid;
    opacity: 0; animation: lsCornerIn 0.6s ease forwards;
  }
  .ls-tl { top: 20px; left: 20px; border-width: 2px 0 0 2px; animation-delay: 0.1s; }
  .ls-tr { top: 20px; right: 20px; border-width: 2px 2px 0 0; animation-delay: 0.2s; }
  .ls-bl { bottom: 20px; left: 20px; border-width: 0 0 2px 2px; animation-delay: 0.3s; }
  .ls-br { bottom: 20px; right: 20px; border-width: 0 2px 2px 0; animation-delay: 0.4s; }
  @keyframes lsCornerIn { to { opacity: 1; } }

  .ls-wrap { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 2rem; }

  .ls-ring-system { position: relative; width: 220px; height: 220px; margin-bottom: 2.2rem; }
  .ls-ring { position: absolute; border-radius: 50%; opacity: 0; }

  .ls-ring-outer {
    inset: 0;
    border: 1.5px solid rgba(37,99,235,0.2);
    border-top-color: rgba(37,99,235,0.9); border-right-color: rgba(37,99,235,0.5);
    animation: lsRotCW 4.5s linear infinite, lsFadeIn 0.5s ease 0.4s forwards;
  }
  .ls-ring-outer::after {
    content: ''; position: absolute; width: 10px; height: 10px; border-radius: 50%;
    background: #2563eb; box-shadow: 0 0 12px #2563eb, 0 0 24px rgba(37,99,235,0.6);
    top: -5px; left: calc(50% - 5px);
  }
  .ls-ring-mid {
    inset: 20px;
    border: 1px dashed rgba(37,99,235,0.28); border-top-color: rgba(147,197,253,0.8);
    animation: lsRotCCW 3.2s linear infinite, lsFadeIn 0.5s ease 0.7s forwards;
  }
  .ls-ring-mid::after {
    content: ''; position: absolute; width: 7px; height: 7px; border-radius: 50%;
    background: #93c5fd; box-shadow: 0 0 9px #93c5fd, 0 0 18px rgba(147,197,253,0.5);
    bottom: -3.5px; left: calc(50% - 3.5px);
  }
  .ls-ring-inner {
    inset: 40px;
    border: 1.5px solid var(--ls-ring-inner-border);
    box-shadow: var(--ls-ring-inner-shadow);
    animation: lsRingPulse 2.8s ease-in-out infinite, lsFadeIn 0.5s ease 1s forwards;
  }
  .ls-logo-circle {
    position: absolute; inset: 54px; border-radius: 50%;
    background: var(--ls-logo-bg); border: 1px solid var(--ls-logo-border);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 48px var(--ls-logo-shadow), inset 0 0 24px rgba(37,99,235,0.05);
    opacity: 0; animation: lsLogoIn 0.9s cubic-bezier(0.34,1.56,0.64,1) 1.3s forwards;
  }
  /* Neutral drop-shadow, not the blue one the city seal used — a blue glow
     behind a red-and-black mark reads as a colour cast. */
  .ls-logo-circle img { width: 82%; height: 82%; object-fit: contain; filter: drop-shadow(0 3px 10px rgba(15,23,42,0.28)); }

  @keyframes lsRotCW  { from { transform: rotate(0deg); }  to { transform: rotate(360deg); } }
  @keyframes lsRotCCW { from { transform: rotate(0deg); }  to { transform: rotate(-360deg); } }
  @keyframes lsFadeIn { to { opacity: 1; } }
  @keyframes lsLogoIn { from { opacity: 0; transform: scale(0.4); } to { opacity: 1; transform: scale(1); } }
  @keyframes lsRingPulse {
    0%,100% { box-shadow: 0 0 14px rgba(37,99,235,0.18), inset 0 0 14px rgba(37,99,235,0.07); }
    50%      { box-shadow: 0 0 34px rgba(37,99,235,0.44), inset 0 0 28px rgba(37,99,235,0.16); }
  }
  .ls-ring-system.complete .ls-ring-inner { animation: lsRingFlash 0.5s ease forwards; }
  @keyframes lsRingFlash {
    0%   { box-shadow: 0 0 14px rgba(37,99,235,0.18), inset 0 0 14px rgba(37,99,235,0.07); }
    40%  { box-shadow: 0 0 70px rgba(37,99,235,0.85), inset 0 0 50px rgba(37,99,235,0.4); border-color: rgba(37,99,235,0.9); }
    100% { box-shadow: 0 0 30px rgba(37,99,235,0.35), inset 0 0 20px rgba(37,99,235,0.12); }
  }

  .ls-city  { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 3.2px; color: var(--ls-text-secondary); margin-bottom: 9px; opacity: 0; animation: lsRise 0.7s ease 1.7s forwards; }
  .ls-title { font-size: 3rem; font-weight: 900; color: var(--ls-text-primary); letter-spacing: 6px; line-height: 1; margin-bottom: 8px; text-shadow: var(--ls-title-shadow); opacity: 0; animation: lsRise 0.7s ease 1.9s forwards; }
  .ls-sub   { font-size: 0.71rem; color: var(--ls-text-muted); letter-spacing: 0.6px; margin-bottom: 2.8rem; opacity: 0; animation: lsRise 0.7s ease 2.1s forwards; }
  @keyframes lsRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

  .ls-progress-wrap { width: 340px; opacity: 0; animation: lsRise 0.7s ease 2.3s forwards; }
  .ls-status-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 9px; }
  .ls-status-left { display: flex; align-items: center; gap: 8px; }
  .ls-status-dot { width: 7px; height: 7px; border-radius: 50%; background: #2563eb; box-shadow: 0 0 7px #2563eb; animation: lsDotPulse 1.1s ease-in-out infinite; }
  @keyframes lsDotPulse { 0%,100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.35; transform: scale(0.65); } }
  .ls-status-text { font-family: Consolas, 'Courier New', monospace; font-size: 0.68rem; color: var(--ls-text-accent); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: opacity 0.3s; }
  .ls-pct { font-family: Consolas, 'Courier New', monospace; font-size: 0.72rem; font-weight: 700; color: var(--ls-text-accent); flex-shrink: 0; margin-left: 12px; }
  .ls-track { width: 100%; height: 3px; background: var(--ls-track-bg); border-radius: 2px; overflow: hidden; }
  .ls-fill  { height: 100%; width: 0%; background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 60%, #60a5fa 100%); border-radius: 2px; box-shadow: 0 0 10px rgba(37,99,235,0.7); transition: width 0.12s ease; }

  .ls-pills { display: flex; gap: 7px; justify-content: center; flex-wrap: wrap; margin-top: 14px; opacity: 0; animation: lsRise 0.5s ease 2.7s forwards; }
  .ls-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 0.62rem; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: rgba(37,99,235,0.08); border: 1px solid rgba(37,99,235,0.18); color: var(--ls-pill-inactive); transition: all 0.4s ease; }
  .ls-pill-dot { width: 5px; height: 5px; border-radius: 50%; background: rgba(37,99,235,0.3); flex-shrink: 0; transition: all 0.4s ease; }
  .ls-pill.loaded { background: rgba(37,99,235,0.14); border-color: rgba(37,99,235,0.4); color: var(--ls-pill-loaded); }
  .ls-pill.loaded .ls-pill-dot { background: #2563eb; box-shadow: 0 0 6px #2563eb; }

  .ls-dots { display: flex; gap: 8px; justify-content: center; margin-top: 1.8rem; opacity: 0; animation: lsRise 0.5s ease 2.9s forwards; }
  .ls-dots span { width: 7px; height: 7px; border-radius: 50%; background: rgba(37,99,235,0.35); }
  .ls-dots span:nth-child(1) { animation: lsDotBounce 1.3s ease-in-out -0.26s infinite; }
  .ls-dots span:nth-child(2) { animation: lsDotBounce 1.3s ease-in-out -0.13s infinite; }
  .ls-dots span:nth-child(3) { animation: lsDotBounce 1.3s ease-in-out 0s     infinite; }
  @keyframes lsDotBounce {
    0%,80%,100% { transform: scale(0.55); background: rgba(37,99,235,0.25); }
    40%          { transform: scale(1.2); background: #2563eb; box-shadow: 0 0 9px rgba(37,99,235,0.6); }
  }

  .ls-footer {
    position: absolute; bottom: 22px; left: 50%; transform: translateX(-50%);
    display: flex; align-items: center; gap: 16px;
    font-family: Consolas, 'Courier New', monospace; font-size: 0.58rem;
    color: var(--ls-text-footer); z-index: 10; white-space: nowrap;
    opacity: 0; animation: lsFadeIn 0.5s ease 1s forwards;
  }
  .ls-footer .ls-sep { opacity: 0.4; }
  .ls-footer .ls-conn { color: var(--ls-accent-conn); }
  .ls-footer .ls-conn::before { content: '● '; }

  /* ═══════════════ LEFT BRAND PANEL ═══════════════
     Fully offline: no stock photography, nothing fetched — just a dark navy
     surface, the vendored logo, and a CSS-drawn grid/glow for texture. */
  .brand-panel {
    flex: 1 1 auto; min-width: 0; position: relative;
    background: linear-gradient(165deg, #0a1730 0%, #12274e 55%, #17305e 100%);
    overflow: hidden;
    display: flex; flex-direction: column; justify-content: center;
    padding: 56px 64px;
    color: #fff;
  }
  .bp-grid {
    position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: radial-gradient(ellipse 70% 70% at 30% 40%, #000 40%, transparent 90%);
  }
  .bp-glow {
    position: absolute; z-index: 0; pointer-events: none; border-radius: 50%;
    width: 520px; height: 520px; top: -160px; right: -160px;
    background: radial-gradient(circle, rgba(37,99,235,.35), transparent 70%);
  }
  .brand-text { position: relative; z-index: 1; display: flex; flex-direction: column; max-width: 460px; }
  .brand-head { display: flex; align-items: center; gap: 16px; }
  .brand-seal {
    width: 60px; height: 60px; border-radius: 14px; object-fit: contain; flex: 0 0 auto;
    background: #fff; padding: 6px; box-shadow: 0 8px 24px rgba(0,0,0,.25);
  }
  .brand-word { font-size: 1.7rem; font-weight: 800; letter-spacing: .03em; color: #fff; line-height: 1.15; }
  .brand-word small { display: block; font-size: .62rem; font-weight: 700; letter-spacing: .16em; color: #7fa8f5; text-transform: uppercase; margin-top: 2px; }
  .brand-tagline {
    margin-top: 40px;
    font-size: 2.1rem; font-weight: 800; line-height: 1.25; letter-spacing: -0.01em; color: #fff;
  }
  .brand-desc { margin-top: 14px; font-size: .95rem; color: rgba(255,255,255,.65); line-height: 1.65; max-width: 400px; }

  /* Capability strip — the mockup's photo icon row, drawn with FontAwesome
     glyphs already vendored locally instead of a stock image. */
  .brand-caps { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 46px; }
  .bc-item { display: flex; flex-direction: column; align-items: center; gap: 9px; width: 68px; text-align: center; }
  .bc-icon {
    width: 46px; height: 46px; border-radius: 12px;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; color: #93b8f7;
  }
  .bc-item span { font-size: .68rem; font-weight: 600; color: rgba(255,255,255,.6); line-height: 1.3; }

  .brand-stats { position: relative; z-index: 1; display: flex; gap: 34px; margin-top: 54px; }
  .brand-stats div { display: flex; flex-direction: column; }
  .brand-stats strong { font-size: 1.5rem; font-weight: 800; color: #fff; }
  .brand-stats span { font-size: .7rem; color: rgba(255,255,255,.55); margin-top: 2px; }

  /* ═══════════════ RIGHT FORM PANEL ═══════════════ */
  /* 720px = the original 480px plus half again, so the panel's left edge moves
     240px further left. The collage cannot survive that on every screen: beside
     a 720px panel the brand side needs 966px (56+40 padding + 380 brand text +
     30 gap + 460 collage min-width), i.e. a viewport of ~1690px. The collage has
     fixed pixel offsets inside a position:relative box, so it cannot shrink —
     it can only be clipped by .brand-panel's overflow:hidden. The breakpoint
     below therefore drops it at 1690px rather than showing it cut in half. */
  .form-panel {
    flex: 0 0 720px; background: #fff; border-left: 1px solid #ced0d4;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 40px;
  }
  /* Grown alongside the panel so the extra width goes to the form rather than
     leaving it marooned in white space. */
  .form-box { width: 100%; max-width: 480px; }

  .form-title { font-size: 1.7rem; font-weight: 700; margin-bottom: 8px; }
  .form-sub { font-size: 0.92rem; color: #606770; margin-bottom: 26px; }

  /* Alerts */
  .alert-box { display: none; border-radius: 8px; padding: 11px 14px; font-size: 0.88rem; margin-bottom: 14px; line-height: 1.45; }
  .alert-error   { background: #fdecea; color: #b42318; border: 1px solid #f5c6c0; }
  .alert-lockout { background: #fef3e2; color: #92400e; border: 1px solid #f6d9ae; }
  .alert-warning { background: #fef9e7; color: #92720e; border: 1px solid #f6ecae; }

  /* Fields */
  .field-wrap { position: relative; margin-bottom: 14px; }
  .field {
    width: 100%; height: 50px; border: 1px solid #dddfe2; border-radius: 8px; background: #fff;
    padding: 0 16px; font-size: 1rem; color: #1c1e21; outline: none; font-family: inherit;
    transition: border-color 0.12s ease, box-shadow 0.12s ease;
  }
  .field::placeholder { color: #90949c; }
  .field:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18); }
  .field:disabled { background: #f0f2f5; cursor: not-allowed; }
  #password { padding-right: 50px; }

  .eye-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    width: 34px; height: 34px; border: none; background: transparent; color: #90949c; cursor: pointer;
    border-radius: 50%; font-size: 0.92rem; display: flex; align-items: center; justify-content: center;
  }
  .eye-btn:hover { color: #1c1e21; background: #f0f2f5; }

  .form-bottom-row { display: flex; align-items: center; justify-content: space-between; margin: 2px 0 16px; }
  .remember-wrap { display: flex; align-items: center; gap: 8px; }
  .remember-wrap input { width: 16px; height: 16px; accent-color: #1d4ed8; cursor: pointer; }
  .remember-wrap label { font-size: 0.88rem; cursor: pointer; user-select: none; }
  .forgot-link { border: none; background: none; padding: 0; font-size: 0.88rem; color: #1d4ed8; cursor: pointer; font-family: inherit; }
  .forgot-link:hover { text-decoration: underline; }

  .btn-login {
    width: 100%; height: 50px; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff; font-size: 1.02rem; font-weight: 700; font-family: inherit; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: filter 0.12s ease;
  }
  .btn-login:hover:not(:disabled) { filter: brightness(1.07); }
  .btn-login:disabled { opacity: 0.65; cursor: not-allowed; }

  .btn-spinner {
    display: inline-block; width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%;
    vertical-align: -3px; animation: spin 0.7s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .divider { display: flex; align-items: center; gap: 14px; color: #96999e; font-size: 0.85rem; margin: 20px 0; }
  .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: #dadde1; }

  .create-account-box { display: flex; align-items: center; gap: 14px; background: #f4f7fd; border: 1px solid #e3eaf6; border-radius: 12px; padding: 16px 18px; }
  .ca-left { display: flex; align-items: center; gap: 12px; flex: 1; }
  .ca-icon {
    width: 38px; height: 38px; border-radius: 10px; flex: 0 0 38px;
    background: rgba(37, 99, 235, 0.1); color: #1d4ed8;
    display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
  }
  .ca-text { display: flex; flex-direction: column; }
  .ca-text strong { font-size: 0.9rem; margin-bottom: 2px; }
  .ca-text span { font-size: 0.76rem; color: #606770; line-height: 1.35; }
  .ca-btn {
    border: none; border-radius: 9px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff; font-size: 0.82rem; font-weight: 700; font-family: inherit;
    padding: 10px 16px; cursor: pointer; white-space: nowrap;
    transition: filter 0.12s ease;
  }
  .ca-btn:hover { filter: brightness(1.07); }

  .footer-note { margin-top: 34px; text-align: center; font-size: 0.8rem; color: #90949c; line-height: 1.6; }

  /* ═══════════════ PAGE FOOTER ═══════════════ */
  .page-footer {
    border-top: 1px solid #dadde1; background: #fff; padding: 14px 24px; text-align: center;
    font-size: 0.83rem; color: #606770; line-height: 1.7;
  }
  .page-footer .pf-brand { font-weight: 700; color: #1e3a8a; }
  .page-footer .pf-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 22px; margin-top: 2px; }
  .page-footer .pf-links span { white-space: nowrap; }

  /* ═══════════════ RESPONSIVE ═══════════════ */
  @media (max-width: 900px) {
    .main-split { flex-direction: column; }
    .brand-panel { flex: 0 0 auto; padding: 40px 28px; text-align: center; }
    .brand-text { max-width: none; margin: 0 auto; }
    .brand-head { flex-direction: column; align-items: center; gap: 10px; }
    .brand-tagline { font-size: 1.6rem; }
    .brand-caps { justify-content: center; }
    .brand-stats { justify-content: center; }
    .form-panel { flex: 1 1 auto; border-left: none; padding: 36px 24px 44px; }
  }
</style>
</head>
<body>

<!-- ═══════════════ LOADING SCREEN ═══════════════ -->
<div class="loading-screen" id="loadingScreen">
  <canvas id="lsParticles"></canvas>
  <div class="ls-grid"></div>
  <div class="ls-glow"></div>
  <div class="ls-scan"></div>
  <div class="ls-corner ls-tl"></div>
  <div class="ls-corner ls-tr"></div>
  <div class="ls-corner ls-bl"></div>
  <div class="ls-corner ls-br"></div>
  <div class="ls-wrap">
    <div class="ls-ring-system" id="lsRingSystem">
      <div class="ls-ring ls-ring-outer"></div>
      <div class="ls-ring ls-ring-mid"></div>
      <div class="ls-ring ls-ring-inner"></div>
      <div class="ls-logo-circle">
        <img src="image/execom_icon.png" alt="EXECOM" style="width:70%;height:70%;object-fit:contain;">
      </div>
    </div>
    <div class="ls-city">EXECOM Computer Sales &amp; Services</div>
    <div class="ls-title"><?= e(APP_NAME) ?></div>
    <div class="ls-sub">Private Company Logistics System</div>
    <div class="ls-progress-wrap">
      <div class="ls-status-row">
        <div class="ls-status-left">
          <div class="ls-status-dot"></div>
          <div class="ls-status-text" id="lsStatusText">Initializing system...</div>
        </div>
        <div class="ls-pct" id="lsPct">0%</div>
      </div>
      <div class="ls-track"><div class="ls-fill" id="lsFill"></div></div>
    </div>
    <div class="ls-pills">
      <div class="ls-pill" id="lsPill-po"><div class="ls-pill-dot"></div> Purchase Orders</div>
      <div class="ls-pill" id="lsPill-sup"><div class="ls-pill-dot"></div> Suppliers</div>
      <div class="ls-pill" id="lsPill-rep"><div class="ls-pill-dot"></div> Reports</div>
      <div class="ls-pill" id="lsPill-not"><div class="ls-pill-dot"></div> Notifications</div>
    </div>
    <div class="ls-dots"><span></span><span></span><span></span></div>
  </div>
  <div class="ls-footer">
    <span><?= e(APP_NAME) ?></span>
    <span class="ls-sep">|</span>
    <span class="ls-conn">SECURE CONNECTION</span>
    <span class="ls-sep">|</span>
    <span>PURCHASE ORDERS &nbsp;&bull;&nbsp; SUPPLIERS &nbsp;&bull;&nbsp; REPORTS</span>
  </div>
</div>

<div class="main-split">

<!-- ═══════════════ LEFT: BRAND ═══════════════ -->
<div class="brand-panel">
  <div class="bp-grid" aria-hidden="true"></div>
  <div class="bp-glow" aria-hidden="true"></div>

  <div class="brand-text">
    <div class="brand-head">
      <img src="image/execom_icon.png" alt="" class="brand-seal">
      <div class="brand-word"><?= e(APP_NAME) ?><small>Logistics &amp; Procurement System</small></div>
    </div>

    <div class="brand-tagline">Smarter inventory.<br>Stronger operations.</div>
    <p class="brand-desc">Powering your business, branch by branch — purchase orders, suppliers, inventory, and reports, all from one place.</p>

    <div class="brand-caps">
      <div class="bc-item"><div class="bc-icon"><i class="fas fa-file-invoice-dollar"></i></div><span>Procurement</span></div>
      <div class="bc-item"><div class="bc-icon"><i class="fas fa-boxes-stacked"></i></div><span>Inventory</span></div>
      <div class="bc-item"><div class="bc-icon"><i class="fas fa-warehouse"></i></div><span>Warehouse</span></div>
      <div class="bc-item"><div class="bc-icon"><i class="fas fa-truck"></i></div><span>Distribution</span></div>
      <div class="bc-item"><div class="bc-icon"><i class="fas fa-chart-line"></i></div><span>Reporting</span></div>
    </div>

    <div class="brand-stats">
      <div><strong>5</strong><span>Branches</span></div>
      <div><strong>10+</strong><span>Modules</span></div>
      <div><strong>1</strong><span>System</span></div>
    </div>
  </div>
</div>

<!-- ═══════════════ RIGHT: FORM ═══════════════ -->
<div class="form-panel">
  <div class="form-box">

    <h1 class="form-title">Log into <?= e(APP_NAME) ?></h1>
    <p class="form-sub">Sign in to continue to your account.</p>

    <!-- Alert boxes -->
    <div class="alert-box alert-warning" id="expiredNotice" style="<?= $expired ? 'display:block;' : '' ?>">
      Your session expired. Please log in again.
    </div>
    <div class="alert-box alert-error" id="alertError"></div>
    <div class="alert-box alert-lockout" id="alertLockout">
      <i class="fas fa-lock"></i>&nbsp; Too many failed attempts. Try again in <span id="lockoutTimer"></span>.
    </div>

    <!-- Login form -->
    <form id="loginForm" autocomplete="on" novalidate>
      <div class="field-wrap">
        <input class="field" type="text" id="username" name="username" placeholder="Username" autocomplete="username" required>
      </div>

      <div class="field-wrap">
        <input class="field" type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <button type="button" class="eye-btn" id="eyeToggle" title="Show/hide password" aria-label="Toggle password visibility">
          <i class="fas fa-eye"></i>
        </button>
      </div>

      <div class="form-bottom-row">
        <div class="remember-wrap">
          <input type="checkbox" id="rememberMe" name="rememberMe">
          <label for="rememberMe">Remember me</label>
        </div>
        <button type="button" class="forgot-link" onclick="forgotPassword()">Forgot password?</button>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <span id="btnText"><i class="fas fa-right-to-bracket"></i>&nbsp; Sign In</span>
        <span id="btnLoading" style="display:none;"><span class="btn-spinner"></span>&nbsp; Signing in&hellip;</span>
      </button>
    </form>

    <div class="divider">or</div>

    <div class="create-account-box">
      <div class="ca-left">
        <div class="ca-icon"><i class="fas fa-user-plus"></i></div>
        <div class="ca-text">
          <strong>New to <?= e(APP_NAME) ?>?</strong>
          <span>Contact your administrator to request an account</span>
        </div>
      </div>
      <button class="ca-btn" onclick="requestAccount()">Request Account</button>
    </div>

    <div class="footer-note">
      &copy; <?= date('Y') ?> EXECOM Computer Sales &amp; Services<br>
      All rights reserved.
    </div>

  </div>
</div>

</div><!-- /.main-split -->

<!-- ═══════════════ PAGE FOOTER ═══════════════ -->
<div class="page-footer">
  <div><span class="pf-brand"><?= e(APP_NAME) ?></span> &mdash; Private Company Logistics System of EXECOM Computer Sales &amp; Services</div>
  <div class="pf-links">
    <span>Purchase Orders</span>
    <span>Suppliers &amp; Master Data</span>
    <span>Reports</span>
    <span>Audit Trail</span>
  </div>
</div>

<script>

/* ═══════════════════════════════════════
   RATE LIMITER  (client-side, 5 attempts / 15 min)
═══════════════════════════════════════ */
const RateLimit = {
  key: 'pos_login_rl',
  MAX: 5,
  WINDOW: 5 * 60 * 1000,
  _load() { try { return JSON.parse(localStorage.getItem(this.key)) || {count:0,until:0}; } catch { return {count:0,until:0}; } },
  _save(d) { localStorage.setItem(this.key, JSON.stringify(d)); },
  isLocked() { const d = this._load(); return d.count >= this.MAX && Date.now() < d.until; },
  remaining() { const d = this._load(); return Math.max(0, Math.ceil((d.until - Date.now()) / 1000)); },
  record() {
    const d = this._load();
    d.count = (d.count || 0) + 1;
    if (d.count >= this.MAX) d.until = Date.now() + this.WINDOW;
    this._save(d);
  },
  clear() { localStorage.removeItem(this.key); },
  attemptsLeft() { const d = this._load(); return Math.max(0, this.MAX - (d.count || 0)); }
};

/* ═══════════════════════════════════════
   REMEMBER ME
═══════════════════════════════════════ */
const RememberMe = {
  key: 'pos_rmb',
  TTL: 30 * 24 * 60 * 60 * 1000, // 30 days
  save(username) { localStorage.setItem(this.key, JSON.stringify({ username, exp: Date.now() + this.TTL })); },
  clear() { localStorage.removeItem(this.key); },
  load() {
    try {
      const d = JSON.parse(localStorage.getItem(this.key));
      if (d && d.exp > Date.now()) return d.username;
    } catch {}
    this.clear();
    return null;
  }
};

/* ═══════════════════════════════════════
   LOADING SCREEN
═══════════════════════════════════════ */
function initLoadingScreen() {
  const screen = document.getElementById('loadingScreen');

  const cvs = document.getElementById('lsParticles');
  const ctx = cvs.getContext('2d');
  let W, H, pts = [];

  function resizeCvs() { W = cvs.width = innerWidth; H = cvs.height = innerHeight; }
  window.addEventListener('resize', resizeCvs);
  resizeCvs();

  function mkPt() {
    return { x: Math.random() * W, y: Math.random() * H,
             vx: (Math.random() - 0.5) * 0.35, vy: -(Math.random() * 0.7 + 0.25),
             r: Math.random() * 1.4 + 0.4, a: Math.random() * 0.45 + 0.08,
             blue: Math.random() > 0.45 };
  }
  for (let i = 0; i < 90; i++) pts.push(mkPt());

  let particleRaf;
  function animPts() {
    ctx.clearRect(0, 0, W, H);
    pts.forEach(p => {
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = p.blue ? `rgba(29,78,216,${p.a * 0.5})` : `rgba(37,99,235,${p.a * 0.38})`;
      ctx.fill();
      p.x += p.vx; p.y += p.vy;
      if (p.y < -8) Object.assign(p, mkPt(), { y: H + 8 });
    });
    particleRaf = requestAnimationFrame(animPts);
  }
  animPts();

  const statuses = [
    'Initializing system...',
    'Loading purchase order module...',
    'Connecting to database...',
    'Verifying session...',
    'Loading access permissions...',
    'Finalizing setup...',
    'System ready!'
  ];
  const statusThresholds = [0, 14, 28, 46, 65, 83, 96];
  const pillTrigger = { 'lsPill-po': 28, 'lsPill-sup': 50, 'lsPill-rep': 72, 'lsPill-not': 90 };

  let pct = 0, statIdx = 0;

  setTimeout(() => {
    const iv = setInterval(() => {
      const step = Math.random() * 0.5 + 0.8;
      pct = Math.min(100, pct + step);
      const iPct = Math.floor(pct);

      document.getElementById('lsFill').style.width = pct + '%';
      document.getElementById('lsPct').textContent = iPct + '%';

      const newIdx = statusThresholds.filter(t => pct >= t).length - 1;
      if (newIdx !== statIdx && newIdx < statuses.length) {
        statIdx = newIdx;
        const el = document.getElementById('lsStatusText');
        el.style.opacity = '0';
        setTimeout(() => { el.textContent = statuses[statIdx]; el.style.opacity = '1'; }, 180);
      }

      Object.entries(pillTrigger).forEach(([id, threshold]) => {
        if (pct >= threshold) document.getElementById(id)?.classList.add('loaded');
      });

      if (pct >= 100) {
        clearInterval(iv);
        document.getElementById('lsRingSystem')?.classList.add('complete');
        setTimeout(() => {
          cancelAnimationFrame(particleRaf);
          screen?.classList.add('fade-out');
          setTimeout(() => { if (screen) screen.style.display = 'none'; }, 800);
        }, 600);
      }
    }, 50);
  }, 500);
}

/* ═══════════════════════════════════════
   LOCKOUT COUNTDOWN
═══════════════════════════════════════ */
let _lockoutIv = null;
function startLockoutCountdown() {
  const box   = document.getElementById('alertLockout');
  const timer = document.getElementById('lockoutTimer');
  if (!box || !timer) return;

  const tick = () => {
    const secs = RateLimit.remaining();
    if (secs <= 0) { clearInterval(_lockoutIv); hideLockout(); return; }
    const m = Math.floor(secs / 60), s = secs % 60;
    timer.textContent = `${m}:${s.toString().padStart(2,'0')}`;
  };
  tick();
  _lockoutIv = setInterval(tick, 1000);
  box.style.display = 'block';
  setFormDisabled(true);
}

function hideLockout() {
  const box = document.getElementById('alertLockout');
  if (box) box.style.display = 'none';
  setFormDisabled(false);
}

function setFormDisabled(disabled) {
  ['username','password','rememberMe'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.disabled = disabled;
  });
  const btn = document.getElementById('loginBtn');
  if (btn) btn.disabled = disabled;
}

/* ═══════════════════════════════════════
   LOGIN HANDLER  (session-based, posts to api/auth.php)
═══════════════════════════════════════ */
async function handleLogin(e) {
  e.preventDefault();

  if (RateLimit.isLocked()) { startLockoutCountdown(); return; }

  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  const remember = document.getElementById('rememberMe').checked;
  const errBox   = document.getElementById('alertError');

  errBox.style.display = 'none';

  if (!username || !password) {
    showError('Please enter your username and password.');
    return;
  }

  document.getElementById('btnText').style.display    = 'none';
  document.getElementById('btnLoading').style.display = 'inline-flex';
  document.getElementById('loginBtn').disabled = true;

  try {
    const res = await fetch('api/auth.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ username, password }),
    });
    const data = await res.json();

    if (data.success) {
      RateLimit.clear();
      if (remember) { RememberMe.save(username); } else { RememberMe.clear(); }
      window.location.href = data.redirect || 'dashboard.php';
    } else {
      RateLimit.record();
      const left = RateLimit.attemptsLeft();
      if (RateLimit.isLocked()) {
        startLockoutCountdown();
      } else {
        const warning = left <= 2 ? ` (${left} attempt${left === 1 ? '' : 's'} left before lockout)` : '';
        showError((data.message || 'Invalid username or password.') + warning);
      }
      document.getElementById('btnText').style.display    = 'inline';
      document.getElementById('btnLoading').style.display = 'none';
      document.getElementById('loginBtn').disabled = false;
    }
  } catch (err) {
    showError('Network error. Please try again.');
    document.getElementById('btnText').style.display    = 'inline';
    document.getElementById('btnLoading').style.display = 'none';
    document.getElementById('loginBtn').disabled = false;
    console.error(err);
  }
}

function showError(msg) {
  const b = document.getElementById('alertError');
  b.innerHTML = '<i class="fas fa-circle-exclamation"></i>&nbsp; ' + escapeHtml(msg);
  b.style.display = 'block';
}

function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function forgotPassword() {
  alert('Please contact your system administrator to reset your password.');
}

function requestAccount() {
  alert('Please contact your system administrator to request an account.');
}

/* ═══════════════════════════════════════
   PASSWORD TOGGLE
═══════════════════════════════════════ */
function initPasswordToggle() {
  const btn = document.getElementById('eyeToggle');
  const inp = document.getElementById('password');
  if (!btn || !inp) return;
  btn.addEventListener('click', () => {
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
  });
}

/* ═══════════════════════════════════════
   INIT
═══════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initLoadingScreen();
  initPasswordToggle();

  const remembered = RememberMe.load();
  if (remembered) {
    document.getElementById('username').value = remembered;
    document.getElementById('rememberMe').checked = true;
    document.getElementById('password').focus();
  } else {
    document.getElementById('username').focus();
  }

  if (RateLimit.isLocked()) startLockoutCountdown();

  document.getElementById('loginForm').addEventListener('submit', handleLogin);
});
</script>
</body>
</html>
