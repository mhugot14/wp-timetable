<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH. 'includes/model/Termine_repository.php';
require_once MH_TT_PATH. 'includes/controller/Termin_controller.php';
use DateTime;

class Timetable_controller{
	
	private $timetable_data;
	private $timetable_objects;
	private $my_termine_repository;
	private $id;
	private DateTime $earliest_date;
	private DateTime $last_date;
	
	
	 function __construct($id){
		 $this->id=$id;
		 $this->my_termine_repository = new Termine_repository();
		 $this->timetable_data = $this->my_termine_repository->get_data_by_timetable_id($this->id);
		 $this->timetable_objects=$this->resultset_to_objects();
		 $this->earliest_date=$this->earliest_date();
		 $this->last_date=$this->last_date();
	 }
	
	 public function get_timetable_data(){
		 return $this->timetable_data;
	 }
	 
	 //Liefert frühstes Datum aller Termine der Timetable zurück
	 public function earliest_date():?DateTime{
		$earliest_date = null;
		foreach ($this->timetable_data as $termin) {
        $beginn_datum = DateTime::createFromFormat('Y-m-d', $termin['beginn']); // Wandle das Datum in einen Unix-Timestamp um
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
		foreach ($this->timetable_data as $termin) {
        $ende_datum = DateTime::createFromFormat('Y-m-d', $termin['ende']); 
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
	public function resultset_to_objects():array {
        $termine = array(); // Hier werden die Termine gespeichert

        foreach ($this->timetable_data as $termin) {
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
            $termine[] = new Termin_controller($id, $termin_beginn, $termin_ende, 
					$bildungsgang, $bezeichnung, $ereignistyp, $verantwortlich, $timetable_id);
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


}

