<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 21.08.2015
 * Time: 20:26
 */

class ss_club_blog extends ss_club_base {

    function __construct() {
        parent::__construct();
    }

    /**
     * Инициализация
     */
    public function init() {
        define('SSCLUB_URL', plugin_dir_url(SSCLUB_PATH));

        // Обработка при инициализации
        if (!wp_next_scheduled('ss_club_hourly_event')) {
            wp_schedule_event(time(), 'hourly', 'ss_club_hourly_event');
        }
    }

    /**
     * Добавляет категорию
     */
    public function register_taxonomies() {
        if (apply_filters('ss_access_is_club_available', '')) {
            register_taxonomy('club_category', 'club_post', array(
                'labels' => array(
                    'name' => 'Рубрики клуба',
                    'singular_name' => 'Рубрика клуба',
                ),
                'hierarchical' => true,
                'query_var' => true,
                'rewrite' => true,
                'public' => true,
                'show_ui' => true,
                'show_admin_column' => true,
            ));
        }
    }

    /**
     * Создаёт новый тип записи
     */
    public function register_post_types() {
        if (
            post_type_exists('club_post')
            || !apply_filters('ss_access_is_club_available', '')
        ) {
            return;
        }

        register_post_type('club_post', array(
            'labels' => array(
                'name' 				=> 'Записи клуба',
                'singular_name' 	=> 'Запись клуба',
                'menu_name'			=> 'Клуб',
                'add_new'           => 'Добавить запись',
                'add_new_item'      => 'Добавить запись'
            ),
            'description' 			=> 'Записи клуба',
            'public' 				=> true,
            'publicly_queryable' 	=> true,
            'show_ui' 				=> true,
            'show_in_menu' 			=> true,
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'hierarchical'          => false,
            'taxonomies'            => array('club_category'),
            'rewrite'               => true,
            'query_var'             => false,
            'delete_with_user'      => true,
            'register_meta_box_cb'  => array($this, 'add_meta_boxes'),
            'supports'              => array(
                'title', 'thumbnail'
            ),
        ));
    }

    /**
     * Новые метабоксы
     */
    function add_meta_boxes() {
        add_meta_box('short_description', 'Краткое описание для посетителей, не имеющих доступа',
            array($this, 'short_description_callback'), 'club_post');
        add_meta_box('open_access', 'Открыть доступ', array($this, 'open_access_callback'), 'club_post', 'side');
    }

    /**
     * Коллбэк метабокса
     */
    function short_description_callback() {
        // Используем nonce для верификации
        wp_nonce_field(plugin_basename(SSCLUB_PATH), 'club_noncename');

        // Поля формы для введения данных
        $post_id = get_the_ID();
        $value = get_post_meta($post_id, 'short_description', true);
        echo '<textarea style="width:100%" id="short_description" name="short_description">' . $value . '</textarea>';
    }

    /**
     * Коллбэк метабокса
     */
    function open_access_callback() {
        // Используем nonce для верификации
        wp_nonce_field(plugin_basename(SSCLUB_PATH), 'club_noncename');

        // Поля формы для введения данных
        $post_id = get_the_ID();
        $value = get_post_meta($post_id, 'open_access', true);
        $value = empty($value) ? 1 : $value;

        echo '<label><input type="radio" name="open_access" value="1" ' . checked(1, $value, false) . '> Всем посетителям сайта</label><br>';
        echo '<label><input type="radio" name="open_access" value="2" ' . checked(2, $value, false) . '> Зарегистрированным посетителям</label><br>';
        echo '<label><input type="radio" name="open_access" value="3" ' . checked(3, $value, false) . '> Клиентам имеющим доступ:</label><br>';

        echo '<div id="access_product_list"' . ($value == 3 ? '' : ' style="display: none"') . '>';

        $products = $this->get_access_type_products();
        if (!empty($products) && !empty($products->posts)) {
            $values = get_post_meta($post_id, 'access_product');
            foreach ($products->posts as $product) {
                echo '<label style="padding-left:15px"><input type="checkbox"';
                echo ' name="access_product[' . $product->ID . ']" value="' . $product->ID . '" ';
                echo (isset($values[0][$product->ID]) ? 'checked="checked"' : '') . '> ' . $product->post_title . '</label><br>';
            }
        }
        else {
            $a_tag = '<a href="' . admin_url('edit.php?post_type=product') . '" title="Добавить товары типа доступ">Добавить</a>';
            $content = 'Нет товаров типа доступ (' . $a_tag . ')';
            echo '<span style="font-size: 12px; padding-left: 15px; color: grey;">' . $content . '</span>';
        }

        echo '</div>';

        $value = get_post_meta($post_id, 'close_access_list', true);
        echo '<div style="padding-top: 10px">';
        echo '<label><input type="checkbox" name="close_access_list" value="1" ';
        echo ($value ? 'checked="checked"' : '') . '> Скрыть из списка записей клуба</label>';
        echo '</div>';
    }

    /**
     * Сохраняет данные страницы
     *
     * @param $post_id
     */
    function save_postdata($post_id) {
        // Проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
        if (
            !isset($_POST['club_noncename'])
            || !wp_verify_nonce($_POST['club_noncename'], plugin_basename(SSCLUB_PATH))
        ) {
            return $post_id;
        }

        // Проверяем, если это автосохранение ничего не делаем с данными нашей формы.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Проверяем разрешено ли пользователю указывать эти данные
        if (
            'club_post' == $_POST['post_type']
            && !current_user_can('edit_page', $post_id)
        ) {
            return $post_id;
        }
        elseif (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        // Убедимся что поле установлено.
        if (!isset($_POST['open_access'])) {
            return;
        }

        // Убедимся что поле установлено.
        if ($_POST['open_access'] == 3 && empty($_POST['access_product'])) {
            return;
        }

        // Все ОК. Теперь, нужно найти и сохранить данные
        // Очищаем значение поля input.
        $short_description = sanitize_text_field($_POST['short_description']);
        $open_access = sanitize_text_field($_POST['open_access']);
        $access_product = isset($_POST['access_product']) ? $_POST['access_product'] : array();
        $close_access_list = !empty($_POST['close_access_list']) ? 1 : 0;

        // Обновляем данные в базе данных.
        update_post_meta($post_id, 'short_description', $short_description);
        update_post_meta($post_id, 'open_access', $open_access);
        update_post_meta($post_id, 'access_product', $access_product);
        update_post_meta($post_id, 'close_access_list', $close_access_list);
    }

    /**
     * Подключение стилей и скриптов в админке
     */
    public function admin_enqueue_scripts() {
        global $current_screen;

        if ($current_screen->post_type == 'club_post') {
            wp_enqueue_script('club-js', SSCLUB_URL . 'assets/js/create-club-post.js');
        }

        wp_enqueue_style('club-css', SSCLUB_URL . 'assets/css/style.css');
    }

    /**
     * Подключение стилей и скриптов во фронтэнде
     */
    public function wp_enqueue_scripts() {
        if (!is_editor_page()) {
            wp_enqueue_script('thickbox');
            wp_enqueue_style('thickbox');

            wp_enqueue_script('club-js', SSCLUB_URL . 'assets/js/common.js');
            // Незарегистрированных пользователей не пускаем к закрытым рубрикам клуба
            $unregister_access = (!is_user_logged_in() && 1 == get_option('club_unregister_access', 2) ? 1 : 0);
            $data = array(
                'unregister_access' => $unregister_access,
                'site_url' => site_url(),
                'login_url' => esc_url(get_permalink(wc_get_page_id('myaccount'))),
                'register_url' => site_url() . '/registration/',
                'admin_ajax_url' => admin_url('admin-ajax.php'),
                'ssclub_url' => SSCLUB_URL,
            );
            wp_localize_script('club-js', 'club_data', $data);

            wp_enqueue_style('club-css', SSCLUB_URL . 'assets/css/style.css');
        }
    }

    /**
     * Создание страницы Отмена доступа
     *
     * @param $user_id
     * @return int|WP_Error
     */
    private function create_club_access_off_page($user_id) {
        $page = get_page_by_path('club_access_off');
        if (!$page) {
            $post = array(
                'post_title' => 'Отмена доступа',
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => $user_id,
                'post_name' => 'club_access_off',
                'post_type' => 'page',
                'comment_status' => 'closed'
            );
            $post_id = wp_insert_post($post);

            $key = '_lime-download-page';

            update_post_meta($post_id, "tve_landing_page", 'lime-download-page');
            update_post_meta($post_id, "thrive_icon_pack{$key}", 0);
            update_post_meta($post_id, "thrive_tcb_post_fonts{$key}", array());

            $tve_content = '<div class="tve_lp_header tve_empty_dropzone tve_content_width">
</div>
<div class="tve_lp_content tve_editor_main_content tve_empty_dropzone tve_content_width">
<h1 style="color: rgb(21, 21, 21); font-size: 48px; margin-top: 0px; margin-bottom: 45px;" class="tve_p_center"><span class="tve_custom_font_size  rft" style="font-size: 45px;">Отмена доступа</span></h1><h1 style="color: rgb(21, 21, 21); font-size: 48px; margin-top: 0px; margin-bottom: 45px;" class="tve_p_center">"{ACCESS_NAME}"!</h1>
<p style="font-size: 18px; margin-top: 0px; margin-bottom: 45px;" class="tve_p_center"><font color="#353535">Нажмите кнопку "ОТМЕНИТЬ", чтобы отменить выбранный доступ</font></p>
<div data-tve-style="1" class="thrv_wrapper thrv_button_shortcode tve_centerBtn thrv_button_shortcode_sse club_access_turn_off">
<div style="margin-left: 0px ! important; margin-top: 0px ! important;" class="tve_btn tve_btn8 tve_nb tve_bigBtn tve_purple">
<a style="font-size: 22px; line-height: 22px; padding: 24px 36px ! important;" data-sse-id="1_sse_1445333494693" data-post-id="3083" target="_self" data-redirect="" data-discount="0" data-product="0" data-kind="1" class="tve_btnLink" href="">
<span class="tve_left tve_btn_im">
<i></i>
</span>
<span class="tve_btn_txt">ОТМЕНИТЬ</span>
</a>
</div>
</div>
</div>
<div class="tve_lp_footer tve_empty_dropzone tve_content_width">
</div>';
            update_post_meta($post_id, "tve_content_before_more{$key}", $tve_content);
            update_post_meta($post_id, "tve_content_more_found{$key}", '');
            update_post_meta($post_id, "tve_custom_css{$key}", '');

            update_post_meta($post_id, "tve_globals{$key}", array('e' => '1'));
            update_post_meta($post_id, "tve_has_masonry{$key}", 0);
            update_post_meta($post_id, "tve_page_events{$key}", array());

            $tve_global_scripts = array('head' => '', 'footer' => '');
            update_post_meta($post_id, "tve_global_scripts", $tve_global_scripts);

            update_post_meta($post_id, "tve_save_post{$key}", $tve_content);

            update_post_meta($post_id, "tve_updated_post{$key}", $tve_content);
            update_post_meta($post_id, "tve_user_custom_css{$key}", '');
        }
        else {
            $post_id = $page->ID;
        }

        return $post_id;
    }

    /**
     * Создание страницы Оплата доступа
     *
     * @param $user_id
     * @return int|WP_Error
     */
    private function create_club_access_pay_page($user_id) {
        $page = get_page_by_path('club_access_pay');
        if (!$page) {
            $post = array(
                'post_title' => 'Оплата доступа',
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => $user_id,
                'post_name' => 'club_access_pay',
                'post_type' => 'page',
                'comment_status' => 'closed'
            );
            $post_id = wp_insert_post($post);

            $key = '_lime-download-page';

            update_post_meta($post_id, "tve_landing_page", 'lime-download-page');
            update_post_meta($post_id, "thrive_icon_pack{$key}", 0);
            update_post_meta($post_id, "thrive_tcb_post_fonts{$key}", array());

            $tve_content = '<div class="tve_lp_header tve_empty_dropzone tve_content_width">
</div>
<div class="tve_lp_content tve_editor_main_content tve_empty_dropzone tve_content_width">
<h1 style="color: rgb(21, 21, 21); font-size: 48px; margin-top: 0; margin-bottom: 45px;" class="tve_p_center"><span class="tve_custom_font_size  rft" style="font-size: 45px;">Оплата доступа</span></h1><h1 style="color: rgb(21, 21, 21); font-size: 48px; margin-top: 0; margin-bottom: 45px;" class="tve_p_center">"{ACCESS_NAME}"!</h1>
<p style="font-size: 18px; margin-top: 0; margin-bottom: 45px;" class="tve_p_center"><font color="#353535">Нажмите кнопку "ОПЛАТИТЬ", чтобы оплатить выбранный доступ</font></p>
<div data-tve-style="1" class="thrv_wrapper thrv_button_shortcode tve_centerBtn thrv_button_shortcode_sse club_access_payment">
<div style="margin-left: 0 ! important; margin-top: 0 ! important;" class="tve_btn tve_btn8 tve_nb tve_bigBtn tve_purple">
<a style="font-size: 22px; line-height: 22px; padding: 24px 36px ! important;" data-sse-id="1_sse_1445333494693" data-post-id="3083" target="_self" data-redirect="" data-discount="0" data-product="0" data-kind="1" class="tve_btnLink" href="">
<span class="tve_left tve_btn_im">
<i></i>
</span>
<span class="tve_btn_txt">ОПЛАТИТЬ</span>
</a>
</div>
</div>
</div>
<div class="tve_lp_footer tve_empty_dropzone tve_content_width">
</div>';
            update_post_meta($post_id, "tve_content_before_more{$key}", $tve_content);
            update_post_meta($post_id, "tve_content_more_found{$key}", '');
            update_post_meta($post_id, "tve_custom_css{$key}", '');

            update_post_meta($post_id, "tve_globals{$key}", array('e' => '1'));
            update_post_meta($post_id, "tve_has_masonry{$key}", 0);
            update_post_meta($post_id, "tve_page_events{$key}", array());

            $tve_global_scripts = array('head' => '', 'footer' => '');
            update_post_meta($post_id, "tve_global_scripts", $tve_global_scripts);

            update_post_meta($post_id, "tve_save_post{$key}", $tve_content);

            update_post_meta($post_id, "tve_updated_post{$key}", $tve_content);
            update_post_meta($post_id, "tve_user_custom_css{$key}", '');
        }
        else {
            $post_id = $page->ID;
        }

        return $post_id;
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

        // Страница клуба
        $post = array(
            'post_title'     => 'Клуб',
            'post_content'   => '[ss_club_blogs]',
            'post_status'    => 'publish',
            'post_author'    => get_current_user_id(),
            'post_name'      => 'club',
            'post_type'      => 'page',
            'comment_status' => 'closed'
        );
        $post_id = wp_insert_post($post);
        update_post_meta($post_id, "_thrive_meta_show_post_title", 0);

        // Создание страницы Отмена доступа
        $access_off_post_id = $this->create_club_access_off_page($user_id);
        _controller("Items")->add_post_id_to_hide_list($access_off_post_id);

        // Создание страницы Оплата доступа
        $access_off_post_id = $this->create_club_access_pay_page($user_id);
        _controller("Items")->add_post_id_to_hide_list($access_off_post_id);

        // Создаёт меню Клуб
        $menu_name = 'Главное меню';
        $menu_id = wp_get_nav_menu_object($menu_name);

        if (!$menu_id) {
            $menu_id = wp_create_nav_menu($menu_name);
        }
        else {
            $menu_id = $menu_id->term_id;
        }

        if ($menu_id && !apply_filters('ssc_is_has_menu_item', $post_id)) {
            $item_data = array(
                'menu-item-object-id' => $post_id,
                'menu-item-parent-id' => 0,
                'menu-item-position' => 4,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish'
            );
            wp_update_nav_menu_item($menu_id, 0, $item_data);
        }

        restore_current_blog();
    }

    /**
     * Заменяет опции
     *
     * @param $options
     * @return mixed
     */
    public function thrive_theme_options($options) {
        if (!empty($options)) {
            global $post;

            if (
                $post
                && isset($post->post_name)
                && ($post->post_name == 'club' || $post->post_type == 'club_post')
            ) {
                $options['meta_author_name'] = 0;
                $options['meta_post_date'] = 0;
                $options['meta_post_category'] = 0;
                $options['meta_post_tags'] = 0;
                $options['blog_layout'] = 'full_width';
                $options['blog_post_layout'] = 'full_width';
                $options['sidebar_alignement'] = '';

                if (!is_single()) {
                    $options['featured_image_style'] = 'thumbnail';
                }
                else {
                    if (!get_option('club_display_full_image', 0)) {
                        $options['featured_image_style'] = 'thumbnail';
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Возвращает превью содержимого поста
     *
     * @param $content
     * @return mixed
     */
    public function post_content($content) {
        global $post;

        static $club_dialog_content = false;

        if (
            $post
            && isset($post->post_type)
            && $post->post_type == 'club_post'
            && !is_single()
        ) {
            if ($this->get_access_user_post($post->ID) != 1) {
                $content = get_post_meta($post->ID, 'short_description', true);
            }

            if (!is_admin() && !$club_dialog_content) {

                if (
                    isset($_GET['show_access_dialog'])
                    && $_GET['show_access_dialog']
                    && ($_GET['show_access_dialog'] == 2
                        || $_GET['show_access_dialog'] == 3)
                    && $_GET['pid']
                ) {
                    $content .= '<input type="hidden" id="show_access_dialog_type" value="' . $_GET['show_access_dialog'] . '">';
                    $content .= '<input type="hidden" id="show_access_dialog_post_id" value="' . $_GET['pid'] . '">';
                }

                $my_theme = wp_get_theme();
                $class = ' cnt"';
                if ($my_theme->get('Name') == 'Pressive') {
                    $class = '" style="font-size:16px;font-family:Raleway,sans-serif;font-weight:500;"';
                }

                $content .= '<div id="club-unregister-access-modal-dialog" title="" style="display:none;">
    <input type="hidden" id="club-unregister-postid" value="">
    <div class="club_div_content">
        <div class="club_img_div">
            <img src="' . SSCLUB_URL . 'assets/img/lock_bw.png' . '" />
        </div>
        <p class="club_text_lock">'
            . get_option('club_unregister_message', 'Только участники сообщества имеют доступ в Клуб!') .
        '</p>
    </div>
    <div class="club_div_content woocommerce' . $class . '>
        <div class="club_button_div">
            <span class="club_underbutton_text">Вы уже участник?</span><br><br>
            <button class="button button-primary" onclick="club_login();">Войти на сайт</button>
        </div>
        <div class="club_button_div">
            <span class="club_underbutton_text">Ещё не участник?</span><br><br>
            <button class="button button-primary" onclick="club_register();">Регистрация</button>
        </div>
    </div>
</div>';
                $content .= '<div id="club-need-access-modal-dialog" title="" style="display:none;">
    <div class="club_div_content">
        <div class="club_img_div">
            <img src="' . SSCLUB_URL . 'assets/img/lock_bw.png' . '" />
        </div>
        <p class="club_text_lock">'
            . get_option('club_offer_message', 'Для доступа к этому материалу необходимо приобрести доступ.') .
        '</p>
    </div>
    <div id="club-need-access-modal-content" class="club_div_content woocommerce">
        <img src="' . SSCLUB_URL . 'assets/img/ajax-loader.gif' . '" />
    </div>
</div>';
                $club_dialog_content = true;
            }
        }

        return $content;
    }

    /**
     * Проверка прав доступа к разделу записей
     */
    private function check_access_club() {
        global $post;

        if (
            $post
            && ($post->post_name == 'club' || $post->post_type == 'club_post')
            && !is_user_logged_in()
            && 1 == get_option('club_unregister_access', 2)
        ) {
            // Незарегистрированных пользователей не пускаем к рубрикам клуба
            $redirect_to = get_page_link(get_option('woocommerce_myaccount_page_id'));
            wp_redirect($redirect_to);
        }
    }

    /**
     * Проверка прав доступа к записи клуба
     */
    private function check_access_club_post() {
        global $post;

        if (
            $post
            && $post->post_type == 'club_post'
            && is_single()
            && !is_admin()
        ) {
            $access = $this->get_access_user_post($post->ID);
            if ($access != 1) {
                // Если запись недоступна, то делаем редирект
                $club_post = get_page_by_path('club');
                if ($club_post) {
                    $pid = $post->ID;

                    if ($access == 3) {
                        $post_ids = $this->get_access_type_product_need_buy($post->ID);
                        $pid = implode(',', $post_ids);
                    }
                    remove_filter('post_type_link', array($this, 'post_type_link'));
                    $redirect_to = get_page_link($club_post) . '?show_access_dialog=' . $access . '&pid=' . $pid;
                }
                else {
                    $redirect_to = get_page_link(get_option('woocommerce_myaccount_page_id'));
                }
                wp_redirect($redirect_to);
            }
        }
    }

    /**
     * Ранняя обработка событий
     */
    public function wp() {
        if (!is_404() && !is_admin()) {
            $this->check_access_club();
            $this->check_access_club_post();
        }
    }

    /**
     * Изменяем запрос, если нужно
     *
     * @param $query
     */
    function pre_get_posts($query) {
        // Выходим, если это админ-панель или не тот список
        if (
            !is_admin()
            && !$query->is_404()
            && isset($query->query['club_category'])
        ) {
            $this->check_access_club();

            // Если записи скрыты, то юзеру нужно показать только общие записи и записи, доступ к которым он купил
            if (1 == get_option('club_notaccess_display', 1)) {
                // Если юзер не авторизован, то не показываем записи с доступом
                // "Зарегистрированным посетителям" и "Клиентам имеющим доступ:"
                if (!is_user_logged_in()) {
                    $query->set('meta_query', array(
                        array(
                            'key'     => 'open_access',
                            'value'   => 1,
                            'compare' => '==',
                        ),
                        array(
                            'key' => 'close_access_list',
                            'value' => 0,
                            'compare' => '==',
                        )
                    ));
                }
                else {
                    // Иначе не показываем только записи с доступом "Клиентам имеющим доступ:"
                    // кроме тех, доступ к которым он купил
                    $exclude_ids = $this->get_access_denied_post_ids();
                    if (!empty($exclude_ids)) {
                        $ids = $query->get('post__not_in');
                        $query->set('post__not_in', array_merge($ids, $exclude_ids));
                    }

                    $query->set('meta_query', array(
                        array(
                            'key' => 'close_access_list',
                            'value' => 0,
                            'compare' => '==',
                        )
                    ));
                }
            }
            else {
                $query->set('meta_query', array(
                    array(
                        'key' => 'close_access_list',
                        'value' => 0,
                        'compare' => '==',
                    )
                ));
            }
        }
    }

    /**
     * Изменяем запрос, если нужно
     *
     * @param $query
     */
    function pre_get_posts_later($query) {
        // Если у клиента сейчас действует доступ из списка товаров, то скрываем этот товар из этого списка
        if (
            !is_super_admin()
            && !is_admin()
            && !$query->is_404()
            && isset($query->query['post_type'])
            && $query->query['post_type'] == 'product'
            && is_shop()
        ) {
            $post = '';
            $user_id = get_current_user_id();

            if ($user_id && get_current_blog_id() == SITE_ID_CURRENT_SITE) {
                $access_data = get_user_meta($user_id, 'current_access');
                $access_name = isset($access_data[0]['name']) ? $access_data[0]['name'] : '';
                $post = get_page_by_path($access_name, OBJECT, 'product');
            }

            // Если нужно выводить только товары типа доступ
            if (isset($_GET['access'])) {
                $access_ids = $this->get_access_type_product_ids_by_direct_query();

                if ($post) {
                    $access_ids = array_diff($access_ids, array($post->ID));
                }

                $query->set('post__in', $access_ids);
            }
            else {
                if ($post) {
                    $ids = $query->get('post__not_in');
                    $query->set('post__not_in', array_merge($ids, array($post->ID)));
                }
            }
        }
    }

    /**
     * Заменяем ссылку на запись в зависимости от доступа
     *
     * @param $url
     * @param $post
     * @param $leavename
     * @param $sample
     * @return string
     */
    public function post_type_link($url, $post, $leavename, $sample) {

        if (
            $post
            && $post->post_type == 'club_post'
            && !is_admin()
            && !isset($_GET['tve'])
        ) {
            $access = $this->get_access_user_post($post->ID);

            if ($access == 2) {
                return site_url() . '/club/?need_register=' . $post->ID;
            }
            else if ($access == 3) {
                $product_ids = $this->get_access_type_product_need_buy($post->ID);
                return site_url() . '/club/?need_access=' . implode(',', $product_ids);
            }
        }

        return $url;
    }

    /**
     * Данные формы доступа с кнопками покупки
     */
    public function access_post_form() {
        $html = '';

        if (isset($_REQUEST['product_ids'])) {
            $data = $_REQUEST['product_ids'];
            $product_ids = explode(',', $data);

            if (count($product_ids) > 1) {
                global $wpdb;

                $posts = $wpdb->get_results("
                    SELECT p.ID, p.post_title
                    FROM {$wpdb->prefix}posts p
                    JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_regular_price'
                    WHERE p.ID IN ($data)
                    ORDER BY CAST(pm.meta_value AS unsigned)
                ");
                foreach ($posts as $post) {
                    if (!empty($html)) {
                        $html .= '<div>или</div>';
                    }
                    $html .= '<div id="club-need-access-data-' . $data . '">
    <div class="club_buy_button_div">
        <button class="button button-primary" onclick="club_buy(\'' . get_permalink($post->ID) . '\');">' . $post->post_title . '</button>
    </div>
</div>';
                }
            }
            else {
                $post = get_post($product_ids[0]);
                $html .= '<div id="club-need-access-data-' . $data . '">
    <div class="club_buy_button_div">
        <button class="button button-primary" onclick="club_buy(\'' . get_permalink($product_ids[0]) . '\');">' . $post->post_title . '</button>
    </div>
</div>';
            }
        }

        exit(json_encode(array(
            'html' => $html
        )));
    }

    /**
     * Хук tcb_enqueue_resources
     *
     * @param $enqueue_tcb_resources
     * @return bool
     */
    public function tcb_enqueue_resources($enqueue_tcb_resources) {
        global $post;

        if (
            $post
            && ($post->post_name == 'club'
                || $post->post_name == 'blog'
                || $post->post_type == 'club_post'
            )
        ) {
            return true;
        }

        return $enqueue_tcb_resources;
    }

    /**
     * Проверяет наличие товаров типа доступ и задаёт начальные значения дат
     *
     * @param $order_id
     */
    public function woocommerce_order_status_completed($order_id) {
        $order = new WC_Order($order_id);
        $user_id = $this->get_order_user_id($order);

        if ($user_id) {
            $order_items = $order->get_items();

            if ($order_items) {
                $product_ids = array();
                $order_item_ids = array();
                foreach ($order_items as $order_item) {
                    $order_item_ids[] = $order_item['product_id'];
                }

                if (!empty($order_item_ids)) {
                    $product_ids = $this->get_access_type_product_ids();
                    // Получим все товары типа доступ, которые есть в заказе
                    $product_ids = array_intersect($product_ids, $order_item_ids);
                }

                if (!empty($product_ids)) {
                    foreach ($product_ids as $product_id) {
                        $key = $this->get_key($user_id, $order_id, $product_id);

                        $start_datetime = $this->current_time;

                        // Если куплен товар типа доступ к системе
                        if ($this->is_access_product_by_id($product_id)) {
                            // Если у клиента уже есть активный доступ, то новый ставим в очередь
                            $access_data = get_user_meta($user_id, 'current_access');
                            $access_name = isset($access_data[0]['name']) ? $access_data[0]['name'] : '';
                            if ($this->is_access_product_by_name($access_name)) {
                                // Получим товары типа доступ, купленные юзером
                                $data = $this->get_user_access_type_data($user_id, 0, 0, true);

                                if ($data) {
                                    $end_date_access = 0;
                                    foreach ($data as $_order_id => $products) {
                                        foreach ($products as $_product_id) {

                                            if (isset($_product_id) && is_numeric($_product_id)) {
                                                // Ключ для ранее купленных товаров
                                                $_key = $this->get_key($user_id, $_order_id, $_product_id);

                                                if ($_key != $key) {
                                                    $_end_date_access = get_option($_key . 'club_access_end_date');

                                                    if ($_end_date_access > $end_date_access) {
                                                        $end_date_access = $_end_date_access;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if ($end_date_access) {
                                        $start_datetime = $end_date_access + 1;
                                    }
                                }
                            }
                            else {  // Иначе запоминаем купленный доступ
                                $post = get_post($product_id);
                                if ($post) {
                                    $access_name = trim($post->post_name);

                                    if ($this->is_access_product_by_name($access_name)) {
                                        update_user_meta($user_id, 'current_access', array(
                                            'name' => $access_name, 'start' => $start_datetime)
                                        );

                                        // Проверяем письма в очереди для каждой воронки
                                        _controller("Items")->change_sent_time_in_queue_for_funnels($user_id);
                                    }
                                }
                            }
                        }
                        else {
                            // Получим товары типа доступ к клубу, купленные юзером
                            $data = $this->get_user_access_type_data($user_id, 0, $product_id, true);

                            if ($data) {
                                $end_date_access = 0;
                                foreach ($data as $_order_id => $products) {
                                    foreach ($products as $_product_id) {

                                        if (isset($_product_id) && is_numeric($_product_id)) {
                                            // Ключ для ранее купленных товаров
                                            $_key = $this->get_key($user_id, $_order_id, $_product_id);

                                            if ($_key != $key) {
                                                $_end_date_access = get_option($_key . 'club_access_end_date');

                                                if ($_end_date_access > $end_date_access) {
                                                    $end_date_access = $_end_date_access;
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($end_date_access) {
                                    $start_datetime = $end_date_access + 1;
                                }
                            }
                        }

                        update_option($key . 'club_access_start_date', $start_datetime);

                        $end_datetime = $this->get_end_date_next_month($start_datetime);
                        update_option($key . 'club_access_end_date', $end_datetime);

                        update_option($key . 'club_access_status', 1);

                        $next_payment = $order->get_item_subtotal($order_item, false, true, true); // сумма с учетом купонов
                        update_option($key . 'club_next_payment', $next_payment);

                        // Отправим уведомление о подключении доступа
                        ss_club()->email->email_access_on($user_id, $order_id, $product_id);

                        // Удаление кэша сайта пользователя
                        $this->remove_site_cache($user_id);
                    }

                    // Дата установки дат для доступа
                    update_option($order_id . 'club_access_completed', $this->current_time);

                    // Удаляем данные о выбранном доступе, который нужно оплатить
                    delete_user_meta($user_id, 'need_pay_access_product_id');
                }
            }
        }
    }

    /**
     * Обработка контента
     *
     * @param $content
     * @return mixed
     */
    public function the_content($content) {
        global $post;

        if (
            !$post
            || ($post->post_name != 'club_access_off' && $post->post_name != 'club_access_pay')
            || empty($content)
            || isset($_GET['tve'])
            || isset($_GET['preview'])
        ) {
            return $content;
        }

        // Отмена / оплата доступа
        if (!is_user_logged_in()) {
            $redirect = esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) );
            wp_redirect( apply_filters( 'login_redirect', $redirect, $redirect, wp_get_current_user() ) );
        }
        else {
            $hash = isset($_REQUEST['k']) ? $_REQUEST['k'] : '';
            $data = get_option($hash, array());

            if (empty($data)) {
                wp_die("Нет данных для обработки доступа", "Обработка доступа");
            }

            $user_id = isset($data['user_id']) ? $data['user_id'] : 0;
            $order_id = isset($data['order_id']) ? $data['order_id'] : 0;
            $product_id = isset($data['product_id']) ? $data['product_id'] : 0;

            if ($post->post_name == 'club_access_off') {

                if (
                    empty($user_id)
                    || $user_id != get_current_user_id()
                    || empty($product_id)
                    || empty($order_id)
                ) {
                    wp_die("Не удалось отменить доступ", "Отмена доступа");
                }
                else {
                    $key = $this->get_key(get_current_user_id(), $order_id, $product_id);
                    $access_status = get_option($key . 'club_access_status', -1);

                    if (empty($access_status) || $access_status == -1) {
                        wp_die("Этот доступ уже отменён или же запрос неверен", "Отмена доступа");
                    }

                    $product = get_post($product_id);
                    if (empty($product)) {
                        wp_die("Неверные данные", "Отмена доступа");
                    }

                    $content = str_replace('{ACCESS_NAME}', $product->post_title, $content);

                    $cancelaccess_url = '';
                    $page = get_page_by_path('cancelaccess');
                    if ($page) {
                        $cancelaccess_url = esc_url(site_url() . '/cancelaccess/');
                    }

                    $end_datetime = get_option($key . 'club_access_end_date', $this->current_time);

                    wp_enqueue_script('ss-club-blog', plugins_url() . '/ss-club/assets/js/access-off.js');
                    $translation_array = array(
                        'url' => VSP_URL,
                        'date' => date(get_option('date_format'), $end_datetime),
                        'user_id' => get_current_user_id(),
                        'order_id' => $order_id,
                        'product_id' => $product_id,
                        'cancelaccess_url' => $cancelaccess_url,
                    );
                    wp_localize_script('ss-club-blog', 'club_access_off_data', $translation_array);
                }
            }
            else {    // Оплата доступа
                if (
                    empty($user_id)
                    || $user_id != get_current_user_id()
                    || empty($product_id)
                    || empty($order_id)
                ) {
                    wp_die("Не удалось оплатить доступ", "Оплата доступа");
                }
                else {
                    // ID платежа
                    $payment_id = get_post_meta($order_id, 'auto_payment_payment_id', true);
                    if (!empty($payment_id)) {
                        wp_die("Оплата по этому заказу уже была произведена", "Оплата доступа");
                    }

                    $product = get_post($product_id);
                    if (empty($product)) {
                        wp_die("Неверные данные", "Оплата доступа");
                    }

                    $content = str_replace('{ACCESS_NAME}', $product->post_title, $content);

                    $success_url = '';
                    $page = get_page_by_path('successpayment');
                    if ($page) {
                        $success_url = esc_url(site_url() . '/successpayment/');
                    }
                    $wait_success_url = '';
                    $page = get_page_by_path('waitsuccesspayment');
                    if ($page) {
                        $wait_success_url = esc_url(site_url() . '/waitsuccesspayment/');
                    }
                    $fail_url = '';
                    $page = get_page_by_path('failpayment');
                    if ($page) {
                        $fail_url = esc_url(site_url() . '/failpayment/');
                    }

                    wp_enqueue_script('ss-club-blog', plugins_url() . '/ss-club/assets/js/access-pay.js');
                    $translation_array = array(
                        'url' => VSP_URL,
                        'date' => date(get_option('date_format')),
                        'key' => $hash,
                        'success_url' => $success_url,
                        'wait_success_url' => $wait_success_url,
                        'fail_url' => $fail_url,
                    );
                    wp_localize_script('ss-club-blog', 'club_access_pay_data', $translation_array);
                }
            }
        }

        return $content;
    }

    /**
     * Не кэшируем некоторые страницы
     *
     * @param $result
     * @param $obj
     * @param $buffer
     * @return mixed
     */
    public function w3tc_can_cache($result, $obj, $buffer) {
        global $post;

        if (
            is_object($post)
            && ($post->post_name == 'club_access_off'
                || $post->post_name == 'club_access_pay')
        ) {
            return false;
        }

        return $result;
    }

    /**
     * Для товаров типа доступ нужно оставить оплату только картой
     *
     * @param $available_gateways
     * @return mixed
     */
    public function woocommerce_available_payment_gateways($available_gateways) {
        if (DOMAIN_CURRENT_SITE != 'salesystems.ru') {
            return $available_gateways;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_type = current(get_the_terms($cart_item['product_id'], 'product_type'))->name;

            if ($product_type == 'access') {
                foreach ($available_gateways as $id => $gateway) {

                    if ($id == 'card') {
                        return array($id => $gateway);
                    }
                }
            }
        }

        return $available_gateways;
    }

    /**
     * Оплата заказа с доступом не должна ограничиваться статусом заказа
     *
     * @param $statuses
     * @param $order
     * @return array
     */
    public function woocommerce_valid_order_statuses_for_payment($statuses, $order) {
        $product_id = $this->get_access_from_order($order);
        if ($product_id) {
            return array(
                'pending',
                'failed',
                'on-hold',
                'processing',
                'completed',
                'refunded',
                'cancelled'
            );
        }

        return $statuses;
    }

    /**
     * Если указана кастомная сумма, то скидку не учитываем
     *
     * @param $discount
     * @param $order
     * @return array
     */
    public function woocommerce_order_amount_discount($discount, $order) {
        foreach ($order->get_items() as $item) {
            $user_id = $this->get_order_user_id($order);

            if ($this->is_club_next_payment($user_id, $order->id, $item['product_id'])) {
                return 0;
            }
        }

        return $discount;
    }

    /**
     * Добавляет в форму авторизации клиента id страницы для редиректа
     */
    public function woocommerce_login_form() {
        if (
            isset($_GET['redirectid'])
            && $_GET['redirectid']
            && is_numeric($_GET['redirectid'])
        ) {
            echo '<input type="hidden" name="redirect" value="' . site_url('?p=' . $_GET['redirectid']) . '">';
        }
    }

    /**
     * Возвращает url для оплаты доступа
     *
     * @param $order
     * @return string
     */
    public function ss_club_get_access_pay_url($order) {
        $product_id = $this->get_access_from_order($order);
        if ($product_id) {
            $user_id = $this->get_order_user_id($order);
            $user_id = !empty($user_id) ? $user_id : get_current_user_id();
            $key = $this->get_key($user_id, $order->id, $product_id);
            $access_status = get_option($key . 'club_access_status', -1);

            if ($access_status) {
                $t = ss_club()->pay->is_payu_payment_enabled() ? 0 : 1;
                return $this->get_access_pay_url($user_id, $order->id, $product_id, $t);
            }
        }

        return '#';
    }

    /**
     * Возвращает url для отмены доступа
     *
     * @param $order
     * @return string
     */
    public function ss_club_get_access_off_url($order) {
        $product_id = $this->get_access_from_order($order);
        if ($product_id) {
            $user_id = $this->get_order_user_id($order);
            $user_id = !empty($user_id) ? $user_id : get_current_user_id();
            $key = $this->get_key($user_id, $order->id, $product_id);
            $access_status = get_option($key . 'club_access_status', -1);

            if ($access_status) {
                return $this->get_access_off_url($user_id, $order->id, $product_id);
            }
        }

        return '#';
    }

    /**
     * Выполнение действий по крону
     */
    public function ss_club_do_hourly_event() {
        ss_club()->pay->check_access_for_payment();
    }

    /**
     * Обработка при деактивации плагина
     */
    public function ss_club_deactivation() {
        wp_clear_scheduled_hook('ss_club_hourly_event');
    }

}
