<?php

namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once MH_TT_PATH.'includes/controller/Einstellungen_controller.php';


class Backend_Einstellungen {
    
    private $my_einstellungen_controller;
    
    public function __construct() {
        $this->my_einstellungen_controller = new Einstellungen_controller();
		add_action('init', [$this,'wp_timetable_register_taxonomy_bildungsgang']);
		add_action('init', [$this,'wp_timetable_register_taxonomy_ereignistyp']);
		//add_action('init',[$this,'wp_timetable_add_default_ereignistypen']);
		add_action('ereignistyp_edit_form_fields',[$this, 'wp_timetable_add_ereignistypen_color_field']);
		add_action('ereignistyp_add_form_fields', [$this,'wp_timetable_add_ereignistypen_color_field_new']);
        add_action('edited_ereignistyp', [$this,'wp_timetable_save_ereignistypen_color_field']);
		add_action('created_ereignistyp', [$this,'wp_timetable_save_ereignistypen_color_field']);
		add_action('wp_head', [$this,'wp_timetable_generate_dynamic_css']);
		
		
		
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
	
}
