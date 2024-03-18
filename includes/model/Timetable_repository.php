<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
namespace timetable;
require_once 'Repository_interface.php';

class Timetable_repository implements Repository_interface{
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'tt_timetable';
		 
	 }
	 public function create( array $data ) {
		 
	 }

	 public function delete( $id ) {
		 
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
			echo $resultSet;
			return FALSE;
	 }
		 
		 
	 }

	 public function update( $id, array $data ) {
		 
	 }

	 
}   