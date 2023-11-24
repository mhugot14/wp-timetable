<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
namespace timetable;


class timetable_repository{
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'usc_schildfaecher';
	 }
	
}   