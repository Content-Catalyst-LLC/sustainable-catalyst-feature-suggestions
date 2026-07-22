(function () {
  'use strict';

  function initConsole(root) {
    if (!root || root.dataset.consoleReady === 'true') return;
    var screens = Array.prototype.slice.call(root.querySelectorAll('[data-console-screen]'));
    if (screens.length < 2) return;

    var previous = root.querySelector('[data-console-action="previous"]');
    var toggle = root.querySelector('[data-console-action="toggle"]');
    var next = root.querySelector('[data-console-action="next"]');
    var currentOutput = root.querySelector('[data-console-current]');
    var toggleLabel = toggle ? toggle.querySelector('[data-console-label]') : null;
    var toggleIcon = toggle ? toggle.querySelector('[data-console-icon]') : null;
    var interval = Math.max(3000, parseInt(root.dataset.interval || '7000', 10));
    var reducedQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var index = 0;
    var timer = null;
    var userPaused = !!(reducedQuery && reducedQuery.matches);
    var interactionPaused = false;

    function show(nextIndex) {
      index = (nextIndex + screens.length) % screens.length;
      screens.forEach(function (screen, screenIndex) {
        var active = screenIndex === index;
        screen.hidden = !active;
        screen.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
      if (currentOutput) currentOutput.textContent = String(index + 1);
    }

    function updateToggle() {
      if (!toggle) return;
      var paused = userPaused;
      toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
      toggle.setAttribute('aria-label', paused ? 'Play release screens' : 'Pause release screens');
      if (toggleLabel) toggleLabel.textContent = paused ? 'Play' : 'Pause';
      if (toggleIcon) toggleIcon.textContent = paused ? '▶' : '❚❚';
    }

    function stop() {
      if (timer) window.clearInterval(timer);
      timer = null;
    }

    function start() {
      stop();
      if (userPaused || interactionPaused || document.hidden) return;
      timer = window.setInterval(function () { show(index + 1); }, interval);
    }

    function temporaryPause(value) {
      interactionPaused = value;
      if (value) stop(); else start();
    }

    if (previous) previous.addEventListener('click', function () { show(index - 1); start(); });
    if (next) next.addEventListener('click', function () { show(index + 1); start(); });
    if (toggle) toggle.addEventListener('click', function () {
      userPaused = !userPaused;
      updateToggle();
      start();
    });

    root.addEventListener('mouseenter', function () { temporaryPause(true); });
    root.addEventListener('mouseleave', function () { temporaryPause(false); });
    root.addEventListener('focusin', function () { temporaryPause(true); });
    root.addEventListener('focusout', function (event) {
      if (!root.contains(event.relatedTarget)) temporaryPause(false);
    });
    document.addEventListener('visibilitychange', start);
    if (reducedQuery && reducedQuery.addEventListener) {
      reducedQuery.addEventListener('change', function (event) {
        userPaused = event.matches;
        updateToggle();
        start();
      });
    }

    root.dataset.consoleReady = 'true';
    root.classList.add('scfs-release-board--enhanced');
    show(0);
    updateToggle();
    start();
  }

  function boot() {
    document.querySelectorAll('[data-release-console="true"]').forEach(initConsole);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
}());
