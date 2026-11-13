// dsme/readtime block (editor). Rendered on the server on the front end; in the
// editor it shows the before/after text with the post's read time, or "𝑥" until
// the post has been saved. No build step: plain wp.element.createElement.
(function (wp, settings) {
    const el = wp.element.createElement;
    const useSelect = wp.data.useSelect;
    const useBlockProps = wp.blockEditor.useBlockProps;
    const PLACEHOLDER = '𝑥';

    settings = settings || { before: '', after: '', afterSingular: '', allowOverride: false };

    /**
     * The number to show from the post's "dsme_readtime" REST field: the
     * override (if allowed and set), else the calculated time, else null.
     */
    function minutesFrom(field, allowOverride) {
        if (!field) {
            return null;
        }
        const override = parseInt(field.override, 10);
        if (allowOverride && override > 0) {
            return override;
        }
        return typeof field.calculated === 'number' ? field.calculated : null;
    }

    function Edit(props) {
        const postId = props.context.postId;
        const postType = props.context.postType;

        const field = useSelect(function (select) {
            if (!postId || !postType) {
                return null;
            }
            const core = select('core');
            core.getEntityRecord('postType', postType, postId); // Fetches the record if needed (Query Loop).
            const record = core.getEditedEntityRecord('postType', postType, postId);
            return record ? record.dsme_readtime : null;
        }, [postId, postType]);

        const minutes = minutesFrom(field, settings.allowOverride);

        return el('div', useBlockProps(),
            el('span', { className: 'dsme-readtime' },
                el('span', { className: 'dsme-readtime-before' }, settings.before),
                el('span', { className: 'dsme-readtime-value' }, minutes === null ? PLACEHOLDER : String(minutes)),
                el('span', { className: 'dsme-readtime-after' }, minutes === 1 ? settings.afterSingular : settings.after)
            )
        );
    }

    wp.blocks.registerBlockType('dsme/readtime', {
        edit: Edit,
        save: function () {
            return null;
        }
    });
})(window.wp, window.dsmeReadtime);
