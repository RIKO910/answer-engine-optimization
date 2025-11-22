jQuery(document).ready(function($) {
    // Show questions modal when the editor is loaded
    $(window).on('load', function() {
        if ($('#aeo-questions-modal').length && $('#post').length) {
            setTimeout(function() {
                $('#aeo-questions-modal').show();
                loadQuestionSuggestions();
            }, 2000);
        }
    });

    // Modal functionality
    var modal = $('#aeo-questions-modal');
    var postId = $('#post_ID').val();

    // Close modal
    $('.aeo-modal-close, #aeo-close-modal').on('click', function() {
        modal.hide();
    });

    // Generate more questions
    $('#aeo-generate-questions').on('click', function() {
        generateQuestions();
    });

    // Apply selected questions
    $('#aeo-apply-selected').on('click', function() {
        applySelectedQuestions();
    });

    function loadQuestionSuggestions() {
        $('#aeo-questions-list').html('<p>' + aeoQuestionsData.generatingText + '</p>');
        generateQuestions();
    }

    function generateQuestions() {
        $('#aeo-generate-questions').prop('disabled', true).text(aeoQuestionsData.generatingText);

        $.ajax({
            url: aeoQuestionsData.ajaxurl,
            type: 'POST',
            data: {
                action: 'aeo_generate_questions',
                post_id: postId,
                nonce: aeoQuestionsData.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayQuestions(response.data);
                } else {
                    alert(aeoQuestionsData.errorText);
                }
            },
            error: function() {
                alert(aeoQuestionsData.errorText);
            },
            complete: function() {
                $('#aeo-generate-questions').prop('disabled', false)
                    .text('Generate More Questions');
            }
        });
    }

    function displayQuestions(questions) {
        var html = '';
        questions.forEach(function(question, index) {
            html += `
                <div class="aeo-question-item">
                    <label>
                        <input type="checkbox" name="aeo_suggested_questions[]" value="${index}" checked>
                        ${question}
                    </label>
                </div>
            `;
        });
        $('#aeo-questions-list').html(html);
    }

    function applySelectedQuestions() {
        var selectedQuestions = [];
        $('input[name="aeo_suggested_questions[]"]:checked').each(function() {
            selectedQuestions.push($(this).parent().text().trim());
        });

        if (selectedQuestions.length > 0) {
            // Add to FAQ items
            selectedQuestions.forEach(function(question) {
                var faqItem = `
                    <div class="aeo-faq-item">
                        <input type="text" name="aeo_faq_question[]" value="${question}" class="widefat">
                        <textarea name="aeo_faq_answer[]" rows="2" class="widefat"></textarea>
                        <button type="button" class="button aeo-remove-faq">Remove</button>
                    </div>
                `;
                $('#aeo-faq-items').append(faqItem);
            });

            modal.hide();
        } else {
            alert('Please select at least one question');
        }
    }
});