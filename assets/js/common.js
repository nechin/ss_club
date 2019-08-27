jQuery(document).ready(function($) {
    // Доступ к разделу клуба и его категориям
    if (club_data.unregister_access == 1) {
        var el_page = $("a[href='" + club_data.site_url + "/club/'],a[href^='" + club_data.site_url + "/club_category/']");
        el_page.attr('href', 'javascript:void(0);').on('click', function() {
            var title = "";
            tb_show(title, '#TB_inline?&height=400&width=500&inlineId=club-unregister-access-modal-dialog', 'null');
            $('#TB_ajaxContent').css('height', 'auto').css('box-sizing', 'content-box');
        });
    }

    // Записи, для которых нужно купить товар
    var el = $("a[href^='" + club_data.site_url + "/club/?need_access=']");
    el.each(function() {
        var href = $(this).attr('href');
        var product_ids = href.replace(club_data.site_url + "/club/?need_access=", "");

        $(this).attr('href', 'javascript:void(0);');
        $(this).attr('accessid', product_ids);

        $(this).on('click', function() {
            club_need_access_dialog($(this).attr('accessid'));
        });
    });

    // Записи, для которых нужно быть авторизованным
    el = $("a[href^='" + club_data.site_url + "/club/?need_register=']");
    el.each(function() {
        var href = $(this).attr('href');
        var product_id = href.replace(club_data.site_url + "/club/?need_register=", "");

        $(this).attr('href', 'javascript:void(0);');
        $(this).attr('postid', product_id);

        $(this).on('click', function() {
            club_need_register_dialog($(this).attr('postid'));
        });
    });

    if ($('#show_access_dialog_type').length > 0) {
        var access_dialog_type = $('#show_access_dialog_type').val();
        var access_dialog_post_id = $('#show_access_dialog_post_id').val();

        if (access_dialog_type == 2) {
            setTimeout(function() {
                club_need_register_dialog(access_dialog_post_id);
            }, 100);
        }
        else if (access_dialog_type == 3) {
            setTimeout(function() {
                club_need_access_dialog(access_dialog_post_id);
            }, 100);
        }
    }
});

function club_need_access_dialog(accessid) {
    var title = "";
    tb_show(title, '#TB_inline?&height=400&width=500&inlineId=club-need-access-modal-dialog', 'null');
    jQuery('#TB_ajaxContent').css('height', 'auto').css('box-sizing', 'content-box');
    jQuery('#TB_closeWindowButton .screen-reader-text').hide();

    if (jQuery('#club-need-access-data-' + accessid).length == 0) {
        jQuery('#TB_ajaxContent #club-need-access-modal-content')
            .html('<img src="' + club_data.ssclub_url + 'assets/img/ajax-loader.gif" />');

        jQuery.getJSON(
            club_data.admin_ajax_url,
            {
                action: 'ss_club_access_post',
                product_ids: accessid
            },
            function (data) {
                jQuery('body').append('<div style="display:none">' + data.html + '</div>');
                jQuery('#TB_ajaxContent #club-need-access-modal-content').html(data.html);
            }
        );
    }
    else {
        var html = jQuery('#club-need-access-data-' + accessid).html();
        jQuery('#TB_ajaxContent #club-need-access-modal-content').html(html);
    }
}

function club_need_register_dialog(postid) {
    jQuery('#club-unregister-postid').val(postid);

    var title = "";
    tb_show(title, '#TB_inline?&height=400&width=500&inlineId=club-unregister-access-modal-dialog', 'null');
    jQuery('#TB_ajaxContent').css('height', 'auto').css('box-sizing', 'content-box');
    jQuery('#TB_closeWindowButton .screen-reader-text').hide();
}

function club_login() {
    var postid = jQuery('#club-unregister-postid').val();
    var url = club_data.login_url;
    if (url != '') {
        document.location = url + "?redirectid=" + postid;
    }
    else {
        document.location = club_data.site_url + "/my-account/?redirectid=" + postid;
    }
}

function club_register() {
    var url = club_data.register_url;
    if (url != '') {
        document.location = url;
    }
    else {
        document.location = club_data.site_url + "/registration/";
    }
}

function club_buy(url) {
    document.location = url;
}
