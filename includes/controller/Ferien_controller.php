<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

require_once 'Controller_interface.php';
//require_once MH_TT_PATH.'includes/model/Termin.php';
require_once MH_TT_PATH.'includes/model/Ferien_repository.php';
/**
 * Description of Ferien_controller
 *
 * @author micha
 */
class Ferien_controller implements Controller_interface  {
	
	private $my_ferien_repository;
	
	public function __construct( ) {
		
		$this->my_ferien_repository= new Ferien_repository; 
	}
	
	//put your code here
	public function add_object( $data ) {
		$this->my_ferien_repository->create($data);
	}

	public function delete_object( $id ) {
		$this->my_ferien_repository->delete($id);
	}

	public function edit_object( $id ) {
		
	}

	public function get_object_by_id( $id ) {
		
	}
	
	public function get_all_data() {
		
		$data = $this->my_ferien_repository->get_data();
		
		return $data;
	}
}
