<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Controller\FerienController;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Model\Repository\FerienRepositoryInterface;
use MH\Timetable\Service\TaxonomyService;

class View {
    private TerminController $terminController;
    private TimetableController $timetableController;
    private FerienController $ferienController;
    private TermineRepositoryInterface $termineRepository;
    private TimetableRepositoryInterface $timetableRepository;
    private FerienRepositoryInterface $ferienRepository;
    private TaxonomyService $taxonomyService;

    public function __construct(
        TerminController $terminController,
        TimetableController $timetableController,
        FerienController $ferienController,
        TermineRepositoryInterface $termineRepository,
        TimetableRepositoryInterface $timetableRepository,
        FerienRepositoryInterface $ferienRepository,
        TaxonomyService $taxonomyService
    ) {
        $this->terminController = $terminController;
        $this->timetableController = $timetableController;
        $this->ferienController = $ferienController;
        $this->termineRepository = $termineRepository;
        $this->timetableRepository = $timetableRepository;
        $this->ferienRepository = $ferienRepository;
        $this->taxonomyService = $taxonomyService;
        $this->initHooks();
    }

    private function initHooks(): void {
        add_action('admin_menu', [$this, 'createMenu']);
        add_action('admin_enqueue_scripts', [$this, 'adminJavascript']);
        add_action('admin_enqueue_scripts', [$this, 'customAdminStyles']);
        add_action('wp_enqueue_scripts', [$this, 'timetableEnqueueStyles']);
        add_action('init', [$this, 'setupShortcodes']);
        add_action('init', [$this, 'handleIcalDownload']);
        
        // POST Handler
        add_action('admin_post_mh_tt_save_termin', [$this, 'handleSaveTermin']);
        add_action('admin_post_mh_tt_save_timetable', [$this, 'handleSaveTimetable']);
        add_action('admin_post_mh_tt_copy_timetable', [$this, 'handleCopyTimetable']);
        add_action('admin_post_mh_tt_save_ferien', [$this, 'handleSaveFerien']);
        add_action('admin_post_mh_tt_import_ferien', [$this, 'handleImportFerien']);
        
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'delete_ferien') add_action('admin_init', [$this, 'handleDeleteFerien']);
            if ($_GET['action'] === 'delete_timetable') add_action('admin_init', [$this, 'handleDeleteTimetable']);
        }
    }

    public function createMenu(): void {
        add_menu_page('Timetable', 'Timetables', 'manage_options', 'mh-timetable', [$this, 'renderTimetablePage'], 'dashicons-clock', 30);
        add_submenu_page('mh-timetable', 'Termine', 'Termine', 'manage_options', 'termine', [$this, 'renderTerminePage']);
        add_submenu_page('mh-timetable', 'Einstellungen', 'Einstellungen', 'manage_options', 'Einstellungen', [$this, 'renderEinstellungen']);
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
        $table = new \MH\Timetable\Viewer\ListTable\TermineListTable($this->terminController, $this->timetableController, $this->taxonomyService);
        echo '<form method="get"><input type="hidden" name="page" value="termine">';
        $table->prepare_items();
        $table->display();
        echo '</form></div>';
        $this->renderModalContainer();
    }

    public function renderEinstellungen(): void {
        echo '<div class="wrap"><h1>Einstellungen</h1>';
        echo '<h2>Taxonomien</h2><ul><li><a href="edit-tags.php?taxonomy=bildungsgang" class="button">Bildungsgänge verwalten</a></li> ';
        echo '<li><a href="edit-tags.php?taxonomy=ereignistyp" class="button">Ereignistypen verwalten</a></li></ul>';
        echo '<h2>Ferien & Feiertage</h2>';
        $this->renderFerienForm();
        $ferienTable = new \MH\Timetable\Viewer\ListTable\FerienListTable($this->ferienController);
        $ferienTable->prepare_items();
        echo '<form method="post">'; $ferienTable->display(); echo '</form>';
        $this->renderFerienImportForm();
        echo '</div>';
    }

    // --- Modals ---
    private function renderModalContainer(): void {
        $ereignistypen = $this->taxonomyService->getEreignistypen();
        $timetables = $this->timetableController->getTimetablesForDropdown();
        ?>
        <div id="mh-tt-modal" class="mh-tt-modal" style="display:none;"><div class="mh-tt-modal-content">
            <span class="mh-tt-close">&times;</span><h3 id="modal-title">Termin bearbeiten</h3>
            <form id="mh-tt-termin-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mh_tt_save_termin"><input type="hidden" name="id" id="f_id">
                <?php wp_nonce_field('termin_speichern_nonce', 'termin_speichern_nonce'); ?>
                <table class="form-table">
                    <tr><th>Timetable</th><td><select name="timetable_ID" id="f_timetable_id" required><option value="">-- wählen --</option>
                        <?php foreach ($timetables as $tt) printf('<option value="%d">%d | %s</option>', $tt['id'], $tt['id'], esc_html($tt['bezeichnung'])); ?>
                    </select></td></tr>
                    <tr><th>Bildungsgang</th><td><select name="bildungsgang" id="f_bildungsgang" required><option value="">-- wählen --</option>
                        <?php foreach ($this->taxonomyService->getBildungsgaenge() as $bg) printf('<option value="%s">%s</option>', esc_attr($bg->name), esc_html($bg->name)); ?>
                    </select></td></tr>
                    <tr><th>Bezeichnung</th><td><input type="text" name="bezeichnung" id="f_bezeichnung" class="regular-text" required></td></tr>
                    <tr><th>Ereignistyp</th><td><select name="ereignistyp" id="f_ereignistyp" required>
                        <?php foreach ($ereignistypen as $et) printf('<option value="%s">%s</option>', esc_attr($et->name), esc_html($et->name)); ?>
                    </select></td></tr>
                    <tr><th>Zeitraum</th><td>Von: <input type="date" name="beginn" id="f_beginn" required> Bis: <input type="date" name="ende" id="f_ende" required></td></tr>
                    <tr><th>Verantwortlich</th><td><input type="text" name="verantwortlich" id="f_verantwortlich" class="regular-text"></td></tr>
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
            <form id="mh-tt-timetable-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mh_tt_save_timetable"><input type="hidden" name="id" id="tt_f_id">
                <?php wp_nonce_field('timetable_speichern_nonce', 'timetable_speichern_nonce'); ?>
                <table class="form-table">
                    <tr><th>Bezeichnung</th><td><input type="text" name="bezeichnung" id="tt_f_bezeichnung" class="regular-text" required></td></tr>
                    <tr><th>Beschreibung</th><td><textarea name="beschreibung" id="tt_f_beschreibung" class="regular-text" rows="3"></textarea></td></tr>
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
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="mh_tt_copy_timetable"><input type="hidden" name="source_id" id="copy_source_id">
                <?php wp_nonce_field('copy_timetable_nonce', 'copy_timetable_nonce'); ?>
                <table class="form-table">
                    <tr><th>Neuer Name</th><td><input type="text" name="new_name" id="copy_new_name" class="regular-text" required></td></tr>
                    <tr><th>Neues Enddatum</th><td><input type="date" name="new_end_date" required><p class="description">Datum, an dem der letzte Termin der Kopie enden soll.</p></td></tr>
                </table>
                <button type="submit" class="button button-primary">Kopie erstellen</button><button type="button" class="button mh-tt-close-btn">Abbrechen</button>
            </form>
        </div></div>
        <?php
    }

    // --- Handlers ---
    public function handleSaveTermin(): void {
        check_admin_referer('termin_speichern_nonce', 'termin_speichern_nonce');
        if (strpos((string)$_POST['id'], ',') !== false) $this->terminController->processBulkSubmission($_POST);
        else $this->terminController->processFormSubmission($_POST);
        wp_redirect(admin_url('admin.php?page=termine&message=success')); exit;
    }

    public function handleSaveTimetable(): void {
        check_admin_referer('timetable_speichern_nonce', 'timetable_speichern_nonce');
        $this->timetableController->processFormSubmission($_POST);
        wp_redirect(admin_url('admin.php?page=mh-timetable&message=success')); exit;
    }

    public function handleCopyTimetable(): void {
        check_admin_referer('copy_timetable_nonce', 'copy_timetable_nonce');
        $this->timetableController->copyTimetable($_POST);
        wp_redirect(admin_url('admin.php?page=mh-timetable&message=copied')); exit;
    }

    public function handleIcalDownload(): void {
        if (!isset($_GET['download_ical'])) return;
        $id = (int)$_GET['timetable_id']; $bg = sanitize_text_field($_GET['bg'] ?? '');
        $timetable = $this->timetableRepository->find($id);
        $termine = $this->termineRepository->getByTimetableId($id);
        if (!empty($bg)) $termine = array_filter($termine, fn($t) => $t->getBildungsgang() === $bg);
        $icalService = new \MH\Timetable\Service\IcalService();
        $content = $icalService->generateString($termine, $timetable->getBezeichnung() . " - " . $bg);
        header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="timetable.ics"');
        echo $content; exit;
    }

    // (Andere Handlers für Ferien wie gehabt...)
    

    public function adminJavascript(): void {
        wp_enqueue_script('mh_tt_javascript', MH_TT_URL . 'includes/Viewer/js/mh_tt_javascript.js', ['jquery'], '1.5', true);
        wp_localize_script('mh_tt_javascript', 'mh_tt_params', ['nonce' => wp_create_nonce('mh_tt_nonce'), 'ajaxurl' => admin_url('admin-ajax.php')]);
    }

    public function customAdminStyles(): void { wp_enqueue_style('custom-admin-style', MH_TT_URL . 'includes/Viewer/css/timetable_admin.css'); }
    public function timetableEnqueueStyles(): void 
	{ 
		 // Dashicons für das Frontend laden
		wp_enqueue_style('dashicons');
    
		// Dein eigenes CSS
		 wp_enqueue_style('timetable-frontend', MH_TT_URL . 'includes/Viewer/css/timetable_css.css');
	}
    public function setupShortcodes(): void { add_shortcode('insertTimetable', [$this, 'shortcodeInsertTimetable']); }
    public function shortcodeInsertTimetable($atts, string $content = ''): string {
        $atts = shortcode_atts(['id' => 0, 'entwurf' => 'nein'], $atts); $id = (int)$atts['id'];
        $timetable = $this->timetableRepository->find($id); if (!$timetable) return '<p>Nicht gefunden.</p>';
        $termine = $this->termineRepository->getByTimetableId($id); $timetable->setTermine($termine);
        $alleFerien = $this->ferienRepository->getAll(); $ferienMap = [];
        foreach ($alleFerien as $f) { $curr = clone $f->getStartdatum(); while ($curr <= $f->getEnddatum()) { $ferienMap[$curr->format('d.m.y')] = $f->getName(); $curr->modify('+1 day'); } }
        $frontendView = new \MH\Timetable\Viewer\TimetableFrontendView($timetable, (string)$atts['entwurf'], $ferienMap);
        return $frontendView->render();
    }
	private function renderFerienForm(): void
    {
        ?>
        <div class="postbox" style="padding: 20px; margin-bottom: 20px;">
            <h3>Neue Ferien/Feiertag manuell hinzufügen</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_save_ferien">
                <?php wp_nonce_field('mh_tt_ferien_action', 'mh_tt_ferien_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label>Name</label></th>
                        <td><input type="text" name="ferien_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label>Zeitraum</label></th>
                        <td>
                            Von: <input type="date" name="startdatum" required> 
                            Bis: <input type="date" name="enddatum" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Typ</label></th>
                        <td>
                            <select name="typ">
                                <option value="Ferien">Ferien</option>
                                <option value="Feiertag">Feiertag</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary">Speichern</button></p>
            </form>
        </div>
        <?php
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
        ?>
        <div class="postbox" style="padding: 20px; margin-top: 20px;">
            <h3>Ferien & Feiertage per API importieren</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_import_ferien">
                <?php wp_nonce_field('mh_tt_import_action', 'mh_tt_import_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label>Jahr & Bundesland</label></th>
                        <td>
                            <select name="jahr">
                                <?php foreach ($years as $year) echo "<option value='$year'>$year</option>"; ?>
                            </select>
                            <select name="bundesland">
                                <?php foreach ($bundeslaender as $code => $name) echo "<option value='$code'>$name</option>"; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" class="button">Daten jetzt abrufen</button></p>
            </form>
        </div>
        <?php
    }
	
	public function handleSaveFerien(): void
    {
        check_admin_referer('mh_tt_ferien_action', 'mh_tt_ferien_nonce');
        $this->ferienController->saveFerien($_POST);
        wp_redirect(admin_url('admin.php?page=Einstellungen&message=saved'));
        exit;
    }

    public function handleImportFerien(): void
    {
        check_admin_referer('mh_tt_import_action', 'mh_tt_import_nonce');
        $count = $this->ferienController->importFromApi((int)$_POST['jahr'], sanitize_text_field($_POST['bundesland']));
        wp_redirect(admin_url('admin.php?page=Einstellungen&message=imported&count=' . $count));
        exit;
    }

    public function handleDeleteFerien(): void
    {
        $id = (int)$_GET['id'];
        check_admin_referer('delete_ferien_' . $id);
        $this->ferienController->deleteFerien($id);
        wp_redirect(admin_url('admin.php?page=Einstellungen&message=deleted'));
        exit;
    }
}