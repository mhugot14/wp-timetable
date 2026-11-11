<?php
/**
 * Plugin Name: Timetable Schulverwaltung
 * Version: 0.9.0
 * Author: Michael Hugot
 * Requires PHP: 7.4
 */

declare(strict_types=1);

namespace MH\Timetable;

if (!defined('ABSPATH')) exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback ohne Composer (z. B. Release-ZIP ohne vendor/): PSR-4 für die eigenen Klassen.
    spl_autoload_register(function (string $class): void {
        $prefix = 'MH\\Timetable\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $file = __DIR__ . '/includes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    });
}

define('MH_TT_VERSION', '0.9.0');
define('MH_TT_PATH', plugin_dir_path(__FILE__));
define('MH_TT_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, [\MH\Timetable\Install\Schema::class, 'install']);
register_deactivation_hook(__FILE__, [\MH\Timetable\Install\Schema::class, 'deactivate']);

add_action('plugins_loaded', function() {
    \MH\Timetable\Install\Schema::maybeUpgrade();

    $kernel = new \MH\Timetable\Kernel();
    $kernel->boot();
});
