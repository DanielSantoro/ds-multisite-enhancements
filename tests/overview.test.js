const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function setup(fetchResponse) {
    let change;
    const toggle = {
        checked: false,
        disabled: false,
        addEventListener: function (event, handler) { change = handler; }
    };
    const status = { textContent: '', dataset: { enabled: 'Enabled.', disabled: 'Disabled.', error: 'Save failed.' } };
    const settings = { hidden: true, href: 'https://example.test/wp-admin/network/admin.php?page=dsme-read-time', dataset: { menuTitle: 'Read Time' } };
    const submit = { hidden: false };
    const form = {
        dataset: { ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php' },
        querySelector: function (selector) {
            return {
                'input[name="enabled"]': toggle,
                '.dsme-feature-status': status,
                '.dsme-feature-settings': settings,
                '.dsme-feature-submit': submit
            }[selector];
        }
    };
    const menu = {
        items: [],
        querySelectorAll: function () { return this.items.map(function (item) { return item.link; }); },
        appendChild: function (item) { this.items.push(item); }
    };
    const document = {
        querySelectorAll: function () { return [form]; },
        querySelector: function () { return menu; },
        createElement: function (tag) {
            return tag === 'li' ? {
                appendChild: function (link) { this.link = link; link.parentElement = this; }
            } : {};
        }
    };
    const source = fs.readFileSync(path.join(__dirname, '../js/overview.js'), 'utf8');
    vm.runInNewContext(source, {
        document,
        URL,
        FormData: function () {},
        fetch: async function () { return fetchResponse; }
    });
    return { toggle, status, settings, submit, menu, change };
}

test('successful changes update the switch, settings link and menu', async function () {
    const ui = setup({ ok: true, json: async function () { return { success: true }; } });
    assert.equal(ui.submit.hidden, true);
    ui.toggle.checked = true;
    await ui.change();
    assert.equal(ui.settings.hidden, false);
    assert.equal(ui.status.textContent, 'Enabled.');
    assert.equal(ui.menu.items[0].link.textContent, 'Read Time');
    assert.equal(ui.menu.items[0].hidden, false);
    ui.toggle.checked = false;
    await ui.change();
    assert.equal(ui.settings.hidden, true);
    assert.equal(ui.menu.items[0].hidden, true);
    assert.equal(ui.status.textContent, 'Disabled.');
    assert.equal(ui.toggle.disabled, false);
});

test('failed saves restore the switch and report an error', async function () {
    const ui = setup({ ok: false, json: async function () { return { success: false }; } });
    ui.toggle.checked = true;
    await ui.change();
    assert.equal(ui.toggle.checked, false);
    assert.equal(ui.toggle.disabled, false);
    assert.equal(ui.settings.hidden, true);
    assert.equal(ui.menu.items.length, 0);
    assert.equal(ui.status.textContent, 'Save failed.');
});