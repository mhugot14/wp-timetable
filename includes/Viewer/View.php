<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Controller\FerienController;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Model\Repository\FerienRepositoryInterface;
use MH\Timetable\Service\StammdatenService;
use MH\Timetable\Service\IcalService;
use MH\Timetable\Service\FristenService;
use MH\Timetable\Service\KonfliktService;
use MH\Timetable\Service\Berechtigungen;
use MH\Timetable\Install\Schema;
use MH\Timetable\Model\Entity\Timetable;

class View {
    /** Berechtigung für alle Verwaltungsfunktionen des Plugins. */
    public const CAPABILITY = 'manage_options';

    private TerminController $terminController;
    private TimetableController $timetableController;
    private FerienController $ferienController;
    private TermineRepositoryInterface $termineRepository;
    private TimetableRepositoryInterface $timetableRepository;
    private FerienRepositoryInterface $ferienRepository;
    private StammdatenService $stammdaten;
    private IcalService $icalService;
    private FristenService $fristenService;
    private KonfliktService $konfliktService;
    private Berechtigungen $berechtigungen;

    public function __construct(
        TerminController $terminController,
        TimetableController $timetableController,
        FerienController $ferienController,
        TermineRepositoryInterface $termineRepository,
        TimetableRepositoryInterface $timetableRepository,
        FerienRepositoryInterface $ferienRepository,
        StammdatenService $stammdaten,
        IcalService $icalService,
        FristenService $fristenService,
        KonfliktService $konfliktService,
        Berechtigungen $berechtigungen
    ) {
        $this->berechtigungen = $berechtigungen;
        $this->fristenService = $fristenService;
        $this->konfliktService = $konfliktService;
        $this->terminController = $terminController;
        $this->timetableController = $timetableController;
        $this->ferienController = $ferienController;
        $this->termineRepository = $termineRepository;
        $this->timetableRepository = $timetableRepository;
        $this->ferienRepository = $ferienRepository;
        $this->stammdaten = $stammdaten;
        $this->icalService = $icalService;
        $this->initHooks();
    }

    private function initHooks(): void {
        add_action('admin_menu', [$this, 'createMenu']);
        add_action('admin_menu', [$this, 'createMenuEinstellungen'], 30);
        add_action('admin_enqueue_scripts', [$this, 'adminJavascript']);
        add_action('admin_enqueue_scripts', [$this, 'customAdminStyles']);
        add_action('admin_notices', [$this, 'renderAdminNotices']);
        add_action('wp_enqueue_scripts', [$this, 'timetableEnqueueStyles']);
        add_action('init', [$this, 'setupShortcodes']);
        add_action('init', [$this, 'handleIcalDownload']);

        // POST/GET Handler über admin-post.php
        add_action('admin_post_mh_tt_save_termin', [$this, 'handleSaveTermin']);
        add_action('admin_post_mh_tt_delete_termin', [$this, 'handleDeleteTermin']);
        add_action('admin_post_mh_tt_bulk_copy_termin', [$this, 'handleBulkCopyTermin']);
        add_action('admin_post_mh_tt_save_timetable', [$this, 'handleSaveTimetable']);
        add_action('admin_post_mh_tt_delete_timetable', [$this, 'handleDeleteTimetable']);
        add_action('admin_post_mh_tt_copy_timetable', [$this, 'handleCopyTimetable']);
        add_action('admin_post_mh_tt_save_ferien', [$this, 'handleSaveFerien']);
        add_action('admin_post_mh_tt_import_ferien', [$this, 'handleImportFerien']);
        add_action('admin_post_mh_tt_save_frist', [$this, 'handleSaveFrist']);
        add_action('admin_post_mh_tt_delete_frist', [$this, 'handleDeleteFrist']);

        if (isset($_GET['action']) && $_GET['action'] === 'delete_ferien') {
            add_action('admin_init', [$this, 'handleDeleteFerien']);
        }
    }

    public function createMenu(): void {
        add_menu_page('Timetable', 'Timetables', self::CAPABILITY, 'mh-timetable', [$this, 'renderTimetablePage'], 'dashicons-clock', 30);
        add_submenu_page('mh-timetable', 'Termine', 'Termine', self::CAPABILITY, 'termine', [$this, 'renderTerminePage']);
    }

    /** Einstellungen als letzter Menüpunkt (nach Bildungsgänge/Ereignistypen). */
    public function createMenuEinstellungen(): void {
        add_submenu_page('mh-timetable', 'Einstellungen', 'Einstellungen', self::CAPABILITY, 'Einstellungen', [$this, 'renderEinstellungen']);
    }

    public function renderTimetablePage(): void {
        echo '<div class="wrap"><h1 class="wp-heading-inline">Timetables verwalten</h1>';
        echo '<button class="page-title-action mh-tt-timetable-add-btn">+ Neue Timetable</button><hr class="wp-header-end">';
        $table = new \MH\Timetable\Viewer\ListTable\TimetablesListTable($this->timetableController);
        $table->prepare_items();
        $table->display();
        echo '</div>';
        $this->renderTimetableModalContainer();
        $this->renderCopyModal();
    }

    public function renderTerminePage(): void {
        echo '<div class="wrap"><h1 class="wp-heading-inline">Terminübersicht</h1>';
        echo '<button class="page-title-action mh-tt-add-btn">+ Neuen Termin anlegen</button><hr class="wp-header-end">';
        $table = new \MH\Timetable\Viewer\ListTable\TermineListTable($this->terminController, $this->timetableController, $this->stammdaten, $this->konfliktService);
        echo '<form method="get"><input type="hidden" name="page" value="termine">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
        $this->renderModalContainer();
        $this->renderBulkCopyModal();      // 🟢 Das Kopier-Modal für Termine
    }

    public function renderEinstellungen(): void {
        echo '<div class="wrap"><h1>Einstellungen</h1>';

        // --- Ferien & Feiertage ---
        echo '<h2 class="wp-heading-inline">Ferien &amp; Feiertage</h2> ';
        echo AdminModal::button('mh-tt-ferien-modal', '+ Ferien/Feiertag', ['typ' => 'Ferien'], 'Ferien oder Feiertag hinzufügen');
        echo ' ' . AdminModal::button('mh-tt-import-modal', 'Per API importieren', ['jahr' => (int)date('Y'), 'bundesland' => 'NW'], 'Ferien & Feiertage per API importieren');
        echo '<p class="description">Werden für Konfliktwarnungen, Fristen (Schultage) und das Kopieren von Timetables genutzt.</p>';
        $ferienTable = new \MH\Timetable\Viewer\ListTable\FerienListTable($this->ferienController);
        $ferienTable->prepare_items();
        echo '<form method="post">'; $ferienTable->display(); echo '</form>';

        // --- Abhängige Fristen ---
        $this->renderFristen();
        echo '</div>';

        $this->renderFerienForm();
        $this->renderFerienImportForm();
    }

    // --- Abhängige Fristen ---
    private function renderFristen(): void
    {
        $regeln = $this->fristenService->getRegeln();
        $typen = $this->stammdaten->getEreignistypen();
        ?>
        <h2 id="fristen" class="wp-heading-inline" style="margin-top:2em;">Abhängige Fristen</h2>
        <?php echo AdminModal::button('mh-tt-frist-modal', '+ Neue Regel', ['richtung' => 'vor', 'abstand' => 1], 'Neue Fristen-Regel'); ?>
        <p>Regeln zwischen Ereignistypen, jeweils innerhalb derselben Timetable und desselben Bildungsgangs.
           Wird der Bezugstermin verschoben, wandern die abhängigen Termine mit (Abstand in Schultagen bleibt,
           unterschreitet aber nie den Mindestabstand). Verstöße erscheinen als Hinweis in der Terminliste.</p>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th>Regel</th><th style="width:120px;">Aktion</th></tr></thead>
            <tbody>
            <?php if (empty($regeln)): ?>
                <tr><td colspan="2"><em>Noch keine Regeln angelegt.</em></td></tr>
            <?php endif; ?>
            <?php foreach ($regeln as $regel):
                $deleteUrl = wp_nonce_url(admin_url('admin-post.php?action=mh_tt_delete_frist&id=' . rawurlencode($regel['id'])), 'delete_frist_' . $regel['id']);
                ?>
                <tr>
                    <td><?php echo esc_html($this->fristenService->beschreibe($regel)); ?></td>
                    <td><a href="<?php echo esc_url($deleteUrl); ?>" class="button" onclick="return confirm('Regel wirklich löschen?')">Löschen</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $optionen = '';
        foreach ($typen as $t) {
            $optionen .= sprintf('<option value="%d">%s</option>', $t->getId(), esc_html($t->getAnzeigename()));
        }
        echo AdminModal::start('mh-tt-frist-modal', 'Neue Fristen-Regel', 'mh_tt_save_frist', 'mh_tt_frist_action', 'mh_tt_frist_nonce', false);
        echo AdminModal::zeile('Ereignistyp', '<select name="abhaengig_id" required><option value="">– wählen –</option>' . $optionen . '</select>', 'der abhängige Termin, z. B. NE');
        echo AdminModal::zeile('Regel', '<select name="richtung"><option value="vor">endet spätestens … vor Beginn von</option><option value="nach">beginnt frühestens … nach Ende von</option></select>');
        echo AdminModal::zeile('Abstand', '<input type="number" name="abstand" value="1" min="0" max="60" style="width:80px;" required> Schultag(e)', '0 = am selben Tag');
        echo AdminModal::zeile('Bezug', '<select name="bezug_id" required><option value="">– wählen –</option>' . $optionen . '</select>', 'der Bezugstermin, z. B. ZK');
        echo AdminModal::ende('Regel anlegen', '<p class="description">Beispiele: „NE endet spätestens 3 Schultage vor Beginn von ZK“ · „ZA beginnt frühestens 1 Schultag nach Ende von ZK“.</p>');
    }

    public function handleSaveFrist(): void
    {
        $this->requireCapability();
        check_admin_referer('mh_tt_frist_action', 'mh_tt_frist_nonce');

        $fehler = $this->fristenService->addRegel(
            (int)($_POST['abhaengig_id'] ?? 0),
            (int)($_POST['bezug_id'] ?? 0),
            sanitize_key($_POST['richtung'] ?? ''),
            (int)($_POST['abstand'] ?? -1)
        );
        $this->addNotice($fehler === null ? 'success' : 'error', [$fehler ?? 'Die Regel wurde angelegt.']);
        wp_safe_redirect(admin_url('admin.php?page=Einstellungen#fristen'));
        exit;
    }

    public function handleDeleteFrist(): void
    {
        $this->requireCapability();
        $id = sanitize_text_field(wp_unslash($_GET['id'] ?? ''));
        check_admin_referer('delete_frist_' . $id);

        $ok = $this->fristenService->deleteRegel($id);
        $this->addNotice($ok ? 'success' : 'error', [$ok ? 'Die Regel wurde gelöscht.' : 'Die Regel wurde nicht gefunden.']);
        wp_safe_redirect(admin_url('admin.php?page=Einstellungen#fristen'));
        exit;
    }

    // --- Admin-Meldungen ---

    /** @see AdminNotices::add() */
    private function addNotice(string $type, array $messages): void {
        AdminNotices::add($type, $messages);
    }

    /** Wandelt ein Fehler-Array ['feld' => ['Text', ...]] in eine flache Liste um. */
    private static function flattenErrors(array $errors): array {
        $flat = [];
        array_walk_recursive($errors, function ($msg) use (&$flat) { $flat[] = (string)$msg; });
        return $flat;
    }

    public function renderAdminNotices(): void {
        AdminNotices::render();
    }

    private function redirectTo(string $page): void {
        wp_safe_redirect(admin_url('admin.php?page=' . rawurlencode($page)));
        exit;
    }

    private function requireCapability(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die('Sie haben keine Berechtigung für diese Aktion.', 'Keine Berechtigung', ['response' => 403]);
        }
    }

    // --- Modals ---
    private function renderModalContainer(): void {
        $ereignistypen = $this->stammdaten->getEreignistypen();
        $timetables = $this->timetableController->getTimetablesForDropdown();
        ?>
        <div id="mh-tt-modal" class="mh-tt-modal" style="display:none;"><div class="mh-tt-modal-content">
            <span class="mh-tt-close">&times;</span><h3 id="modal-title">Termin bearbeiten</h3>
            <form id="mh-tt-termin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_save_termin"><input type="hidden" name="id" id="f_id">
                <?php wp_nonce_field('termin_speichern_nonce', 'termin_speichern_nonce'); ?>
                <table class="form-table">
                    <tr><th>Timetable</th><td><select name="timetable_ID" id="f_timetable_id" required><option value="">-- wählen --</option>
                        <?php foreach ($timetables as $tt) printf('<option value="%d">%d | %s</option>', $tt['id'], $tt['id'], esc_html($tt['bezeichnung'])); ?>
                    </select></td></tr>
                    <tr><th>Bildungsgang</th><td><select name="bildungsgang" id="f_bildungsgang" required><option value="">-- wählen --</option>
                        <?php echo StammdatenService::optionen($this->stammdaten->getBildungsgaenge()); ?>
                    </select></td></tr>
                    <tr><th>Bezeichnung</th><td><input type="text" name="bezeichnung" id="f_bezeichnung" class="regular-text" maxlength="<?php echo Schema::LEN_TERMIN_BEZEICHNUNG; ?>" required></td></tr>
                    <tr><th>Ereignistyp</th><td><select name="ereignistyp" id="f_ereignistyp" required>
                                  <option value="">-- wählen --</option>
                        <?php echo StammdatenService::optionen($ereignistypen); ?>
                    </select></td></tr>
                    <tr><th>Zeitraum</th><td>Von: <input type="date" name="beginn" id="f_beginn" required> Bis: <input type="date" name="ende" id="f_ende" required></td></tr>
                    <tr><th>Verantwortlich</th><td><input type="text" name="verantwortlich" id="f_verantwortlich" class="regular-text" maxlength="<?php echo Schema::LEN_TERMIN_VERANTWORTLICH; ?>"></td></tr>
                </table>
                <button type="submit" class="button button-primary">Speichern</button><button type="button" class="button mh-tt-close-btn">Abbrechen</button>
            </form>
        </div></div>
        <?php
    }

    private function renderTimetableModalContainer(): void {
        ?>
        <div id="mh-tt-timetable-modal" class="mh-tt-modal" style="display:none;"><div class="mh-tt-modal-content">
            <span class="mh-tt-close">&times;</span><h3 id="tt-modal-title">Zeittafel bearbeiten</h3>
            <form id="mh-tt-timetable-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_save_timetable"><input type="hidden" name="id" id="tt_f_id">
                <?php wp_nonce_field('timetable_speichern_nonce', 'timetable_speichern_nonce'); ?>
                <table class="form-table">
                    <tr><th>Bezeichnung</th><td><input type="text" name="bezeichnung" id="tt_f_bezeichnung" class="regular-text" maxlength="<?php echo Schema::LEN_TIMETABLE_BEZEICHNUNG; ?>" required></td></tr>
                    <tr><th>Beschreibung</th><td><textarea name="beschreibung" id="tt_f_beschreibung" class="regular-text" rows="3" maxlength="<?php echo Schema::LEN_TIMETABLE_BESCHREIBUNG; ?>" required></textarea></td></tr>
                    <tr><th>Status</th><td><select name="status" id="tt_f_status">
                        <?php foreach (Timetable::STATUS_LABELS as $value => $label) printf('<option value="%s">%s</option>', esc_attr($value), esc_html($label)); ?>
                    </select>
                    <p class="description">Entwürfe zeigen im Frontend einen Hinweis und haben keinen iCal-Export bzw. kein Kalender-Abo.</p></td></tr>
                </table>
                <button type="submit" class="button button-primary">Speichern</button>
            </form>
        </div></div>
        <?php
    }

    private function renderCopyModal(): void {
        ?>
        <div id="mh-tt-copy-modal" class="mh-tt-modal" style="display:none;"><div class="mh-tt-modal-content">
            <span class="mh-tt-close">&times;</span><h3>Zeittafel kopieren & verschieben</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_copy_timetable"><input type="hidden" name="source_id" id="copy_source_id">
                <?php wp_nonce_field('copy_timetable_nonce', 'copy_timetable_nonce'); ?>
                <table class="form-table">
                    <tr><th>Neuer Name</th><td><input type="text" name="new_name" id="copy_new_name" class="regular-text" maxlength="<?php echo Schema::LEN_TIMETABLE_BEZEICHNUNG; ?>" required></td></tr>
                    <tr><th>Neues Enddatum</th><td><input type="date" name="new_end_date" required><p class="description">Datum, an dem der letzte Termin der Kopie enden soll.</p></td></tr>
                    <tr><th>Verschiebung</th><td>
                        <label><input type="radio" name="verschiebung" value="wochen" checked> Wochentagsgenau (ganze Wochen)</label><br>
                        <label><input type="radio" name="verschiebung" value="tage"> Exakt bis zum Enddatum (Wochentage können sich ändern)</label>
                        <p class="description">Wochentagsgenau: Eine Konferenz am Dienstag liegt auch in der Kopie an einem Dienstag. Das Ende kann dafür bis zu 3 Tage vom gewünschten Datum abweichen.</p>
                    </td></tr>
                    <tr><th>Schultage</th><td>
                        <label><input type="checkbox" name="schultage" value="1" checked> Termine in Ferien, an Feiertagen oder am Wochenende auf den nächsten Schultag legen</label>
                        <p class="description">Nutzt die unter „Einstellungen“ hinterlegten Ferien und Feiertage.</p>
                    </td></tr>
                </table>
                <p class="description">Die Kopie wird als <strong>Entwurf</strong> angelegt.</p>
                <button type="submit" class="button button-primary">Kopie erstellen</button><button type="button" class="button mh-tt-close-btn">Abbrechen</button>
            </form>
        </div></div>
        <?php
    }

    // --- Handlers: Termine ---
    public function handleSaveTermin(): void {
        $this->requireCapability();
        check_admin_referer('termin_speichern_nonce', 'termin_speichern_nonce');

        $isBulk = strpos((string)($_POST['id'] ?? ''), ',') !== false;
        $errors = $isBulk
            ? $this->terminController->processBulkSubmission(wp_unslash($_POST))
            : $this->terminController->processFormSubmission(wp_unslash($_POST));

        if (empty($errors)) {
            $this->addNotice('success', [$isBulk ? 'Die ausgewählten Termine wurden geändert.' : 'Der Termin wurde gespeichert.']);
        } else {
            $intro = $isBulk ? 'Massenänderung nicht vollständig durchgeführt:' : 'Der Termin wurde nicht gespeichert:';
            $this->addNotice('error', array_merge([$intro], self::flattenErrors($errors)));
        }

        $hinweise = $this->terminController->getHinweise();
        if (!empty($hinweise['info'])) {
            $this->addNotice('info', array_merge(['Abhängige Termine wurden mitverschoben:'], $hinweise['info']));
        }
        if (!empty($hinweise['warning'])) {
            $this->addNotice('warning', array_merge(['Bitte prüfen (gespeichert wurde trotzdem):'], array_values(array_unique($hinweise['warning']))));
        }
        $this->redirectTo('termine');
    }

    public function handleDeleteTermin(): void {
        $this->requireCapability();
        $id = (int)($_GET['id'] ?? 0);
        check_admin_referer('delete_termin_' . $id);

        if ($this->terminController->deleteObject($id)) {
            $this->addNotice('success', ['Der Termin wurde gelöscht.']);
        } else {
            $this->addNotice('error', ['Der Termin konnte nicht gelöscht werden.']);
        }
        $this->redirectTo('termine');
    }

    public function handleBulkCopyTermin(): void
    {
        $this->requireCapability();
        check_admin_referer('bulk_copy_termin_nonce', 'bulk_copy_termin_nonce');

        $ids = array_filter(array_map('intval', explode(',', (string)($_POST['selected_ids'] ?? ''))));
        $targetId = (int)($_POST['target_timetable_id'] ?? 0);

        if (!empty($ids) && $targetId > 0) {
            $this->terminController->processBulkCopy($ids, $targetId);
            $this->addNotice('success', [sprintf('%d Termin(e) wurden kopiert.', count($ids))]);
        } else {
            $this->addNotice('error', ['Keine Termine oder keine Ziel-Zeittafel ausgewählt.']);
        }
        $this->redirectTo('termine');
    }

    // --- Handlers: Timetables ---
    public function handleSaveTimetable(): void {
        $this->requireCapability();
        check_admin_referer('timetable_speichern_nonce', 'timetable_speichern_nonce');

        $errors = $this->timetableController->processFormSubmission(wp_unslash($_POST));
        if (empty($errors)) {
            $this->addNotice('success', ['Die Timetable wurde gespeichert.']);
        } else {
            $this->addNotice('error', array_merge(['Die Timetable wurde nicht gespeichert:'], self::flattenErrors($errors)));
        }
        $this->redirectTo('mh-timetable');
    }

    public function handleDeleteTimetable(): void {
        $this->requireCapability();
        $id = (int)($_GET['id'] ?? 0);
        check_admin_referer('delete_timetable_' . $id);

        $result = $this->timetableController->deleteObject($id);
        $this->addNotice($result['success'] ? 'success' : 'error', [$result['message']]);
        $this->redirectTo('mh-timetable');
    }

    public function handleCopyTimetable(): void {
        $this->requireCapability();
        check_admin_referer('copy_timetable_nonce', 'copy_timetable_nonce');

        $result = $this->timetableController->copyTimetable(wp_unslash($_POST));

        if (!$result['success']) {
            $this->addNotice('error', ['Die Timetable konnte nicht kopiert werden: ' . $result['message']]);
            $this->redirectTo('mh-timetable');
        }

        $this->addNotice('success', [$result['message']]);
        if (!empty($result['verschoben'])) {
            $this->addNotice('info', array_merge(
                [sprintf('%d Termin(e) lagen nicht auf einem Schultag und wurden auf den nächsten Schultag gelegt:', count($result['verschoben']))],
                $result['verschoben']
            ));
        }
        if (!empty($result['warnungen'])) {
            $this->addNotice('warning', $result['warnungen']);
        }
        $this->redirectTo('mh-timetable');
    }

    // --- iCal ---
    public function handleIcalDownload(): void {
        if (!isset($_GET['download_ical'])) return;

        $id = (int)($_GET['timetable_id'] ?? 0);
        $bg = sanitize_text_field(wp_unslash($_GET['bg'] ?? ''));

        $timetable = $id > 0 ? $this->timetableRepository->find($id) : null;
        // Entwürfe sind nur für die Verwaltung abrufbar – für alle anderen (auch
        // Kalender-Abos) sieht ein Entwurf aus wie eine nicht vorhandene Zeittafel.
        if (!$timetable || ($timetable->isEntwurf() && !current_user_can(self::CAPABILITY))) {
            wp_die('Diese Zeittafel existiert nicht.', 'Nicht gefunden', ['response' => 404]);
        }

        $termine = $this->termineRepository->getByTimetableId($id);
        if ($bg !== '') {
            $termine = array_filter($termine, fn($t) => $t->getBildungsgang() === $bg);
        }

        $title = $timetable->getBezeichnung() . ($bg !== '' ? ' - ' . $bg : '');
        $filename = sanitize_file_name($title) ?: 'timetable';
        $content = $this->icalService->generateString($termine, $title);

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        // Abo (webcal) → inline, damit Kalender-Clients den Feed direkt lesen; sonst Download
        $disposition = isset($_GET['abo']) ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '.ics"');
        echo $content;
        exit;
    }

    // --- Assets ---
    /** Lädt Admin-Assets nur auf den Plugin-Seiten. */
    private function isPluginAdminPage(): bool {
        return AdminNotices::isPluginPage();
    }

    public function adminJavascript(): void {
        if (!$this->isPluginAdminPage()) return;
        wp_enqueue_script('mh_tt_javascript', MH_TT_URL . 'includes/Viewer/js/mh_tt_javascript.js', ['jquery'], MH_TT_VERSION, true);
        wp_localize_script('mh_tt_javascript', 'mh_tt_params', ['nonce' => wp_create_nonce('mh_tt_nonce'), 'ajaxurl' => admin_url('admin-ajax.php')]);
    }

    public function customAdminStyles(): void { if (!$this->isPluginAdminPage()) return; wp_enqueue_style('custom-admin-style', MH_TT_URL . 'includes/Viewer/css/timetable_admin.css', [], MH_TT_VERSION); }
    public function timetableEnqueueStyles(): void
	{
		wp_enqueue_style('dashicons');

		$css_path = MH_TT_PATH . 'includes/Viewer/css/timetable_css.css';
		// Dynamische Version basierend auf dem Speicherdatum der Datei
		$ver = file_exists($css_path) ? filemtime($css_path) : '1.0.0';

		wp_enqueue_style(
			'timetable-frontend',
			MH_TT_URL . 'includes/Viewer/css/timetable_css.css',
			[],
			(string)$ver // Zwingt den Browser zum Neuladen bei jeder Änderung
		);

		// Editor-Script nur für Bearbeiter*innen (wird in editorKonfiguration() eingebunden)
		wp_register_script('timetable-editor', MH_TT_URL . 'includes/Viewer/js/timetable_editor.js', ['timetable-frontend'], MH_TT_VERSION, true);

		// Nur registrieren – eingebunden wird das Script erst, wenn der Shortcode rendert.
		wp_register_script(
			'timetable-frontend',
			MH_TT_URL . 'includes/Viewer/js/timetable_frontend.js',
			[],
			MH_TT_VERSION,
			true
		);
	}

    // --- Shortcode ---
    public function setupShortcodes(): void { add_shortcode('insertTimetable', [$this, 'shortcodeInsertTimetable']); }
    public function shortcodeInsertTimetable($atts, string $content = ''): string {
        $atts = shortcode_atts(['id' => 0, 'entwurf' => 'nein'], $atts); $id = (int)$atts['id'];
        $timetable = $this->timetableRepository->find($id); if (!$timetable) return '<p>Nicht gefunden.</p>';
        $termine = $this->termineRepository->getByTimetableId($id); $timetable->setTermine($termine);
        $alleFerien = $this->ferienRepository->getAll(); $ferienMap = [];
        foreach ($alleFerien as $f) { $curr = clone $f->getStartdatum(); while ($curr <= $f->getEnddatum()) { $ferienMap[$curr->format('d.m.y')] = $f->getName(); $curr->modify('+1 day'); } }
        wp_enqueue_script('timetable-frontend');
        // Entwurf: Status der Timetable; das Shortcode-Attribut entwurf="ja" bleibt aus
        // Kompatibilitätsgründen zusätzlich wirksam.
        $istEntwurf = $timetable->isEntwurf() || $atts['entwurf'] === 'ja';
        $frontendView = new \MH\Timetable\Viewer\TimetableFrontendView(
            $timetable,
            $istEntwurf ? 'ja' : 'nein',
            $ferienMap,
            $this->stammdaten->getFarbMap(),
            $this->editorKonfiguration(),
            $this->stammdaten->getBezeichnungMap()
        );
        return $frontendView->render();
    }

    /**
     * Bearbeiten im Frontend: welche Bildungsgänge darf die angemeldete Person
     * bearbeiten? Bei Rechten wird zusätzlich das Editor-Script geladen.
     * (Die REST-Schnittstelle prüft beim Speichern unabhängig davon erneut.)
     */
    private function editorKonfiguration(): array {
        $userId = get_current_user_id();
        $bgs = $this->berechtigungen->bearbeitbareBildungsgaenge($userId);
        if (!$bgs) return [];

        $ereignistypen = [];
        foreach ($this->stammdaten->getEreignistypen() as $et) {
            if ($et->isAktiv()) $ereignistypen[$et->getKuerzel()] = $et->getAnzeigename();
        }

        wp_enqueue_script('timetable-editor');
        wp_localize_script('timetable-editor', 'mhTtEditor', [
            'root'  => esc_url_raw(rest_url(\MH\Timetable\Controller\RestController::NS . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        return [
            'bildungsgaenge' => array_map(fn($bg) => $bg->getKuerzel(), array_filter($bgs, fn($bg) => $bg->isAktiv())),
            'ereignistypen'  => $ereignistypen,
        ];
    }

    // --- Ferien ---
	private function renderFerienForm(): void
    {
        echo AdminModal::start('mh-tt-ferien-modal', 'Ferien oder Feiertag hinzufügen', 'mh_tt_save_ferien', 'mh_tt_ferien_action', 'mh_tt_ferien_nonce', false);
        echo AdminModal::zeile('Name', '<input type="text" id="ferien_name" name="name" class="regular-text" required>', 'z. B. „Osterferien“ oder „Pfingstmontag“', 'ferien_name');
        echo AdminModal::zeile('Von', '<input type="date" id="ferien_von" name="startdatum" required>', '', 'ferien_von');
        echo AdminModal::zeile('Bis', '<input type="date" id="ferien_bis" name="enddatum" required>', 'bei einem einzelnen Feiertag gleich „Von“', 'ferien_bis');
        echo AdminModal::zeile('Typ', '<select name="typ"><option value="Ferien">Ferien</option><option value="Feiertag">Feiertag</option></select>');
        echo AdminModal::ende();
    }

	private function renderFerienImportForm(): void
    {
        $years = range((int)date('Y'), (int)date('Y') + 3);
        $bundeslaender = [
            "NW" => "Nordrhein-Westfalen", "BW" => "Baden-Württemberg", "BY" => "Bayern",
            "BE" => "Berlin", "BB" => "Brandenburg", "HB" => "Bremen", "HH" => "Hamburg",
            "HE" => "Hessen", "MV" => "Mecklenburg-Vorpommern", "NI" => "Niedersachsen",
            "RP" => "Rheinland-Pfalz", "SL" => "Saarland", "SN" => "Sachsen",
            "ST" => "Sachsen-Anhalt", "SH" => "Schleswig-Holstein", "TH" => "Thüringen"
        ];
        $jahre = '';
        foreach ($years as $year) $jahre .= sprintf('<option value="%d">%d</option>', $year, $year);
        $laender = '';
        foreach ($bundeslaender as $code => $name) $laender .= sprintf('<option value="%s">%s</option>', esc_attr($code), esc_html($name));

        echo AdminModal::start('mh-tt-import-modal', 'Ferien & Feiertage per API importieren', 'mh_tt_import_ferien', 'mh_tt_import_action', 'mh_tt_import_nonce', false);
        echo AdminModal::zeile('Jahr', '<select name="jahr">' . $jahre . '</select>');
        echo AdminModal::zeile('Bundesland', '<select name="bundesland">' . $laender . '</select>', 'Quelle: ferien-api.de und feiertage-api.de. Bereits vorhandene Einträge werden übersprungen.');
        echo AdminModal::ende('Daten jetzt abrufen');
    }

	public function handleSaveFerien(): void
    {
        $this->requireCapability();
        check_admin_referer('mh_tt_ferien_action', 'mh_tt_ferien_nonce');

        if ($this->ferienController->saveFerien(wp_unslash($_POST))) {
            $this->addNotice('success', ['Der Eintrag wurde gespeichert.']);
        } else {
            $this->addNotice('error', ['Der Eintrag wurde nicht gespeichert (ungültige Daten oder bereits vorhanden).']);
        }
        $this->redirectTo('Einstellungen');
    }

    public function handleImportFerien(): void
    {
        $this->requireCapability();
        check_admin_referer('mh_tt_import_action', 'mh_tt_import_nonce');

        $count = $this->ferienController->importFromApi(
            (int)($_POST['jahr'] ?? 0),
            sanitize_text_field(wp_unslash($_POST['bundesland'] ?? ''))
        );
        $this->addNotice($count > 0 ? 'success' : 'warning', [
            $count > 0
                ? sprintf('%d Ferien/Feiertage wurden importiert.', $count)
                : 'Es wurden keine neuen Einträge importiert (bereits vorhanden oder API nicht erreichbar).'
        ]);
        $this->redirectTo('Einstellungen');
    }

    public function handleDeleteFerien(): void
    {
        $this->requireCapability();
        $id = (int)($_GET['id'] ?? 0);
        check_admin_referer('delete_ferien_' . $id);

        if ($this->ferienController->deleteFerien($id)) {
            $this->addNotice('success', ['Der Eintrag wurde gelöscht.']);
        } else {
            $this->addNotice('error', ['Der Eintrag konnte nicht gelöscht werden.']);
        }
        $this->redirectTo('Einstellungen');
    }

    private function renderBulkCopyModal(): void
    {
        $timetables = $this->timetableController->getTimetablesForDropdown();
        ?>
        <div id="mh-tt-bulk-copy-modal" class="mh-tt-modal" style="display:none;">
            <div class="mh-tt-modal-content">
                <span class="mh-tt-close">&times;</span>
                <h3>Termine kopieren</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mh_tt_bulk_copy_termin">
                    <input type="hidden" name="selected_ids" id="bulk_copy_ids" value="">
                    <?php wp_nonce_field('bulk_copy_termin_nonce', 'bulk_copy_termin_nonce'); ?>

                    <p>Wählen Sie die Ziel-Zeittafel aus, in die die markierten Termine kopiert werden sollen:</p>

                    <table class="form-table">
                        <tr>
                            <th>Ziel-Zeittafel</th>
                            <td>
                                <select name="target_timetable_id" required>
                                    <?php foreach ($timetables as $tt): ?>
                                        <option value="<?php echo (int)$tt['id']; ?>"><?php echo esc_html($tt['id'] . ' | ' . $tt['bezeichnung']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top:20px;">
                        <button type="submit" class="button button-primary">Kopieren ausführen</button>
                        <button type="button" class="button mh-tt-close-btn">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
