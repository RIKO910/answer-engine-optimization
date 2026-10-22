/* Answer Engine Optimization: post editor meta box (FAQ rows). */
jQuery(function ($) {
    var i18n = window.aeoMetaBoxData || {};

    function faqRow(question, answer) {
        var $item = $('<div class="aeo-faq-item"></div>');
        $('<input type="text" name="aeo_faq_question[]" class="widefat">')
            .attr('placeholder', i18n.questionLabel || 'Question')
            .val(question || '')
            .appendTo($item);
        $('<textarea name="aeo_faq_answer[]" rows="2" class="widefat"></textarea>')
            .attr('placeholder', i18n.answerLabel || 'Answer')
            .val(answer || '')
            .appendTo($item);
        $('<button type="button" class="button aeo-remove-faq"></button>')
            .text(i18n.removeLabel || 'Remove')
            .appendTo($item);
        return $item;
    }

    // Exposed for the question suggestions modal.
    window.aeoAddFaqRow = function (question, answer) {
        var $row = faqRow(question, answer);
        $('#aeo-faq-items').append($row);
        return $row;
    };

    $('#aeo-add-faq').on('click', function () {
        window.aeoAddFaqRow('', '').find('input').trigger('focus');
    });

    $(document).on('click', '.aeo-remove-faq', function () {
        $(this).closest('.aeo-faq-item').remove();
    });
});
