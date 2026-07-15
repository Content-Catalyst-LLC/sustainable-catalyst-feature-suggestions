(function () {
  'use strict';

  function initializeForm(form) {
    if (!form || form.dataset.scfsFormReady === '1') {
      return;
    }
    form.dataset.scfsFormReady = '1';
    var fields = [].slice.call(form.querySelectorAll('.scfs-form-field'));
    var pages = [].slice.call(new Set(fields.map(function (field) {
      return parseInt(field.dataset.page || '1', 10);
    }))).sort(function (a, b) { return a - b; });
    var current = 0;

    function values(key) {
      var elements = form.querySelectorAll('[name="scfs_response[' + key + ']"],[name="scfs_response[' + key + '][]"]');
      return [].slice.call(elements).filter(function (element) {
        return ['checkbox', 'radio'].indexOf(element.type) === -1 || element.checked;
      }).map(function (element) { return element.value; });
    }

    function conditions() {
      fields.forEach(function (field) {
        var key = field.dataset.conditionField;
        if (!key) {
          field.hidden = false;
          return;
        }
        var vals = values(key);
        var operator = field.dataset.conditionOperator;
        var expected = field.dataset.conditionValue || '';
        var show = operator === 'answered' ? vals.length > 0
          : operator === 'not_equals' ? vals.indexOf(expected) === -1
            : operator === 'contains' ? vals.some(function (value) { return value.indexOf(expected) !== -1; })
              : vals.indexOf(expected) !== -1;
        field.hidden = !show;
        field.querySelectorAll('input,select,textarea').forEach(function (element) {
          element.disabled = !show;
        });
      });
    }

    function showPage() {
      var multi = form.dataset.multiPage === '1';
      fields.forEach(function (field) {
        if (multi) {
          field.style.display = parseInt(field.dataset.page || '1', 10) === pages[current] && !field.hidden ? '' : 'none';
        } else {
          field.style.display = field.hidden ? 'none' : '';
        }
      });
      var progress = form.querySelector('.scfs-progress');
      if (progress) {
        progress.textContent = multi ? 'Step ' + (current + 1) + ' of ' + pages.length : '';
      }
      var previous = form.querySelector('.scfs-prev');
      var next = form.querySelector('.scfs-next');
      var submit = form.querySelector('.scfs-form-submit');
      if (previous) previous.style.display = multi && current > 0 ? 'inline-block' : 'none';
      if (next) next.style.display = multi && current < pages.length - 1 ? 'inline-block' : 'none';
      if (submit) submit.style.display = !multi || current === pages.length - 1 ? 'inline-block' : 'none';
    }

    if (form.dataset.randomize === '1') {
      pages.forEach(function (page) {
        var same = fields.filter(function (field) { return parseInt(field.dataset.page || '1', 10) === page; });
        var parent = same[0] && same[0].parentNode;
        if (!parent) return;
        same.sort(function () { return Math.random() - 0.5; }).forEach(function (field) {
          parent.insertBefore(field, parent.querySelector('.scfs-page-actions'));
        });
      });
    }

    var key = 'scfs-survey-' + form.dataset.formId;
    function save() {
      if (form.dataset.saveResume !== '1') return;
      var data = {};
      new FormData(form).forEach(function (value, name) {
        if (name.indexOf('scfs_response[') === 0) {
          data[name] = data[name] ? [].concat(data[name], value) : value;
        }
      });
      localStorage.setItem(key, JSON.stringify(data));
    }

    form.addEventListener('change', function () { conditions(); showPage(); save(); });
    form.addEventListener('input', save);

    var next = form.querySelector('.scfs-next');
    if (next) {
      next.addEventListener('click', function () {
        conditions();
        var visible = fields.filter(function (field) { return field.style.display !== 'none'; });
        var invalid = visible.map(function (field) { return field.querySelector(':invalid'); }).find(Boolean);
        if (invalid) {
          invalid.reportValidity();
          return;
        }
        current = Math.min(current + 1, pages.length - 1);
        showPage();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    var previous = form.querySelector('.scfs-prev');
    if (previous) {
      previous.addEventListener('click', function () {
        current = Math.max(0, current - 1);
        showPage();
      });
    }

    if (form.dataset.saveResume === '1') {
      try {
        var stored = JSON.parse(localStorage.getItem(key) || '{}');
        Object.keys(stored).forEach(function (name) {
          var vals = [].concat(stored[name]);
          form.querySelectorAll('[name="' + CSS.escape(name) + '"]').forEach(function (element) {
            if (['checkbox', 'radio'].indexOf(element.type) !== -1) {
              element.checked = vals.indexOf(element.value) !== -1;
            } else {
              element.value = vals[0] || '';
            }
          });
        });
      } catch (error) {}
    }

    conditions();
    showPage();
    form.addEventListener('submit', function (event) {
      var first = form.querySelector(':invalid');
      if (first) {
        event.preventDefault();
        first.focus();
        first.setAttribute('aria-invalid', 'true');
      } else {
        localStorage.removeItem(key);
      }
    });
  }

  function initialize(context) {
    (context || document).querySelectorAll('.scfs-form').forEach(initializeForm);
  }

  document.addEventListener('DOMContentLoaded', function () { initialize(document); });
  document.addEventListener('scfs:support-view-changed', function (event) {
    initialize(event.detail && event.detail.workspace ? event.detail.workspace : document);
  });

  window.SCFSForms = { initialize: initialize, initializeForm: initializeForm };
}());
