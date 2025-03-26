<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once MH_TT_PATH. 'includes/controller/Termin_controller.php';
require_once MH_TT_PATH. 'includes/controller/Timetable_controller.php';
require_once MH_TT_PATH. 'includes/controller/Einstellungen_controller.php';

class Backend_List_Table_Termine extends \WP_List_Table {
	
	private $data;
	private $my_termin_controller;
	private $my_timetable_controller;
	private $my_einstellungen_controller;
	
    public function __construct() {
        parent::__construct([
            'singular' => 'custom_item',
            'plural'   => 'custom_items',
            'ajax'     => false,
			'screen'   => null
        ]);
		
		$this->my_termin_controller=new Termin_controller();
		$this->my_timetable_controller=new Timetable_controller();
		$this->my_einstellungen_controller=new Einstellungen_controller();

	}
   
	public function set_data($data){
		$this->data = $data;
	}
	public function prepare_items() {
		$user_id = get_current_user_id();

		// 🟢 ZUERST prüfen, ob zurückgesetzt werden soll
		if (!empty($_POST['reset_filter'])) {
			delete_user_meta($user_id, 'filter_bildungsgang');
			delete_user_meta($user_id, 'filter_ereignistyp');
			delete_user_meta($user_id, 'filter_timetable');

			// Zurücksetzen in lokalen Variablen sicherstellen
			$filter_bildungsgang = '';
			$filter_ereignistyp  = '';
			$filter_timetable    = '';
		}

		// 📥 Wenn POST-Filter gesetzt sind, speichern
		elseif (!empty($_POST['filter_action'])) {
			$filter_bildungsgang = sanitize_text_field($_POST['filter_bildungsgang'] ?? '');
			$filter_ereignistyp  = sanitize_text_field($_POST['filter_ereignistyp'] ?? '');
			$filter_timetable    = sanitize_text_field($_POST['filter_timetable'] ?? '');

			update_user_meta($user_id, 'filter_bildungsgang', $filter_bildungsgang);
			update_user_meta($user_id, 'filter_ereignistyp', $filter_ereignistyp);
			update_user_meta($user_id, 'filter_timetable', $filter_timetable);
		}

		// 🔄 Andernfalls: aus User-Meta lesen
		else {
			$filter_bildungsgang = get_user_meta($user_id, 'filter_bildungsgang', true) ?: '';
			$filter_ereignistyp  = get_user_meta($user_id, 'filter_ereignistyp', true) ?: '';
			$filter_timetable    = get_user_meta($user_id, 'filter_timetable', true) ?: '';
		}

		// 📦 Daten laden
		$this->items = $this->my_termin_controller->get_filtered_termine(
			$filter_timetable, $filter_bildungsgang, $filter_ereignistyp
		);

		// Spalten etc.
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);

		usort($this->items, array(&$this, 'sort_data'));
	}
	
	public function get_columns(){
		$columns = array(
			 'cb' => '<input type="checkbox" />',  // Checkbox-Spalte für Mehrfachauswahl
			'id' => 'ID',
			'bildungsgang' => 'Bildungsgang',
			'bezeichnung' => 'Bezeichnung',
			'ereignistyp' => 'Ereignistyp',
			'beginn' => 'Beginn',
			'ende' => 'Ende',
			'verantwortlich' => 'Verantwortlich',
			'timetable_ID' => 'Timetable',
			'actions' => 'Aktionen'
		);
		return $columns;
		
	}
	
	public function column_default($item, $column_name){
		switch($column_name){
			case 'id':
			case 'bildungsgang':
			case 'bezeichnung':
			case 'ereignistyp':
			case 'beginn':
			case 'ende':
			case 'verantwortlich':
			case 'timetable_ID':
				return $item[$column_name];
			case 'actions':
			default:
				return "no value";
		}
	}
	
	public function column_cb($item) {
    return sprintf(
        '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
    );
}

	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Löschen',
			'bulk-edit' => 'Mehrfachänderung'
		];
		return $actions;
}
	//Aktionen in der der letzten Tabellenspalte Aktionen
	public function column_actions($item){
    $nonce_field_edit=wp_nonce_field( 'termin_edit_nonce', 'termin_edit_nonce' );
    echo sprintf(
        '<form enctype="multipart/form-data" method="post" action="" style="display: inline-block;">
				<input type="hidden" name="id" value="%s">
			%s	
            <button type="submit" name="edit_termin_button">Edit</button>
        </form>',
        $item['id'],
		$nonce_field_edit
		
    );
	$nonce_field_loeschen=wp_nonce_field('termin_loeschen_nonce','termin_loeschen_nonce');
    echo sprintf(
        '<form enctype="multipart/form-data" method="post" action="" style="display: inline-block;">
			<input type="hidden" name="id" value="%s">
			%s	
            <button type="submit" name="loeschen_termin_button">Löschen</button>
        </form>',
        $item['id'],
		$nonce_field_loeschen
    );
}
	
	   // Diese Methode fügt eine benutzerdefinierte Aktion für das Hinzufügen eines neuen Datensatzes hinzu
  /*  public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '</div>';
        }
	}*/
	
	 public function get_sortable_columns()
    {
       return array(	'id' => array('id',true),
						'bildungsgang' => array('bildungsgang', false),
						'timetable_ID' => array('timetable_ID',false),
						'beginn'	=> array('beginn',false)
			);
	    	    
		
    }
	
	 /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

	  /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
	{
		// Set defaults
		$sorting = $this->get_sorting_preferences();
		if (!empty($sorting)){
			$orderby = $sorting['orderby'];
			$order = $sorting['order'];
		}
		else{
			$orderby = 'id';
			$order = 'asc';
		}

		// If orderby is set, use this as the sort column
		if(!empty($_GET['orderby']))
		{
			$orderby = $_GET['orderby'];
		}

		// If order is set use this as the order
		if(!empty($_GET['order']))
		{
			 $order = $_GET['order'];
			 $this->save_sorting_preferences(esc_attr($_GET['orderby']), esc_attr($_GET['order']));
		}

		// Convert string IDs to integers for numerical comparison
		$idA = intval($a['id']);
		$idB = intval($b['id']);

		// Perform numerical comparison
		if($orderby === 'id') {
			$result = $idA - $idB;
		} else {
			$result = strcmp( $a[$orderby], $b[$orderby] );
		}

		// Adjust result based on sort order
		if($order === 'asc')
		{
			return $result;
		}

		return -$result;
	}
	
	//Funktion zum Speichern der der Sortierreihenfolge in Meta-User von WP
	function save_sorting_preferences($orderby, $order) {
		$user_id = get_current_user_id();
		if ($user_id) {
			update_user_meta($user_id, 'termine_orderby', $orderby);
			update_user_meta($user_id, 'termine_order', $order);
		}
	}
	
	//Funktion zum Speichern der der Sortierreihenfolge in Meta-User von WP
	function get_sorting_preferences() {
		$user_id = get_current_user_id();
		if ($user_id) {
			$orderby = get_user_meta($user_id, 'termine_orderby', true);
			$order = get_user_meta($user_id, 'termine_order', true);

			return [
				'orderby' => $orderby ?: 'id', // Standardwert 'id' verwenden, wenn kein Wert gespeichert wurde
				'order' => $order ?: 'asc',     // Standardwert 'asc' verwenden
			];
		}
		return ['orderby' => 'id', 'order' => 'asc'];
	}
	
	//Methode für die Durchführung der Bulk-Actions
		public function process_bulk_action() {
		// Prüfe, ob die "Löschen"-Aktion ausgeführt wird
		if ('bulk-delete' === $this->current_action()) {
			// Hole die ausgewählten IDs aus dem POST-Request
			
			
			if(!empty($_POST['bulk-delete'])){
			$delete_ids = $_POST['bulk-delete'];	
				echo '<div class="form_success">Die Termine mit den IDs ';
				// Lösche jeden Eintrag anhand der ID
				foreach ($delete_ids as $id) {
					// Führe deine Löschlogik hier aus
					$this->my_termin_controller->delete_object($id);
					echo $id.',  ';
				}
				echo 'wurden gelöscht.</div>';	
			}
			else{
				echo '<ul class=form_errors><li>Keine Datensätze ausgewählt</li></ul>';
			}
		}
		else if ('bulk-edit' === $this->current_action())
		{
			if(!empty($_POST['bulk-delete'])){
				$update_ids = $_POST['bulk-delete'];	
				$this->my_termin_controller->process_bulk_edit($update_ids,$_POST);
				echo '<div class=form_success>Die ausgewählten Datensätze wurden geändert.</div>';
			}
			else {
				echo '<ul class=form_errors><li>Keine Datensätze ausgewählt</li></ul>';
			}
		}
		}
		public function extra_tablenav($which) {
			if ($which == "top") {
				$user_id = get_current_user_id();
				
				// 🟢 Bei POST: Werte übernehmen und speichern
				if (isset($_POST['filter_action'])) {
					$selected_bildungsgang = sanitize_text_field($_POST['filter_bildungsgang']);
					$selected_ereignistyp  = sanitize_text_field($_POST['filter_ereignistyp']);
					$selected_timetable    = sanitize_text_field($_POST['filter_timetable']);

					update_user_meta($user_id, 'filter_bildungsgang', $selected_bildungsgang);
					update_user_meta($user_id, 'filter_ereignistyp', $selected_ereignistyp);
					update_user_meta($user_id, 'filter_timetable', $selected_timetable);
				} else {
					// 🟡 Kein POST: Werte aus user_meta laden
					$selected_bildungsgang = get_user_meta($user_id, 'filter_bildungsgang', true);
					$selected_ereignistyp  = get_user_meta($user_id, 'filter_ereignistyp', true);
					$selected_timetable    = get_user_meta($user_id, 'filter_timetable', true);
				}
				


				// 🔽 Dropdown-Daten vorbereiten
				$timetable_data     = $this->my_timetable_controller->get_timetables_for_dropdown();
				$ereignistyp_data   = $this->my_einstellungen_controller->get_ereignistypen();
				$bildungsgang_data  = $this->my_einstellungen_controller->get_bildungsgaenge();
				?>
				<div class="alignright actions">
					<form method="POST" action="">
						<input type="hidden" name="page" value="termine">

						<!-- 🟢 Timetable-Dropdown -->
						<select name="filter_timetable">
							<option value="">Alle Timetables</option>
							<?php foreach ($timetable_data as $timetable) { ?>
								<option value="<?php echo esc_attr($timetable['id']); ?>" <?php selected($selected_timetable, $timetable['id']); ?>>
									<?php echo esc_html($timetable['id'] . ' | ' . $timetable['bezeichnung']); ?>
								</option>
							<?php } ?>
						</select>

						<!-- 🟢 Bildungsgang-Dropdown -->
						<select name="filter_bildungsgang">
							<option value="">Alle Bildungsgänge</option>
							<?php foreach ($bildungsgang_data as $bildungsgang) { ?>
								<option value="<?php echo esc_attr($bildungsgang->name); ?>" <?php selected($selected_bildungsgang, $bildungsgang->name); ?>>
									<?php echo esc_html($bildungsgang->name . ' | ' . $bildungsgang->description); ?>
								</option>
							<?php } ?>
						</select>

						<!-- 🟢 Ereignistyp-Dropdown -->
						<select name="filter_ereignistyp">
							<option value="">Alle Ereignistypen</option>
							<?php foreach ($ereignistyp_data as $ereignistyp) { ?>
								<option value="<?php echo esc_attr($ereignistyp->slug); ?>" <?php selected($selected_ereignistyp, $ereignistyp->slug); ?>>
									<?php echo esc_html($ereignistyp->name . ' | ' . $ereignistyp->description); ?>
								</option>
							<?php } ?>
						</select>

						<input type="submit" name="filter_action" class="button action" value="Filtern">
						<input type="submit" name="reset_filter" class="button" value="Zurücksetzen">
					</form>
				</div>
				<?php
			}
		}

	
}