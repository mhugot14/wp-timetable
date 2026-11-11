<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

/**
 * Einheitliche modale Dialoge für die Admin-Seiten (gleiches Aussehen wie die
 * bestehenden Dialoge bei Terminen/Timetables: .mh-tt-modal).
 *
 * Öffnen: Ein Button mit data-mh-modal="<id>" öffnet den Dialog. data-werte
 * (JSON) befüllt die Formularfelder, data-titel setzt die Überschrift.
 * Die Logik dazu steckt in js/mh_tt_javascript.js.
 */
class AdminModal
{
    /**
     * Button, der einen Dialog öffnet.
     * @param array<string,mixed> $werte Feldname → Wert (Checkbox: bool, Mehrfachauswahl: array)
     */
    public static function button(string $modalId, string $label, array $werte = [], string $titel = '', string $klasse = 'page-title-action', bool $autoOeffnen = false): string
    {
        return sprintf(
            '<button type="button" class="%s" data-mh-modal="%s" data-werte="%s" data-titel="%s"%s>%s</button>',
            esc_attr($klasse),
            esc_attr($modalId),
            esc_attr((string)wp_json_encode((object)$werte)),
            esc_attr($titel),
            $autoOeffnen ? ' data-mh-auto="1"' : '',
            esc_html($label)
        );
    }

    /** Beginn eines Dialogs mit Formular an admin-post.php. */
    public static function start(string $id, string $titel, string $aktion, string $nonceAktion, string $nonceFeld, bool $mitId = true): string
    {
        ob_start();
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="mh-tt-modal" style="display:none;" role="dialog" aria-modal="true">
            <div class="mh-tt-modal-content">
                <span class="mh-tt-close" aria-label="Schließen">&times;</span>
                <h3 class="mh-tt-modal-titel"><?php echo esc_html($titel); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr($aktion); ?>">
                    <?php if ($mitId): ?><input type="hidden" name="id" value=""><?php endif; ?>
                    <?php wp_nonce_field($nonceAktion, $nonceFeld); ?>
                    <table class="form-table">
        <?php
        return (string)ob_get_clean();
    }

    /** Ende eines Dialogs mit Speichern/Abbrechen. */
    public static function ende(string $submitLabel = 'Speichern', string $zusatz = ''): string
    {
        return '</table>' . $zusatz
            . '<p class="mh-tt-modal-aktionen"><button type="submit" class="button button-primary">' . esc_html($submitLabel) . '</button> '
            . '<button type="button" class="button mh-tt-close-btn">Abbrechen</button></p>'
            . '</form></div></div>';
    }

    /** Eine Formularzeile. $feld ist fertiges HTML. */
    public static function zeile(string $label, string $feld, string $beschreibung = '', string $for = ''): string
    {
        return sprintf(
            '<tr><th>%s</th><td>%s%s</td></tr>',
            $for !== '' ? '<label for="' . esc_attr($for) . '">' . esc_html($label) . '</label>' : esc_html($label),
            $feld,
            $beschreibung !== '' ? '<p class="description">' . esc_html($beschreibung) . '</p>' : ''
        );
    }
}
