<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';
require_once MH_TT_PATH. 'includes/model/Termine_repository.php';
require_once MH_TT_PATH. 'includes/controller/Termin_controller.php';
require_once 'Controller_interface.php';

use DateTime;

class Timetable_controller implements Controller_interface{
	
	private $timetable_data;
	private $timetable_objects;
	private $my_termine_repository;
	private $my_timetable_repository;
	private $my_timetable, $loesch_timetable;

	
	 function __construct(){
		 $this->my_timetable_repository = new Timetable_repository();
	 
	 }
	 public function add_object($data) {
		$my_timetable = new Timetable();
		$my_timetable->set_bezeichnung( $data['bezeichnung']);
		$my_timetable->set_beschreibung($data['beschreibung'] );
		
	   
		$my_timetable->save();
	 }

	 public function delete_object( $id ) {
		 $loesch_timetable= new Timetable( $id );
		 if ($loesch_timetable->get_laenge()>0){
			 echo "Timetable kann nicht gelöscht werden, da noch Termine zugeordnet sind.";
			 return 0;
		 }
		 else{
				$this->my_timetable_repository->delete($id);
				return 1;
		 }
	 }

	 public function edit_object( $data) {
		$my_timetable = new Timetable($data['id']);
		$my_timetable->set_bezeichnung($data['bezeichnung']);
		$my_timetable->set_beschreibung($data['beschreibung']);
		
		$my_timetable->update();
	 }

	 public function get_object_by_id( $id ) {
		$my_timetable = new Timetable($id);
         		
			return $my_timetable;
	
	 }
	 
	 public function get_timetables_for_dropdown(){
		 $timetable_for_dropdown = [];
		 $resultset = $this->my_timetable_repository->get_data();
		 
		 foreach ($resultset as $row){
			 $timetable_for_dropdown[] = array('id'=>$row['id'], 'bezeichnung'=>$row['bezeichnung']);
		 }
		 
		 return $timetable_for_dropdown;
		 
		 
	 }
	 
	 public function process_form_submission($form_data){
		
		if (!isset($form_data['timetable_speichern_nonce']) || 
			!wp_verify_nonce($form_data['timetable_speichern_nonce'], 'timetable_speichern_nonce')) {
           wp_die('Nonce-Fehler!'); // Sicherheitsüberprüfung für Nonce
        }
		//Daten einlesen und bereinigen
		$san_data = [];
		
         if (!empty ($form_data['id'])){
			 $san_data['id']=sanitize_text_field($form_data['id']);
		 }		
		 $san_data['bezeichnung'] = sanitize_text_field($form_data['bezeichnung']);
		 $san_data['beschreibung'] = sanitize_text_field($form_data['beschreibung']);
		 		
		$errors = $this->check_form_data($san_data);
		
		if (empty($errors) && empty($form_data['id'])){
		 $this->add_object($san_data);
		}
		else if (empty($errors) && !empty($form_data['id'])){
		 $this->edit_object($san_data);
		}
		else{
			return $errors;
		}
	}
	
	public function check_form_data($data){
		$errors = [];
				
		 if (empty($data['beschreibung'])) {
			$errors['beschreibung'][] = 'Gib eine Beschreibung an.';
		 }
		
		 if (empty($data['bezeichnung'])) {
			$errors['bezeichnung'][] = 'Gib eine Bezeichnung an.';
		 }
		 

		return $errors;
	}
	
}
