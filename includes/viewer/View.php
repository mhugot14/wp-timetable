<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once 'Backend_List_Table.php';
require_once 'Timetable_frontend_view.php';
require_once 'Backend_Sidebox.php';
require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH.'includes/model/Termine_repository.php';
require_once MH_TT_PATH.'includes/controller/Termin_controller.php';

class View{
	
	private $my_termin_controller;
	public function __construct(){
		
		//BACKEND
		$this->my_termin_controller = new Termin_controller();
		add_action('admin_menu', [$this, 'create_menu']);
		
		//add_action('add_meta_boxes', [$this,'register_metaboxes']);
		//add_filter('meta_box_location', [$this,'my_meta_box_location'], 10, 3);
		
		
		
		//FRONTEND
		add_action('init', [$this,'setup_shortcodes']);
		
		//do_action( 'timetable/viewer/View/init', $this );
		//$this->myPluginHelpers = new Plugin_Helpers;
		
		// Hook, um die Funktion aufzurufen für das Einbinden von CSS
		add_action('wp_enqueue_scripts', [$this,'timetable_enqueue_styles']);
		add_action( 'admin_enqueue_scripts',[$this, 'custom_admin_styles'] );
		
		add_action( 'admin_post_handle_csv_upload', [$this,'handle_csv_upload_callback'] );
		
		
	}
	
	public function setup_shortcodes(){
		add_shortcode('insertTimetable',[$this,'shortcode_insertTimetable']);
	}
	/* $atts: einzelne Parameter, die dem shortcode übergeben haben als array
	 * $content: Inhalt, der zwischen dem anfänglichen und dem schließendem Shortcode
	 *           eingetragen wurde
	 * $name: name des shortcodes selbst
	 */
	public function shortcode_insertTimetable($atts,string $content, string $name):string{
		//auslesen der übergendenen IDs 
		$rueckgabe="";
		$timetable_id="0";
		try{
			if (is_array($atts)) {
				// Jetzt kannst du auf den Index zugreifen
				$timetable_id=$atts['id'];
			} 
			else {
					echo "kein Array";
			}
			
		} catch (Exception $ex) {
		  
			echo "fehler: ".$ex;
		}
		
		
		$my_timetable_frontend_view = new timetable_frontend_view($timetable_id);
		
		$rueckgabe .= $my_timetable_frontend_view->print_grid();
		return $rueckgabe;
	}  
	//Initialisierung des Admin-Menüs durch die Funktion create_menu
	
	public function create_menu(){
		add_menu_page('Timetable',
					 'Timetable',
					 'manage_options', 
					 'mh-timetable', 
					 [$this, 'render_timetable_page'],
					'dashicons-clock',30
				);

	}
	
	//funktioniert nicht
	public function register_metaboxes(){
		echo '<h1>hallo register metaboxes</h1>';
		add_meta_box(
				'mh-metabox-timetable',
				'Timetables',
				[$this,'render_metabox_timetable'],
				'mh-timetable',
				'normal',
				'default'
				);
	
	}
	//funktoniert nicht
	public function render_metabox_timetable(){
		echo "Hallo aus der Render-Metabox-timetable";
	}
	/*
	function my_meta_box_location($location, $post_type, $meta_box_id) {
    if ($meta_box_id === 'mh-metabox-timetable') {
        return 'right'; // 'normal', 'advanced', 'side'
    }

    return $location;
}*/
	
	public function render_timetable_page(){
		echo "<h1>Timetable - deine Übersicht bei der Zeugnisschreibung</h1>"
		. "<p style='font-size:14px;'>Mit Timetable kannst du eine Übersicht im GANTT-Stil erzeugen, "
				. "die einem gesamten Kollegium eine Übersicht bietet, was an welchem Tag "
				. "bzw. Zeitraum während der Zeugnisschreibung zu tun ist.<br/><br/>"
				. "So organisiert du entspannt die Noteneinsammlung, über APAs, Konferenzen und Ausgaben</p>";
		
		$my_termine_repository = new Termine_repository();
		$data = $my_termine_repository->get_data();
		?>
		<div class="be_table_box_wrap">
			 <div class="list_table">
		<?php	 
			$this->create_backend_table($data);
		?>
			</div>
		<?php
		$my_sidebox = new Backend_Sidebox('Termine CSV-Import',$this->create_csv_upload()); 
		echo $my_sidebox->print();
		?>
		</div>
		<?php
	}

	
	public function create_backend_table($data){

		$table = new Backend_List_Table();
		$table->set_data( $data );
		$table->prepare_items();
		$table->display();
		
	}
	
	public function create_csv_upload(){
		$formular="<p>Lade nachstehend eine CSV-Datei mit Terminen hoch.";
		$formular .='<form enctype="multipart/form-data" method="post" '
				. /*'action="'. esc_url( admin_url('admin-post.php') ).'*/'">
					 <input type="hidden" name="action" value="handle_csv_upload">
					<input type="file" class="file" name="csv_file" accept=".csv"><br/><br/>
					<input type="submit" class="button button-primary" value="Upload CSV">
					</form>';
		
		$formular .= $this->my_termin_controller->handle_csv_upload();
		return $formular;
	}
	
	
	public function modal_termine(){
		?>	
		<div id="modalTermine" class="modal">
			<div class="modal-content">
				<span class="close">&times;</span>
				<h2>Neuen Termin hinzufügen</h2>
				<form id="myForm">
					<!-- Hier die Formularfelder für die Dateneingabe -->
					<input type="text" name="bildungsgang" placeholder="Bildungsgang">
					<input type="text" name="bezeichnung" placeholder="Bezeichnung">
					<input type="text" name="ereignistyp" placeholder="Ereignistyp">
					<input type="text" name="beginn" placeholder="Beginn">
					<input type="text" name="ende" placeholder="Ende">
					<input type="text" name="verantwortlich" placeholder="verantwortlich">
					<input type="text" name="timetable_ID" placeholder="Timetable ID">
					
					<input type="submit" value="Speichern">
				</form>
			</div>
		</div>
	<?php
	}

	
	public function timetable_enqueue_styles() {
    // Eindeutiger Bezeichner für dein Stylesheet
		$handle = 'timetable';

    // URL zur CSS-Datei in deinem Plugin
		$src = plugins_url('/css/timetable_css.css', __FILE__);

    // Array der Abhängigkeiten (hier keine Abhängigkeiten, daher leer)
		$deps = array();

    // Versionsnummer für Versionierung und Cache-Busting (kann auch 'null' sein)
		$ver = '1.1';

    // Füge das Stylesheet hinzu
	   wp_enqueue_style($handle, $src, $deps, $ver);
	}
	
	//Backend CSS Einbinden
	function custom_admin_styles() {
    // Pfad zur CSS-Datei Ihres Plugins/Themas
    $css_url = plugins_url('/css/timetable_admin.css', __FILE__);
    
    // Registrieren des CSS-Stils
    wp_register_style( 'custom-admin-style', $css_url, false, '1.0.0' );

    // Einbinden des CSS-Stils
    wp_enqueue_style( 'custom-admin-style' );
	}

}	

