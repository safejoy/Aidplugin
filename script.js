jQuery(document).ready(function($){
    
    // Open modal when button is clicked
    $(document).on('click', '.safejoy-open-btn', function(e){
        e.preventDefault();
        var modalId = $(this).data('modal');
        $('#' + modalId).fadeIn(300);
        $('body').css('overflow', 'hidden'); // Prevent scrolling
    });

    // Close modal when X is clicked
    $(document).on('click', '.safejoy-close', function(){
        $(this).closest('.safejoy-modal').fadeOut(300);
        $('body').css('overflow', 'auto');
    });

    // Close modal when clicking outside the content
    $(document).on('click', '.safejoy-modal', function(e){
        if ($(e.target).hasClass('safejoy-modal')) {
            $(this).fadeOut(300);
            $('body').css('overflow', 'auto');
        }
    });

    // Close modal on ESC key
    $(document).on('keydown', function(e){
        if (e.key === 'Escape' || e.keyCode === 27) {
            $('.safejoy-modal:visible').fadeOut(300);
            $('body').css('overflow', 'auto');
        }
    });

    // Add new link field
    $(document).on('click', '.safejoy-add-link', function(e){
        e.preventDefault();
        var linkInput = '<input type="url" name="links[]" placeholder="https://example.com" required>';
        $(this).siblings('.safejoy-links').append(linkInput);
    });

    // Submit form via AJAX
    $(document).on('submit', '.safejoy-form', function(e){
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('.safejoy-submit-btn');
        var $message = $form.siblings('.safejoy-message');
        
        // Disable submit button
        $submitBtn.prop('disabled', true).text('Submitting...');
        $message.html('');

        $.ajax({
            url: safejoy_ajax.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&action=safejoy_submit_form',
            dataType: 'json',
            success: function(response){
                if (response.success) {
                    $message.html('<p class="safejoy-success">' + response.data + '</p>');
                    // Reset form
                    $form[0].reset();
                    // Reset links to just one field
                    $form.find('.safejoy-links').html('<input type="url" name="links[]" placeholder="https://example.com" required>');
                    
                    // Close modal after 2 seconds
                    setTimeout(function(){
                        $form.closest('.safejoy-modal').fadeOut(300);
                        $('body').css('overflow', 'auto');
                        $message.html('');
                    }, 2000);
                } else {
                    $message.html('<p class="safejoy-error">' + response.data + '</p>');
                }
            },
            error: function(){
                $message.html('<p class="safejoy-error">An error occurred. Please try again.</p>');
            },
            complete: function(){
                // Re-enable submit button
                $submitBtn.prop('disabled', false).text('Submit');
            }
        });
    });

    // Remove empty link fields before submit (optional cleanup)
    $(document).on('submit', '.safejoy-form', function(){
        $(this).find('input[name="links[]"]').each(function(){
            if ($(this).val().trim() === '') {
                $(this).remove();
            }
        });
    });
});