<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

namespace timetable;

//require_once MH_TT_PATH. 'includes/controller/Termin_controller.php';
require_once MH_TT_PATH. 'includes/controller/Timetable_controller.php';



 class Backend_Timetable_Edit{
	 
	 private $my_timetable_controller;
	
	public function __construct(){
		$this->my_timetable_controller = new Timetable_controller();
	}
	
public function edit_timetable(){
		 ?>
		<div class="wrap_termin_hinzufuegen">
		<h2>Timetables hinzufügen, ändern oder kopieren</h2>
		
		<?php
		//Im Formular wird auf Termin speichern geklickt
		if (isset($_POST['timetable_speichern'])) {
		   $errors = $this->my_timetable_controller->process_form_submission($_POST);
			if (!empty($errors)) {
				// Es gibt Fehler, das Formular mit Fehlermeldungen rendern
				$this->render_form_with_errors($_POST, $errors);
			} else {
			   $this->render_form()
				?>
				<div class="form_success">Timetable erfolgreich gespeichert!</div>
				<?php        
			}
		}
		//In der Tabelle wird der Button BEARBEITEN gedruckt.
		else if (isset($_POST['timetable_edit_nonce']) && wp_verify_nonce($_POST['timetable_edit_nonce'], 'timetable_edit_nonce')) {
		   $id = sanitize_text_field($_POST['id']);
			$timetable = $this->my_timetable_controller->get_object_by_id( $id );
		    $this->render_form_for_edit($timetable);
			
		} 
		
		//In der Tabelle wird der Button LÖSCHEN geklickt
		else if (isset($_POST['timetable_loeschen_nonce']) && 
				wp_verify_nonce($_POST['timetable_loeschen_nonce'], 'timetable_loeschen_nonce')) {
			$id = sanitize_text_field($_POST['id']);
			$timetable = $this->my_timetable_controller->get_object_by_id($id);

			// Überprüfen, ob delete_confirmation gesetzt wurde und true ist
			if (isset($_POST['delete_confirmation']) && $_POST['delete_confirmation'] === 'true') {
				  // Löschen des Objekts
				$loesch_result=$this->my_timetable_controller->delete_object($id);
				$this->render_form();
				if ($loesch_result==0){
					echo '<ul class="form_errors"><li>Timetable mit der ID '.$id.' konnte nicht gelöscht werden<br/> '
							. 'Es sind noch Termine zugeordnet.</li></ul>';
				}
				else{
					echo '<div class="form_success">Timetable mit der ID '.$id.' gelöscht.</div>';
				}
			} else {
				// Zeige den Bestätigungsdialog an, wenn delete_confirmation noch nicht gesetzt ist
				$this->modal_dialog_delete_check($timetable);
			}
		}
		
		else{
			$this->render_form();
			}
	}
		
		public function render_form(){
		?>
		
		<div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_timetable_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="" readonly>
		<!--	<input type="text" size="10" name="timetable_ID" placeholder="Timetable ID">-->			
			<input type="text" size="40" name="bezeichnung" placeholder="Bezeichnung*">			
			<input type="text" size="70" name="beschreibung" placeholder="Beschreibung*">			
		 	<br><br>
			<?php wp_nonce_field( 'timetable_speichern_nonce', 'timetable_speichern_nonce' ); ?>
			<input type="submit" name="timetable_speichern" value="Speichern">
			<input type="submit" value="Abbrechen">
		</form>
		</div>
		</div> <!--wrap_termin_hinzufuegen schliessen-->
		
		<?php
	}
	public function render_form_for_edit($timetable_object){
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_timetable_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="<?php echo $timetable_object->get_id();?>" readonly>			
			<input type="text" size="40" name="bezeichnung" placeholder="Bezeichnung*" 
				   value="<?php echo $timetable_object->get_bezeichnung();?>">			
			<input type="text" size="70"  name="beschreibung" placeholder="Beschreibung*" 
				   value="<?php echo $timetable_object->get_beschreibung();?>">			
			<br><br>
		 	<?php wp_nonce_field( 'timetable_speichern_nonce', 'timetable_speichern_nonce' ); ?>
			<input type="submit" name="timetable_speichern" value="Speichern">
			<input type="submit" value="Abbrechen">
		</form>
		        
      	</div>
		</div> <!--wrap_termin_hinzufuegen schliessen-->
		<?php
		
	
	}
	public function render_form_with_errors($form_data, $errors) {
		?><div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_termin_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="<?php echo $form_data['id'];?>" readonly>

			<input type="text" name="bezeichnung" placeholder="Bezeichnung*" 
				   value="<?php echo $form_data['bezeichnung'];?>">			
			<input type="text" name="beschreibung" placeholder="Beschreibung*" 
				   value="<?php echo $form_data['beschreibung'];?>">			
			<br><br>
		 	<?php wp_nonce_field( 'timetable_speichern_nonce', 'timetable_speichern_nonce' ); ?>
			<input type="submit" name="timetable_speichern" value="Speichern">
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
	public function modal_dialog_delete_check($loesch_timetable) {
    $dialog_text = 'Möchten Sie wirklich den Termin mit der\nID: ' . $loesch_timetable->get_id() . 
			'\nBezeichnung: ' . $loesch_timetable->get_bezeichnung() . '\nloeschen?';
    ?>
    <form id="delete_confirmation_form" method="post" action="">
        <input type="hidden" id="delete_confirmation" name="delete_confirmation" value="false">
		 <?php wp_nonce_field('timetable_loeschen_nonce', 'timetable_loeschen_nonce'); ?>
        <input type="hidden" name="id" value="<?php echo $loesch_timetable->get_id(); ?>">
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