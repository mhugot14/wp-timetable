<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

require_once 'Timetable_repository.php';
require_once 'Termine_repository.php';
require_once 'Termin.php';
require_once MH_TT_PATH. '/includes/Plugin_Helpers.php';

use DateTime;
/**
 * Description of Timetable
 *
 * @author micha
 */
class Timetable {
	private $id;
	private $bezeichnung, $beschreibung;
	private DateTime $erzeugt_am;
	private DateTime $earliest_date;
	private DateTime $last_date;
	private $laenge; //Länge der Timetable in Tagen
	private $timetable_objects;
	private $my_timetable_repository;
	private $my_Termine_repository;
	function __construct($id){
		 $this->id=$id;
		 $this->initialize_object($id);
		 
		 
	}
	
	public function initialize_object($id){
		$this->my_timetable_repository = new Timetable_repository();
		 
			$timetable_data = $this->my_timetable_repository->find($id);
			
			if ($timetable_data != false){
				$this->bezeichnung = $timetable_data[0]['bezeichnung'];
				$this->beschreibung= $timetable_data[0]['beschreibung'];
				$date_time_object = DateTime::createFromFormat('Y-m-d', $timetable_data[0]['erzeugt_am']);
				if ($date_time_object!== FALSE){
					$this->erzeugt_am = $date_time_object; 
				}else {
					 // Handle den Fall, in dem das DateTime-Objekt nicht erstellt werden konnte
					  echo "Das Datenbank-Datum der Timetable konnte nicht erzeugt werden";
				}
				
				$this->timetable_objects=$this->termine_to_objects();
				if ($this->timetable_objects!=null){
					$this->earliest_date=$this->earliest_date();
					$this->last_date=$this->last_date();
					$this->laenge=$this->get_laenge_in_tagen();
				}
			}
	}
	
	 //Liefert frühstes Datum aller Termine der Timetable zurück
	 public function earliest_date():?DateTime{
		$earliest_date = null;
		foreach ($this->timetable_objects as $termin) {
        $beginn_datum = $termin->get_termin_beginn(); // Wandle das Datum in einen Unix-Timestamp um
			if ($beginn_datum !== false) {
				if ($earliest_date === null || $beginn_datum < $earliest_date) {
					$earliest_date = $beginn_datum;
				}
			}
		}
		if ($earliest_date !== null) {
			return $earliest_date; // Formatierung des gefundenen frühesten Datums
		} else {
			return null; // Wenn kein gültiges Datum gefunden wurde
		}	
			return $earliest_date;
	}
	
	//Liefert das letzte Datum aller Termine der Timetable zurück
	 public function last_date():?DateTime{
		$last_date=null;
		foreach ($this->timetable_objects as $termin) {
        $ende_datum = $termin->get_termin_ende(); 
			if ($ende_datum !== false) {
				if ($last_date === null || $ende_datum > $last_date) {
					$last_date = $ende_datum;
				}
			}
		}
		if ($last_date !== null) {
			return $last_date; // Formatierung des gefundenen frühesten Datums
		} else {
			return null; // Wenn kein gültiges Datum gefunden wurde
		}	
			return $last_date;
	}
	
	//Erstellt aus einem Resultset ein Array von Objekten
	public function termine_to_objects():array {
        $termine = array(); // Hier werden die Termine gespeichert
		$this->my_Termine_repository = new Termine_repository();
		$termine_resultset = $this->my_Termine_repository->get_data_by_timetable_id($this->id);
        foreach ($termine_resultset as $termin) {
            // Daten aus dem ResultSet extrahieren
            $id = $termin['id'];
            $termin_beginn = $termin['beginn'];
            $termin_ende = $termin['ende'];
            $bildungsgang = $termin['bildungsgang'];
            $bezeichnung = $termin['bezeichnung'];
			$ereignistyp=$termin['ereignistyp'];
            $verantwortlich = $termin['verantwortlich'];
            $timetable_id = $termin['timetable_ID'];

            // Ein Objekt der Klasse Termin_controller erstellen und zum Array hinzufügen
			$termin = new Termin($termin_beginn, $termin_ende, 
					$bildungsgang, $bezeichnung, $ereignistyp, $verantwortlich, $timetable_id);
			$termin->set_id( $id );
			$termine[] = $termin;
		}
			 // Sortiere das Array nach Bildungsgang und dann nach Datum
        usort($termine, function ($a, $b) {
            $bildungsgangComparison = strcmp($a->get_bildungsgang(), $b->get_bildungsgang());

            if ($bildungsgangComparison === 0) {
                // Wenn die Bildungsgänge gleich sind, vergleiche nach Datum
                return $a->get_termin_beginn()<=> $b->get_termin_beginn();
            }
            return $bildungsgangComparison;
        });

		return $termine;
	}
	
	public function check_anzahl_termine():bool{
		$status=false;
		
		if (count($this->timetable_objects) >= 2){
			$status = true;
	}
		
		return $status;
		
	}
   //Array mit allen Tagen innerhalb der Timetable (z.B. für einen Kopf)
	public function get_dates( ):?array{
		$dates=array();
		$date= clone $this->earliest_date;
		
		while($date<=$this->last_date){
			$dates[]= clone $date;
			$date->modify('+1 day');
		}
		return $dates;		
	}
	
	public function get_laenge_in_tagen(){
		
		$laenge = ($this->get_earliest_date()->diff($this->get_last_date()))->days+1;
		return $laenge;
	}
	//Funktion generiert eine iCal-Datei für einen bestimmten Bildungsgang
	public function generate_ical($bildungsgang){
		 // iCal-Inhalt erstellen
			$ical_content = "BEGIN:VCALENDAR\n";
			$ical_content .= "VERSION:2.0\n";
			// Weitere iCal-Einträge hinzufügen...
			$ical_content .= "END:VCALENDAR\n";

		// Pfad zum Speichern der iCal-Datei
			$file_name= sanitize_file_name('/timetable_' . $this->get_id().'_'.$bildungsgang.'.ics');
			$file_dir = Plugin_Helpers::create_upload_folder( 'timetable/icals');
			$file_path = $file_dir .$file_name;

		// iCal-Datei speichern
			file_put_contents($file_path, $ical_content);
			
			$download_file_path= Plugin_Helpers::get_download_path( 'timetable/icals').$file_name;

    // Rückgabewert: Downloadpfad zur gespeicherten iCal-Datei
    return $download_file_path;
	}
	
	public function get_timetable_objects() {
		return $this->timetable_objects;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_earliest_date(): DateTime {
		return $this->earliest_date;
	}

	public function get_last_date(): DateTime {
		return $this->last_date;
	}

	public function set_timetable_objects( $timetable_objects ): void {
		$this->timetable_objects = $timetable_objects;
	}

	public function set_id( $id ): void {
		$this->id = $id;
	}

	public function set_earliest_date( DateTime $earliest_date ): void {
		$this->earliest_date = $earliest_date;
	}

	public function set_last_date( DateTime $last_date ): void {
		$this->last_date = $last_date;
	}
	public function get_laenge() {
		return $this->laenge;
	}

	public function set_laenge( $laenge ): void {
		$this->laenge = $laenge;
	}

	public function get_bezeichnung() {
		return $this->bezeichnung;
	}

	public function get_beschreibung() {
		return $this->beschreibung;
	}

	public function get_erzeugt_am(): DateTime {
		return $this->erzeugt_am;
	}

	public function set_bezeichnung( $bezeichnung ): void {
		$this->bezeichnung = $bezeichnung;
	}

	public function set_beschreibung( $beschreibung ): void {
		$this->beschreibung = $beschreibung;
	}

	public function set_erzeugt_am( DateTime $erzeugt_am ): void {
		$this->erzeugt_am = $erzeugt_am;
	}




}
