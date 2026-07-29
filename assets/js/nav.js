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
  var tabs = document.querySelectorAll('.impact-step');
  var panels = document.querySelectorAll('.tab-panel');
  if (tabs.length && panels.length) {
    function activate(tabId) {
      tabs.forEach(function (t) {
        var isActive = t.getAttribute('data-tab') === tabId;
        t.classList.toggle('is-active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      panels.forEach(function (p) {
        p.classList.toggle('is-active', p.id === tabId);
      });
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(tab.getAttribute('data-tab'));
      });
    });
  }
})();
