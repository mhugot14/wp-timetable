<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

class View{
	
	public function __construct(){
		add_action('admin_menu', [$this, 'create_menu']);
		add_action('add_meta_boxes', [$this,'register_metaboxes']);
		
		do_action( 'timetable/View/init', $this );
		//$this->myPluginHelpers = new Plugin_Helpers;
	}
	

	
	//Initialisierung des Admin-Menüs durch die Funktion create_menu
	
	public function create_menu(){
		add_menu_page('Timetable',
					 'Timetable',
					 'manage_options', 
					 'mh-timetable', 
					 [$this, 'render_timetable_page'],
					'dashicons-clock',30
				);
		
	

	}
	
	public function register_metaboxes(){
		echo '<h1>hey</h1>';
		add_meta_box(
				'mh-metabox-timetable',
				'Timetables',
				[$this,'render_metabox_timetable'],
				'mh-timetable',
				'normal'
				);
	
	}
	
	public function render_timetable_page(){
		echo "<h1>Timetable - deine Übersicht bei der Zeugnisschreibung</h1>"
		. "<p>Mit Timetable kannst du eine Übersicht im GANTT-Stil erzeugen, "
				. "die einem gesamten Kollegium eine Übersicht bietet, was an welchem Tag "
				. "bzw. Zeitraum während der Zeugnisschreibung zu tun ist.</p>"
				. "<p>So organisiert du entspannt die Noteneinsammlung, über APAs, Konferenzen und Ausgaben "
				. "mit dieser Timetable</p>";
	}
	
	public function render_metabox_timetable(){
		echo 'hi';
	}
	
}