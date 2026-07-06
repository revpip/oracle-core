(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var root = document.querySelector('[data-oracle-assessment]');
    if (!root) return;

    var mouseInput = root.querySelector('[data-oracle-mouse-events]');
    var count = 0;
    var bump = function () {
      count += 1;
      if (mouseInput) mouseInput.value = String(count);
    };

    ['mousemove', 'touchstart', 'keydown', 'scroll'].forEach(function (eventName) {
      window.addEventListener(eventName, bump, { passive: true });
    });

    root.querySelectorAll('.oracle-question input[type="radio"]').forEach(function (input) {
      input.addEventListener('change', function () {
        var fieldset = input.closest('.oracle-question');
        if (fieldset) fieldset.classList.add('oracle-question-complete');
      });
    });
  });
})();
