// Block editor sidebar panel: "Override calculated read time to show:".
// Only enqueued on tracked post types while per-post overrides are allowed.
// Stores the value in the post's "dsme_readtime" REST field, saved with the post.
(function (wp) {
    const el = wp.element.createElement;
    const __ = wp.i18n.__;
    const sprintf = wp.i18n.sprintf;
    const useSelect = wp.data.useSelect;
    const useDispatch = wp.data.useDispatch;
    const TextControl = wp.components.TextControl;
    // WordPress 6.6+ has it in wp.editor; older versions in wp.editPost.
    const PluginDocumentSettingPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || wp.editPost.PluginDocumentSettingPanel;

    function Panel() {
        const field = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('dsme_readtime');
        }, []);
        const editPost = useDispatch('core/editor').editPost;

        if (!field) {
            return null;
        }

        const override = parseInt(field.override, 10) > 0 ? String(field.override) : '';
        const help = typeof field.calculated === 'number'
            ? sprintf(__('Leave blank to use the calculated time (currently %d).', 'ds-multisite-enhancements'), field.calculated)
            : __('Leave blank to use the calculated time, available after the post is saved.', 'ds-multisite-enhancements');

        return el(PluginDocumentSettingPanel, { name: 'dsme-readtime', title: __('Read Time', 'ds-multisite-enhancements') },
            el(TextControl, {
                label: __('Override calculated read time to show:', 'ds-multisite-enhancements'),
                type: 'number',
                min: 1,
                step: 1,
                value: override,
                help: help,
                onChange: function (value) {
                    const number = parseInt(value, 10);
                    editPost({ dsme_readtime: Object.assign({}, field, { override: number > 0 ? number : 0 }) });
                }
            })
        );
    }

    wp.plugins.registerPlugin('dsme-readtime', { render: Panel });
})(window.wp);
