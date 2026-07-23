(function () {
    'use strict';

    var config = window.SCFSPluginDiscovery || null;
    if (!config || !config.restUrl || !window.fetch) {
        return;
    }

    function fragmentNode(selector, html) {
        var template = document.createElement('template');
        template.innerHTML = String(html || '').trim();
        return template.content.querySelector(selector);
    }

    function replaceFragment(selector, html) {
        var current = document.querySelector(selector);
        var replacement = fragmentNode(selector, html);
        if (current && replacement) {
            current.replaceWith(replacement);
        }
    }

    function showNotice(message, isError) {
        var notice = document.querySelector('[data-scfs-discovery-notice]');
        if (!notice) {
            return;
        }
        notice.hidden = false;
        notice.classList.toggle('notice-success', !isError);
        notice.classList.toggle('notice-error', !!isError);
        var paragraph = notice.querySelector('p');
        if (paragraph) {
            paragraph.textContent = message;
        }
        notice.setAttribute('tabindex', '-1');
        notice.focus();
    }

    function setBusy(form, busy) {
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        Array.prototype.forEach.call(form.querySelectorAll('button, select'), function (control) {
            control.disabled = busy;
        });
        var spinner = form.querySelector('.spinner');
        if (spinner) {
            spinner.classList.toggle('is-active', busy);
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-scfs-discovery-decision]');
        if (!form) {
            return;
        }
        event.preventDefault();

        var formData = new FormData(form);
        var pluginFile = formData.get('plugin_file');
        var decision = formData.get('decision');
        if (!pluginFile || !decision) {
            showNotice(config.error, true);
            return;
        }

        setBusy(form, true);
        showNotice(config.saving, false);

        window.fetch(config.restUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify({
                plugin_file: pluginFile,
                decision: decision
            })
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || config.error);
                }
                return payload;
            });
        }).then(function (payload) {
            var fragments = payload.fragments || {};
            replaceFragment('[data-scfs-discovery-summary]', fragments.summary);
            replaceFragment('[data-scfs-discovery-matches]', fragments.matches);
            replaceFragment('[data-scfs-discovery-review]', fragments.review);

            var ignoredCurrent = document.querySelector('[data-scfs-discovery-ignored]');
            var ignoredReplacement = fragmentNode('[data-scfs-discovery-ignored]', fragments.ignored);
            if (ignoredCurrent && ignoredReplacement) {
                ignoredCurrent.replaceWith(ignoredReplacement);
            }
            showNotice(payload.message || 'Plugin discovery decision saved.', false);
        }).catch(function (error) {
            setBusy(form, false);
            showNotice(error.message || config.error, true);
        });
    });
}());
