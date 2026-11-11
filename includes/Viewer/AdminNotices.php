<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

/**
 * Admin-Meldungen nach dem Post/Redirect/Get-Muster: Die Meldung wird pro
 * Benutzer*in zwischengespeichert und nach dem Redirect auf einer Plugin-Seite angezeigt.
 */
class AdminNotices
{
    /** Seiten-Slugs des Plugins (Meldungen und Admin-Assets nur dort) */
    public const PLUGIN_PAGES = ['mh-timetable', 'termine', 'Einstellungen', 'mh-tt-bildungsgaenge', 'mh-tt-ereignistypen'];

    /**
     * @param string   $type     success | error | warning | info
     * @param string[] $messages
     */
    public static function add(string $type, array $messages): void
    {
        $key = self::key();
        $notices = get_transient($key) ?: [];
        $notices[] = ['type' => $type, 'messages' => array_values($messages)];
        set_transient($key, $notices, 5 * MINUTE_IN_SECONDS);
    }

    public static function isPluginPage(): bool
    {
        return in_array($_GET['page'] ?? '', self::PLUGIN_PAGES, true);
    }

    public static function render(): void
    {
        if (!self::isPluginPage()) return;

        $notices = get_transient(self::key());
        if (empty($notices)) return;
        delete_transient(self::key());

        foreach ($notices as $notice) {
            $type = in_array($notice['type'], ['success', 'error', 'warning', 'info'], true) ? $notice['type'] : 'info';
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            if (count($notice['messages']) === 1) {
                echo '<p>' . esc_html($notice['messages'][0]) . '</p>';
            } else {
                echo '<ul style="list-style:disc; margin-left:20px;">';
                foreach ($notice['messages'] as $msg) echo '<li>' . esc_html($msg) . '</li>';
                echo '</ul>';
            }
            echo '</div>';
        }
    }

    private static function key(): string
    {
        return 'mh_tt_notices_' . get_current_user_id();
    }
}
