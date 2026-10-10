<?php
declare(strict_types=1);

/**
 * Site-wide animations.
 * - Fade-up on scroll happens ONCE per element, then it stays visible.
 * - Hero panel has NO entrance delay, rotator first image is instant.
 * - Respects prefers-reduced-motion.
 */
function render_animations(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;

    return <<<'HTML'
<style>
/* ---------- Fade-up on scroll (one-shot) ---------- */
[data-anim] {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity .7s cubic-bezier(.2,.7,.2,1), transform .7s cubic-bezier(.2,.7,.2,1);
  will-change: opacity, transform;
}
[data-anim].is-visible {
  opacity: 1;
  transform: none;
  will-change: auto;
}

/* ---------- Hero text stagger (fast, runs once) ---------- */
[data-hero] > * {
  opacity: 0;
  transform: translateY(18px);
  animation: heroIn .6s cubic-bezier(.2,.7,.2,1) forwards;
}
[data-hero] > *:nth-child(1) { animation-delay: .05s; }
[data-hero] > *:nth-child(2) { animation-delay: .15s; }
[data-hero] > *:nth-child(3) { animation-delay: .25s; }
[data-hero] > *:nth-child(4) { animation-delay: .35s; }
[data-hero] > *:nth-child(5) { animation-delay: .45s; }
[data-hero] > *:nth-child(6) { animation-delay: .55s; }

@keyframes heroIn {
  to { opacity: 1; transform: none; }
}

/* ---------- Staggered children (one-shot) ---------- */
[data-stagger] > * {
  opacity: 0;
  transform: translateY(20px);
  transition: opacity .55s cubic-bezier(.2,.7,.2,1), transform .55s cubic-bezier(.2,.7,.2,1);
  will-change: opacity, transform;
}
[data-stagger].is-visible > * {
  opacity: 1;
  transform: none;
  will-change: auto;
}
[data-stagger].is-visible > *:nth-child(1) { transition-delay: .05s; }
[data-stagger].is-visible > *:nth-child(2) { transition-delay: .12s; }
[data-stagger].is-visible > *:nth-child(3) { transition-delay: .19s; }
[data-stagger].is-visible > *:nth-child(4) { transition-delay: .26s; }
[data-stagger].is-visible > *:nth-child(5) { transition-delay: .33s; }
[data-stagger].is-visible > *:nth-child(6) { transition-delay: .40s; }

/* ---------- Card hover lift ---------- */
.hover-lift { transition: transform .35s ease, box-shadow .35s ease; }
.hover-lift:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -18px rgba(15,23,42,.35); }

/* ---------- Hero gradient shift ---------- */
.anim-gradient {
  background-size: 200% 200%;
  animation: gradientShift 14s ease-in-out infinite;
}
@keyframes gradientShift {
  0%,100% { background-position: 0% 50%; }
  50%     { background-position: 100% 50%; }
}

/* ---------- Floating hero panel ---------- */
.anim-float { animation: floaty 6s ease-in-out infinite; }
@keyframes floaty {
  0%,100% { transform: translateY(0); }
  50%     { transform: translateY(-8px); }
}

/* ---------- Rotating image panel ---------- */
.rotator { position: relative; overflow: hidden; }
.rotator .rot-slide {
  position: absolute; inset: 0;
  opacity: 0;
  transform: scale(1.04);
  transition: opacity .5s ease, transform .7s ease;
  background-size: cover;
  background-position: center;
}
.rotator .rot-slide.is-active { opacity: 1; transform: scale(1); }

/* First slide appears instantly — no fade on page load */
.rotator:not(.rotator-started) .rot-slide.is-active {
  transition: none;
}

.rot-dots { display: flex; gap: 6px; justify-content: center; }
.rot-dots button {
  width: 8px; height: 8px; border-radius: 999px;
  background: rgba(255,255,255,.45); border: 0; cursor: pointer;
  transition: background .3s ease, width .3s ease;
}
.rot-dots button.is-active { background: #fff; width: 22px; }

/* ---------- WhatsApp pulse ring ---------- */
.pulse-ring { position: relative; }
.pulse-ring::before {
  content: "";
  position: absolute; inset: 0;
  border-radius: 999px;
  background: currentColor;
  opacity: .35;
  animation: pulseRing 2.2s cubic-bezier(.2,.7,.3,1) infinite;
  pointer-events: none;
  z-index: -1;
}
@keyframes pulseRing {
  0%   { transform: scale(1);   opacity: .35; }
  70%  { transform: scale(1.5); opacity: 0; }
  100% { transform: scale(1.5); opacity: 0; }
}

/* ---------- Button shine sweep ---------- */
.btn-shine { position: relative; overflow: hidden; }
.btn-shine::after {
  content: "";
  position: absolute;
  top: -50%; left: -75%;
  width: 50%; height: 200%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,.35), transparent);
  transform: skewX(-20deg);
  transition: left .6s ease;
}
.btn-shine:hover::after { left: 125%; }

/* ---------- Accessibility ---------- */
@media (prefers-reduced-motion: reduce) {
  [data-anim], [data-stagger] > * {
    opacity: 1 !important;
    transform: none !important;
    transition: none !important;
  }
  [data-hero] > * {
    opacity: 1 !important;
    transform: none !important;
    animation: none !important;
  }
  .anim-gradient, .anim-float, .pulse-ring::before { animation: none !important; }
  .rotator .rot-slide { transition: none !important; }
}
</style>
<script>
(function(){
  'use strict';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- One-shot reveal --- */
  function revealAll() {
    var targets = document.querySelectorAll('[data-anim],[data-stagger]');
    if (!targets.length) return;

    if (reduced || !('IntersectionObserver' in window)) {
      targets.forEach(function(el){ el.classList.add('is-visible'); });
      return;
    }

    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          var el = e.target;
          if (el.dataset.animDone === '1') return;
          el.dataset.animDone = '1';
          el.classList.add('is-visible');
          io.unobserve(el);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    targets.forEach(function(el){ io.observe(el); });
  }

  /* --- Rotator --- */
  function initRotators() {
    document.querySelectorAll('[data-rotator]').forEach(function(root){
      var slides = root.querySelectorAll('.rot-slide');
      var dots = root.querySelectorAll('.rot-dots button');
      if (slides.length < 2) return;
      var i = 0;
      var delay = parseInt(root.getAttribute('data-rotator-interval') || '4500', 10);

      function show(n) {
        slides.forEach(function(s, k){ s.classList.toggle('is-active', k === n); });
        dots.forEach(function(d, k){ d.classList.toggle('is-active', k === n); });
        i = n;
      }
      function next() { show((i + 1) % slides.length); }

      var timer = null;
      function start() { if (reduced) return; if (timer) return; timer = setInterval(next, delay); }
      function stop()  { if (timer) { clearInterval(timer); timer = null; } }

      dots.forEach(function(d, k){
        d.addEventListener('click', function(){ stop(); show(k); start(); });
      });

      root.addEventListener('mouseenter', stop);
      root.addEventListener('mouseleave', start);
      document.addEventListener('visibilitychange', function(){
        if (document.hidden) stop(); else start();
      });

      show(0);
      // Let the first slide render instantly, then enable transition for later swaps
      setTimeout(function(){ root.classList.add('rotator-started'); }, 100);
      start();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ revealAll(); initRotators(); });
  } else {
    revealAll(); initRotators();
  }
})();
</script>
HTML;
}