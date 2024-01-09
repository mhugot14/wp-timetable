<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once MH_TT_PATH.'includes/controller/Timetable_controller.php';

class Timetable_frontend_view{
	
	private $my_timetable_controller;
	private $id;
	
	 function __construct($id){
		 $this->id=$id;
		 $this->my_timetable_controller = new Timetable_controller($this->id);
	 }
	
	public function print():string{
		  $print="";
		  $print.="<p>Timetable Beginn: ". $this->my_timetable_controller->earliest_date()."</p>";
		  $print.="<p>Timetable Ende: ". $this->my_timetable_controller->last_date()."</p>";
		  $print .= '<table>';
		 
		
		foreach ($this->my_timetable_controller->get_timetable_data() as $termin){
			$print .= '<tr>';
			$print .= '<td>'.$termin['bildungsgang'].'</td>';
			$print .= '<td>'.$termin['bezeichnung'].'</td>';
			$print .= '<td>'.$termin['ereignistyp'].'</td>';
			$print .= '<td>'.$termin['beginn'].'</td>';
			$print .= '<td>'.$termin['ende'].'</td>';
			$print .= '<td>'.$termin['verantwortlich'].'</td>';
			$print .= '</tr>';
		}
		
		$print .= '</table>';
		return $print;
	}
	
}