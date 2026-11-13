// Read Time settings pages (network and site): live preview and Copy buttons.
(function () {
    const singular = document.querySelector('.dsme-readtime-preview[data-preview="singular"]');
    const plural = document.querySelector('.dsme-readtime-preview[data-preview="plural"]');
    const before = document.getElementById('dsme_readtime_before');
    const after = document.getElementById('dsme_readtime_after');
    const afterSingular = document.getElementById('dsme_readtime_after_singular');
    const minimum = document.getElementById('dsme_readtime_minimum');

    // Same rule as dsme_readtime_singularize(): "minutes" -> "minute", "mins" -> "min".
    function singularize(text) {
        return text.replace(/\b(min(?:ute)?)s\b/i, '$1');
    }

    // Plural example: 5 minutes, or the minimum time if that is higher.
    function pluralValue() {
        const min = minimum ? parseInt(minimum.value, 10) : NaN;
        return Math.max(5, isNaN(min) ? 0 : min);
    }

    function render(preview, value, afterText) {
        preview.querySelector('.dsme-readtime-before').textContent = before ? before.value : '';
        preview.querySelector('.dsme-readtime-value').textContent = String(value);
        preview.querySelector('.dsme-readtime-after').textContent = afterText;
    }

    function update() {
        const afterText = after ? after.value : '';
        const custom = afterSingular ? afterSingular.value : '';
        if (afterSingular) {
            afterSingular.placeholder = singularize(afterText);
        }
        if (singular) {
            render(singular, 1, custom !== '' ? custom : singularize(afterText));
        }
        if (plural) {
            render(plural, pluralValue(), afterText);
        }
    }

    if (singular || plural) {
        [before, after, afterSingular, minimum].forEach(function (field) {
            if (field) {
                field.addEventListener('input', update);
            }
        });
        update();
    }

    document.querySelectorAll('.dsme-copy').forEach(function (button) {
        const target = document.getElementById(button.dataset.copyTarget);
        const status = button.parentElement.querySelector('.dsme-copy-status');

        button.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(target.textContent);
                status.textContent = button.dataset.copied;
            } catch (error) {
                // No clipboard access (e.g. not HTTPS): select the code so it can be copied by hand.
                const range = document.createRange();
                range.selectNodeContents(target);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                status.textContent = button.dataset.failed;
            }
        });
    });
})();
