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

class View
{
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

    /**
     * Registriert alle WordPress Hooks.
     */
    private function initHooks(): void
    {
        // Admin Menü
        add_action('admin_menu', [$this, 'createMenu']);
        
        // Assets
        add_action('admin_enqueue_scripts', [$this, 'adminJavascript']);
        add_action('admin_enqueue_scripts', [$this, 'customAdminStyles']);
        add_action('wp_enqueue_scripts', [$this, 'timetableEnqueueStyles']);

        // AJAX & Shortcodes
        add_action('wp_ajax_print_timetable', [$this, 'printTimetableCallback']);
        add_action('init', [$this, 'setupShortcodes']);
        
        // Formular-Handling (CSV)
        add_action('admin_post_handle_csv_upload', [$this, 'handleCsvUploadCallback']);
		
		 // Formular-Handler für Ferien
		add_action('admin_post_mh_tt_save_ferien', [$this, 'handleSaveFerien']);
		add_action('admin_post_mh_tt_import_ferien', [$this, 'handleImportFerien']);
    
		// Lösch-Aktion (falls über GET gelöst)
		 if (isset($_GET['action']) && $_GET['action'] === 'delete_ferien') {
			 add_action('admin_init', [$this, 'handleDeleteFerien']);
			 }
	   //Speichern von Terminen
		add_action('admin_post_mh_tt_save_termin', [$this, 'handleSaveTermin']);
		add_action('admin_post_mh_tt_save_timetable', [$this, 'handleSaveTimetable']);
	
		// Hook für das Löschen von Timetables (falls über GET)
		 if (isset($_GET['action']) && $_GET['action'] === 'delete_timetable') {
		add_action('admin_init', [$this, 'handleDeleteTimetable']);
		 }
		 
		 add_action('init', [$this, 'handleIcalDownload']);
		 add_action('admin_post_mh_tt_copy_timetable', [$this, 'handleCopyTimetable']);
    }

    public function createMenu(): void
    {
        add_menu_page(
            'Timetable',
            'Timetables',
            'manage_options',
            'mh-timetable',
            [$this, 'renderTimetablePage'],
            'dashicons-clock',
            30
        );

        add_submenu_page(
            'mh-timetable',
            'Termine',
            'Termine',
            'manage_options',
            'termine',
            [$this, 'renderTerminePage']
        );

        add_submenu_page(
            'mh-timetable',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'Einstellungen',
            [$this, 'renderEinstellungen']
        );
    }

    public function renderTimetablePage(): void
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">Timetables verwalten</h1>';
		echo '<button class="page-title-action mh-tt-timetable-add-btn">+ Neue Timetable</button>';
		echo '<hr class="wp-header-end">';

		$table = new \MH\Timetable\Viewer\ListTable\TimetablesListTable($this->timetableController);
		$table->prepare_items();
		$table->display();
		echo '</div>';

		$this->renderTimetableModalContainer();
		$this->renderCopyModal(); // NEU: Das Kopier-Modal
	}

	public function renderTerminePage(): void
	{
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">Terminübersicht</h1>';
		echo '<button class="page-title-action mh-tt-add-btn">+ Neuen Termin anlegen</button>';
		echo '<hr class="wp-header-end">';

		// Tabelle initialisieren (jetzt mit 3 Argumenten)
		$table = new \MH\Timetable\Viewer\ListTable\TermineListTable(
			$this->terminController, 
			$this->timetableController,
			$this->taxonomyService
		);

		// WICHTIG: Formular für Filter und Bulk-Actions
		echo '<form method="get">';
		// Wir müssen die 'page' mitschicken, damit WP weiß, wo wir sind
		echo '<input type="hidden" name="page" value="termine">';

		$table->prepare_items();
		$table->display();
		echo '</form>';

		echo '</div>';
		$this->renderModalContainer();
	}
	
	private function renderTimetableModalContainer(): void
	{
		?>
		<div id="mh-tt-timetable-modal" class="mh-tt-modal" style="display:none;">
			<div class="mh-tt-modal-content">
				<span class="mh-tt-close">&times;</span>
				<h3 id="tt-modal-title">Zeittafel bearbeiten</h3>
				<form id="mh-tt-timetable-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="mh_tt_save_timetable">
					<input type="hidden" name="id" id="tt_f_id" value="">
					<?php wp_nonce_field('timetable_speichern_nonce', 'timetable_speichern_nonce'); ?>
					<table class="form-table">
						<tr>
							<th>Bezeichnung</th>
							<td><input type="text" name="bezeichnung" id="tt_f_bezeichnung" class="regular-text" required></td>
						</tr>
						<tr>
							<th>Beschreibung</th>
							<td><textarea name="beschreibung" id="tt_f_beschreibung" class="regular-text" rows="3"></textarea></td>
						</tr>
					</table>
					<button type="submit" class="button button-primary">Speichern</button>
				</form>
			</div>
		</div>
		<?php
	}

    private function createCsvUploadForm(): string
    {
        $url = admin_url('admin-post.php');
        return "
            <form enctype='multipart/form-data' method='post' action='{$url}'>
                <input type='hidden' name='action' value='handle_csv_upload'>
                <input type='file' name='csv_file' accept='.csv'><br><br>
                <input type='checkbox' name='loeschen' value='loeschen'> Bisherige Einträge löschen<br><br>
                <input type='submit' class='button button-primary' value='Upload CSV'>
            </form>
        ";
    }

    /**
     * Delegiert den CSV Upload an den Controller.
     */
    public function handleCsvUploadCallback(): void
    {
        $result = $this->terminController->handleCsvUpload();
        wp_redirect(admin_url('admin.php?page=termine&message=' . urlencode($result)));
        exit;
    }

    public function setupShortcodes(): void
    {
        add_shortcode('insertTimetable', [$this, 'shortcodeInsertTimetable']);
    }

    public function shortcodeInsertTimetable($atts, string $content = ''): string
    {
        $atts = shortcode_atts(['id' => 0, 'entwurf' => 'nein'], $atts);
        $id = (int)$atts['id'];

        $timetable = $this->timetableRepository->find($id);
        if (!$timetable) return '<p>Timetable nicht gefunden.</p>';

        $termine = $this->termineRepository->getByTimetableId($id);
        $timetable->setTermine($termine);

        // 🟢 Ferien laden und für das Grid mappen
        $alleFerien = $this->ferienRepository->getAll();
        $ferienMap = [];
        foreach ($alleFerien as $f) {
            $current = clone $f->getStartdatum();
            while ($current <= $f->getEnddatum()) {
                $ferienMap[$current->format('d.m.y')] = $f->getName();
                $current->modify('+1 day');
            }
        }
		  // 🟢 View mit Ferien initialisieren
        $frontendView = new \MH\Timetable\Viewer\TimetableFrontendView($timetable, (string)$atts['entwurf'], $ferienMap);
        return $frontendView->render();
    }

    // --- Assets ---
   public function adminJavascript(): void
	{
		// 1. Stelle: Registrierung
		wp_enqueue_script(
			'mh_tt_javascript', 
			MH_TT_URL . 'includes/Viewer/js/mh_tt_javascript.js', 
			['jquery'], 
			'1.4', // Version auf 1.4 erhöhen!
			true
		);

		// 2. Stelle: Daten-Übergabe (MUSS der gleiche Name wie oben sein)
		wp_localize_script('mh_tt_javascript', 'mh_tt_params', [
			'nonce'   => wp_create_nonce('mh_tt_nonce'),
			'ajaxurl' => admin_url('admin-ajax.php') // Das hier ist lebenswichtig
		]);
	}

    public function customAdminStyles(): void
    {
        wp_enqueue_style('custom-admin-style', MH_TT_URL . 'includes/Viewer/css/timetable_admin.css');
    }

    public function timetableEnqueueStyles(): void
    {
        wp_enqueue_style('timetable-frontend', MH_TT_URL . 'includes/Viewer/css/timetable_css.css');
    }

    // Platzhalter für die Tabellen-Logik (müssen wir noch namespacen)
    private function createBackendTable(string $typ): void
    {
        echo "<p>Tabelle für $typ wird geladen...</p>";
    }

    private function renderTerminBearbeiten(): void { /* ... */ }
    private function renderTimetableBearbeiten(): void { /* ... */ }
    public function renderEinstellungen(): void {
		echo '<div class="wrap">';
		echo '<h1>Einstellungen</h1>';

		echo '<h2>Taxonomien</h2>';
		echo '<ul>';
		echo '<li><a href="edit-tags.php?taxonomy=bildungsgang" class="button">Bildungsgänge verwalten</a></li>';
		echo '<li><a href="edit-tags.php?taxonomy=ereignistyp" class="button">Ereignistypen verwalten</a></li>';
		echo '</ul>';

		echo '<h2>Ferien & Feiertage</h2>';

		// 1. Formular für neue Ferien (kann in eigene Methode)
		$this->renderFerienForm();

		// 2. Die neue List Table anzeigen
		$ferienTable = new \MH\Timetable\Viewer\ListTable\FerienListTable($this->ferienController);
		$ferienTable->prepare_items();

		echo '<form method="post">';
		$ferienTable->display();
		echo '</form>';

		// 3. API Import Formular
		$this->renderFerienImportForm();

		echo '</div>';
	
	}
    public function printTimetableCallback(): void { /* ... */ }
	
	/**
     * Rendert das Formular für die manuelle Eingabe von Ferien.
     */
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

    /**
     * Rendert das Formular für den API-Import.
     */
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
        <div class="postbox" style="padding: 20px;">
            <h3>Ferien & Feiertage per API importieren</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mh_tt_import_ferien">
                <?php wp_nonce_field('mh_tt_import_action', 'mh_tt_import_nonce'); ?>

                <select name="jahr">
                    <?php foreach ($years as $year) echo "<option value='$year'>$year</option>"; ?>
                </select>

                <select name="bundesland">
                    <?php foreach ($bundeslaender as $code => $name) echo "<option value='$code'>$name</option>"; ?>
                </select>

                <button type="submit" class="button">Daten jetzt abrufen</button>
            </form>
        </div>
        <?php
    }
	
	public function handleSaveFerien(): void
    {
        check_admin_referer('mh_tt_ferien_action', 'mh_tt_ferien_nonce');

        $data = [
            'name'       => $_POST['ferien_name'],
            'startdatum' => $_POST['startdatum'],
            'enddatum'   => $_POST['enddatum'],
            'typ'        => $_POST['typ']
        ];

        $this->ferienController->saveFerien($data);
        wp_redirect(admin_url('admin.php?page=Einstellungen&message=saved'));
        exit;
    }

    public function handleImportFerien(): void
    {
        check_admin_referer('mh_tt_import_action', 'mh_tt_import_nonce');

        $count = $this->ferienController->importFromApi(
            (int)$_POST['jahr'],
            sanitize_text_field($_POST['bundesland'])
        );

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
	private function renderModalContainer(): void
	{
		$ereignistypen = $this->taxonomyService->getEreignistypen();
		$timetables    = $this->timetableController->getTimetablesForDropdown();

	   ?>
	   <div id="mh-tt-modal" class="mh-tt-modal" style="display:none;">
		   <div class="mh-tt-modal-content">
			   <span class="mh-tt-close">&times;</span>
			   <h3 id="modal-title">Termin bearbeiten</h3>

			   <form id="mh-tt-termin-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				   <input type="hidden" name="action" value="mh_tt_save_termin">
				   <input type="hidden" name="id" id="f_id" value="">
				   <?php wp_nonce_field('termin_speichern_nonce', 'termin_speichern_nonce'); ?>

				   <table class="form-table">
					   <tr>
						   <th><label>Timetable</label></th>
						   <td>
							   <select name="timetable_ID" id="f_timetable_id" required>
								   <option value="">-- Bitte wählen --</option>
								   <?php foreach ($timetables as $tt): ?>
									   <option value="<?php echo $tt['id']; ?>"><?php echo esc_html($tt['id'] . ' | ' . $tt['bezeichnung']); ?></option>
								   <?php endforeach; ?>
							   </select>
						   </td>
					   </tr>
					   <tr>
							<th><label>Bildungsgang</label></th>
							<td>
								<select name="bildungsgang" id="f_bildungsgang" required>
									<option value="">-- Bitte wählen --</option>
									<?php foreach ($this->taxonomyService->getBildungsgaenge() as $bg): ?>
										<option value="<?php echo esc_attr($bg->name); ?>"><?php echo esc_html($bg->name); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					   <tr>
						   <th><label>Bezeichnung</label></th>
						   <td><input type="text" name="bezeichnung" id="f_bezeichnung" class="regular-text" required></td>
					   </tr>
					   <tr>
						   <th><label>Ereignistyp</label></th>
						   <td>
							   <select name="ereignistyp" id="f_ereignistyp" required>
								   <?php foreach ($ereignistypen as $type): ?>
									   <option value="<?php echo esc_attr($type->name); ?>"><?php echo esc_html($type->name); ?></option>
								   <?php endforeach; ?>
							   </select>
						   </td>
					   </tr>
					   <tr>
						   <th><label>Zeitraum</label></th>
						   <td>
							   Von: <input type="date" name="beginn" id="f_beginn" required> 
							   Bis: <input type="date" name="ende" id="f_ende" required>
						   </td>
					   </tr>
					   <tr>
						   <th><label>Verantwortlich</label></th>
						   <td><input type="text" name="verantwortlich" id="f_verantwortlich" class="regular-text"></td>
					   </tr>
				   </table>

				   <div style="margin-top:20px;">
					   <button type="submit" class="button button-primary" id="f_submit">Speichern</button>
					   <button type="button" class="button mh-tt-close-btn">Abbrechen</button>
				   </div>
			   </form>
		   </div>
	   </div>
	   <?php
   }
   
   public function handleSaveTermin(): void
	{
		// Sicherheit prüfen
		 check_admin_referer('termin_speichern_nonce', 'termin_speichern_nonce');

		$idField = $_POST['id'] ?? '';

		if (strpos($idField, ',') !== false) {
			// Es ist ein Bulk-Edit
			$this->terminController->processBulkSubmission($_POST);
			$message = 'bulk_success';
		} else {
			// Normaler Save (Neu oder Einzel-Edit)
			$errors = $this->terminController->processFormSubmission($_POST);
			$message = empty($errors) ? 'success' : 'error';
		}

		wp_redirect(admin_url('admin.php?page=termine&message=' . $message));
		exit;
	}
	/**
 * Verarbeitet das POST-Signal vom Timetable-Modal.
 */
	public function handleSaveTimetable(): void
	{
		// 1. Sicherheit: Nonce prüfen (muss zum Hidden-Field im Modal passen)
		check_admin_referer('timetable_speichern_nonce', 'timetable_speichern_nonce');

		// 2. Daten an den Controller übergeben
		// Der Controller kümmert sich um Sanitize, Validation und Repository-Update
		$errors = $this->timetableController->processFormSubmission($_POST);

		// 3. Feedback-Status ermitteln
		$status = empty($errors) ? 'success' : 'error';

		// 4. Zurück zur Zeittafel-Liste leiten
		wp_redirect(admin_url('admin.php?page=mh-timetable&message=' . $status));
		exit;
	}
	
	public function handleIcalDownload(): void
	{
		if (!isset($_GET['download_ical'])) return;

		$id = (int)$_GET['timetable_id'];
		$bg = sanitize_text_field($_GET['bg'] ?? '');

		$timetable = $this->timetableRepository->find($id);
		$termine = $this->termineRepository->getByTimetableId($id);

		// Filtern nach Bildungsgang, falls angegeben
		if (!empty($bg)) {
			$termine = array_filter($termine, fn($t) => $t->getBildungsgang() === $bg);
		}

		$icalService = new \MH\Timetable\Service\IcalService();
		$content = $icalService->generateString($termine, $timetable->getBezeichnung() . " - " . $bg);

		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="timetable.ics"');
		echo $content;
		exit;
	}
	
	private function renderCopyModal(): void
	{
		?>
		<div id="mh-tt-copy-modal" class="mh-tt-modal" style="display:none;">
			<div class="mh-tt-modal-content">
				<span class="mh-tt-close">&times;</span>
				<h3>Timetable kopieren & verschieben</h3>
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
					<input type="hidden" name="action" value="mh_tt_copy_timetable">
					<input type="hidden" name="source_id" id="copy_source_id">
					<?php wp_nonce_field('copy_timetable_nonce', 'copy_timetable_nonce'); ?>

					<p>Neuer Name:<br><input type="text" name="new_name" id="copy_new_name" class="regular-text" required></p>
					<p>Neues Enddatum (Referenz):<br><input type="date" name="new_end_date" required></p>
					<p class="description">Alle Termine werden so verschoben, dass der letzte Termin am gewählten Datum endet.</p>

					<button type="submit" class="button button-primary">Kopie erstellen</button>
				</form>
			</div>
		</div>
		<?php
	}
	public function handleCopyTimetable(): void
		{
			check_admin_referer('copy_timetable_nonce', 'copy_timetable_nonce');

			$success = $this->timetableController->copyTimetable($_POST);

			$status = $success ? 'copied' : 'error';
			wp_redirect(admin_url('admin.php?page=mh-timetable&message=' . $status));
			exit;
		}
}