(function () {
  'use strict';

  // ---------------------------------------------------------------
  // Fixed header offset — measure header height and push main
  // content down so it isn't hidden behind the fixed header.
  // The header is position:fixed (not sticky) because it lives
  // inside a .wp-block-template-part wrapper that is too short
  // for position:sticky to work.
  // ---------------------------------------------------------------
  function adjustHeaderOffset() {
    var header = document.querySelector('.site-header');
    var main = document.querySelector('.site-main');
    if (!header || !main) return;
    if (header.classList.contains('not-sticky')) {
      document.body.classList.remove('has-fixed-header');
      main.style.removeProperty('--rcmi-header-offset');
      return;
    }
    document.body.classList.add('has-fixed-header');
    main.style.setProperty('--rcmi-header-offset', header.offsetHeight + 'px');
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', adjustHeaderOffset);
  } else {
    adjustHeaderOffset();
  }
  window.addEventListener('load', adjustHeaderOffset);
  window.addEventListener('resize', adjustHeaderOffset);

  // Mobile nav toggle.
  var btn = document.querySelector('.nav-toggle');
  var panel = document.getElementById('mobile-nav');
  if (btn && panel) {
    btn.addEventListener('click', function () {
      var open = panel.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    panel.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        panel.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Impact strip tabs (show/hide panels).
  var strips = document.querySelectorAll('.rcmi-impact-strip-wrapper');
  if (strips.length) {
    // Plugin JS handles wrapper-based tabs with transitions.
    // Only bind fallback markup here.
  }
  var tabs = document.querySelectorAll('.impact-step');
  var panels = document.querySelectorAll('.tab-panel');
  if (tabs.length && panels.length && !document.querySelector('.rcmi-impact-strip-wrapper')) {
    var panelsContainer = panels[0] ? panels[0].parentElement : null;
    var isAnimating = false;
    function activate(tabId) {
      if (isAnimating) return;
      var currentPanel = null, targetPanel = null;
      panels.forEach(function (p) {
        if (p.classList.contains('is-active')) currentPanel = p;
        if (p.id === tabId) targetPanel = p;
      });
      if (!targetPanel || targetPanel === currentPanel) {
        tabs.forEach(function (t) {
          var isActive = t.getAttribute('data-tab') === tabId;
          t.classList.toggle('is-active', isActive);
          t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        return;
      }
      var transition = panelsContainer ? panelsContainer.getAttribute('data-transition') : 'none';
      tabs.forEach(function (t) {
        var isActive = t.getAttribute('data-tab') === tabId;
        t.classList.toggle('is-active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      if (!transition || transition === 'none') {
        panels.forEach(function (p) { p.classList.toggle('is-active', p.id === tabId); });
        return;
      }
      isAnimating = true;
      if (panelsContainer) {
        var panelHeight = currentPanel ? currentPanel.offsetHeight : 0;
        panelsContainer.classList.add('is-animating');
        if (panelHeight) panelsContainer.style.minHeight = panelHeight + 'px';
      }
      targetPanel.classList.add('tab-entering', 'is-active');
      void targetPanel.offsetHeight;
      requestAnimationFrame(function () { targetPanel.classList.add('tab-entered'); });
      if (currentPanel) currentPanel.classList.add('tab-leaving');
      setTimeout(function () {
        if (currentPanel) currentPanel.classList.remove('is-active', 'tab-leaving');
        targetPanel.classList.remove('tab-entering', 'tab-entered');
        if (panelsContainer) {
          panelsContainer.classList.remove('is-animating');
          panelsContainer.style.minHeight = '';
        }
        isAnimating = false;
      }, 420);
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('mousedown', function (e) { e.preventDefault(); });
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        if (document.activeElement === tab) { tab.blur(); }
        activate(tab.getAttribute('data-tab'));
      });
    });
  }
})();
