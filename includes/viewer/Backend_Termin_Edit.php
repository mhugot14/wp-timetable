<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

require_once MH_TT_PATH. 'includes/controller/Termin_controller.php';
require_once MH_TT_PATH. 'includes/controller/Timetable_controller.php';

/**
 * Description of Backend_Termin_Edit
 *
 * @author micha
 */
class Backend_Termin_Edit {
	
	private $my_termin_controller, $my_timetable_controller;
	
	public function __construct(){
		$this->my_termin_controller = new Termin_controller();
		$this->my_timetable_controller = new Timetable_controller();
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
		<h2>Termin hinzufügen</h2>
		
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
		} 
		else if (isset($_GET['action'])){
			echo 'GET: '.$_GET['id'].$_GET['action'];
			$action = sanitize_text_field($_GET['action']);
			$id = sanitize_text_field($_GET['id']);
			
			switch ($action){
				case 'delete':
					$check_modal=$this->modal_dialog_delete_check($id);
					if($check_modal){
						$this->my_termin_controller->delete_object($id);
					}
					break;
				case 'edit':
					$edit_termin = $this->my_termin_controller->get_object_by_id($id);
					$this->render_form_with_errors($edit_termin,array());
			}
			
		}
		else  {
        // Erster Aufruf des Formulars oder Abbrechen-Button, das Standard-Formular rendern
        $this->render_form();
		}
		
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
	
	public function render_form_with_errors($form_data, $errors) {
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<select name="timetable_ID" >
				<option value="" disabled selected>Timetable wählen...*</option>
				<?php
				$timetable_data = $this->my_timetable_controller->get_timetables_for_dropdown();
				foreach ($timetable_data as $timetable){
					if ($timetable['id']==$form_data['timetable_ID']){
						echo '<option value="'.$timetable['id'].'" selected>'.$timetable['id']
						 .' | '.$timetable['bezeichnung'].'</option>';
					}
					else{
						echo '<option value="'.$timetable['id'].'">'.$timetable['id']
						 .' | '.$timetable['bezeichnung'].'</option>';
					}
				}?>
				
			</select>
		<!--	<input type="text" size="10" name="timetable_ID" placeholder="Timetable ID">-->			
			<input type="text" name="bildungsgang" placeholder="Bildungsgang*" value="<?php echo $form_data['bildungsgang'];?>">			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*" value="<?php echo $form_data['bezeichnung'];?>">			
			<input type="text" name="ereignistyp" placeholder="Ereignistyp*" value="<?php echo $form_data['ereignistyp'];?>">			
			<input type="text" size="10" name="beginn" id="datepicker_begin" placeholder="Beginn*" value="<?php echo $form_data['beginn'];?>">			
			<input type="text" size="10" name="ende" id="datepicker_end" placeholder="Ende*" value="<?php echo $form_data['ende'];?>">
			<input type="text" name="verantwortlich" placeholder="Verantwortlich"value="<?php echo $form_data['verantwortlich'];?>"><br><br>
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
	public function modal_dialog_delete_check($id){
		$loesch_termin = $this->my_termin_controller->get_object_by_id( $id );
		
		$dialog_text= 'Möchten Sie wirklich den Termin mit der ID '
				.$loesch_termin->get_id().' '.$loesch_termin->get_bezeichnung().' loeschen?';
		  ?>
		<script>
			jQuery(document).ready(function($) {
				// Beim Laden der Seite den Bestätigungsdialog anzeigen
				if (confirm('Möchten Sie wirklich den Termin löschen?')) {
					// Wenn der Benutzer "Ja" klickt, den Termin löschen
					window.location.href = '<?php echo admin_url("admin.php?page=mh-timetable&action=delete-success&id=" . $id); ?>';
					return true;
				} else {
					return false;
				}
			});
		</script>
    <?php
				
		
	}
}
