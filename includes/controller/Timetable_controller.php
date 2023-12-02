<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH. 'includes/model/Termine_repository.php';

class Timetable_controller{
	
	private $timetable_data;
	private $my_termine_repository;
	private $id;
	private $earliest_date;
	private $last_date;
	
	 function __construct($id){
		 $this->id=$id;
		 $this->my_termine_repository = new Termine_repository();
		 $this->timetable_data = $this->my_termine_repository->get_data_by_timetable_id($this->id);
	 }
	
	 public function get_timetable_data(){
		 return $this->timetable_data;
	 }
	 
	 
	 
	 public function earliest_date(){
		$earliest_date = null;
		foreach ($this->timetable_data as $termin) {
        $beginn_datum = strtotime($termin['beginn']); // Wandle das Datum in einen Unix-Timestamp um

			if ($beginn_datum !== false) {
				if ($earliest_date === null || $beginn_datum < $earliest_date) {
					$earliest_date = $beginn_datum;
				}
			}
		}
		if ($earliest_date !== null) {
			return date('d.m.y', $earliest_date); // Formatierung des gefundenen frühesten Datums
		} else {
			return null; // Wenn kein gültiges Datum gefunden wurde
		}	
			return $earliest_date;
	}
	
	 public function last_date(){
		$last_date = null;
		foreach ($this->timetable_data as $termin) {
        $ende_datum = strtotime($termin['ende']); // Wandle das Datum in einen Unix-Timestamp um

			if ($ende_datum !== false) {
				if ($last_date === null || $ende_datum > $last_date) {
					$last_date = $ende_datum;
				}
			}
		}
		if ($last_date !== null) {
			return date('d.m.y', $last_date); // Formatierung des gefundenen frühesten Datums
		} else {
			return null; // Wenn kein gültiges Datum gefunden wurde
		}	
			return $last_date;
	}
	
}
