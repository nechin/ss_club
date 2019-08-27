<?php
/**
 * Created by Alexander Vitkalov.
 * User: Alexander Vitkalov
 * Date: 25.08.2015
 * Time: 16:24
 */

class ss_club_shortcode extends ss_club_base {

    function __construct() {

    }

    /**
     * Вывод записей клуба
     *
     * @param $atts
     * @return string
     */
    public function club_blogs($atts) {
        if (defined('SSCLUB_BLOGS_CONTENT')) {
            return SSCLUB_BLOGS_CONTENT;
        }

        ob_start();

        include_once (SSCLUB_DIR . '/templates/blogs.php');

        $content = ob_get_clean();

        define('SSCLUB_BLOGS_CONTENT', $content);

        return $content;
    }


} 