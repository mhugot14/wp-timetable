<?php
/**
 * Plugin Name: Timetable Schulverwaltung
 * Version: 0.6.0
 * Author: Michael Hugot
 */

declare(strict_types=1);

namespace MH\Timetable;

if (!defined('ABSPATH')) exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

define('MH_TT_PATH', plugin_dir_path(__FILE__));
define('MH_TT_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    //error_log('DEBUG: Plugin geladen, starte Kernel');
    $kernel = new \MH\Timetable\Kernel();
    $kernel->boot();
});