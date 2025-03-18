<?php

namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once MH_TT_PATH.'includes/controller/Einstellungen_controller.php';
require_once MH_TT_PATH.'includes/controller/Ferien_controller.php';
require_once MH_TT_PATH.'includes/viewer/Backend_List_Table_Ferien.php';


class Backend_Einstellungen {
    
    private $my_einstellungen_controller, $my_ferien_controller ;
    
    public function __construct() {
        $this->my_einstellungen_controller = new Einstellungen_controller();
		$this->my_ferien_controller = new Ferien_controller();
		add_action('init', [$this,'wp_timetable_register_taxonomy_bildungsgang']);
		add_action('init', [$this,'wp_timetable_register_taxonomy_ereignistyp']);
		//add_action('init',[$this,'wp_timetable_add_default_ereignistypen']);
		add_action('ereignistyp_edit_form_fields',[$this, 'wp_timetable_add_ereignistypen_color_field']);
		add_action('ereignistyp_add_form_fields', [$this,'wp_timetable_add_ereignistypen_color_field_new']);
        add_action('edited_ereignistyp', [$this,'wp_timetable_save_ereignistypen_color_field']);
		add_action('created_ereignistyp', [$this,'wp_timetable_save_ereignistypen_color_field']);
		add_action('wp_head', [$this,'wp_timetable_generate_dynamic_css']);
		add_action('admin_init', [$this, 'handle_ferien_form']);
		add_action('admin_init', [$this, 'handle_ferien_delete']);
		add_action('admin_init', [$this, 'handle_ferien_import']);
		
    }

	function wp_timetable_register_taxonomy_bildungsgang() {
		register_taxonomy('bildungsgang', ['Timetables'], [
			'labels' => [
				'name'              => __('Bildungsgänge', 'timetables'),
				'singular_name'     => __('Bildungsgang', 'timetables'),
				'menu_name'         => __('Bildungsgänge', 'timetables'),
				'all_items'         => __('Alle Bildungsgänge', 'timetables'),
				'edit_item'         => __('Bildungsgang bearbeiten', 'timetables'),
				'view_item'         => __('Bildungsgang ansehen', 'timetables'),
				'update_item'       => __('Bildungsgang aktualisieren', 'timetables'),
				'add_new_item'      => __('Neuen Bildungsgang hinzufügen', 'timetables'), // ÄNDERT "Schlagwort anlegen"
				'new_item_name'     => __('Name des neuen Bildungsgangs', 'timetables'), // ÄNDERT "Neues Schlagwort"
				'search_items'      => __('Bildungsgang suchen', 'timetables'),
				'popular_items'     => __('Beliebte Bildungsgänge', 'timetables'),
				'separate_items_with_commas' => __('Bildungsgänge durch Kommas trennen', 'timetables'),
				'add_or_remove_items' => __('Bildungsgang hinzufügen oder entfernen', 'timetables'),
				'choose_from_most_used' => __('Wähle aus den am häufigsten verwendeten', 'timetables'),
				'not_found'         => __('Keine Bildungsgänge gefunden.', 'timetables'),
			],
			'public'            => true,
			'hierarchical'      => false, 
			'show_admin_column' => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
		]);
	}

	
	function wp_timetable_register_taxonomy_ereignistyp() {
		register_taxonomy('ereignistyp', null, [
			'labels' => [
				'name'              => __('Ereignistypen', 'timetables'),
				'singular_name'     => __('Ereignistyp', 'timetables'),
				'menu_name'         => __('Ereignistypen', 'timetables'),
				'all_items'         => __('Alle Ereignistypen', 'timetables'),
				'edit_item'         => __('Ereignistyp bearbeiten', 'timetables'),
				'view_item'         => __('Ereignistyp ansehen', 'timetables'),
				'update_item'       => __('Ereignistyp aktualisieren', 'timetables'),
				'add_new_item'      => __('Neuen Ereignistyp hinzufügen', 'timetables'), // ÄNDERT "Schlagwort anlegen"
				'new_item_name'     => __('Name des neuen Ereignistyps', 'timetables'), // ÄNDERT "Neues Schlagwort"
				'search_items'      => __('Ereignistyp suchen', 'timetables'),
				'popular_items'     => __('Beliebte Ereignistypen', 'timetables'),
				'separate_items_with_commas' => __('Ereignistypen durch Kommas trennen', 'timetables'),
				'add_or_remove_items' => __('Ereignistyp hinzufügen oder entfernen', 'timetables'),
				'choose_from_most_used' => __('Wähle aus den am häufigsten verwendeten', 'timetables'),
				'not_found'         => __('Keine Ereignistypen gefunden.', 'timetables'),
			],
			'public'            => true,
			'hierarchical'      => false, 
			'show_admin_column' => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
		]);
	}
	
	 function wp_timetable_add_default_ereignistypen() {
    // Standard-Ereignistypen definieren
    $default_types = [
        ['name' => 'ZK', 'description' => 'Zeugniskonferenzen.', 'color' => '#704E2E'],
        ['name' => 'ZA', 'description' => 'Zeugnisausgabe', 'color' => '#2E4756'],
        ['name' => 'NE', 'description' => 'Noteneingabe', 'color' => '#7BB7AF'],
        ['name' => 'APA', 'description' => 'Allgemeiner Prüfungsausschuss.', 'color' => '#B98DA0'],
		['name' => 'SONSTIGES', 'description' => 'Sonstiges', 'color' => '#D5C3C9'],
    ];

    foreach ($default_types as $type) {
        // Prüfen, ob der Ereignistyp schon existiert
        if (!term_exists($type['name'], 'ereignistyp')) {
            // Ereignistyp mit Beschreibung anlegen
            $term = wp_insert_term($type['name'], 'ereignistyp', [
                'description' => $type['description']
            ]);

            if (!is_wp_error($term) && isset($term['term_id'])) {
                // Farbe als Metadaten speichern
                update_term_meta($term['term_id'], 'ereignisfarbe', $type['color']);
            }
        }
    }
}

	function wp_timetable_add_ereignistypen_color_field($term) {
		// Prüfen, ob $term ein Objekt ist (Bearbeiten-Modus)
		$color = '';
		if (is_object($term)) {
			$color = get_term_meta($term->term_id, 'ereignisfarbe', true);
		}
		?>
		<tr class="form-field">
			<th scope="row"><label for="ereignisfarbe"><?php _e('Anzeigefarbe', 'textdomain'); ?></label></th>
			<td>
				<input type="color" name="ereignisfarbe" id="ereignisfarbe" value="<?php echo esc_attr($color); ?>">
				<span style="color:<?php echo esc_attr($color); ?>"><?php echo esc_attr($color); ?></span>
				<p class="description"><?php _e('Wähle eine Farbe für diesen Ereignistyp.', 'textdomain'); ?></p>
			</td>
		</tr>
		<?php
	}

// Separater Callback für `add_form_fields`
	function wp_timetable_add_ereignistypen_color_field_new() {
		?>
		<div class="form-field">
			<label for="ereignisfarbe"><?php _e('Anzeigefarbe', 'textdomain'); ?></label>
			<input type="color" name="ereignisfarbe" id="ereignisfarbe" value="">
			<span style="#FFFFFF">#FFFFFF</span>
			<p class="description"><?php _e('Wähle eine Farbe für diesen Ereignistyp.', 'textdomain'); ?></p>
		</div>
		<?php
	}

	function wp_timetable_save_ereignistypen_color_field($term_id) {
		if (isset($_POST['ereignisfarbe'])) {
			update_term_meta($term_id, 'ereignisfarbe', sanitize_hex_color($_POST['ereignisfarbe']));
		}
	}
	
	function wp_timetable_generate_dynamic_css() {
    $ereignistypen = get_terms(['taxonomy' => 'ereignistyp', 'hide_empty' => false]);

    if (!empty($ereignistypen)) {
        echo '<style>';
        foreach ($ereignistypen as $ereignistyp) {
            $color = get_term_meta($ereignistyp->term_id, 'ereignisfarbe', true);
            $slug = sanitize_title($ereignistyp->name); // Name in einen slug umwandeln
            if ($color) {
                echo ".td_{$slug} { background-color: {$color} !important; color: white; }";
            }
        }
        echo '</style>';
    }
}

	public function generiere_Einstellungsseite(){
		 echo '<h1>Einstellungen</h1>';
		echo '<ul><li><a href="edit-tags.php?taxonomy=bildungsgang">Bildungsgänge verwalten</a></li>';
		echo '<li><a href="edit-tags.php?taxonomy=ereignistyp">Ereignistypen verwalten</a></li>';
		echo '</ul>';
		
		echo '<h2>Ferien</h2>';
		
		$this->render_ferien_form();
		 // Ferien-Tabelle
	    $ferien_table = new Backend_List_Table_Ferien();
		 $ferien_table->prepare_items();
		echo '<form method="post">';
		 $ferien_table->display();
		echo '</form>';
		$this->render_ferien_import_form();
	
	}
	function timetable_custom_admin_css() {
    echo '<style>
        #toplevel_page_mh-timetable ul.wp-submenu li:last-child {
            margin-left: 15px; /* Einrücken, damit es untergeordnet aussieht */
        }
    </style>';
	}	

	public static function on_activate() {
		error_log("🚀 Plugin wurde aktiviert!");
		$settings = new self(); // Neues Objekt erstellen
		$settings->wp_timetable_register_taxonomy_ereignistyp(); // Taxonomie registrieren
		$settings->wp_timetable_add_default_ereignistypen(); // Standard-Ereignistypen hinzufügen
	}
	
	private function render_ferien_form() {
		?>
		<h3>Neue Ferien hinzufügen</h3>
		<form method="post">
			<label for="ferien_name">Name:</label>
			<input type="text" name="ferien_name" required>

			<label for="startdatum">Startdatum:</label>
			<input type="date" name="startdatum" required>

			<label for="enddatum">Enddatum:</label>
			<input type="date" name="enddatum" required>

			<label for="typ">Typ:</label>
			<select name="typ" required>
				<option value="ferien" <?php selected($_POST['typ'] ?? '', 'ferien'); ?>>Ferien</option>
				<option value="feiertag" <?php selected($_POST['typ'] ?? '', 'feiertag'); ?>>Feiertag</option>
			</select>

			<input type="hidden" name="action" value="save_ferien">
			<?php wp_nonce_field('save_ferien_action', 'save_ferien_nonce'); ?>

			<button type="submit" class="button button-primary">Speichern</button>
		</form>
		<hr>
		<?php
	}

	public function handle_ferien_form() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ferien') {
			if (!isset($_POST['save_ferien_nonce']) || !wp_verify_nonce($_POST['save_ferien_nonce'], 'save_ferien_action')) {
				die('Sicherheitsüberprüfung fehlgeschlagen.');
			}
				$ferieneintrag = [
			'name'       => sanitize_text_field($_POST['ferien_name']),
			'startdatum' => sanitize_text_field($_POST['startdatum']),
			'enddatum'   => sanitize_text_field($_POST['enddatum']),
			'typ'        => sanitize_text_field($_POST['typ'])
		];

			$this->my_ferien_controller->add_object($ferieneintrag);
			

			wp_redirect(admin_url('admin.php?page=Einstellungen'));
			exit;
		}
	}
	
	public function handle_ferien_delete() {
    if (isset($_GET['action']) && $_GET['action'] === 'delete_ferien' && isset($_GET['id'])) {
        // Sicherheitsprüfung mit Nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_ferien_' . $_GET['id'])) {
            wp_die(__('Sicherheitsprüfung fehlgeschlagen.', 'timetables'));
        }

        $id = intval($_GET['id']);
        $this->my_ferien_controller->delete_object($id);

        // Nach dem Löschen zurückleiten
        wp_redirect(admin_url('admin.php?page=Einstellungen'));
        exit;
    }
	}	
	private function render_ferien_import_form() {
		$years = range(date('Y'), date('Y') + 5); // Aktuelles Jahr + 5 Jahre
		$bundeslaender = [
			"NW" => "Nordrhein-Westfalen",
			"BW" => "Baden-Württemberg",
			"BY" => "Bayern",
			"BE" => "Berlin",
			"BB" => "Brandenburg",
			"HB" => "Bremen",
			"HH" => "Hamburg",
			"HE" => "Hessen",
			"MV" => "Mecklenburg-Vorpommern",
			"NI" => "Niedersachsen",
			"RP" => "Rheinland-Pfalz",
			"SL" => "Saarland",
			"SN" => "Sachsen",
			"ST" => "Sachsen-Anhalt",
			"SH" => "Schleswig-Holstein",
			"TH" => "Thüringen"
		];
		?>
		<h3>Ferien per API abrufen</h3>
		<form method="post">
			<label for="jahr">Jahr:</label>
			<select name="jahr">
				<?php foreach ($years as $year): ?>
					<option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="bundesland">Bundesland:</label>
			<select name="bundesland">
				<?php foreach ($bundeslaender as $code => $name): ?>
					<option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
				<?php endforeach; ?>
			</select>

			<input type="hidden" name="action" value="fetch_ferien">
			<?php wp_nonce_field('fetch_ferien_action', 'fetch_ferien_nonce'); ?>

			<button type="submit" class="button button-primary">Ferien abrufen</button>
		</form>
		<hr>
		<?php
	}
	
	public function handle_ferien_import() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_ferien') {
			if (!isset($_POST['fetch_ferien_nonce']) || !wp_verify_nonce($_POST['fetch_ferien_nonce'], 'fetch_ferien_action')) {
				wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', 'timetables'));
			}

			$jahr = intval($_POST['jahr']);
			$bundesland = sanitize_text_field($_POST['bundesland']);

			// 🟢 Ferien API abrufen
			$ferien_api_url = "https://ferien-api.de/api/v1/holidays/{$bundesland}/{$jahr}";
			$ferien_response = wp_remote_get($ferien_api_url);
			$ferien_daten = json_decode(wp_remote_retrieve_body($ferien_response), true);

			// 🟢 Feiertage API abrufen
			$feiertage_api_url = "https://feiertage-api.de/api/?jahr={$jahr}&nur_land={$bundesland}";
			$feiertage_response = wp_remote_get($feiertage_api_url);
			$feiertage_daten = json_decode(wp_remote_retrieve_body($feiertage_response), true);

			// ❌ Fehlerbehandlung, falls keine Daten geladen werden konnten
			if (empty($ferien_daten) && empty($feiertage_daten)) {
				echo '<div class="error"><p>Keine Daten für das gewählte Jahr/Bundesland gefunden.</p></div>';
				return;
			}

			// 🔹 Ferien speichern
			if (!empty($ferien_daten)) {
				foreach ($ferien_daten as $ferien) {
					$ferieneintrag = [
						'name'       => sanitize_text_field($ferien['name']),
						'startdatum' => sanitize_text_field($ferien['start']),
						'enddatum'   => sanitize_text_field($ferien['end']),
						'typ'        => 'Ferien'
					];
					$this->my_ferien_controller->add_object($ferieneintrag);
				}
			}

			// 🔹 Feiertage speichern
			if (!empty($feiertage_daten)) {
				foreach ($feiertage_daten as $feiertag_name => $feiertag) {
					$feiertagseintrag = [
						'name'       => sanitize_text_field($feiertag_name), // Name des Feiertags (z. B. "Ostermontag")
						'startdatum' => sanitize_text_field($feiertag['datum']), // Datum des Feiertags
						'enddatum'   => sanitize_text_field($feiertag['datum']), // Ist gleich Startdatum
						'typ'        => 'Feiertag'
					];
					$this->my_ferien_controller->add_object($feiertagseintrag);
				}
			}

			echo '<div class="updated"><p>Ferien & Feiertage wurden erfolgreich gespeichert.</p></div>';
		}
	}

}

	

