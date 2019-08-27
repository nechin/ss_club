<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 15.10.2015
 * Time: 15:46
 */

/**
 * Обработка уведомлений
 * 
 * Class ss_club_email
 */
class ss_club_email {

    function __construct() {
        
    }

    /**
     * Подключение доступа в Клуб
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     */
    public function email_access_on($user_id, $order_id, $product_id) {
        $data = apply_filters('ssmt_club_email_access_on', $order_id, $product_id);
        if ($data) {
            $this->send_email($user_id, $data['subject'], $data['message']);
        }
    }

    /**
     * Отключение доступа в Клуб
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     * @param $reason
     */
    public function email_access_off($user_id, $order_id, $product_id, $reason) {
        $reason_text = array(
            0 => 'За неуплату',
            1 => 'Отмена доступа администратором',
            2 => 'Ваше указание',
            3 => 'Замена на другой доступ'
        );
        // Клиенту
        $data = apply_filters('ssmt_club_email_access_off', $order_id, $product_id, $reason_text[$reason], 0);
        if ($data) {
            $this->send_email($user_id, $data['subject'], $data['message']);
        }
        // Инфобизнесмену
        $data = apply_filters('ssmt_club_email_access_off', $order_id, $product_id, $reason_text[$reason], 1);
        if ($data) {
            $user_ids = apply_filters('sse_get_blog_users_by_role', get_current_blog_id(), SS_ROLE_BUSINESSMAN);
            if (!empty($user_ids)) {
                $this->send_email($user_ids[0], $data['subject'], $data['message'], true);
            }
        }
    }

    /**
     * Успешный рекуррентный платеж
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     */
    public function email_payment_comlete($user_id, $order_id, $product_id) {
        $data = apply_filters('ssmt_club_email_payment_comlete', $order_id, $product_id);
        if ($data) {
            $this->send_email($user_id, $data['subject'], $data['message']);
        }
    }

    /**
     * Ошибка рекуррентного платежа
     *
     * @param $user_id
     * @param $order_id
     * @param $product_id
     */
    public function email_payment_error($user_id, $order_id, $product_id) {
        $data = apply_filters('ssmt_club_email_payment_error', $order_id, $product_id);
        if ($data) {
            $this->send_email($user_id, $data['subject'], $data['message']);
        }
    }

    /**
     * Отправка уведомления
     *
     * @param $user_id
     * @param $subject
     * @param $message
     * @param $to_ib - отправка инфобизнесмену
     */
    public function send_email($user_id, $subject, $message, $to_ib=false) {
        if ($user_id && is_numeric($user_id)) {
            $headers = array('Content-type: text/html; charset=utf-8');

            // Укажем данные суперадмина
            $super_admin_id = sse_get_super_admin_id();
            $user = get_userdata($super_admin_id);
            if ($user) {
                $reply_to = $to_ib ? $user->get('user_email') : _controller('Items')->get_reply_to_email();
                $from_email = _controller('Items')->get_from_email($to_ib);
                $user_from_name = esc_html(trim($user->get('first_name') . ' ' . $user->get('last_name')));

                if (
                    !empty($from_email)
                    && filter_var($from_email, FILTER_VALIDATE_EMAIL)
                ) {
                    $headers = apply_filters('ssp_add_email_headers', $headers, $user_from_name, $from_email, $reply_to);
                }
            }

            $user = get_userdata($user_id);
            wp_mail($user->user_email, $subject, $message, $headers);
        }
    }

}
