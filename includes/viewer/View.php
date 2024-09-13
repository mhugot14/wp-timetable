<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once 'Backend_List_Table_Termine.php';
require_once 'Backend_List_Table_Timetables.php';
require_once 'Timetable_frontend_view.php';
require_once 'Backend_Sidebox.php';
require_once 'Backend_Termin_Edit.php';
require_once 'Backend_Timetable_Edit.php';

require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH.'includes/model/Termine_repository.php';
require_once MH_TT_PATH.'includes/controller/Termin_controller.php';

class View{
	
	private $my_termin_controller, $my_timetable_controller, $my_termine_repository,
			$my_timetable_repository;
	//Konstruktor
	public function __construct(){
		
		//BACKEND
		$this->my_termin_controller = new Termin_controller();
		$this->my_timetable_controller = new Timetable_controller();
		$this->my_termine_repository = new Termine_repository();
		$this->my_timetable_repository = new Timetable_repository();
		
		add_action('admin_menu', [$this, 'create_menu']);
	//	add_action('admin_enqueue_scripts', [$this,'timetable_enqueue_datepicker']);
		add_action('admin_enqueue_scripts', [$this, 'admin_javascript']);
			
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
		$default_atts = [
			'id' => 0,
			'entwurf' => 'nein'
		];
		$atts = shortcode_atts($default_atts, $atts, $name);
		
		$my_timetable_frontend_view = new timetable_frontend_view($atts);
		
		$rueckgabe .= $my_timetable_frontend_view->print_grid();
		return $rueckgabe;
	}  
	//Initialisierung des Admin-Menüs durch die Funktion create_menu
	
	public function create_menu(){
		add_menu_page('Timetable',
					 'Timetables',
					 'manage_options', 
					 'mh-timetable', 
					 [$this, 'render_timetable_page'],
					'dashicons-clock',30
				);

		add_submenu_page(
        'mh-timetable', // Übergeordnete Seite (null für keine übergeordnete Seite)
        'Termine',
        'Termine',
        'manage_options', // Berechtigung, hier kannst du die entsprechende Berechtigung ändern
        'termine',
        [$this, 'render_termine_page']
    );


		add_submenu_page(
        'mh-timetable', // Übergeordnete Seite (null für keine übergeordnete Seite)
        'Einstellungen',
        'Einstellungen',
        'manage_options', // Berechtigung, hier kannst du die entsprechende Berechtigung ändern
        'Einstellungen',
        [$this, 'render_einstellungen']
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

	public function render_timetable_page(){
			echo "<h1>Timetable - deine Übersicht bei der Zeugnisschreibung</h1>"
		. "<p style='font-size:14px;'>Mit Timetable kannst du eine Übersicht im GANTT-Stil erzeugen, "
				. "die einem gesamten Kollegium eine Übersicht bietet, was an welchem Tag "
				. "bzw. Zeitraum während der Zeugnisschreibung zu tun ist.<br/><br/>"
				. "So organisiert du entspannt die Noteneinsammlung, über APAs, Konferenzen und Ausgaben</p>";
		
		//Bereich, um neue Datensätze anzulegen
		$this->render_timetable_bearbeiten();
		echo "<br><br><h2>Timetables</h2>".
				"<p>Eine Übersicht aller vorhandenen Zeittafeln.</p>";
		$this->create_backend_table("timetables");
			
			
	}
	
	public function render_termine_page(){
		echo "<h1>Terminübersicht - alle Termine der Timetables</h1>";
		
		
		//Bereich, um neue Datensätze anzulegen
		$this->render_termin_bearbeiten();
		//Bereich der di;e Tabellen ausgibt.
		$this->create_backend_table("termine");
		//Box unten für den CSV-Upload
		$my_sidebox = new Backend_Sidebox('Termine CSV-Import',$this->create_csv_upload()); 
		echo $my_sidebox->print();
		
	}

	
	public function create_backend_table($typ){
		$table= null;
			//Form einfügen
			?>
		<form method="post" id="bulkEditForm">
			<div class="list_table">
			<?php
			if ($typ=="timetables"){
				$table = new Backend_List_Table_Timetables();
				$table->process_bulk_action();
				$table->set_data( $this->my_timetable_repository->get_data() );
			}

			else if ($typ=="termine"){
				$table = new Backend_List_Table_Termine();
				$table->process_bulk_action();
				$table->set_data( $this->my_termine_repository->get_data() );
			}
			else{
				echo '<p style="color:red;">Kenne den Typ nicht<p>';
			}
			$table->prepare_items();
			$table->display();
				?>
		</form>
			</div>
	
		<?php
	}
	
	public function render_termin_bearbeiten(){
		$my_backend_termin_edit = new Backend_Termin_Edit();
		$html = $my_backend_termin_edit->edit_termin();
		return $html;
	}
	
	public function render_timetable_bearbeiten(){
		$my_backend_timetable_edit = new Backend_Timetable_Edit();
		$html = $my_backend_timetable_edit->edit_timetable();
		return $html;
	}
	
	public function create_csv_upload(){
		$formular="<p>Lade nachstehend eine CSV-Datei mit Terminen hoch.";
		$formular .='<form enctype="multipart/form-data" method="post" '
				. /*'action="'. esc_url( admin_url('admin-post.php') ).'*/'">
					 <input type="hidden" name="action" value="handle_csv_upload">
					<input type="file" class="file" name="csv_file" accept=".csv"><br/><br/>
					<input type="checkbox" name="loeschen" value="loeschen"> bisherige Einträge löschen<br/><br/>
					<input type="submit" class="button button-primary" value="Upload CSV">
					</form>';
		
		$formular .= $this->my_termin_controller->handle_csv_upload();
		return $formular;
	}
	
	public function render_einstellungen(){
		echo '<h1>Einstellungen</h1>';
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
	
	public  function admin_javascript() {
        // Pfad zu deiner JavaScript-Datei anpassen
	    $skriptpfad = plugin_dir_url(__FILE__) . 'mh_tt_javascript.js';
        wp_enqueue_script('mh_tt_javascript',$skriptpfad , array('jquery'), null, true);
		// jQuery UI Datepicker-Skript einbinden
		wp_enqueue_script('jquery-ui-datepicker');
		// jQuery UI Datepicker-Stil einbinden
		wp_enqueue_style('jquery-ui-datepicker-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
		
		
		error_log("admin_javascript() wurde aufgerufen");
    echo '<script>console.log("admin_javascript() wurde aufgerufen");</script>';
    }

}	

