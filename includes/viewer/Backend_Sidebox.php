<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
 namespace timetable;

class Backend_Sidebox{

private $header, $content;

public function __construct( $header, $content ) {
	$this->header = $header;
	$this->content = $content;
}

public function print(){
	
	$ausgabe =	 
			'<div class="be_sidebox">
				 <div class="sidebox_header">
					 <h2>'.$this->get_header().'</h2>	
				 </div>
				 <div class="sidebox_content">'.
					 $this->get_content().
				 '</div>
			</div>'; 
	
	return $ausgabe;
}

public function get_header() {
	return $this->header;
}

public function get_content() {
	return $this->content;
}

public function set_header( $header ): void {
	$this->header = $header;
}

public function set_content( $content ): void {
	$this->content = $content;
}




	
}