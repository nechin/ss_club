<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 02.09.2015
 * Time: 12:28
 */

/**
 * Базовый класс
 * Class ss_club_base
 */
class ss_club_base {

    public $current_time = 0;

    function __construct() {
        $this->current_time = current_time('timestamp');
    }

    /**
     * Ключ опции параметра клуба
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return string
     */
    public function get_key($user_id, $order_id, $product_id) {
        return implode('_', array($user_id, $order_id, $product_id));
    }

    /**
     * Определяет, что указан товар типа доступ к системе
     *
     * @param $product_id
     * @return bool
     */
    public function is_access_product_by_id($product_id) {
        if (in_array(get_post($product_id)->post_name, array(SSA_BEGINNER, SSA_STANDARD, SSA_EXPERT))) {
            return true;
        }

        return false;
    }

    /**
     * Определяет, что указан товар типа доступ к системе
     *
     * @param $product_name
     * @return bool
     */
    public function is_access_product_by_name($product_name) {
        if (in_array($product_name, array(SSA_BEGINNER, SSA_STANDARD, SSA_EXPERT))) {
            return true;
        }

        return false;
    }

    /**
     * Определяет, что товар является доступом и активен для данного пользователя
     *
     * @param $user_id
     * @param $product_id
     * @return bool
     */
    public function is_current_access_product($user_id, $product_id) {
        $access_data = get_user_meta($user_id, 'current_access');
        $access_name = isset($access_data[0]['name']) ? $access_data[0]['name'] : '';
        if (
            $this->is_access_product_by_id($product_id)
            && $this->is_access_product_by_name($access_name)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Товары типа Доступ
     *
     * @return WP_Query
     */

    public function get_access_type_products() {
        remove_action('pre_get_posts', 'ssc_custom_post_order');

        $args = array(
            'post_type'				=> 'product',
            'post_status' 			=> 'publish',
            'orderby' 				=> 'meta_value_num',
            'order' 				=> 'ASC',
            'posts_per_page' 		=> '100',
            'paged'					=> 1,
            'meta_key'              => '_regular_price',
            'meta_query' 			=> array(
                array(
                    'key' 		=> '_visibility',
                    'value' 	=> array('catalog', 'visible'),
                    'compare' 	=> 'IN'
                )
            ),
            'tax_query' 			=> array(
                array(
                    'taxonomy' 		=> 'product_type',
                    'terms' 		=> array('access'),
                    'field' 		=> 'slug',
                    'operator' 		=> 'IN'
                )
            )
        );
        $products = new WP_Query($args);

        return $products;
    }

    /**
     * Список id товаров типа доступ
     *
     * @return array
     */
    public function get_access_type_product_ids() {
        $product_ids = array();
        $products = $this->get_access_type_products();

        if (!empty($products) && !empty($products->posts)) {

            foreach ($products->posts as $product) {
                $product_ids[] = $product->ID;
            }
            $product_ids = array_unique($product_ids);
        }

        if (empty($product_ids)) {
            $product_ids = $this->get_access_type_product_ids_by_direct_query();
        }

        return $product_ids;
    }

    /**
     * Список id товаров типа доступ прямым запросом
     *
     * @return array
     */
    public function get_access_type_product_ids_by_direct_query() {
        global $wpdb;

        $product_ids = array();
        $products = $wpdb->get_results("
            SELECT tr.object_id
            FROM {$wpdb->prefix}term_relationships tr
            LEFT JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->prefix}posts p ON p.ID = tr.object_id
            WHERE t.slug = 'access' AND tt.taxonomy = 'product_type' AND p.post_status = 'publish'
        ");

        if (!empty($products)) {

            foreach ($products as $product) {
                $product_ids[] = $product->object_id;
            }
            $product_ids = array_unique($product_ids);
        }

        return $product_ids;
    }

    /**
     * Проверяет, что товар типа доступ существует и активен
     *
     * @param $product_id
     * @return bool
     */
    public function check_access_type_product($product_id) {
        if ($product_id) {
            $product_ids = $this->get_access_type_product_ids();

            if (in_array($product_id, $product_ids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверим дату начала доступа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return bool
     */
    private function check_access_start_date($user_id, $order_id, $product_id) {
        if (!empty($user_id) && !empty($order_id) && !empty($product_id)) {
            $key = $this->get_key($user_id, $order_id, $product_id);

            // Проверим дату начала доступа
            $club_access_start_date = $this->get_club_access_start_date($key);
            // Если дата начала доступа задана
            if ($club_access_start_date) {
                // Если дата начала доступа ещё не наступила
                if ($club_access_start_date > $this->current_time) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Проверим дату окончания доступа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @param $access_hour
     * @return bool
     */
    private function check_access_end_date($user_id, $order_id, $product_id, $access_hour=2) {
        if (!empty($user_id) && !empty($order_id) && !empty($product_id)) {
            $key = $this->get_key($user_id, $order_id, $product_id);

            $club_access_end_date = $this->get_club_access_end_date($key);
            // Если дата отмены доступа задана
            if ($club_access_end_date) {
                $club_access_end_date = $club_access_end_date + $access_hour * HOUR_IN_SECONDS - 1;

                // Если дата отмены ещё не наступила
                if ($club_access_end_date > $this->current_time) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Возвращает результат запроса для получение данных доступа
     *
     * @param $user_ids
     * @param $order_id
     * @param $product_ids
     * @return array|null|object
     */
    private function get_access_type_results($user_ids, $order_id, $product_ids) {
        global $wpdb;

        if (!empty($product_ids)) {

            if (empty($user_ids)) {
                $user_ids_query = "SELECT user_id FROM {$wpdb->prefix}vspostman_clients_contacts";
            }
            else {
                $user_ids_query = "'" . implode("','", $user_ids) . "'";
            }
            return $wpdb->get_results("
                SELECT pm.meta_value as user_id, oi.order_id, oim.meta_value as product_id
                FROM {$wpdb->prefix}woocommerce_order_items as oi
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
                JOIN {$wpdb->prefix}postmeta pm ON pm.post_id = oi.order_id
                WHERE oim.meta_key = '_product_id' AND oim.meta_value IN ('" . implode("','", $product_ids) . "')
                    AND pm.meta_key = '_customer_user' AND pm.meta_value IN (" . $user_ids_query . ")
                    " . ($order_id ? " AND oi.order_id = '" . $order_id . "'" : "") . "
            ");
            /* Если нужно возвращать только выполненные заказы
                JOIN {$wpdb->prefix}term_relationships tr ON tr.object_id = oi.order_id
                JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                JOIN {$wpdb->prefix}terms t ON t.term_id = tt.term_id

                AND t.slug = 'completed' AND tt.taxonomy = 'shop_order_status' */
        }

        return array();
    }

    /**
     * Возвращает данные по купленным товарам
     *
     * @param int $order_id
     * @param int $product_id
     * @param bool $check_access - нужно ли возвращать товары с отменённым доступом, у которых прошёл срок действия доступа
     * @param int $access_hour - колчество часов, которые добавляются к дате окончания доступа при проверке
     * @return array
     */
    public function get_access_type_data($order_id=0, $product_id=0, $check_access=false, $access_hour=2) {
        $product_ids = array();

        if ($product_id) {
            $product_ids[] = $product_id;
        }
        else {
            $product_ids = $this->get_access_type_product_ids();
        }

        if (!empty($product_ids)) {
            $results = $this->get_access_type_results(array(), $order_id, $product_ids);
            if ($results) {
                $data = array();
                foreach ($results as $result) {
                    $return = true;
                    // Если нужно проверить, не отменён ли доступ
                    if ($check_access) {
                        $return = $this->check_access_end_date($result->user_id, $result->order_id, $result->product_id, $access_hour);
                    }

                    if ($return) {
                        $data[$result->user_id][$result->order_id][] = $result->product_id;
                    }
                }

                return $data;
            }
        }

        return array();
    }

    /**
     * Возвращает товары типа доступ, купленные юзером
     *
     * @param $user_id
     * @param int $order_id
     * @param int $product_id
     * @param bool $check_access - нужно ли возвращать товары с отменённым доступом, у которых дата окончания доступа ещё не наступила
     * @return array
     */
    public function get_user_access_type_data($user_id, $order_id=0, $product_id=0, $check_access=false) {
        if ($user_id) {
            $product_ids = array();

            if ($product_id) {
                $product_ids[] = $product_id;
            }
            else {
                $product_ids = $this->get_access_type_product_ids();
            }

            if (!empty($product_ids)) {
                $results = $this->get_access_type_results(array(), $order_id, $product_ids);
                if ($results) {
                    $data = array();
                    foreach ($results as $result) {
                        $return = true;
                        // Если нужно проверить, не наступила ли дата окончания доступа
                        if ($check_access) {
                            $return = $this->check_access_end_date($user_id, $result->order_id, $result->product_id);
                        }

                        if ($return) {
                            $data[$result->order_id][] = $result->product_id;
                        }
                    }

                    return $data;
                }
            }
        }

        return array();
    }

    /**
     * Возвращает товары типа доступ, подпадающие под условия
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return array
     */
    public function get_user_access_products($user_id, $order_id=0, $product_id=0) {
        if ($user_id) {
            $results = $this->get_user_access_type_data($user_id, $order_id, $product_id);

            if ($results) {
                $data = array();
                foreach ($results as $order_id => $products) {
                    foreach ($products as $product_id) {
                        // Проверим дату начала доступа
                        $return = $this->check_access_start_date($user_id, $order_id, $product_id);

                        // Если доступ не отменён
                        if (
                            $return
                            && $this->check_access_end_date($user_id, $order_id, $product_id)
                        ) {
                            $data[] = $product_id;
                        }
                    }
                }
                $data = array_unique($data);

                return $data;
            }
        }

        return array();
    }

    /**
     * Возвращает товары типа доступ, подпадающие под условия, но не принадлежащие указанному заказу
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @param $only_system_access - проверять только товары доступ к системе
     * @return array
     */
    public function get_user_other_access_products($user_id, $order_id, $product_id, $only_system_access=false) {
        if ($user_id) {
            $results = $this->get_user_access_type_data($user_id, 0, 0, true);

            if ($results) {
                $data = array();
                foreach ($results as $_order_id => $products) {
                    foreach ($products as $_product_id) {

                        if (
                            ($only_system_access
                            && !$this->is_access_product_by_id($_product_id))
                            ||
                            ($_order_id == $order_id
                            && $_product_id == $product_id)
                        ) {
                            continue;
                        }

                        // Если доступ не закончился
                        if ($this->check_access_end_date($user_id, $_order_id, $_product_id)) {
                            $data[] = $_product_id;
                        }
                    }
                }
                $data = array_unique($data);

                return $data;
            }
        }

        return array();
    }

    /**
     * Возвращает записи клуба определённого доступа
     *
     * @param $access_type
     * @return array
     */
    public function get_post_ids_by_access($access_type) {
        global $wpdb;

        $post_ids = array();

        if ($access_type) {
            // Для начала найдём записи с доступом "Клиентам имеющим доступ:"
            $results = $wpdb->get_results("
                SELECT post_id
                FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'open_access' AND meta_value = '$access_type'
            ");
            if ($results) {
                foreach ($results as $result) {
                    $post_ids[] = $result->post_id;
                }
            }
        }

        return $post_ids;
    }

    /**
     * Возвращает post_id тех записей клуба с доступом "Клиентам имеющим доступ", доступа к которым у юзера нет
     *
     * @return array
     */
    public function get_access_denied_post_ids() {
        $exclude_ids = array();
        $user_id = get_current_user_id();

        // Получим записи с доступом "Клиентам имеющим доступ:"
        $post_ids = $this->get_post_ids_by_access(3);
        if (!empty($post_ids)) {

            // Получим купленные товары типа доступ
            $buyed_product_ids = $this->get_user_access_products($user_id);
            if (!empty($buyed_product_ids)) {

                // Если в записи не указан товар типа доступ, купленный юзером, то не показываем её
                foreach ($post_ids as $post_id) {
                    $access_product_ids = get_post_meta($post_id, 'access_product');
                    $diff = array_diff($access_product_ids[0], $buyed_product_ids);

                    // Если куплены не все товары, то нужно исключить из списка вывод этих записей
                    if (count($diff) == count($access_product_ids[0])) {
                        $exclude_ids[] = $post_id;
                    }
                }
            }
            else {
                $exclude_ids = $post_ids;
            }
        }

        return $exclude_ids;
    }

    /**
     * Возвращает список id товаров типа доступ, которые нужно купить для полного доступа
     *
     * @param $post_id
     * @return array
     */
    public function get_access_type_product_need_buy($post_id) {
        if ($post_id) {
            $user_id = get_current_user_id();

            // Товары, нужные для покупки доступа к записи
            $access_product_ids = get_post_meta($post_id, 'access_product');

            // Получим купленные товары типа доступ
            $buyed_product_ids = $this->get_user_access_products($user_id);
            if (!empty($buyed_product_ids)) {
                $diff = array_diff($access_product_ids[0], $buyed_product_ids);

                return $diff;
            }
            else {
                return $access_product_ids[0];
            }
        }

        return array();
    }

    /**
     * Проверка доступа к записи
     *
     * @param $post_id
     * @return int (1 - доступна, 2 и 3 - недоступна)
     */
    public function get_access_user_post($post_id) {
        if ($post_id) {
            // Если пользователь неавторизован, то он может просматривать только общедоступные записи
            if (!is_user_logged_in()) {
                $access = get_post_meta($post_id, 'open_access', true);

                if ($access != 1) {
                    return 2;
                }
            }
            else {
                // Инфобизнесмен имеет доступ к такой записи
                $user_id = get_current_user_id();
                if (!sse_is_user_has_role($user_id, SS_ROLE_BUSINESSMAN)) {
                    // Проверим доступ к записи с доступом "Клиентам имеющим доступ:"
                    $access_denied_post_ids = $this->get_access_denied_post_ids();

                    if (in_array($post_id, $access_denied_post_ids)) {
                        return 3;
                    }
                }
            }
        }

        return 1;
    }

    /**
     * Данные доступов пользователя для конкретного товара, или всех товаров
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @param $check_access
     * @param $main - Доступы на основном сайте или на текущем
     * @return array
     */
    public function get_user_access_data($user_id, $order_id, $product_id, $check_access, $main=true) {
        $result = array();

        if ($main) {
            switch_to_blog(SITE_ID_CURRENT_SITE);
        }

        $data = $this->get_user_access_type_data($user_id, $order_id, $product_id, $check_access);
        if (!empty($data)) {
            $result = $this->prepare_user_access_data($user_id, $data);
        }

        if ($main) {
            restore_current_blog();
        }

        return $result;
    }

    /**
     * Возвращает дату следующего платежа, добавляя месяц к текущей
     *
     * @param $time
     * @return int
     */
    public function get_end_date_next_month($time) {
        if (!empty($time) && is_numeric($time)) {
            $day = date("j", $time);
            $month = date("n", $time);
            // Если это последний день месяца, или это январь и день больше 28
            if (
                $day == date("j", strtotime("last day of", $time))
                || ($month == 1 && $day > 28)
            ) {
                // Вернём последний день следующего месяца
                return strtotime("last day of next month", $time);
            }
            else {
                // Иначе нужно вернуть тот же день следующего месяца
                return strtotime("+1 month", $time);
            }
        }

        return $time;
    }

    /**
     * Дата начала доступа
     *
     * @param $key
     * @return bool|mixed|string|void
     */
    public function get_club_access_start_date($key) {
        return get_option($key . 'club_access_start_date', 0);
    }

    /**
     * Дата окончания доступа
     *
     * @param $key
     * @return bool|mixed|string|void
     */
    public function get_club_access_end_date($key) {
        return get_option($key . 'club_access_end_date', 0);
    }

    /**
     * Возвращает размер последнего платежа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return mixed
     */
    public function get_club_last_payment($user_id, $order_id, $product_id) {
        $last_payment_total = 0;

        $order = new WC_Order($order_id);

        if ($order instanceof WC_Order) {
            $order_payments = $order->get_payments();
            $last_payment_id = array_shift($order_payments);
            $last_payment = new WC_Pay($last_payment_id);

            if ($last_payment instanceof WC_Pay) {
                $last_payment_total = $last_payment->get_total();
            }
        }

        return empty($last_payment_total)
            ? $this->get_club_next_payment($user_id, $order_id, $product_id)
            : $last_payment_total;
    }

    /**
     * Возвращает размер следующего платежа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return mixed
     */
    public function get_club_next_payment($user_id, $order_id, $product_id) {
        $key = $this->get_key($user_id, $order_id, $product_id);
        $next_payment = get_option($key . 'club_own_next_payment', ''); // Размер платежа, заданного вручную

        if (!is_numeric($next_payment) || $next_payment < 0) {
            $next_payment = get_option($key . 'club_next_payment', ''); // Размер платежа, заданного при покупке

            if (!is_numeric($next_payment) || $next_payment < 0) {
                $order = new WC_Order($order_id);
                foreach ($order->get_items() as $item) {
                    // Указанный товар
                    if ($item['product_id'] == $product_id) {
                        // Посчитанная сумма с учетом купонов
                        $next_payment = $order->get_item_subtotal_by_current_price($item, false, true, true);
                        break;
                    }
                }
            }
        }

        return $next_payment;
    }

    /**
     * Устанавливает размер следующего платежа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return mixed
     */
    public function set_club_next_payment($user_id, $order_id, $product_id) {
        $key = $this->get_key($user_id, $order_id, $product_id);
        $next_payment = get_option($key . 'club_next_payment', ''); // Размер платежа, заданного при покупке

        $order = new WC_Order($order_id);
        foreach ($order->get_items() as $item) {
            // Указанный товар
            if ($item['product_id'] == $product_id) {
                // Посчитанная сумма с учетом купонов
                $next_payment = $order->get_item_subtotal_by_current_price($item, false, true, true);
                break;
            }
        }

        update_option($key . 'club_next_payment', $next_payment);

        return $next_payment;
    }

    /**
     * Если сумма следующего платежа задана, то вернёт true
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return mixed
     */
    public function is_club_next_payment($user_id, $order_id, $product_id) {
        $key = $this->get_key($user_id, $order_id, $product_id);
        // Размер платежа, заданного вручную
        $own_next_payment = get_option($key . 'club_own_next_payment', '');
        // Размер платежа, заданного при покупке
        $next_payment = get_option($key . 'club_next_payment', '');

        if (
            (is_numeric($own_next_payment) && $own_next_payment >= 0)
            || (is_numeric($next_payment) && $next_payment >= 0)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Подготавливает данные доступа
     *
     * @param $user_id
     * @param $data
     * @return array
     */
    public function prepare_user_access_data($user_id, $data) {
        if (!empty($data)) {
            $prepared_data = array();

            foreach ($data as $order_id => $products) {
                foreach ($products as $product_id) {
                    $key = $this->get_key($user_id, $order_id, $product_id);
                    // Начало доступа
                    $access_start_date = $this->get_club_access_start_date($key);

                    // Окончание доступа
                    $access_end_date = $this->get_club_access_end_date($key);

                    // Статус доступа (-1 - нет доступа, 1 - есть доступ)
                    $access_status = get_option($key . 'club_access_status', -1);

                    // Следующий платёж
                    $next_payment = $this->get_club_next_payment($user_id, $order_id, $product_id);

                    $product_data = get_post($product_id);

                    $prepared_data[$order_id][] = array(
                        'id' => $product_id,
                        'name' => $product_data->post_title,
                        'access_start_date' => $access_start_date,
                        'access_end_date' => $access_end_date,
                        'access_status' => $access_status,
                        'next_payment' => $next_payment,
                    );
                }
            }

            return $prepared_data;
        }

        return array();
    }

    /**
     * Генерирует хэш
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return string
     */
    private function get_hash($user_id, $order_id, $product_id) {
        $key = $this->get_key($user_id, $order_id, $product_id);
        $hash = hash_hmac('haval128,5', $key, 'ssclb');
        update_option($hash, array(
            'user_id' => $user_id,
            'order_id' => $order_id,
            'product_id' => $product_id
        ));

        return $hash;
    }

    /**
     * Ссылка на оплату доступа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @param $type
     * @return string
     */
    public function get_access_pay_url($user_id, $order_id, $product_id, $type=0) {
        if ($type) {
            $order = new WC_Order($order_id);
            return $order->get_checkout_payment_url();
        }
        else {
            $hash = $this->get_hash($user_id, $order_id, $product_id);
            return site_url() . '/club_access_pay/?k=' . $hash;
        }
    }

    /**
     * Ссылка на отмену доступа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @return string
     */
    public function get_access_off_url($user_id, $order_id, $product_id) {
        $hash = $this->get_hash($user_id, $order_id, $product_id);

        return site_url() . '/club_access_off/?k=' . $hash;
    }

    /**
     * Если заказ с доступом, то возвращает product_id
     *
     * @param $order
     * @return bool
     */
    public function get_access_from_order($order) {
        if ($order->id) {
            foreach ($order->get_items() as $item) {
                $product_type = current(get_the_terms($item['product_id'], 'product_type'))->name;

                if ($product_type == 'access') {
                    return $item['product_id'];
                }
            }
        }

        return false;
    }

    /**
     * Можно ли продлить доступ
     *
     * @param $order
     * @return bool
     */
    public function is_can_prolong_access($order) {
        $product_id = $this->get_access_from_order($order);

        if ($product_id) {
            $user_id = $this->get_order_user_id($order);
            if (empty($user_id)) {
                $user_id = get_current_user_id();
            }
            $key = $this->get_key($user_id, $order->id, $product_id);
            $access_status = get_option($key . 'club_access_status', -1);

            if ($access_status == 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Можно ли отменить доступ
     *
     * @param $order
     * @return bool
     */
    public function is_can_stop_access($order) {
        // Если есть активные доступы
        return $this->is_can_prolong_access($order);
    }

    /**
     * ID пользователя в заказе
     *
     * @param $order
     * @return mixed
     */
    public function get_order_user_id($order) {

        if (empty($order)) {
            return 0;
        }

        $user_id = !empty($order->user_id) ? $order->user_id : get_post_meta($order->id, '_customer_user', true);
        return $user_id;
    }

    /**
     * Удаление файлов и директорий
     *
     * @param $dirname
     * @return bool
     */
    public function remove_files_folders($dirname) {
        // Проверка
        if (!file_exists($dirname)) {
            return false;
        }

        // Удаление файла
        if (is_file($dirname)) {

            if (strpos($dirname, '.htaccess') !== false) {
                return true;
            }

            return @unlink($dirname);
        }

        // Обходим директорию
        if (@is_dir($dirname)) {
            $dir = dir($dirname);

            while (false !== $entry = $dir->read()) {
                // Пропускаем указатели
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                // Рекурсивно
                $this->remove_files_folders("$dirname/$entry");
            }
            $dir->close();

            @rmdir($dirname);
        }

        return true;
    }

    /**
     * Удаление кэша сайта
     *
     * @param $user_id
     */
    public function remove_site_cache($user_id) {

        if (defined('W3TC_CACHE_PAGE_ENHANCED_DIR')) {
            $blog_ids = sse_get_user_blog_ids_by_role($user_id, SS_ROLE_BUSINESSMAN);

            if (
                isset($blog_ids[0])
                && $blog_ids[0] != SITE_ID_CURRENT_SITE
            ) {
                $siteurl = get_site_url($blog_ids[0]);
                $siteurl = str_replace('http://', '', $siteurl);
                $dir = W3TC_CACHE_PAGE_ENHANCED_DIR . '/' . $siteurl;
                $this->remove_files_folders($dir);

                $domain = dm_get_domain($blog_ids[0]);
                if ($domain && $domain != $siteurl) {
                    $dir = W3TC_CACHE_PAGE_ENHANCED_DIR . '/' . $domain;
                    $this->remove_files_folders($dir);
                }
            }
        }
    }

}
