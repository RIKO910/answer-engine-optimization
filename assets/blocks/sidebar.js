/* Answer Engine Optimization: live AEO score panel in the block editor sidebar. */
(function (wp) {
    if (!wp || !wp.plugins || !wp.data) { return; }
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var __ = wp.i18n.__;
    var useSelect = wp.data.useSelect;
    var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
    if (!Panel) { return; }

    function color(score) {
        return score >= 71 ? '#059669' : (score >= 41 ? '#d97706' : '#e11d48');
    }

    function ScorePanel() {
        var data = useSelect(function (select) {
            var ed = select('core/editor');
            return {
                id: ed.getCurrentPostId(),
                title: ed.getEditedPostAttribute('title') || '',
                content: ed.getEditedPostContent() || ''
            };
        }, []);
        var state = useState(null);
        var result = state[0], setResult = state[1];
        var busy = useState(false);

        useEffect(function () {
            var t = setTimeout(function () {
                busy[1](true);
                wp.apiFetch({
                    path: '/aeo-genius/v1/score-preview',
                    method: 'POST',
                    data: { post_id: data.id, title: data.title, content: data.content }
                }).then(function (r) { setResult(r); }).catch(function () {}).finally(function () { busy[1](false); });
            }, 1500);
            return function () { clearTimeout(t); };
        }, [data.title, data.content]);

        if (!result) {
            return el(Panel, { name: 'aeo-score', title: __('AEO score', 'answer-engine-optimization'), className: 'aeo-score-panel' },
                el('p', null, __('Checking…', 'answer-engine-optimization')));
        }
        var tips = (result.recommendations || []).slice(0, 5);
        return el(Panel, { name: 'aeo-score', title: __('AEO score', 'answer-engine-optimization') + ': ' + result.score, className: 'aeo-score-panel' },
            el('div', { style: { display: 'flex', alignItems: 'baseline', gap: '8px', marginBottom: '8px' } },
                el('span', { style: { fontSize: '28px', fontWeight: 600, color: color(result.score), lineHeight: 1 } }, String(result.score)),
                el('span', { style: { color: '#71717a' } }, '/ 100' + (busy[0] ? ' · …' : ''))
            ),
            el('div', { style: { height: '6px', background: '#f4f4f5', borderRadius: '3px', overflow: 'hidden', marginBottom: '12px' } },
                el('div', { style: { width: result.score + '%', height: '100%', background: color(result.score) } })),
            tips.length ? el('ul', { style: { margin: 0, paddingLeft: '16px' } },
                tips.map(function (t) {
                    return el('li', { key: t.id, style: { marginBottom: '6px' } }, t.tip, el('span', { style: { color: '#71717a' } }, ' (+' + t.gain + ')'));
                })) : el('p', null, __('Everything checks out.', 'answer-engine-optimization'))
        );
    }

    wp.plugins.registerPlugin('aeo-score', { render: ScorePanel, icon: null });
})(window.wp);
