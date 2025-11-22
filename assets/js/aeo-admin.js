jQuery(document).ready(function($) {
    // FAQ item management
    $('#aeo-add-faq').on('click', function() {
        var faqItem = `
            <div class="aeo-faq-item">
                <input type="text" name="aeo_faq_question[]" placeholder="Question" class="widefat">
                <textarea name="aeo_faq_answer[]" rows="2" placeholder="Answer" class="widefat"></textarea>
                <button type="button" class="button aeo-remove-faq">Remove</button>
            </div>
        `;
        $('#aeo-faq-items').append(faqItem);
    });

    $(document).on('click', '.aeo-remove-faq', function() {
        $(this).closest('.aeo-faq-item').remove();
    });

    // Question suggestions modal
    if ($('#aeo-questions-modal').length) {
        var modal = $('#aeo-questions-modal');
        var postId = $('#post_ID').val();

        // Show modal on button click (you'll need to add this button to your admin UI)
        $('.aeo-show-questions').on('click', function() {
            modal.show();
            loadQuestionSuggestions();
        });

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
            $('#aeo-questions-list').html('<p>Loading questions...</p>');

            // Get target questions from settings
            var targetQuestions = '<?php echo esc_js(get_option("aeo_settings")["aeo_target_questions"] ?? ""); ?>';
            targetQuestions = targetQuestions.split('\n').filter(q => q.trim() !== '');

            if (targetQuestions.length > 0) {
                displayQuestions(targetQuestions);
            } else {
                generateQuestions();
            }
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
                    $('#aeo-generate-questions').prop('disabled', false).text('Generate More Questions');
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
    }

    // Settings page enhancements
    if ($('body.settings_page_answer-engine-optimization').length) {
        // Add example questions button
        $('.aeo-example-questions').on('click', function() {
            var examples = [
                'What is the main purpose of this plugin?',
                'How does answer engine optimization work?',
                'Why is AEO important for my website?',
                'When should I use AEO techniques?',
                'Where can I see the results of AEO?'
            ].join('\n');

            $('#aeo_target_questions').val(examples);
        });
    }
});