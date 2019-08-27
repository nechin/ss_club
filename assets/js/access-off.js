function deleteClubAccess(el) {
    if (jQuery(el).hasClass('button-disabled')) {
        return false;
    }

    if (confirm('Доступ будет отменен с ' + club_access_off_data.date + '. Продолжить?')) {
        jQuery(el).addClass('button-disabled');

        jQuery.ajax({
            url: club_access_off_data.url + 'ajax.php',
            dataType: 'json',
            type: 'POST',
            data: {
                controller: 'clients',
                act: 'delete_access_product_data',
                user_id: club_access_off_data.user_id,
                order_id: club_access_off_data.order_id,
                product_id: club_access_off_data.product_id,
                by_client: 1
            },
            success: function(data) {
                if (data.success === true) {
                    if (club_access_off_data.cancelaccess_url != '') {
                        window.location = club_access_off_data.cancelaccess_url;
                    }
                    else {
                        alert('Доступ отменен');
                    }
                }
                else {
                    jQuery(el).removeClass('button-disabled');
                    alert('Не удалось отменить доступ');
                }
            }
        });
    }
}

jQuery(document).ready(function($) {
    $('.club_access_turn_off a').click(function() {
        $(this).attr('href', 'javascript:void(0);');
        deleteClubAccess(this);
        return false;
    });
});
