jQuery(document).ready(function($) {
    // FAQ accordion functionality
    $('.aeo-faq-section').each(function() {
        var $section = $(this);
        var $questions = $section.find('.aeo-faq-question');

        // Make first question active by default
        $questions.first().addClass('active')
            .next('.aeo-faq-answer').show();

        // Click handler for questions
        $questions.on('click', function() {
            var $answer = $(this).next('.aeo-faq-answer');

            if ($(this).hasClass('active')) {
                $(this).removeClass('active');
                $answer.slideUp();
            } else {
                $questions.removeClass('active');
                $section.find('.aeo-faq-answer').slideUp();

                $(this).addClass('active');
                $answer.slideDown();
            }
        });
    });

    // HowTo step highlighting
    $('[data-aeo-howto="true"]').each(function() {
        var $list = $(this);
        var steps = $list.find('li');

        steps.each(function(index) {
            $(this).attr('id', 'step-' + (index + 1));

            // Add step number
            if (!$(this).html().match(/^<strong>step \d+/i)) {
                $(this).html('<strong>Step ' + (index + 1) + ':</strong> ' + $(this).html());
            }
        });
    });

    // Definition term tooltips
    $('[data-aeo-definition="true"]').each(function() {
        var $term = $(this);
        var definition = $term.next('span').text();

        $term.tooltip({
            content: definition,
            position: {
                my: 'center bottom',
                at: 'center top-10'
            },
            tooltipClass: 'aeo-definition-tooltip'
        });
    });

    // Voice search optimization - add speak buttons
    if (typeof speechSynthesis !== 'undefined') {
        $('.aeo-direct-answer, .aeo-faq-answer').each(function() {
            var $answer = $(this);
            var text = $answer.text();

            var $speakButton = $('<button class="aeo-speak-button" title="Read aloud">🔊</button>');
            $answer.prepend($speakButton);

            $speakButton.on('click', function() {
                var utterance = new SpeechSynthesisUtterance(text);
                speechSynthesis.speak(utterance);
            });
        });
    }
});