<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 15.10.2015
 * Time: 15:38
 */

/**
 * Оплата доступа
 *
 * Class ss_club_pay
 */
class ss_club_pay {

    private $current_time = 0;

    function __construct() {
        define('SS_CLUB_PAYMENT_ATTEMPTS', 2);  //Максимум попыток платежа для заказа

        $this->current_time = current_time('timestamp');
    }

    /**
     * Логгирование данных
     *
     * @param $text
     * @param $data
     */
    public function log_data($text, $data) {
        if (WP_DEBUG == true) {
            $upload_dir = wp_upload_dir();
            file_put_contents($upload_dir['path'] . "/club_log.txt", "\n $text \n" . $data, FILE_APPEND);
        }
    }

    /**
     * Проверяет, что в качестве шлюза выбран payu (автоплатёж)
     *
     * @return bool
     */
    public function is_payu_payment_enabled() {
        $wc_checkout = get_option('woocommerce_card_settings', array());

        if (
            !empty($wc_checkout)
            && $wc_checkout['enabled'] == 'yes'
            && $wc_checkout['gateway'] == 'payu'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Проверяет, совершён ли платёж
     *
     * @param $payment_id
     * @return bool
     */
    private function is_payment_done($payment_id) {
        if (!empty($payment_id)) {
            $status = get_post_meta($payment_id, '_status', true);

            $this->log_data("is_payment_done_status", $status);

            // Если оплата прошла успешно
            if (!empty($status) && $status === 'AUTHORIZED') {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет, что платёж в процессе
     *
     * @param $payment_id
     * @return bool
     */
    private function is_payment_process($payment_id) {
        if (!empty($payment_id)) {
            $status = get_post_meta($payment_id, '_status', true);

            $this->log_data("is_payment_process_status", $status);

            // Если платёж в процессе
            if (!empty($status) && $status === 'PROCESS') {
                return true;
            }
        }

        return false;
    }

    /**
     * Если payment_id платежа известно и платёж не прошёл, то возвращает false
     *
     * @param $order_id
     * @return bool
     */
    public function is_payment_error($order_id) {
        // ID платежа
        $payment_id = get_post_meta($order_id, 'auto_payment_payment_id', true);

        $this->log_data("is_payment_error", $payment_id);

        if (
            !empty($payment_id)
            && !$this->is_payment_done($payment_id)
            && !$this->is_payment_process($payment_id)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Проводит автоплатёж
     *
     * @param $order_id
     * @param $product_ids
     * @return bool|int|WP_Error
     */
    private function do_auto_payment($order_id, $product_ids) {
        if (!empty($order_id) && !empty($product_ids)) {
            $recurrent_payment = new WC_Recurrent_Payments_Payu();
            $payment_id = $recurrent_payment->create_payment($order_id, $product_ids);

            return $payment_id;
        }

        return false;
    }

    /**
     * Проверка количества попыток оплаты
     *
     * @param $user_id
     * @param $order_id
     * @param $product_ids
     */
    private function processing_attempts($user_id, $order_id, $product_ids) {
        // Попытки
        $attempts = get_post_meta($order_id, 'auto_payment_attempts', true);
        $attempts = !empty($attempts) && is_numeric($attempts) ? $attempts : 0;
        $attempts++;
        // Если попыток больше допустимого
        if ($attempts >= SS_CLUB_PAYMENT_ATTEMPTS) {
            foreach ($product_ids as $product_id) {
                $this->stop_access($user_id, $order_id, $product_id);
            }
        }
        else {
            update_post_meta($order_id, 'auto_payment_attempts', $attempts);
        }
    }

    /**
     * Делает автоплатёж и выполняет послеплатёжные действия
     *
     * @param $user_id
     * @param $order_id
     * @param $product_ids
     */
    private function try_payment($user_id, $order_id, $product_ids) {
        $payment_id = $this->do_auto_payment($order_id, $product_ids);

        $this->log_data("do_auto_payment", $payment_id);

        // Если заказ оплачен, то меняем дату окончания доступа
        if ($this->is_payment_done($payment_id)) {
            $this->prolong_access($user_id, $order_id, $product_ids);

            $this->log_data("prolong_access_product_ids", print_r($product_ids, true));
        }
        else if (!empty($payment_id)) {
            // Иначе сохраняем данные для следующей проверки
            update_post_meta($order_id, 'auto_payment_payment_id', $payment_id);

            $this->log_data("update_post_meta", $payment_id);

            if ($this->is_payment_error($payment_id)) {
                // Проверим количество попыток оплаты
                $this->processing_attempts($user_id, $order_id, $product_ids);
            }
        }
    }

    /**
     * Продлевает доступ при успешной оплате
     *
     * @param $user_id
     * @param $order_id
     * @param $product_ids
     */
    public function prolong_access($user_id, $order_id, $product_ids) {
        if (!empty($product_ids)) {
            $ss_club = ss_club();

            foreach ($product_ids as $product_id) {
                $key = $ss_club->blog->get_key($user_id, $order_id, $product_id);
                $current_time = $this->current_time;
                $time = $ss_club->blog->get_end_date_next_month($current_time);
                update_option($key . 'club_access_end_date', $time);
                update_option($key . 'club_access_status', 1);

                // Отправим уведомление об успешном платеже
                $ss_club->email->email_payment_comlete($user_id, $order_id, $product_id);

                // Запоминаем доступ, если его не было
                $current_access = get_user_meta($user_id, 'current_access');
                if (empty($current_access)) {
                    $post = get_post($product_id);

                    if ($post) {
                        $access_name = trim($post->post_name);

                        if ($ss_club->blog->is_access_product_by_name($access_name)) {
                            update_user_meta($user_id, 'current_access', array(
                                'name' => $access_name, 'start' => $current_time)
                            );

                            // Проверяем письма в очереди для каждой воронки
                            _controller("Items")->change_sent_time_in_queue_for_funnels($user_id);

                            // Удаление кэша сайта пользователя
                            ss_club()->blog->remove_site_cache($user_id);
                        }
                    }
                }
            }
        }

        delete_post_meta($order_id, 'auto_payment_payment_id');
    }

    /**
     * Отменяет доступ
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     */
    private function stop_access($user_id, $order_id, $product_id) {
        $ss_club = ss_club();

        $key = $ss_club->blog->get_key($user_id, $order_id, $product_id);
        update_option($key . 'club_access_status', -1);
        update_option($key . 'club_access_end_date', $this->current_time - 2 * HOUR_IN_SECONDS);

        // Если указан текущий доступ к системе
        if (
            get_current_blog_id() == SITE_ID_CURRENT_SITE
            && $ss_club->blog->is_current_access_product($user_id, $product_id)
        ) {
            update_user_meta($user_id, 'current_access', '');
        }

        // Отправим уведомление об отключении доступа в Клуб
        $reason = ss_club()->blog->get_user_other_access_products($user_id, $order_id, $product_id) ? 3 : 0;
        $ss_club->email->email_access_off($user_id, $order_id, $product_id, $reason);

        // ID платежа
        $payment_id = get_post_meta($order_id, 'auto_payment_payment_id', true);
        $this->log_data("stop_access_payment_id", $payment_id);

        // Если запрос на оплату был сделан ранее и оплата не прошла
        if (
            !empty($payment_id)
            && $this->is_payment_error($payment_id)
        ) {
            // Отправим уведомление об ошибке рекуррентного платежа
            $ss_club->email->email_payment_error($user_id, $order_id, $product_id);
        }

        delete_post_meta($order_id, 'auto_payment_payment_id');
    }

    /**
     * Обработка данных для оплаты
     *
     * @param $user_id
     * @param $data
     */
    private function processing_data_for_payment($user_id, $data) {
        if (!empty($data) && count($data)) {
            foreach ($data as $order_id => $products) {

                $this->log_data("order_id", $order_id);

                $product_ids = array(); // Товары, которые нужно оплатить в одном заказе

                foreach ($products as $product) {
                    $product_id = $product['id'];
                    $access_end_date = $product['access_end_date'];
                    $access_status = $product['access_status'];

                    // Все товары, срок оплаты которых наступил, нужно обработать
                    if (
                        $access_end_date
                        && $access_end_date < $this->current_time
                    ) {
                        // Если доступ не отключен
                        if ($access_status == 1) {
                            // Если время оплаты истекло или у клиента есть другой такой же активный оплаченный доступ,
                            // то отменяем текущий доступ
                            if (
                                $access_end_date + 2 * HOUR_IN_SECONDS - 1 < $this->current_time
                                || ss_club()->blog->get_user_other_access_products($user_id, $order_id, $product_id)
                            ) {
                                $this->stop_access($user_id, $order_id, $product_id);

                                $this->log_data("stop_access_product_id", $product_id);
                            }
                            else {
                                $product_ids[] = $product_id;
                            }
                        }
                    }
                }

                if (
                    $this->is_payu_payment_enabled()
                    && !empty($product_ids)
                    && apply_filters('ss_access_is_recurrent_available', '')
                ) {
                    $this->log_data("pay_access_by_data_product_ids", print_r($product_ids, true));

                    $this->pay_access_by_data($user_id, $order_id, $product_ids);
                }
            }
        }
    }

    /**
     * Оплата и отмена/продление доступа
     *
     * @param $user_id
     * @param int $order_id
     * @param array $product_ids
     * @return bool - false, если не оплачено
     */
    public function pay_access_by_data($user_id, $order_id, $product_ids) {
        // ID платежа
        $payment_id = get_post_meta($order_id, 'auto_payment_payment_id', true);

        $this->log_data("payment_id", $payment_id);
        // Если запрос на оплату был сделан ранее, то проверим статус
        if (!empty($payment_id)) {

            // Если товар оплачен, то меняем дату окончания доступа
            if ($this->is_payment_done($payment_id)) {
                $this->prolong_access($user_id, $order_id, $product_ids);

                $this->log_data("prolong_access_product_ids", print_r($product_ids, true));

                return true;

            }   // Если платёж не в процессе
            else if (!$this->is_payment_process($payment_id)) {
                // Выполняем попытку платежа
                $this->try_payment($user_id, $order_id, $product_ids);
            }
        }
        else {
            // Иначе выполняем попытку платежа
            $this->try_payment($user_id, $order_id, $product_ids);
        }

        return false;
    }

    /**
     * Проверка доступа, запрос оплаты
     */
    public function check_access_for_payment() {
        $this->log_data("check_access_for_payment", date("Y-m-d H:i:s", $this->current_time));

        $user_with_access = array();

        // Сначала получим данные доступа всех пользователей подсайта, купивших доступ,
        // у которых дата окончания не старше трёх часов
        $access_data = ss_club()->blog->get_access_type_data(0, 0, true, 3);

        if (!empty($access_data)) {
            foreach ($access_data as $user_id => $data) {
                $this->log_data("user_id", $user_id);

                if (!empty($data)) {
                    // Подготовим данные
                    $data = ss_club()->blog->prepare_user_access_data($user_id, $data);

                    $this->log_data("prepare_user_access_data", print_r($data, true));

                    // Обработаем данные
                    $this->processing_data_for_payment($user_id, $data);

                    $user_with_access[] = $user_id;
                }
            }
        }

        // Проверим наличие доступа к системе у каждого инфобизнесмена
        if (get_current_blog_id() == SITE_ID_CURRENT_SITE) {
            global $wpdb;

            $users = $wpdb->get_col("
                SELECT user_id FROM " . ssp_gt('clients_contacts') . "
                WHERE activated = 1 AND user_id NOT IN ('" . implode("','", $user_with_access) . "')
            ");

            if (!empty($users)) {
                foreach ($users as $user_id) {
                    $access_data = get_user_meta($user_id, 'current_access');

                    if (
                        !empty($access_data)
                        && isset($access_data[0])
                        && !empty($access_data[0])
                    ) {
                        $access = isset($access_data[0]['name']) ? $access_data[0]['name'] : '';
                        $access_post = $access ? get_page_by_path($access, OBJECT, 'product') : null;
                        $product_id = $access_post ? $access_post->ID : 0;

                        // Проверим, что текущий товар типа доступ куплен юзером
                        $data = ss_club()->blog->get_user_access_type_data($user_id, 0, $product_id, true);

                        if (empty($data)) {
                            update_user_meta($user_id, 'current_access', '');
                            $this->log_data("clear_access_user_id", $user_id);
                        }
                    }
                }
            }
        }
    }

    /**
     * Инициализация админки
     */
    public function admin_init() {
        // Проверка доступа и выполнение необходимых операций
        // &club=ss-club-cron
        if (
            isset($_GET['club'])
            && 'ss-club-cron' == $_GET['club']
        ) {
            $this->check_access_for_payment();
        }

        // Добавим некоторые операции над доступом через параметры
        // &club=manual-action&club_action=set-start&user_id=1234&order_id=1234&product_id=1234
        if (WP_DEBUG == true) {
            if (
                isset($_GET['club'])
                && 'manual-action' == $_GET['club']
                && isset($_GET['club_action'])
                && isset($_GET['user_id'])
                && get_userdata($_GET['user_id'])
                && isset($_GET['order_id'])
                && isset($_GET['product_id'])
            ) {
                switch ($_GET['club_action']) {
                    case 'set-start':
                        $key = ss_club()->blog->get_key($_GET['user_id'], $_GET['order_id'], $_GET['product_id']);
                        $time = $this->current_time - 30 * DAY_IN_SECONDS;
                        update_option($key . 'club_access_start_date', $time);
                        wp_die("Дата начала доступа установлена в " . date("Y-m-d H:i:s", $time), "Операции над доступом");
                    break;
                    case 'set-end':
                        $key = ss_club()->blog->get_key($_GET['user_id'], $_GET['order_id'], $_GET['product_id']);
                        $time = $this->current_time;
                        update_option($key . 'club_access_end_date', $time);
                        wp_die("Дата окончания доступа установлена в " . date("Y-m-d H:i:s", $time), "Операции над доступом");
                    break;
                    case 'stop':
                        $key = ss_club()->blog->get_key($_GET['user_id'], $_GET['order_id'], $_GET['product_id']);
                        update_option($key . 'club_access_status', -1);
                        // Если указан текущий доступ к системе
                        /*if ($ss_club->blog->is_current_access_product($_GET['user_id'], $_GET['product_id'])) {
                            update_user_meta($_GET['user_id'], 'current_access', '');
                        }*/
                        wp_die("Доступ остановлен", "Операции над доступом");
                    break;
                }

                wp_die("Неверные параметры", "Операции над доступом");
            }
        }
    }

    /**
     * Продление доступа при успешной оплате вручную
     *
     * @param $order_id
     */
    public function woocommerce_payment_complete($order_id) {
        $order = new WC_Order($order_id);
        if ($order) {
            $user_id = !empty($order->user_id) ? $order->user_id : get_post_meta($order->id, '_customer_user', true);
            $completed_time = get_option($order_id . 'club_access_completed', 0);
            foreach ($order->get_items() as $item) {
                $product_type = current(get_the_terms($item['product_id'], 'product_type'))->name;

                if ($product_type == 'access') {
                    $product_id = $item['product_id'];
                    // Если завершённый платёж не является первичной оплатой доступа
                    if ($completed_time && $this->current_time - $completed_time > 10) {
                        $this->prolong_access($user_id, $order_id, array($product_id));
                    }
                }
            }
        }
    }

}
