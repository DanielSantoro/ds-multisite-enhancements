document.querySelectorAll('.dsme-feature-form').forEach(function (form) {
    const toggle = form.querySelector('input[name="enabled"]');
    const status = form.querySelector('.dsme-feature-status');
    const settings = form.querySelector('.dsme-feature-settings');
    const submit = form.querySelector('.dsme-feature-submit');
    submit.hidden = true;

    toggle.addEventListener('change', async function () {
        const enabled = toggle.checked;
        const data = new FormData(form);
        toggle.disabled = true;
        status.textContent = '';

        try {
            const response = await fetch(form.dataset.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error('Save failed');
            }
            if (settings) {
                settings.hidden = !enabled;
                const menu = document.querySelector('#toplevel_page_ds-multisite-enhancements .wp-submenu');
                if (menu) {
                    const page = new URL(settings.href).searchParams.get('page');
                    let link = Array.from(menu.querySelectorAll('a')).find(function (item) {
                        return new URL(item.href).searchParams.get('page') === page;
                    });
                    if (!link && enabled) {
                        const item = document.createElement('li');
                        link = document.createElement('a');
                        link.href = settings.href;
                        link.textContent = settings.dataset.menuTitle;
                        item.appendChild(link);
                        menu.appendChild(item);
                    }
                    if (link) {
                        link.parentElement.hidden = !enabled;
                    }
                }
            }
            status.textContent = enabled ? status.dataset.enabled : status.dataset.disabled;
        } catch (error) {
            toggle.checked = !enabled;
            status.textContent = status.dataset.error;
        } finally {
            toggle.disabled = false;
        }
    });
});