<?php
declare(strict_types=1);

namespace MH\Timetable\Service;

/**
 * Service zur Verwaltung von WordPress-Taxonomien und zugehörigem CSS.
 */
class TaxonomyService
{
    public function registerTaxonomies(): void
    {
        // 1. Bildungsgänge
        register_taxonomy('bildungsgang', [], [
            'labels' => [
                'name'              => 'Bildungsgänge',
                'singular_name'     => 'Bildungsgang',
                'menu_name'         => 'Bildungsgänge',
                'all_items'         => 'Alle Bildungsgänge',
                'edit_item'         => 'Bildungsgang bearbeiten',
                'add_new_item'      => 'Neuen Bildungsgang hinzufügen',
                'new_item_name'     => 'Name des neuen Bildungsgangs',
            ],
            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
        ]);

        // 2. Ereignistypen
        register_taxonomy('ereignistyp', [], [
            'labels' => [
                'name'              => 'Ereignistypen',
                'singular_name'     => 'Ereignistyp',
                'menu_name'         => 'Ereignistypen',
                'all_items'         => 'Alle Ereignistypen',
                'edit_item'         => 'Ereignistyp bearbeiten',
                'add_new_item'      => 'Neuen Ereignistyp hinzufügen',
            ],
            'public'            => true,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
        ]);
		 // Nach der Registrierung die Defaults prüfen
		$this->addDefaultTerms();
		$this->initTaxonomyHooks();
        $this->initTaxonomyHooks();
    }

    /**
     * Registriert Hooks für die Farbfelder in den Taxonomien.
     */
    private function initTaxonomyHooks(): void
    {
        // Felder hinzufügen (beim Erstellen und Bearbeiten)
        add_action('ereignistyp_edit_form_fields', [$this, 'renderColorField']);
        add_action('ereignistyp_add_form_fields', [$this, 'renderColorFieldNew']);
        
        // Speichern der Felder
        add_action('edited_ereignistyp', [$this, 'saveColorField']);
        add_action('created_ereignistyp', [$this, 'saveColorField']);
    }

    public function renderColorField($term): void
    {
        $color = get_term_meta($term->term_id, 'ereignisfarbe', true) ?: '#ffffff';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="ereignisfarbe">Anzeigefarbe</label></th>
            <td>
                <input type="color" name="ereignisfarbe" id="ereignisfarbe" value="<?php echo esc_attr($color); ?>">
                <p class="description">Wähle eine Farbe für diesen Ereignistyp im GANTT-Grid.</p>
            </td>
        </tr>
        <?php
    }

    public function renderColorFieldNew(): void
    {
        ?>
        <div class="form-field">
            <label for="ereignisfarbe">Anzeigefarbe</label>
            <input type="color" name="ereignisfarbe" id="ereignisfarbe" value="#ffffff">
            <p class="description">Wähle eine Farbe für diesen Ereignistyp.</p>
        </div>
        <?php
    }

    public function saveColorField($term_id): void
    {
        if (isset($_POST['ereignisfarbe'])) {
            update_term_meta($term_id, 'ereignisfarbe', sanitize_hex_color($_POST['ereignisfarbe']));
        }
    }

    /**
     * Generiert das dynamische CSS für das Frontend/Backend.
     */
    public function generateDynamicCss(): void
    {
        $terms = get_terms(['taxonomy' => 'ereignistyp', 'hide_empty' => false]);
        if (empty($terms) || is_wp_error($terms)) return;

        echo '<style id="mh-timetable-dynamic-css">';
        foreach ($terms as $term) {
            $color = get_term_meta($term->term_id, 'ereignisfarbe', true);
            if ($color) {
                $slug = sanitize_title($term->name);
                echo ".td_{$slug} { background-color: {$color} !important; color: white; }\n";
            }
        }
        echo '</style>';
    }

    /**
     * Legt Standard-Ereignistypen an, falls diese noch nicht existieren.
     */
    public function addDefaultTerms(): void
	{
		$defaults = [
			['name' => 'ZK', 'desc' => 'Zeugniskonferenz', 'color' => '#704E2E'],
			['name' => 'ZA', 'desc' => 'Zeugnisausgabe', 'color' => '#2E4756'],
			['name' => 'NE', 'desc' => 'Noteneingabe', 'color' => '#7BB7AF'],
			['name' => 'APA', 'desc' => 'Allgemeiner Prüfungsausschuss', 'color' => '#B98DA0'],
		];

		foreach ($defaults as $type) {
			if (!term_exists($type['name'], 'ereignistyp')) {
				$term = wp_insert_term($type['name'], 'ereignistyp', [
					'description' => $type['desc']
				]);

				if (!is_wp_error($term) && isset($term['term_id'])) {
					update_term_meta($term['term_id'], 'ereignisfarbe', $type['color']);
				}
			}
		}
	}
	
		/**
	 * Holt alle registrierten Ereignistypen für das Dropdown.
	 */
		public function getEreignistypen(): array
		{
			$terms = get_terms([
				'taxonomy'   => 'ereignistyp',
				'hide_empty' => false,
			]);

			return is_wp_error($terms) ? [] : $terms;
		}
		
		public function getBildungsgaenge(): array
		{
			$terms = get_terms(['taxonomy' => 'bildungsgang', 'hide_empty' => false]);
			return is_wp_error($terms) ? [] : $terms;
		}

}