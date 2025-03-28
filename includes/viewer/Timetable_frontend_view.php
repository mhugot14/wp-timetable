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
	private $id, $timetable_laenge, $entwurf;
	private DateTime $timetable_start, $timetable_ende;
	
	 function __construct($atts){
		 
		 $this->id=$atts['id'];
		 $this->entwurf=$atts['entwurf'];
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
		$today = (new DateTime())->format( 'd.m.y');
		$entwurf_text="";
		if ($this->entwurf=='ja'){
			$entwurf_text=' <span style="color:red;"> | ENTWURF - noch nicht eintragen</span>';
		}
		$html = '<h2>'.$this->my_timetable->get_bezeichnung().$entwurf_text.'</h2>'
				.'<p><b>'.$this->my_timetable->get_beschreibung().'</b></p>';
		
		// 🟢 Druck-Button einfügen
$html .= '<button onclick="openPrintView'.$this->id.'()" class="button">️Im neuen Fenster öffnen</button>';

// 🟢 JavaScript-Funktion für das Popup
	$html .= '<script>
    function openPrintView'.$this->id.'() {
        let printWindow = window.open("' . esc_url_raw(admin_url("admin-ajax.php?action=print_timetable&id=" . $this->id)) . '", "PrintWindow", "width=900,height=600");
        printWindow.focus();
    }
	</script>';

		
		//Test Javascript, um den aktuellen Tag vorne anzuzeigen
		// JavaScript zum automatischen Scrollen
		$html .= '<script>';
		$html .= 'window.onload = function() {';
		$html .= 'var todayColumn = document.querySelector("th.timetable_date_today");';
		 $html .= 'if (todayColumn) {';
		$html .= 'var todayColumnOffset = todayColumn.offsetLeft;';
		$html .= 'var tableContainer = document.querySelector(".timetable-container");';
		$html .= 'var viewportWidth = tableContainer.offsetWidth;';
		$html .= 'var scrollOffset = todayColumnOffset - (viewportWidth / 2);'; // Reduziere den Scrollwert um die Hälfte der Viewport-Breite
		$html .= 'tableContainer.scrollLeft = scrollOffset;';
		$html .= '}';
		$html .= '};';
		$html .= '</script>';
		 // 🟢 Feiertage & Ferien abrufen
    $ferien_und_feiertage = $this->my_timetable->get_ferien_und_feiertage();
		

        if ($this->my_timetable->check_anzahl_termine()==true){
			$html .='<div class="timetable-container">'.'<table class="timetablegrid">'
				. '<thead class="timetablegrid_thead"><tr classe_timetablegrid_tr><th class="sticky_column">Bildungsgang</th>';
			$dates = $this->my_timetable->get_dates();
			foreach ($dates as $date) {
				$wochentag = $this->get_wochentag($date);
				$this_date = $date->format('d.m.y');
				$css_klasse = "timetable_date";
				if (($wochentag=='Sa' OR $wochentag=='So') AND $this_date!=$today){
					$css_klasse.='_weekend';
				}
				   // 🟢 Feiertage & Ferien hervorheben
				elseif (isset($ferien_und_feiertage[$this_date])) {
					$css_klasse .= '_holiday';
				}
				if ($this_date==$today){
					$css_klasse.='_today';
				}
				
				$html .= '<th class="'.$css_klasse.'">' . $date->format('d.m.') .
							' | '.$wochentag .'</th>';
			
				
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
					$ical_text="";
					if ($this->entwurf=="nein"){
						$ical_text = ' <a href="'.$this->my_timetable->generate_ical( $currentBildungsgang ).'">(iCal)</a> ';	
					}
					
					
					$html .='<tr><td class="sticky_column">' . $termin->get_bildungsgang() . 
							$ical_text.'</td>';
				}	
					while ($zaehler<count($dates)){
						$date = $dates[$zaehler];
						$date_day_month= (clone $date)->format( 'd.m.y');

								$wochentag=$this->get_wochentag($date);
						if ($date!=$termin->get_termin_beginn()){
							
							if($date_day_month ==$today){
								$html.='<td class="today"></td>';
							}
						   elseif (isset($ferien_und_feiertage[$date_day_month])) {
								$html.='<td class="holiday"></td>';
						   }
							elseif($wochentag=='Sa'OR $wochentag=='So'){
								$html.='<td class="weekend"></td>';
							}
							else{
								$html.='<td></td>';
							}
							
							$zaehler+=1;
						}
						else{
							$dauer = $termin->get_dauer();
							$html .= '<td colspan="'.strval($dauer).'" class="td_'.
										sanitize_title($termin->get_ereignistyp()).'">'.$termin->get_ereignistyp() ;
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