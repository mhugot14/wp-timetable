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
	
	private $my_termin_controller, $my_timetable_controller;
	public function __construct(){
		
		//BACKEND
		$this->my_termin_controller = new Termin_controller();
		$this->my_timetable_controller = new Timetable_controller();
		add_action('admin_menu', [$this, 'create_menu']);
		add_action('admin_enqueue_scripts', [$this,'timetable_enqueue_datepicker']);
			
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
		add_submenu_page(
        'mh-timetable', // Übergeordnete Seite (null für keine übergeordnete Seite)
        'Termin',
        'Termin erstellen',
        'manage_options', // Berechtigung, hier kannst du die entsprechende Berechtigung ändern
        'Termin_neu_bearbeiten',
        [$this, 'render_termin_bearbeiten']
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
		
		//Bereich, um neue Datensätze anzulegen
		$this->edit_termin();
		//Bereich der die Tabellen ausgibt.
		$this->create_backend_table($data);
		//Box unten für den CSV-Upload
		$my_sidebox = new Backend_Sidebox('Termine CSV-Import',$this->create_csv_upload()); 
		echo $my_sidebox->print();
		
	}

	
	public function create_backend_table($data){
		?>
		<h2>Termine - Übersicht</h2>
		 <div class="list_table">
		<?php
		$table = new Backend_List_Table();
		$table->set_data( $data );
		$table->prepare_items();
		$table->display();
	    ?>
		
			</div>
	
		<?php
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
	
	public function render_termin_bearbeiten(){
		$html = $this->edit_termin();
		return $html;
	}
	public function render_form_with_errors($form_data, $errors) {
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<select name="timetable_ID" >
				<option value="" disabled selected>Timetable wählen...*</option>
				<?php
				$timetable_data = $this->my_timetable_controller->get_timetables_for_dropdown();
				foreach ($timetable_data as $timetable){
					echo '<option value="'.$timetable['id'].'">'.$timetable['id']
						 .' | '.$timetable['bezeichnung'].'</option>';
				}?>
				
			</select>
		<!--	<input type="text" size="10" name="timetable_ID" placeholder="Timetable ID">-->			
			<input type="text" name="bildungsgang" placeholder="Bildungsgang*">			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*">			
			<input type="text" name="ereignistyp" placeholder="Ereignistyp*">			
			<input type="text" size="10" name="beginn" id="datepicker_begin" placeholder="Beginn*">			
			<input type="text" size="10" name="ende" id="datepicker_end" placeholder="Ende*">
			<input type="text" name="verantwortlich" placeholder="Verantwortlich"><br><br>
		 	<?php wp_nonce_field( 'termin_speichern_nonce', 'termin_speichern_nonce' ); ?>
			<input type="submit" name="termin_speichern" value="Speichern">
			<input type="submit" value="Abbrechen">
		</form>
			<h3>Es sind Fehler aufgetreten</h3>
			<?php
			if (!empty($errors)) {
              echo '<ul class="form_errors">';
            foreach ($errors as $field_name => $field_errors) {
                echo '<li><b>' . ucfirst($field_name) . '</b>: ';
                echo implode(', ', $field_errors);
                echo '</li>';
            }
            echo '</ul>';
        
        }
        ?>
		</div>
		<?php
		
	}
	
	public function render_form(){
		?>
		
		<div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<select name="timetable_ID" >
				<option value="" disabled selected>Timetable wählen...*</option>
				<?php
				$timetable_data = $this->my_timetable_controller->get_timetables_for_dropdown();
				foreach ($timetable_data as $timetable){
					echo '<option value="'.$timetable['id'].'">'.$timetable['id']
						 .' | '.$timetable['bezeichnung'].'</option>';
				}?>
				
			</select>
		<!--	<input type="text" size="10" name="timetable_ID" placeholder="Timetable ID">-->			
			<input type="text" name="bildungsgang" placeholder="Bildungsgang*">			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*">			
			<input type="text" name="ereignistyp" placeholder="Ereignistyp*">			
			<input type="text" size="10" name="beginn" id="datepicker_begin" placeholder="Beginn*">			
			<input type="text" size="10" name="ende" id="datepicker_end" placeholder="Ende*">
			<input type="text" name="verantwortlich" placeholder="Verantwortlich"><br><br>
		 	<?php wp_nonce_field( 'termin_speichern_nonce', 'termin_speichern_nonce' ); ?>
			<input type="submit" name="termin_speichern" value="Speichern">
			<input type="submit" value="Abbrechen">
		</form>
		</div>
		
		<?php
	}
	
	public function edit_termin(){
		 $icon_url = plugins_url('/css/calender_icon_32px.png', __FILE__);
		?>	
		<script>
			jQuery(document).ready(function($) {
				// Datepicker für das Feld mit der ID "datepicker_begin" aktivieren
				$('#datepicker_begin').datepicker({
					// Deaktiviere die manuelle Eingabe im Textfeld
					beforeShow: function(input, inst) {
						$(input).prop('readonly', true);
					},
					// Zeige den Datepicker nur beim Klick auf eine Schaltfläche
					showOn: "button",
					buttonImage: "<?php echo $icon_url; ?>", // Passe den Pfad zur Schaltfläche an
					buttonImageOnly: true, // Zeige nur das Bild, keine Schaltfläche mit Text
					dateFormat: 'yy-mm-dd'
				});

				// Datepicker für das Feld mit der ID "datepicker_end" aktivieren
				$('#datepicker_end').datepicker({
					// Deaktiviere die manuelle Eingabe im Textfeld
					beforeShow: function(input, inst) {
						$(input).prop('readonly', true);
					},
					// Zeige den Datepicker nur beim Klick auf eine Schaltfläche
					showOn: "button",
					buttonImage: "<?php echo $icon_url; ?>", // Passe den Pfad zur Schaltfläche an
					buttonImageOnly: true, // Zeige nur das Bild, keine Schaltfläche mit Text
					dateFormat: 'yy-mm-dd' 
				});
			});

		</script>
		<h2>Termin hinzufügen oder bearbeiten</h2>
		
		<?php
		if (isset($_POST['termin_speichern'])) {
		   $errors = $this->my_termin_controller->process_form_submission($_POST);
		if (!empty($errors)) {
            // Es gibt Fehler, das Formular mit Fehlermeldungen rendern
            $this->render_form_with_errors($_POST, $errors);
        } else {
           $this->render_form()
            ?>
            <div class="form_success">Termin erfolgreich gespeichert!</div>
			
			<?php
            
        }
    } else {
        // Erster Aufruf des Formulars oder Abbrechen-Button, das Standard-Formular rendern
        $this->render_form();
    }
		
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
	
	// Funktion zum Einbinden des Datepickers
	public function timetable_enqueue_datepicker() {
		// jQuery UI Datepicker-Skript einbinden
		wp_enqueue_script('jquery-ui-datepicker');

		// jQuery UI Datepicker-Stil einbinden
		wp_enqueue_style('jquery-ui-datepicker-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
	}

}	

