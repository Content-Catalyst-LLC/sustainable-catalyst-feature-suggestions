(() => {
  'use strict';

  const initialize = () => {
    document.querySelectorAll('.scfs-editorial-meta').forEach((root) => {
      const transition = root.querySelector('[name="scfs_editorial_transition"]');
      const schedule = root.querySelector('[name="scfs_editorial_scheduled_at"]');
      if (!transition || !schedule) return;

      const updateScheduleRequirement = () => {
        const required = transition.value === 'scheduled';
        schedule.required = required;
        schedule.setAttribute('aria-required', required ? 'true' : 'false');
      };
      transition.addEventListener('change', updateScheduleRequirement);
      updateScheduleRequirement();
    });

    document.querySelectorAll('[data-scfs-editorial-confirm]').forEach((control) => {
      control.addEventListener('click', (event) => {
        if (!window.confirm(control.getAttribute('data-scfs-editorial-confirm') || 'Continue?')) {
          event.preventDefault();
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
