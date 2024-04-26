<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */
namespace timetable;

require_once 'Controller_interface.php';
require_once MH_TT_PATH.'includes/model/Termin.php';
require_once MH_TT_PATH.'includes/model/Termine_repository.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use DateTime;


/**
 * Description of Termin
 *
 * @author micha
 */
class Termin_controller implements Controller_interface {
	
	private $my_termin;
	private $my_termin_repository;
	
	public function __construct( ) {
		
		$this->my_termin_repository= new Termine_repository; 
	}
	
	public function process_form_submission($form_data){
			
		if (!isset($form_data['termin_speichern_nonce']) || 
			!wp_verify_nonce($form_data['termin_speichern_nonce'], 'termin_speichern_nonce')) {
           wp_die('Nonce-Fehler!'); // Sicherheitsüberprüfung für Nonce
        }
		//Daten einlesen und bereinigen
		$san_data = [];
		
		 if (!empty($form_data['timetable_ID'])){
			$san_data['timetable_ID'] = sanitize_text_field($form_data['timetable_ID']);
		 } 	 
		 $san_data['bezeichnung'] = sanitize_text_field($form_data['bezeichnung']);
		 $san_data['bildungsgang'] = sanitize_text_field($form_data['bildungsgang']);
		 $san_data['beginn'] = sanitize_text_field($form_data['beginn']);
		 $san_data['ende'] = sanitize_text_field($form_data['ende']);
		 $san_data['verantwortlich'] = sanitize_text_field($form_data['verantwortlich']);
		 $san_data['ereignistyp'] = sanitize_text_field($form_data['ereignistyp']);
		 
		 if (empty($san_data['verantwortlich'])){
			 $san_data['verwantwortlich']="";
		 }
		
		$errors = $this->check_form_data($san_data);
		
		if (empty($errors)){
		 $this->add_object($san_data);
			
		}
		else{
			return $errors;
		}
	}
	
	
	public function check_form_data($data){
		$errors = [];
		
		 if (empty($data['timetable_ID']) OR $data['timetable_ID']=="" ) {
			$errors['timetable_ID'][] = 'Eine Timetable muss ausgewählt werden.';
		 	}
		
		 if (empty($data['bildungsgang'])) {
			$errors['bildungsgang'][] = 'Gib einen Bildungsgang an.';
		 }
		
		 if (empty($data['bezeichnung'])) {
			$errors['bezeichnung'][] = 'Gib eine Bezeichnung an.';
		 }
		 
		 if (empty($data['ereignistyp'])) {
			$errors['ereignistyp'][] = 'Gib einen Ereignistyp an.';
		 }
		 
		 if (empty($data['beginn'])) {
			$errors['beginn'][] = 'Gib einen Beginn an.';
		 }
		 else if (strtotime($data['beginn']) === false) {
			$errors['beginn'][] = 'Das Enddatum ist ungültig.';
		}	

    // Überprüfe das Format des Startdatums
    
		 
		if (empty($data['ende'])) {
			$errors['ende'][] = 'Gib ein Ende an.';
		 }
		 
		 else if (strtotime($data['beginn']) === false) {
        $errors['beginn'][] = 'Das Startdatum ist ungültig.';
    }
		else{ 
			$ende_date = DateTime::createFromFormat('Y-m-d', $data['ende']);
	        $beginn_date = DateTime::createFromFormat('Y-m-d', $data['beginn']);
    // Überprüfe, ob das Ende-Datum größer oder gleich dem Beginn-Datum ist
		    if ($ende_date < $beginn_date) {
				$errors['ende'][] = 'Das Enddatum darf nicht vor dem Startdatum liegen.';
			}
		}
		return $errors;
	}
	
	public function add_object($data) {
		
		
		$my_termin = new Termin($data['beginn'], $data['ende'], 
					$data['bildungsgang'], $data['bezeichnung'], $data['ereignistyp'], 
					$data['verantwortlich'], $data['timetable_ID']);
	
		$my_termin->save();
	}

	public function delete_object( $id ) {
		$this->my_termin_repository->delete($id);
	}

	public function edit_object( $id ) {
		
	}

	public function get_object_by_id( $id ) { 
		$resultset = $this->my_termin_repository->find($id);
		$result = $resultset[0];
		if (!empty($result)){
		/*	$my_termin = new Termin(
					$resultset[0]['beginn'], 
					$resultset[0]['ende'], 
					$resultset[0]['bildungsgang'], 
					$resultset[0]['bezeichnung'], 
					$resultset[0]['ereignistyp'], 
					$resultset[0]['verantwortlich'], 
					$resultset[0]['timetable_ID']
					);*/
		$my_termin = new Termin(
            $result->beginn,
            $result->ende,
            $result->bildungsgang,
            $result->bezeichnung,
            $result->ereignistyp,
            $result->verantwortlich,
            $result->timetable_ID
        );
			$my_termin->set_id($id);
			
			return $my_termin;
		}
		else {
			return null;
		}
		
	}
	
	public function handle_csv_upload() {
		$ergebnis="";
    // Überprüfen Sie, ob eine Datei hochgeladen wurde
		if 	( isset( $_FILES['csv_file'] )){
		if ( isset( $_FILES['csv_file'] ) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK ) {
			$file_info = pathinfo( $_FILES['csv_file']['name'] );

			// Überprüfen Sie, ob die Datei eine CSV-Datei ist
			if ( strtolower( $file_info['extension'] ) === 'csv' ) {
				$upload_dir = wp_upload_dir();
				$upload_path = $upload_dir['path'] . '/' . $_FILES['csv_file']['name'];
				//DEBUG
				//	$ergebnis.= 'upload_dir: '.print_r($upload_dir);
				//	$ergebnis.= '<br/>upload_path: '.print_r($upload_path);
				//	$ergebnis.= '<br/>file_info: '.print_r($file_info);
				//	$ergebnis.= '<br/>';
				// Versuchen Sie, die Datei zu verschieben
				if ( move_uploaded_file( $_FILES['csv_file']['tmp_name'], $upload_path ) ) {
					// Erfolgreicher Upload
					$ergebnis.= '<p>CSV-Datei erfolgreich hochgeladen und in '
								.$upload_path.' gespeichert.</p>';
					
					if (isset($_POST['loeschen']) && $_POST['loeschen'] == 'loeschen') {
						$this->delete_all_objetcs();
					}
					//Verarbeitung der CSV-Datei
					$ergebnis.= $this->read_csv($upload_path);
				} 
				else {
					// Fehler beim Verschieben der Datei
					$ergebnis.= 'Fehler beim Hochladen der CSV-Datei.';
				}
			} 
			else {
				// Datei ist keine CSV-Datei
				$ergebnis.= 'Bitte laden Sie eine CSV-Datei hoch.';
			}
		}	
		else {
				// Fehler beim Hochladen der Datei
				$ergebnis.= 'Fehler beim Hochladen der Datei.';
			}
		}
		
		
		return $ergebnis;
	}
	
	public function delete_all_objetcs(){
		$this->my_termin_repository->delete_all();
	}
	
	 public function read_csv($filepath) {
		 
		 $ergebnis="<p>Importierte Daten:<br/>";
        // Überprüfen Sie, ob die Datei existiert
        if (!file_exists($filepath)) {
            return false;
        }    
        // Laden der CSV-Datei
        $reader = IOFactory::createReader('Csv');
        $reader->setDelimiter(';');
        $reader->setEnclosure('');
        $reader->setSheetIndex(0); // Falls die CSV mehrere Blätter hat, hier das Blatt festlegen
        $spreadsheet = $reader->load($filepath);

        // Extrahieren der Daten aus dem ersten Blatt
        $worksheet = $spreadsheet->getActiveSheet();
        $csv_data = [];

        // Lesen der Header-Zeile
		$header_row = [];
		$first_row = true;
					//DEBUG
					$ergebnis.="<b>Überschriften</b> <br/>";
		foreach ($worksheet->getRowIterator() as $row) {
			if ($first_row) {
				foreach ($row->getCellIterator() as $cell) {
					$header_row[] = $cell->getFormattedValue();
					//DEBUG
					$ergebnis.= $cell->getFormattedValue().'<br/>';
				}
				$first_row = false;
				continue;
			}
				//DEBUG
				$ergebnis.= '<br/>';
				
			$row_data = [];
			$my_key=0;
			foreach ($row->getCellIterator() as $key => $cell) {
				$value = $cell->getFormattedValue();
				if ($value == Null){
					$value='';
				}
				$row_data[$header_row[$my_key]] = $value;
				//DEBUG
				$ergebnis.= '<b>'.$header_row[$my_key].'</b>: '.$value.'<br/>';
				$my_key++;
			}
			$csv_data[] = $row_data;
		}
		//DEBUG
				$ergebnis.= '<br/>';
		// Erzeugen von Termin-Objekten und Speichern in die Datenbank
		foreach ($csv_data as $data) {
			
			$this->add_object($data);
			
		}
		$ergebnis.='</p>';
		return $ergebnis; // oder geben Sie die verarbeiteten Daten zurück, wenn nötig
	}
}
