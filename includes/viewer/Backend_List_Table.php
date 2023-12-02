<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Backend_List_Table extends \WP_List_Table {
	
	private $data;
	
    public function __construct() {
        parent::__construct([
            'singular' => 'custom_item',
            'plural'   => 'custom_items',
            'ajax'     => false,
			'screen'   => null
        ]);


	}
   
	public function set_data($data){
		$this->data = $data;
	}
	public function prepare_items(){
		$this->items = $this->data;
		$columns= $this->get_columns();
		$this->_column_headers=array($columns);
	}	
	
	public function get_columns(){
		$columns = array(
			'id' => 'ID',
			'bildungsgang' => 'Bildungsgang',
			'bezeichnung' => 'Bezeichnung',
			'ereignistyp' => 'Ereignistyp',
			'beginn' => 'Beginn',
			'ende' => 'Ende',
			'verantwortlich' => 'Verantwortlich',
			'timetable_ID' => 'Timetable'
		);
		return $columns;
		
	}
	
	public function column_default($item, $column_name){
		switch($column_name){
			case 'id':
			case 'bildungsgang':
			case 'bezeichnung':
			case 'ereignistyp':
			case 'beginn':
			case 'ende':
			case 'verantwortlich':
			case 'timetable_ID':
				return $item[$column_name];
			default:
				return "no value";
		}
	}
	
	   // Diese Methode fügt eine benutzerdefinierte Aktion für das Hinzufügen eines neuen Datensatzes hinzu
    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '<a href="admin.php?page=my-page&action=add_new">Neuen Datensatz hinzufügen</a>';
            echo '</div>';
        }
	}
		
}