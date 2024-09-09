<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
namespace timetable;
require_once 'Repository_interface.php';

class Timetable_repository implements Repository_interface{
	
	private $wpdb;
	private $tabellenname;
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'tt_timetable';
		 
	 }
	 
	 //Funktion legt ein neues Objekt in der Datenbank an, die ID vergibt die DB.
	 public function create( $timetable ) {
		  try{
			$rueck=$this->wpdb->insert($this->tabellenname,
					
				array(
						'bezeichnung' => $timetable->get_bezeichnung(),
						'beschreibung' => $timetable->get_beschreibung()
					)
				);
			}
			
		catch (Exception $ex) {
			
			echo "Die Timetable <i>".$timetable->get_bezeichnung()."</i> konnte nicht "
					. "in die DB importiert werden: ".$ex;
		
		}
		 
	 }

	 public function delete( $id ) {
		  try{
					$query=$this->wpdb->prepare("DELETE FROM ".$this->tabellenname." WHERE id = %d;", $id);
					$this->wpdb->query($query);
				} catch (Exception $ex) {
					echo "Objekt konnte nicht gelöscht werden: ".$ex;
				} 
		 
	 }


	 public function get_data() {
		 $resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.';', ARRAY_A); 
		 return $resultSet;
	 }
	 
	 public function find($id){
		$query = $this->wpdb->prepare('SELECT * FROM ' . $this->tabellenname . ' WHERE id = %d;', $id);
		$resultSet = $this->wpdb->get_results($query, ARRAY_A);
		
		 if ($resultSet != NULL){
			return $resultSet;
		 }
		else {
			echo "Es konnte keine Timetable gefunden werden.";//$resultSet;
			return FALSE;
	 }
		 
		 
	 }

	 public function update( $id,  $timetable ) {
		   try{
			$rueck=$this->wpdb->update($this->tabellenname,
					
				array(
						'bezeichnung' => $timetable->get_bezeichnung(),
						'beschreibung' => $timetable->get_beschreibung()
				),
				array(
						'id'=>$id)
				);
			}
			
		catch (Exception $ex) {
			
			echo "Die Timetable nicht in die DB importiert werden: ".$ex;
		
		}
		 
	 }

	 
}   