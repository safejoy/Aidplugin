jQuery(document).ready(function($){
    // Open modal
    $(document).on("click",".safejoy-open-btn",function(){
        $(this).next(".safejoy-modal").show();
    });

    // Close modal
    $(document).on("click",".safejoy-close",function(){
        $(this).closest(".safejoy-modal").hide();
    });

    // Add new link field
    $(document).on("click",".add-link",function(e){
        e.preventDefault();
        $(this).siblings(".safejoy-links").append('<input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>');
    });

    // Submit form
    $(document).on("submit",".safejoy-form",function(e){
        e.preventDefault();
        let $form = $(this);
        $.post(safejoy_ajax.ajax_url, $form.serialize()+"&action=safejoy_submit_form", function(response){
            let $msg = $form.siblings(".safejoy-message");
            if(response.success){
                $msg.html("<p style='color:green;'>"+response.data+"</p>");
                $form[0].reset();
                $form.find(".safejoy-links").html('<input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>');
            } else {
                $msg.html("<p style='color:red;'>"+response.data+"</p>");
            }
        });
    });
});
