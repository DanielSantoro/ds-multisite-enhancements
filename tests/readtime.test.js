const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function load(file, context) {
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../js', file), 'utf8'), context);
}

function input(value) {
    return {
        value: value,
        handlers: {},
        addEventListener: function (event, handler) { this.handlers[event] = handler; },
        type: function (next) { this.value = next; this.handlers.input(); }
    };
}

function previewEl() {
    const spans = {
        '.dsme-readtime-before': { textContent: '' },
        '.dsme-readtime-value': { textContent: '' },
        '.dsme-readtime-after': { textContent: '' }
    };
    return {
        spans: spans,
        text: function () { return Object.values(spans).map(function (span) { return span.textContent; }).join(''); },
        querySelector: function (selector) { return spans[selector]; }
    };
}

function settingsPage(clipboard) {
    const previews = { singular: previewEl(), plural: previewEl() };
    const spans = previews.plural.spans;
    const fields = {
        dsme_readtime_before: input('Read Time: '),
        dsme_readtime_after: input(' minutes'),
        dsme_readtime_after_singular: input(''),
        dsme_readtime_minimum: input('')
    };
    const status = { textContent: '' };
    const code = { textContent: "<?php dsme_the_readtime(); ?>" };
    const button = {
        dataset: { copyTarget: 'dsme-snippet-php', copied: 'Copied.', failed: 'Press Ctrl+C.' },
        parentElement: { querySelector: function () { return status; } },
        addEventListener: function (event, handler) { this.click = handler; }
    };
    const selection = { ranges: [], removeAllRanges: function () { this.ranges = []; }, addRange: function (range) { this.ranges.push(range); } };
    const document = {
        querySelector: function (selector) { return previews[/data-preview="(\w+)"/.exec(selector)[1]]; },
        querySelectorAll: function () { return [button]; },
        getElementById: function (id) { return id === 'dsme-snippet-php' ? code : fields[id]; },
        createRange: function () { return { selectNodeContents: function (node) { this.node = node; } }; }
    };
    const window = { getSelection: function () { return selection; } };
    load('readtime-settings.js', { document, window, navigator: { clipboard } });
    return { spans, previews, fields, status, button, selection, code };
}

test('preview shows the before/after text exactly, spaces included', function () {
    const page = settingsPage({});
    assert.equal(page.spans['.dsme-readtime-value'].textContent, '5');
    assert.equal(page.spans['.dsme-readtime-before'].textContent, 'Read Time: ');
    assert.equal(page.spans['.dsme-readtime-after'].textContent, ' minutes');
    page.fields.dsme_readtime_before.type('Reading: ');
    page.fields.dsme_readtime_after.type(' min');
    assert.equal(page.spans['.dsme-readtime-before'].textContent, 'Reading: ');
    assert.equal(page.spans['.dsme-readtime-after'].textContent, ' min');
});

test('singular preview drops the s from minutes and mins, or uses the singular field', function () {
    const page = settingsPage({});
    assert.equal(page.previews.singular.text(), 'Read Time: 1 minute');
    assert.equal(page.previews.plural.text(), 'Read Time: 5 minutes');
    assert.equal(page.fields.dsme_readtime_after_singular.placeholder, ' minute');
    page.fields.dsme_readtime_after.type(' mins');
    assert.equal(page.previews.singular.text(), 'Read Time: 1 min');
    assert.equal(page.previews.plural.text(), 'Read Time: 5 mins');
    page.fields.dsme_readtime_after.type(' MINUTES to read');
    assert.equal(page.previews.singular.text(), 'Read Time: 1 MINUTE to read');
    page.fields.dsme_readtime_after_singular.type(' quick minute');
    assert.equal(page.previews.singular.text(), 'Read Time: 1 quick minute');
    assert.equal(page.previews.plural.text(), 'Read Time: 5 MINUTES to read');
});

test('preview uses the minimum time when it is above 5', function () {
    const page = settingsPage({});
    page.fields.dsme_readtime_minimum.type('8');
    assert.equal(page.spans['.dsme-readtime-value'].textContent, '8');
    page.fields.dsme_readtime_minimum.type('2');
    assert.equal(page.spans['.dsme-readtime-value'].textContent, '5');
    page.fields.dsme_readtime_minimum.type('');
    assert.equal(page.spans['.dsme-readtime-value'].textContent, '5');
});

test('copy button copies the snippet, or selects it when the clipboard is unavailable', async function () {
    let copied = null;
    const page = settingsPage({ writeText: async function (text) { copied = text; } });
    await page.button.click();
    assert.equal(copied, page.code.textContent);
    assert.equal(page.status.textContent, 'Copied.');

    const blocked = settingsPage({ writeText: async function () { throw new Error('denied'); } });
    await blocked.button.click();
    assert.equal(blocked.status.textContent, 'Press Ctrl+C.');
    assert.equal(blocked.selection.ranges[0].node, blocked.code);
});

// Minimal wp.* stubs: createElement returns a plain tree; useSelect runs the selector.
function wpStub(records, edited) {
    const registered = {};
    const el = function (type, props) {
        return { type: type, props: props || {}, children: Array.prototype.slice.call(arguments, 2) };
    };
    const core = {
        getEntityRecord: function () {},
        getEditedEntityRecord: function (kind, type, id) { return records[id]; }
    };
    const editor = { getEditedPostAttribute: function (name) { return edited.record[name]; } };
    return {
        registered: registered,
        wp: {
            element: { createElement: el },
            data: {
                useSelect: function (fn) { return fn(function (store) { return store === 'core' ? core : editor; }); },
                useDispatch: function () { return { editPost: function (edits) { Object.assign(edited.record, edits); } }; }
            },
            blockEditor: { useBlockProps: function () { return { className: 'wp-block-dsme-readtime' }; } },
            blocks: { registerBlockType: function (name, settings) { registered[name] = settings; } },
            i18n: { __: function (text) { return text; }, sprintf: function (text, n) { return text.replace('%d', n); } },
            components: { TextControl: 'TextControl' },
            editor: { PluginDocumentSettingPanel: 'Panel' },
            plugins: { registerPlugin: function (name, settings) { registered[name] = settings; } }
        }
    };
}

function blockText(tree) {
    const span = tree.children[0];
    return span.children.map(function (child) { return child.children.join(''); }).join('');
}

test('block shows 𝑥 until the post has a value, then the override or calculated time', function () {
    const stub = wpStub({ 1: { dsme_readtime: { calculated: null, override: 0 } }, 2: { dsme_readtime: { calculated: 4, override: 0 } }, 3: { dsme_readtime: { calculated: 4, override: 9 } }, 4: { dsme_readtime: { calculated: 1, override: 0 } } }, {});
    load('readtime-block.js', { window: { wp: stub.wp, dsmeReadtime: { before: 'Read Time: ', after: ' minutes', afterSingular: ' minute', allowOverride: true } } });
    const edit = stub.registered['dsme/readtime'].edit;
    assert.equal(stub.registered['dsme/readtime'].save(), null);
    assert.equal(blockText(edit({ context: {} })), 'Read Time: 𝑥 minutes');
    assert.equal(blockText(edit({ context: { postId: 1, postType: 'post' } })), 'Read Time: 𝑥 minutes');
    assert.equal(blockText(edit({ context: { postId: 2, postType: 'post' } })), 'Read Time: 4 minutes');
    assert.equal(blockText(edit({ context: { postId: 4, postType: 'post' } })), 'Read Time: 1 minute');
    assert.equal(blockText(edit({ context: { postId: 3, postType: 'portfolio' } })), 'Read Time: 9 minutes');
});

test('block ignores overrides while they are not allowed', function () {
    const stub = wpStub({ 3: { dsme_readtime: { calculated: 4, override: 9 } } }, {});
    load('readtime-block.js', { window: { wp: stub.wp, dsmeReadtime: { before: '', after: ' min', allowOverride: false } } });
    assert.equal(blockText(stub.registered['dsme/readtime'].edit({ context: { postId: 3, postType: 'post' } })), '4 min');
});

test('sidebar panel edits the override and keeps the calculated time', function () {
    const edited = { record: { dsme_readtime: { calculated: 6, override: 0 } } };
    const stub = wpStub({}, edited);
    load('readtime-panel.js', { window: { wp: stub.wp } });
    const render = stub.registered['dsme-readtime'].render;

    let control = render().children[0];
    assert.equal(control.props.label, 'Override calculated read time to show:');
    assert.equal(control.props.value, '');
    assert.match(control.props.help, /currently 6/);

    control.props.onChange('12');
    assert.deepEqual({ ...edited.record.dsme_readtime }, { calculated: 6, override: 12 });
    assert.equal(render().children[0].props.value, '12');

    render().children[0].props.onChange('');
    assert.equal(edited.record.dsme_readtime.override, 0);
});
