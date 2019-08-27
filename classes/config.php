<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 21.08.2015
 * Time: 20:31
 */

class ss_club_config {

    function __construct() {

    }

    /**
     * Раздел Клуб
     */
    public function admin_config_menu() {
        if (apply_filters('ss_access_is_club_available', '')) {
            add_options_page('Клуб', 'Клуб', 'manage_options', 'ss-club-config', array($this, 'page'));
        }
    }

    /**
     * Действия при создании блога
     *
     * @param $blog_id
     * @param $user_id
     * @param $domain
     * @param $path
     * @param $site_id
     * @param $meta
     */
    public function new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {
        switch_to_blog($blog_id);

        update_option('club_unregister_access', 2);
        update_option('club_notaccess_display', 1);
        update_option('club_unregister_message', 'Только участники сообщества имеют доступ в Клуб!');
        update_option('club_offer_message', 'Для доступа к этому материалу необходимо приобрести доступ.');
        update_option('club_display_full_image', 0);

        restore_current_blog();
    }

    /**
     * Вывод страницы
     */
    public function page() {
        $club_unregister_access = get_option('club_unregister_access', 1);
        $club_unregister_access = empty($club_unregister_access) ? 1 : $club_unregister_access;

        $club_notaccess_display = get_option('club_notaccess_display', 1);
        $club_notaccess_display = empty($club_notaccess_display) ? 1 : $club_notaccess_display;

        $club_unregister_message = get_option('club_unregister_message', '');
        $club_unregister_message = empty($club_unregister_message)
            ? 'Только участники сообщества имеют доступ в Клуб!'
            : $club_unregister_message;

        $club_offer_message = get_option('club_offer_message', '');
        $club_offer_message = empty($club_offer_message)
            ? 'Для доступа к этому материалу необходимо приобрести доступ.'
            : $club_offer_message;

        $club_display_full_image = get_option('club_display_full_image', 0);

        include_once (SSCLUB_DIR . '/templates/config.php');
    }

    /**
     * Добавляем список опций
     *
     * @param $options
     * @return mixed
     */
    public function whitelist_options($options) {
        $options['club-config'] = array(
            "club_unregister_access",
            "club_notaccess_display",
            "club_unregister_message",
            "club_offer_message",
            "club_display_full_image",
        );
        return $options;
    }

    /**
     * Инициализация
     */
    public function init() {
        // Если обновили настройки, то сбросим кэш
        if (
            function_exists('w3tc_flush_posts')
            && isset($_GET['page'])
            && 'ss-club-config' == $_GET['page']
            && isset($_GET['settings-updated'])
        ) {
            w3tc_flush_posts();
        }
    }

}
