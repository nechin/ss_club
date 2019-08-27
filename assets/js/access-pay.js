function payClubAccess(el) {
    if (jQuery(el).hasClass('button-disabled')) {
        return false;
    }

    if (confirm('Доступ будет оплачен на месяц вперед. Продолжить?')) {
        jQuery(el).addClass('button-disabled');

        jQuery.ajax({
            url: club_access_pay_data.url + 'ajax.php',
            dataType: 'json',
            type: 'POST',
            data: {
                controller: 'clients',
                act: 'pay_access_product_data',
                key: club_access_pay_data.key
            },
            success: function(data) {
                if (data.success === true) {

                    if (data.result === true) {

                        if (club_access_pay_data.success_url != '') {
                            window.location = club_access_pay_data.success_url;
                        }
                        else {
                            alert('Доступ оплачен');
                        }
                    }
                    else {
                        if (club_access_pay_data.wait_success_url != '') {
                            window.location = club_access_pay_data.wait_success_url;
                        }
                        else {
                            alert('Запрос на оплату отправлен и в течение минуты будет выполнен');
                        }
                    }
                }
                else {
                    // Переход на страницу оплаты
                    if (data.result !== false && data.result != '') {
                        window.location = data.result;
                    } // Сообщение об ошибке платежа
                    else if (club_access_pay_data.fail_url != '') {
                        window.location = club_access_pay_data.fail_url;
                    }
                    else {
                        jQuery(el).removeClass('button-disabled');
                        alert('Не удалось оплатить доступ');
                    }
                }
            }
        });
    }
}

jQuery(document).ready(function($) {
    $('.club_access_payment a').click(function() {
        $(this).attr('href', 'javascript:void(0);');
        payClubAccess(this);
        return false;
    });
});
