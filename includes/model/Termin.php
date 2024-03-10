<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;
use DateTime;
/**
 * Description of Termine_controller
 *
 * @author micha
 */
class Termin {
	private $id;
	private DateTime $termin_beginn, $termin_ende;
	private string $bildungsgang, $bezeichnung, $ereignistyp, $verantwortlich, $timetable_id;
	
	
	function __construct($id, $termin_beginn, $termin_ende, $bildungsgang, 
							$bezeichnung, $ereignistyp,$verantwortlich, $timetable_id){
		$this->id=$id;
		$this->termin_beginn=DateTime::createFromFormat('Y-m-d', $termin_beginn); 
		$this->termin_ende=DateTime::createFromFormat('Y-m-d', $termin_ende); 
		$this->bildungsgang=$bildungsgang;
		$this->bezeichnung=$bezeichnung;
		$this->ereignistyp = $ereignistyp;
		$this->verantwortlich=$verantwortlich;
		$this->timetable_id=$timetable_id;
		
	}
	function get_dauer(){
		$dauer=0;
		$dauer = $this->termin_beginn->diff($this->termin_ende);
		return ($dauer->days+1);
	}
	
	public function get_id() {
            return $this->id;
        }
		public function get_ereignistyp(): string {
			return $this->ereignistyp;
		}

		        public function get_termin_beginn(): DateTime {
            return $this->termin_beginn;
        }

        public function get_termin_ende(): DateTime {
            return $this->termin_ende;
        }

        public function get_bildungsgang(): string {
            return $this->bildungsgang;
        }

        public function get_bezeichnung(): string {
            return $this->bezeichnung;
        }

        public function get_verantwortlich(): string {
            return $this->verantwortlich;
        }

        public function set_id($id): void {
            $this->id = $id;
        }

        public function set_termin_beginn(DateTime $termin_beginn): void {
            $this->termin_beginn = $termin_beginn;
        }

        public function set_termin_ende(DateTime $termin_ende): void {
            $this->termin_ende = $termin_ende;
        }

        public function set_bildungsgang(string $bildungsgang): void {
            $this->bildungsgang = $bildungsgang;
        }

        public function set_bezeichnung(string $bezeichnung): void {
            $this->bezeichnung = $bezeichnung;
        }

        public function set_verantwortlich(string $verantwortlich): void {
            $this->verantwortlich = $verantwortlich;
        }


}
