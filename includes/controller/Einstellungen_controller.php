<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

/**
 * Description of Einstellungen_controller
 *
 * @author micha
 */
class Einstellungen_controller {
		
	public function __construct(){
		
	}
	

	
	public function save(){
		
	}
	
	public function add_einstellung($typ, $wert){
		
	}
	
	public function get_bildungsgaenge(){
		$bildungsgaenge = get_terms([
		'taxonomy'   => 'bildungsgang',
		'hide_empty' => false, // Auch leere Taxonomie-Begriffe anzeigen
			]);
		return $bildungsgaenge;
	}
	
	public function get_ereignistypen(){
		$ereignistyp = get_terms([
		'taxonomy'   => 'ereignistyp',
		'hide_empty' => false, // Auch leere Taxonomie-Begriffe anzeigen
			]);
		return $ereignistyp;
	}
}

