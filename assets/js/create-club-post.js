jQuery(document).ready(function() {
    jQuery('input[name="open_access"]').on('change', function () {
        if (jQuery(this).val() == 3) {
            jQuery('#access_product_list').show();
        }
        else {
            jQuery('#access_product_list').hide();
        }
    });

    jQuery('#normal-sortables').css('min-height', '0px');
    jQuery('#slugdiv').hide();
});