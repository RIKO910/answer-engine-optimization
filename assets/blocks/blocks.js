/* Answer Engine Optimization: FAQ, HowTo, Key Takeaways and Direct Answer blocks.
 * Plain ES5 + wp globals (no build step). Rendering happens in PHP. */
(function (wp) {
    if (!wp || !wp.blocks) { return; }
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var be = wp.blockEditor || wp.editor;
    var RichText = be.RichText;
    var InspectorControls = be.InspectorControls;
    var useBlockProps = be.useBlockProps;
    var C = wp.components;

    var icon = function (path) {
        return el('svg', { viewBox: '0 0 24 24', width: 24, height: 24, 'aria-hidden': true }, el('path', { d: path, fill: 'currentColor' }));
    };

    function update(list, index, patch) {
        var copy = list.slice();
        copy[index] = Object.assign({}, copy[index], patch);
        return copy;
    }
    function remove(list, index) {
        var copy = list.slice();
        copy.splice(index, 1);
        return copy;
    }
    function move(list, index, dir) {
        var copy = list.slice();
        var target = index + dir;
        if (target < 0 || target >= copy.length) { return copy; }
        var tmp = copy[target]; copy[target] = copy[index]; copy[index] = tmp;
        return copy;
    }
    function rowTools(onUp, onDown, onRemove) {
        return el('div', { className: 'aeo-be-tools' },
            el(C.Button, { icon: 'arrow-up-alt2', label: __('Move up', 'answer-engine-optimization'), onClick: onUp, size: 'small' }),
            el(C.Button, { icon: 'arrow-down-alt2', label: __('Move down', 'answer-engine-optimization'), onClick: onDown, size: 'small' }),
            el(C.Button, { icon: 'trash', label: __('Remove', 'answer-engine-optimization'), onClick: onRemove, isDestructive: true, size: 'small' })
        );
    }

    /* ---------- FAQ ---------- */
    wp.blocks.registerBlockType('aeo/faq', {
        apiVersion: 3,
        title: __('FAQ (AEO)', 'answer-engine-optimization'),
        description: __('Questions and answers, shown on the page and marked up as FAQPage schema.', 'answer-engine-optimization'),
        category: 'aeo',
        icon: icon('M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z'),
        keywords: ['faq', 'question', 'answer', 'aeo'],
        attributes: { items: { type: 'array', default: [] }, heading: { type: 'string', default: '' } },
        supports: { html: false },
        edit: function (props) {
            var items = props.attributes.items || [];
            var set = function (v) { props.setAttributes({ items: v }); };
            var blockProps = useBlockProps ? useBlockProps({ className: 'aeo-be aeo-be-faq' }) : { className: 'aeo-be aeo-be-faq' };
            return el('div', blockProps,
                el(RichText, { tagName: 'h2', className: 'aeo-be-heading', value: props.attributes.heading, allowedFormats: [], placeholder: __('Heading (optional), e.g. Frequently asked questions', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ heading: v }); } }),
                items.map(function (item, i) {
                    return el('div', { className: 'aeo-be-row', key: i },
                        el(RichText, { tagName: 'p', className: 'aeo-be-q', value: item.q, allowedFormats: [], placeholder: __('Question', 'answer-engine-optimization'), onChange: function (v) { set(update(items, i, { q: v })); } }),
                        el(RichText, { tagName: 'p', className: 'aeo-be-a', value: item.a, allowedFormats: ['core/bold', 'core/italic', 'core/link'], placeholder: __('Answer (1–3 sentences work best)', 'answer-engine-optimization'), onChange: function (v) { set(update(items, i, { a: v })); } }),
                        rowTools(function () { set(move(items, i, -1)); }, function () { set(move(items, i, 1)); }, function () { set(remove(items, i)); })
                    );
                }),
                el(C.Button, { variant: 'secondary', icon: 'plus', onClick: function () { set(items.concat([{ q: '', a: '' }])); } }, __('Add question', 'answer-engine-optimization'))
            );
        },
        save: function () { return null; }
    });

    /* ---------- HowTo ---------- */
    wp.blocks.registerBlockType('aeo/howto', {
        apiVersion: 3,
        title: __('How-to steps (AEO)', 'answer-engine-optimization'),
        description: __('Numbered steps marked up as HowTo schema.', 'answer-engine-optimization'),
        category: 'aeo',
        icon: icon('M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z'),
        keywords: ['howto', 'steps', 'guide', 'aeo'],
        attributes: {
            title: { type: 'string', default: '' },
            description: { type: 'string', default: '' },
            minutes: { type: 'number', default: 0 },
            supplies: { type: 'string', default: '' },
            steps: { type: 'array', default: [] }
        },
        supports: { html: false },
        edit: function (props) {
            var a = props.attributes;
            var steps = a.steps || [];
            var set = function (v) { props.setAttributes({ steps: v }); };
            var blockProps = useBlockProps ? useBlockProps({ className: 'aeo-be aeo-be-howto' }) : { className: 'aeo-be aeo-be-howto' };
            return el('div', blockProps,
                el(InspectorControls, null,
                    el(C.PanelBody, { title: __('Details', 'answer-engine-optimization') },
                        el(C.TextControl, { type: 'number', min: 0, label: __('Total time (minutes)', 'answer-engine-optimization'), value: a.minutes || '', onChange: function (v) { props.setAttributes({ minutes: parseInt(v, 10) || 0 }); } }),
                        el(C.TextControl, { label: __('Supplies (comma separated)', 'answer-engine-optimization'), value: a.supplies, onChange: function (v) { props.setAttributes({ supplies: v }); } })
                    )
                ),
                el(RichText, { tagName: 'h2', className: 'aeo-be-heading', value: a.title, allowedFormats: [], placeholder: __('How to …', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ title: v }); } }),
                el(RichText, { tagName: 'p', value: a.description, placeholder: __('Short description (optional)', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ description: v }); } }),
                el('ol', { className: 'aeo-be-steps' },
                    steps.map(function (step, i) {
                        return el('li', { className: 'aeo-be-row', key: i },
                            el(RichText, { tagName: 'p', className: 'aeo-be-q', value: step.name, allowedFormats: [], placeholder: __('Step name (optional)', 'answer-engine-optimization'), onChange: function (v) { set(update(steps, i, { name: v })); } }),
                            el(RichText, { tagName: 'p', className: 'aeo-be-a', value: step.text, allowedFormats: ['core/bold', 'core/italic', 'core/link'], placeholder: __('What to do', 'answer-engine-optimization'), onChange: function (v) { set(update(steps, i, { text: v })); } }),
                            rowTools(function () { set(move(steps, i, -1)); }, function () { set(move(steps, i, 1)); }, function () { set(remove(steps, i)); })
                        );
                    })
                ),
                el(C.Button, { variant: 'secondary', icon: 'plus', onClick: function () { set(steps.concat([{ name: '', text: '' }])); } }, __('Add step', 'answer-engine-optimization'))
            );
        },
        save: function () { return null; }
    });

    /* ---------- Key takeaways ---------- */
    wp.blocks.registerBlockType('aeo/key-takeaways', {
        apiVersion: 3,
        title: __('Key takeaways (AEO)', 'answer-engine-optimization'),
        description: __('A short TL;DR list. Marked as speakable for voice assistants.', 'answer-engine-optimization'),
        category: 'aeo',
        icon: icon('M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z'),
        keywords: ['tldr', 'summary', 'takeaways', 'aeo'],
        attributes: { title: { type: 'string', default: '' }, items: { type: 'array', default: [] } },
        supports: { html: false },
        edit: function (props) {
            var items = props.attributes.items && props.attributes.items.length ? props.attributes.items : [''];
            var set = function (v) { props.setAttributes({ items: v }); };
            var blockProps = useBlockProps ? useBlockProps({ className: 'aeo-be aeo-be-takeaways aeo-key-takeaways' }) : { className: 'aeo-be aeo-be-takeaways aeo-key-takeaways' };
            return el('div', blockProps,
                el(RichText, { tagName: 'p', className: 'aeo-be-heading', value: props.attributes.title, allowedFormats: [], placeholder: __('Key takeaways', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ title: v }); } }),
                el('ul', null, items.map(function (item, i) {
                    return el('li', { key: i, className: 'aeo-be-row aeo-be-inline' },
                        el(RichText, { tagName: 'span', value: item, allowedFormats: ['core/bold', 'core/italic', 'core/link'], placeholder: __('One short sentence', 'answer-engine-optimization'), onChange: function (v) { var c = items.slice(); c[i] = v; set(c); } }),
                        el(C.Button, { icon: 'trash', label: __('Remove', 'answer-engine-optimization'), size: 'small', onClick: function () { set(remove(items, i)); } })
                    );
                })),
                el(C.Button, { variant: 'secondary', icon: 'plus', onClick: function () { set(items.concat([''])); } }, __('Add point', 'answer-engine-optimization'))
            );
        },
        save: function () { return null; }
    });

    /* ---------- Direct answer ---------- */
    wp.blocks.registerBlockType('aeo/direct-answer', {
        apiVersion: 3,
        title: __('Direct answer (AEO)', 'answer-engine-optimization'),
        description: __('The one- or two-sentence answer AI assistants can quote. Put it near the top.', 'answer-engine-optimization'),
        category: 'aeo',
        icon: icon('M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'),
        keywords: ['answer', 'summary', 'aeo', 'snippet'],
        attributes: { question: { type: 'string', default: '' }, answer: { type: 'string', default: '' } },
        supports: { html: false },
        edit: function (props) {
            var a = props.attributes;
            var words = (a.answer || '').replace(/<[^>]+>/g, ' ').trim().split(/\s+/).filter(Boolean).length;
            var blockProps = useBlockProps ? useBlockProps({ className: 'aeo-be aeo-direct-answer' }) : { className: 'aeo-be aeo-direct-answer' };
            return el('div', blockProps,
                el(RichText, { tagName: 'p', className: 'aeo-be-q', value: a.question, allowedFormats: [], placeholder: __('Question this page answers (optional)', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ question: v }); } }),
                el(RichText, { tagName: 'p', value: a.answer, allowedFormats: ['core/bold', 'core/italic', 'core/link'], placeholder: __('Answer in 1–2 sentences (under 60 words)', 'answer-engine-optimization'), onChange: function (v) { props.setAttributes({ answer: v }); } }),
                words > 60 ? el('p', { className: 'aeo-be-warn' }, __('Over 60 words. Shorter answers are easier for assistants to quote.', 'answer-engine-optimization')) : null
            );
        },
        save: function () { return null; }
    });
})(window.wp);
