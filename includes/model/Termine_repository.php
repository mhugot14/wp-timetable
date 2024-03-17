<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
namespace timetable;
require_once 'Repository_interface.php';

class Termine_repository implements Repository_interface{
	private $wpdb;
	private $tabellenname;
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'tt_termine';
	 }
	 public function create($termin) {
		 try{
			$rueck=$this->wpdb->insert($this->tabellenname,
					
				array(
						'bildungsgang' => $termin->get_bildungsgang(),
						'bezeichnung' => $termin->get_bezeichnung(),
						'ereignistyp' => $termin->get_ereignistyp(),
						'beginn' => $termin->get_termin_beginn()->format('Y-m-d'),
						'ende' => $termin->get_termin_ende()->format('Y-m-d'),
						'verantwortlich'=>$termin->get_verantwortlich(),
						'timetable_ID'=>$termin->get_timetable_id()
					)
				);
			}
			
		catch (Exception $ex) {
			
			echo "Die Termine konnten nicht in die DB importiert werden: ".$ex;
		
		}
	 }

	 public function delete( $id ) {
		 
	 }
	 public function delete_all(  ) {
		 try{
					$this->wpdb->query("TRUNCATE TABLE ".$this->tabellenname.';');
				} catch (Exception $ex) {
					echo "Die Tabelle konnte nicht geleert werden: ".$ex;
				}
	 }

	 public function find( $id ) {
		 
	 }

	 public function get_data() {
		 $resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.';', ARRAY_A); 
		 return $resultSet;
	 }
	 
	 public function get_data_by_timetable_id($timetable_id){
		 $resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.
				 ' WHERE timetable_ID = '.$timetable_id. ' order by bildungsgang, beginn;', ARRAY_A); 
		 return $resultSet;
	 }

	 public function update( $id, array $data ) {
		 
	 }
 
	 
	
}   