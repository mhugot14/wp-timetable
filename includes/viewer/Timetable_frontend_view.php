<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once MH_TT_PATH.'includes/controller/Timetable_controller.php';
require_once MH_TT_PATH.'includes/model/Timetable.php';
use DateTime;

class Timetable_frontend_view{
	
	private $my_timetable_controller;
	private $my_timetable;
	private $id, $timetable_laenge;
	private DateTime $timetable_start, $timetable_ende;
	
	 function __construct($id){
		 $this->id=$id;
		 $this->my_timetable = new Timetable($this->id);
		 if ($this->my_timetable->get_timetable_objects()!=null){
			$this->timetable_start = $this->my_timetable->get_earliest_date(); //DateTime::createFromFormat('d.m.y', $this->my_timetable_controller->earliest_date());
			$this->timetable_ende =$this->my_timetable->get_last_date(); //DateTime::createFromFormat('d.m.y', $this->my_timetable_controller->last_date());
			$this->timetable_laenge= $this->my_timetable->get_laenge();
		 }
	 }
	public function print_grid():string{
		
//		$grid=$this->print_bildungsgaenge_objects();
		$grid=$this->generiere_gantt($this->my_timetable->get_timetable_objects());
		return $grid;
	}
	
	public function generiere_gantt(): string {
		$html = '<h2>'.$this->my_timetable->get_bezeichnung().'</h2>'
				.'<p><b>'.$this->my_timetable->get_beschreibung().'</b></p>';
				
        if ($this->my_timetable->check_anzahl_termine()==true){
			$html .='<div class="timetable-container">'.'<table class="timetablegrid">'
				. '<thead class="timetablegrid_thead"><tr classe_timetablegrid_tr><th class="sticky_column">Bildungsgang</th>';
			$dates = $this->my_timetable->get_dates();
			foreach ($dates as $date) {
					$html .= '<th class="timetable_date">' . $date->format('d.m.') .
							' | '.$this->get_wochentag($date) .'</th>';
				}
			$html .= '</tr></thead><tbody>';

			$currentBildungsgang = null;
			$zaehler=0;
			//Achtung, funktioniert nur dann wenn get_timetable_objects nach Bildungsgängen sortiert
			foreach ($this->my_timetable->get_timetable_objects() as $termin) {
					if ($termin->get_bildungsgang() != $currentBildungsgang) {
					if ($zaehler>0 AND $zaehler<count($dates)){
							$html .= '<td class="td_ende" colspan="'.count($dates)-$zaehler.'">Ende</td>';
						}

					$zaehler=0;
					$currentBildungsgang = $termin->get_bildungsgang();
					$html .='<tr><td class="sticky_column">' . $termin->get_bildungsgang() . '</td>';
				}	
					while ($zaehler<count($dates)){
			//		 echo '<br>'.$currentBildungsgang.' Termin Id: '.$termin->get_id().': $dates['.$zaehler.']: '.
			//				 $dates[$zaehler]->format('d.m'). 
			//					' = $termin->get_termin_beginn(): '.$termin->get_termin_beginn()->format('d.m');
						if ($dates[$zaehler]!=$termin->get_termin_beginn()){
							$html.='<td></td>';
							$zaehler+=1;
						}
						else{
							$dauer = $termin->get_dauer();
							$html .= '<td colspan="'.strval($dauer).'" class="td_'.
										$termin->get_ereignistyp().'">'.$termin->get_ereignistyp() ;
							if ($dauer>6 OR $termin->get_ereignistyp()=="Sonstiges"){

								$html .= ' (<i>'.$termin->get_bezeichnung().'</i>)' ;
							}
							$html.='</td>';
							$zaehler+=$dauer;

							break;

						}	
					}
			}
		$html .= '</tbody></table></div>';
		
		}
		else{
			$html.='<p style="color:red;font-style:italic;">Keine Termine vorhanden</p>';
		}
        return $html;	
	}
	public function get_wochentag(DateTime $date):string{
		$wochentag=$date->format('l');
		 $translations = [
        'Monday'    => 'Mo',
        'Tuesday'   => 'Di',
        'Wednesday' => 'Mi',
        'Thursday'  => 'Do',
        'Friday'    => 'Fr',
        'Saturday'  => 'Sa',
        'Sunday'    => 'So',
    ];

    return $translations[$wochentag];

	}
}