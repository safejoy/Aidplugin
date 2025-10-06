jQuery(document).ready(function($){
    $("#safejoy-open-form").click(function(){
        $("#safejoy-modal").show();
    });
    $("#safejoy-close").click(function(){
        $("#safejoy-modal").hide();
    });

    $("#add-link").click(function(){
        $("#safejoy-links").append('<input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>');
    });

    $("#safejoy-form").submit(function(e){
        e.preventDefault();
        $.post(safejoy_ajax.ajax_url, $(this).serialize() + "&action=safejoy_submit_form", function(response){
            if(response.success){
                $("#safejoy-message").html("<p style='color:green;'>"+response.data+"</p>");
                $("#safejoy-form")[0].reset();
                $("#safejoy-links").html('<input type="url" name="links[]" placeholder="Helpful Link (https://...)" required><br>');
            } else {
                $("#safejoy-message").html("<p style='color:red;'>"+response.data+"</p>");
            }
        });
    });
});
