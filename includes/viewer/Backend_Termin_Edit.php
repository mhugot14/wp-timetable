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
				// Datepicker für "Begin"
				$('#datepicker_begin').datepicker({
					beforeShow: function(input, inst) {
						$(input).prop('readonly', true);
					},
					showOn: "button",
					buttonImage: "<?php echo $icon_url; ?>", 
					buttonImageOnly: true,
					dateFormat: 'yy-mm-dd',
					onSelect: function(selectedDate) {
						// Wenn "Ende"-Datum leer ist, setzen wir es auf das gleiche Datum
						if ($('#datepicker_end').val() === '') {
							$('#datepicker_end').datepicker('setDate', selectedDate);
						}
					}
				});

				// Datepicker für "Ende"
				$('#datepicker_end').datepicker({
					beforeShow: function(input, inst) {
						$(input).prop('readonly', true);
					},
					showOn: "button",
					buttonImage: "<?php echo $icon_url; ?>", 
					buttonImageOnly: true,
					dateFormat: 'yy-mm-dd'
				});
			});
			</script>
		<div class="wrap_termin_hinzufuegen">
		<h2>Termin hinzufügen oder ändern</h2>
		
		<?php
		//Im Formular wird auf Termin speichern geklickt
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
		//In der Tabelle wird der Button BEARBEITEN gedruckt.
		else if (isset($_POST['termin_edit_nonce']) && wp_verify_nonce($_POST['termin_edit_nonce'], 'termin_edit_nonce' ) && isset($_POST['edit_termin_button'])) {
		   $id = sanitize_text_field($_POST['id']);
			$termin = $this->my_termin_controller->get_object_by_id( $id );
		    $this->render_form_for_edit( $termin);
			
		} 
		//In der Tabelle wird der Button LÖSCHEN geklickt
		else if (isset($_POST['termin_loeschen_nonce']) && wp_verify_nonce($_POST['termin_loeschen_nonce'], 'termin_loeschen_nonce') && isset($_POST['loeschen_termin_button'])) {
			$id = sanitize_text_field($_POST['id']);
			$termin = $this->my_termin_controller->get_object_by_id($id);

			// Überprüfen, ob delete_confirmation gesetzt wurde und true ist
			if (isset($_POST['delete_confirmation']) && $_POST['delete_confirmation'] === 'true') {
				  // Löschen des Objekts
				$this->my_termin_controller->delete_object($id);
				$this->render_form();
				echo '<div class="form_success">Termin mit der ID '.$id.' gelöscht.</div>';
				
			} else {
				// Zeige den Bestätigungsdialog an, wenn delete_confirmation noch nicht gesetzt ist
				$this->modal_dialog_delete_check($termin);
		
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
			<input type="text" size="3" name="id" placeholder="id*" value="" readonly>
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
			<input type="text" size="10" name="beginn" id="datepicker_begin" placeholder="Beginn*" readonly>			
			<input type="text" size="10" name="ende" id="datepicker_end" placeholder="Ende*" readonly>
			<input type="text" name="verantwortlich" placeholder="Verantwortlich"><br><br>
		 	<?php wp_nonce_field( 'termin_speichern_nonce', 'termin_speichern_nonce' ); ?>
			<input type="submit" name="termin_speichern" value="Speichern">
			<input type="submit" onclick="jstest()" value="Abbrechen">
		</form>
		</div>
		</div> <!--wrap_termin_hinzufuegen schliessen-->
		
		<?php
	}
	public function render_form_for_edit($termin_object){
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="<?php echo $termin_object->get_id();?>" readonly>
			<select name="timetable_ID" >
				<option value="" disabled selected>Timetable wählen...*</option>
				<?php
				$timetable_data = $this->my_timetable_controller->get_timetables_for_dropdown();
				foreach ($timetable_data as $timetable){
					if ($timetable['id']==$termin_object->get_timetable_ID()){
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
			<input type="text" name="bildungsgang" placeholder="Bildungsgang*" 
				   value="<?php echo $termin_object->get_bildungsgang();?>">			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*" 
				   value="<?php echo $termin_object->get_bezeichnung();?>">			
			<input type="text" name="ereignistyp" placeholder="Ereignistyp*" 
				   value="<?php echo $termin_object->get_ereignistyp();?>">			
			<input type="text" size="10" name="beginn" id="datepicker_begin" 
				   placeholder="Beginn*" readonly value="<?php echo $termin_object->get_termin_beginn_as_string();?>">			
			<input type="text" size="10" name="ende" id="datepicker_end" 
				   placeholder="Ende*" readonly value="<?php echo $termin_object->get_termin_ende_as_string();?>">
			<input type="text" name="verantwortlich" placeholder="Verantwortlich"
				   value="<?php echo $termin_object->get_verantwortlich();?>"><br><br>
		 	<?php wp_nonce_field( 'termin_speichern_nonce', 'termin_speichern_nonce' ); ?>
			<input type="submit" name="termin_speichern" value="Speichern">
			<input type="submit" value="Abbrechen">
		</form>
		        
      	</div>
		</div>
		<?php
		
	
	}
	public function render_form_with_errors($form_data, $errors) {
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="<?php echo $form_data['id'];?>" readonly>

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
			<input type="text" name="bildungsgang" placeholder="Bildungsgang*" 
				   value="<?php echo $form_data['bildungsgang'];?>">			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*" 
				   value="<?php echo $form_data['bezeichnung'];?>">			
			<input type="text" name="ereignistyp" placeholder="Ereignistyp*" 
				   value="<?php echo $form_data['ereignistyp'];?>">			
			<input type="text" size="10" name="beginn" id="datepicker_begin" 
				   placeholder="Beginn*" value="<?php echo $form_data['beginn'];?>" readonly>			
			<input type="text" size="10" name="ende" id="datepicker_end" 
				   placeholder="Ende*" value="<?php echo $form_data['ende'];?>" readonly       >
			<input type="text" name="verantwortlich" placeholder="Verantwortlich" 
				   value="<?php echo $form_data['verantwortlich'];?>"><br><br>
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
	public function modal_dialog_delete_check($loesch_termin) {
    $dialog_text = 'Möchten Sie wirklich den Termin mit der\nID: ' . $loesch_termin->get_id() . '\nBezeichnung: ' . $loesch_termin->get_bezeichnung() . '\nloeschen?';
    ?>
    <form id="delete_confirmation_form" method="post" action="">
        <input type="hidden" id="delete_confirmation" name="delete_confirmation" value="false">
		 <?php wp_nonce_field('termin_loeschen_nonce', 'termin_loeschen_nonce'); ?>
        <input type="hidden" name="id" value="<?php echo $loesch_termin->get_id(); ?>">
		<input type="hidden" name="loeschen_termin_button" value="">
    </form>
    <script>
        jQuery(document).ready(function($) {
			 console.log('JavaScript geladen');
            // Beim Laden der Seite den Bestätigungsdialog anzeigen
            if (confirm('<?php echo $dialog_text;?>')) {
				  console.log('Bestätigung erfolgt. Formular wird gesendet.');
				  
				 // Sicherstellen, dass das Hidden-Feld existiert und der Wert gesetzt wird
				var deleteField = document.getElementById('delete_confirmation');
				if (deleteField) {
					// Wenn der Benutzer "Ja" klickt, den Wert der versteckten Variable auf true setzen
					deleteField.value = 'true';
					console.log('Hidden-Feld erfolgreich auf true gesetzt.');
				} 
				else {
					console.log('Hidden-Feld delete_confirmation nicht gefunden.');
				}  
				  
                
                
				
				 // Sicherstellen, dass das Formular existiert und abgeschickt wird
			 var deleteForm = document.getElementById('delete_confirmation_form');
				if (deleteForm) {
					deleteForm.submit();
					console.log('Formular wurde abgeschickt.');
				}
				else {
					console.log('Formular delete_confirmation_form nicht gefunden.');
				}
			}
			else{
				console.log('Bestätigung abgelehnt.');
					}
        });
    </script>
    <?php
	}

}
