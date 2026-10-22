/* Answer Engine Optimization: "Suggest questions" modal in the post editor. */
jQuery(function ($) {
    var data = window.aeoQuestionsData || {};
    var $modal = $('#aeo-questions-modal');
    var $list = $('#aeo-questions-list');
    var $generate = $('#aeo-generate-questions');
    var suggestions = [];

    if (!$modal.length) {
        return;
    }

    function postId() {
        return $('#post_ID').val() || (window.wp && wp.data && wp.data.select('core/editor') ? wp.data.select('core/editor').getCurrentPostId() : 0);
    }

    function editorContent() {
        try {
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostContent() || '';
            }
        } catch (e) {}
        var $content = $('#content');
        return $content.length ? $content.val() : '';
    }

    function open() {
        $modal.show();
        $modal.find('.aeo-modal-close').trigger('focus');
        if (!suggestions.length) {
            generate();
        }
    }

    function close() {
        $modal.hide();
        $('.aeo-show-questions').trigger('focus');
    }

    function render() {
        $list.empty();
        suggestions.forEach(function (item, index) {
            var id = 'aeo-q-' + index;
            var $row = $('<div class="aeo-question-item"></div>');
            var $label = $('<label></label>').attr('for', id);
            $('<input type="checkbox" checked>').attr('id', id).val(index).appendTo($label);
            $('<span></span>').text(item.question).appendTo($label); // .text(): never inject HTML.
            $row.append($label).appendTo($list);
        });
    }

    function generate() {
        var id = postId();
        $generate.prop('disabled', true).text(data.generatingText);
        if (!suggestions.length) {
            $list.empty().append($('<p></p>').text(data.generatingText));
        }

        $.post(data.ajaxurl, {
            action: 'aeo_generate_questions',
            post_id: id,
            content: editorContent(),
            nonce: data.nonce
        }).done(function (response) {
            if (response && response.success && Array.isArray(response.data)) {
                var seen = {};
                suggestions.forEach(function (s) { seen[s.question.toLowerCase()] = true; });
                response.data.forEach(function (s) {
                    if (s && s.question && !seen[s.question.toLowerCase()]) {
                        suggestions.push({ question: String(s.question), answer: String(s.answer || '') });
                        seen[s.question.toLowerCase()] = true;
                    }
                });
                render();
            } else {
                $list.empty().append($('<p></p>').text(data.errorText));
            }
        }).fail(function () {
            $list.empty().append($('<p></p>').text(data.errorText));
        }).always(function () {
            $generate.prop('disabled', false).text(data.generateText);
        });
    }

    function apply() {
        var picked = $list.find('input:checked').map(function () {
            return suggestions[parseInt(this.value, 10)];
        }).get();

        if (!picked.length) {
            window.alert(data.emptyText);
            return;
        }

        picked.forEach(function (item) {
            if (typeof window.aeoAddFaqRow === 'function') {
                window.aeoAddFaqRow(item.question, item.answer);
            }
        });
        close();
    }

    $(document).on('click', '.aeo-show-questions', function (e) {
        e.preventDefault();
        open();
    });
    $modal.on('click', '.aeo-modal-close, #aeo-close-modal', close);
    $modal.on('click', function (e) {
        if (e.target === $modal[0]) {
            close();
        }
    });
    $(document).on('keydown', function (e) {
        if ('Escape' === e.key && $modal.is(':visible')) {
            close();
        }
    });
    $generate.on('click', generate);
    $('#aeo-apply-selected').on('click', apply);
});
