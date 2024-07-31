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
		<div class="wrap_termin_hinzufuegen">
		<h2>Timetables hinzufügen, ändern oder kopieren</h2>
		
		
		
		<?php
		$this->render_form();
		
		
		}
		
		public function render_form(){
		?>
		
		<div>
			<form enctype="multipart/form-data" method="post" action ="" id="tt_timetable_form">
			<!-- Hier die Formularfelder für die Dateneingabe -->
			<input type="text" size="3" name="id" placeholder="id*" value="" readonly>
		<!--	<input type="text" size="10" name="timetable_ID" placeholder="Timetable ID">-->			
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*">			
			<input type="text" name="beschreibung" placeholder="Beschreibung*">			
		 	<?php wp_nonce_field( 'timetable_speichern_nonce', 'timetable_speichern_nonce' ); ?>
			<input type="submit" name="timetable_speichern" value="Hinzufügen">
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
			<input type="text" name="bezeichnung" placeholder="Bezeichnung*" 
				   value="<?php echo $timetable_object->get_bezeichnung();?>">			
			<input type="text" name="beschreibung" placeholder="Beschreibung*" 
				   value="<?php echo $timetable_object->get_beschreibung();?>">			
			<br><br>
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
}