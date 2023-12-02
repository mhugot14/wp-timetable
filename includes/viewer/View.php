<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once 'Backend_List_Table.php';
require_once 'Timetable_frontend_view.php';
require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH.'includes/model/Termine_repository.php';

class View{
	
	public function __construct(){
		
		//BACKEND
		add_action('admin_menu', [$this, 'create_menu']);
		//add_action('add_meta_boxes', [$this,'register_metaboxes']);
		
		
		
		//FRONTEND
		add_action('init', [$this,'setup_shortcodes']);
		
		//do_action( 'timetable/viewer/View/init', $this );
		//$this->myPluginHelpers = new Plugin_Helpers;
		
	}
	
	public function setup_shortcodes(){
		add_shortcode('insertTimetable',[$this,'shortcode_insertTimetable']);
	}
	
	public function shortcode_insertTimetable($atts,string $content, string $name):string{
		$my_timetable_frontend_view = new Timetable_frontend_view();
		
		$rueckgabe = $my_timetable_frontend_view->print();
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
	//nicht genutzt
	public function register_metaboxes(){
		echo '<h1>hey</h1>';
		add_meta_box(
				'mh-metabox-timetable',
				'Timetables',
				[$this,'render_metabox_timetable'],
				'mh-timetable',
				'normal'
				);
	
	}
	
	public function render_timetable_page(){
		echo "<h1>Timetable - deine Übersicht bei der Zeugnisschreibung</h1>"
		. "<p>Mit Timetable kannst du eine Übersicht im GANTT-Stil erzeugen, "
				. "die einem gesamten Kollegium eine Übersicht bietet, was an welchem Tag "
				. "bzw. Zeitraum während der Zeugnisschreibung zu tun ist.</p>"
				. "<p>So organisiert du entspannt die Noteneinsammlung, über APAs, Konferenzen und Ausgaben "
				. "mit dieser Timetable</p>";
		
		$my_termine_repository = new Termine_repository();
		$data = $my_termine_repository->get_data();
		$this->create_backend_table($data);
	}

	
	public function create_backend_table($data){

		$table = new Backend_List_Table();
		$table->set_data( $data );
		$table->prepare_items();
		$table->display();
		
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
	
}