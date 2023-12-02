<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
namespace timetable;
require_once 'Repository_interface.php';

class Termine_repository implements Repository_interface{
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'tt_termine';
	 }
	 public function create( array $data ) {
		 
	 }

	 public function delete( $id ) {
		 
	 }

	 public function find( $id ) {
		 
	 }

	 public function get_data() {
		 $resultSet = $this->wpdb->get_results('SELECT * FROM '.$this->tabellenname.';', ARRAY_A); 
		 return $resultSet;
	 }

	 public function update( $id, array $data ) {
		 
	 }
 
	 
	
}   