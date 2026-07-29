(function () {
  'use strict';

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
      if (panelsContainer) panelsContainer.classList.add('is-animating');
      targetPanel.classList.add('tab-entering', 'is-active');
      void targetPanel.offsetHeight;
      requestAnimationFrame(function () { targetPanel.classList.add('tab-entered'); });
      if (currentPanel) currentPanel.classList.add('tab-leaving');
      setTimeout(function () {
        if (currentPanel) currentPanel.classList.remove('is-active', 'tab-leaving');
        targetPanel.classList.remove('tab-entering', 'tab-entered');
        if (panelsContainer) panelsContainer.classList.remove('is-animating');
        isAnimating = false;
      }, 420);
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(tab.getAttribute('data-tab'));
      });
    });
  }
})();
