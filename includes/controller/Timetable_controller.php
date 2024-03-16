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

	
	 function __construct(){

	 
	 }
	 public function add_object($data) {
		 
	 }

	 public function delete_object( $id ) {
		 
	 }

	 public function edit_object( $id ) {
		 
	 }

	 public function get_object_by_id( $id ) {
		 
	 }

	 
	
}
