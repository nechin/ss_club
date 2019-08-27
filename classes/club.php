<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 21.08.2015
 * Time: 20:13
 */

class ss_club {

    /**
     * @var null
     */
    protected static $_instance = null;

    /**
     * @var null
     */
    public $blog = null;

    /**
     * @var null
     */
    public $pay = null;

    /**
     * @var null
     */
    public $email = null;

    /**
     * @var null
     */
    private $config = null;

    /**
     * @var null
     */
    private $shortcode = null;

    /**
     * Конструктор
     */
    function __construct() {
        $this->includes();

        $this->blog = new ss_club_blog();
        $this->config = new ss_club_config();
        $this->shortcode = new ss_club_shortcode();
        $this->pay = new ss_club_pay();
        $this->email = new ss_club_email();
    }

    /**
     * @return null|ss_club
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Подключение файлов
     */
    private function includes() {
        include_once (SSCLUB_DIR . '/classes/base.php');
        include_once (SSCLUB_DIR . '/classes/blog.php');
        include_once (SSCLUB_DIR . '/classes/config.php');
        include_once (SSCLUB_DIR . '/classes/shortcode.php');
        include_once (SSCLUB_DIR . '/classes/pay.php');
        include_once (SSCLUB_DIR . '/classes/email.php');
    }

    /**
     * Инициализация
     */
    public function init() {
        $this->register_hooks();
        $this->register_shortcodes();
    }

    /**
     * Хуки
     */
    private function register_hooks() {
        add_filter('ss_club_get_access_type_data', array($this->blog, 'get_access_type_data'), 10, 3);
        add_filter('ss_club_get_user_access_data', array($this->blog, 'get_user_access_data'), 10, 5);
        add_filter('ss_club_get_access_type_product_ids', array($this->blog, 'get_access_type_product_ids'));
        add_filter('ss_club_check_access_type_product', array($this->blog, 'check_access_type_product'));
        //add_filter('ss_club_pay_access', array($this->blog, 'pay_access_by_data'), 10, 3);
        add_filter('ss_club_get_key', array($this->blog, 'get_key'), 10, 3);
        add_filter('ss_club_is_can_prolong_access', array($this->blog, 'is_can_prolong_access'));
        add_filter('ss_club_is_can_stop_access', array($this->blog, 'is_can_stop_access'));
        add_filter('ss_club_get_access_pay_url', array($this->blog, 'ss_club_get_access_pay_url'));
        add_filter('ss_club_get_access_off_url', array($this->blog, 'ss_club_get_access_off_url'));

        add_action('init', array($this->blog, 'init'));
        add_action('init', array($this->blog, 'register_post_types'), 5);
        add_action('init', array($this->blog, 'register_taxonomies'), 5);
        add_action('save_post', array($this->blog, 'save_postdata'));
        add_action('wp_enqueue_scripts', array($this->blog, 'wp_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this->blog, 'admin_enqueue_scripts'));
        add_action('wpmu_new_blog', array($this->blog, 'new_blog'), -1, 6);
        add_action('wp', array($this->blog, 'wp'));
        add_action('pre_get_posts', array($this->blog, 'pre_get_posts'));
        add_action('pre_get_posts', array($this->blog, 'pre_get_posts_later'), 1000);
        add_action('wp_ajax_ss_club_access_post', array($this->blog, 'access_post_form'));
        add_action('woocommerce_order_status_completed', array($this->blog, 'woocommerce_order_status_completed'));
        add_action('ss_club_hourly_event', array($this->blog, 'ss_club_do_hourly_event'));
        add_action('woocommerce_login_form', array($this->blog, 'woocommerce_login_form'));

        add_action('admin_init', array($this->pay, 'admin_init'));
        add_action('woocommerce_payment_complete', array($this->pay, 'woocommerce_payment_complete'));
        add_action('woocommerce_payment_complete_order_status_completed', array($this->pay, 'woocommerce_payment_complete'));

        register_deactivation_hook(SSCLUB_PATH, array($this->blog, 'ss_club_deactivation'));

        add_filter('option_thrive_theme_options', array($this->blog, 'thrive_theme_options'));
        add_filter('the_excerpt', array($this->blog, 'post_content'));
        add_filter('the_content', array($this->blog, 'the_content'), 101);
        add_filter('tve_landing_page_content', array($this->blog, 'the_content'), 101);
        add_filter('post_type_link', array($this->blog, 'post_type_link'), 10, 4);
        add_filter('tcb_enqueue_resources', array($this->blog, 'tcb_enqueue_resources'));
        add_filter('w3tc_can_cache', array($this->blog, 'w3tc_can_cache'), 10, 3);
        add_filter('woocommerce_available_payment_gateways', array($this->blog, 'woocommerce_available_payment_gateways'));
        /*add_filter(
            'woocommerce_order_amount_cart_discount',
            array($this->blog, 'woocommerce_order_amount_discount'),
            10, 2
        );
        add_filter(
            'woocommerce_order_amount_order_discount',
            array($this->blog, 'woocommerce_order_amount_discount'),
            10, 2
        );*/
        /*add_filter(
            'woocommerce_valid_order_statuses_for_payment',
            array($this->blog, 'woocommerce_valid_order_statuses_for_payment'),
            10, 2
        );*/

        add_action('init', array($this->config, 'init'));
        add_action('admin_menu', array($this->config, 'admin_config_menu'));
        add_filter('whitelist_options', array($this->config, 'whitelist_options'));
        add_action('wpmu_new_blog', array($this->config, 'new_blog'), -1, 6);
    }

    /**
     * Шорткоды
     */
    private function register_shortcodes() {
        add_shortcode('ss_club_blogs', array($this->shortcode, 'club_blogs'));
    }

} 