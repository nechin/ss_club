<?php
/**
 * Plugin Name: Club
 * Description: Клуб
 * Version: 1.3.0
 * Author: Alexander Vitkalov
 * Author URI: http://vitkalov.ru
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('ssclub')) {
    define('SSCLUB_PATH', __FILE__);
    define('SSCLUB_DIR', dirname(SSCLUB_PATH));

    include_once(SSCLUB_DIR . '/classes/club.php');
}

$ss_club = ss_club::instance();
$ss_club->init();

function ss_club() {
    return ss_club::instance();
}
