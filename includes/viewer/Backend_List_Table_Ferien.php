<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

namespace timetable;

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once MH_TT_PATH.'includes/controller/Ferien_controller.php';

class Backend_List_Table_Ferien extends \WP_List_Table {
    private $wpdb;
    private $table_name;
	private $my_ferien_controller;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'tt_ferien';
		
		$this->my_ferien_controller = new Ferien_controller();
        
		parent::__construct([
            'singular' => 'ferien',
            'plural'   => 'ferien',
            'ajax'     => false
        ]);
    }

    public function prepare_items() {
		$this->process_bulk_action();
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->items = $this->get_ferien_data();
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => 'Name',
            'startdatum' => 'Startdatum',
            'enddatum'   => 'Enddatum',
			'typ'        => 'Typ',
            'actions'    => 'Aktionen'
        ];
    }

    public function column_default($item, $column_name) {
        return $item[$column_name] ?? '-';
    }

   public function column_cb($item) {
    return sprintf(
        '<input type="checkbox" name="bulk-delete[]" value="%d" />', 
        $item['id']
    );
}

    public function column_actions($item) {
    $nonce = wp_create_nonce('delete_ferien_' . $item['id']);
    return sprintf(
        '<a href="?page=Einstellungen&action=delete_ferien&id=%d&_wpnonce=%s" class="button">Löschen</a>',
        $item['id'],
        $nonce
    );
}


    public function get_sortable_columns() {
        return [
            'name'       => ['name', false],
            'startdatum' => ['startdatum', false],
            'enddatum'   => ['enddatum', false],
            'typ'   => ['typ', false]
        ];
    }

    private function get_ferien_data() {
		return	$this->my_ferien_controller->get_all_data();
        
    }
	
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Löschen',
		];
		return $actions;
}
	
	public function process_bulk_action() {
		  $AAaction = $this->current_action();
		  
    if ($this->current_action() === 'bulk-delete') {
        // Prüfen, ob IDs übermittelt wurden
        if (!empty($_POST['bulk-delete'])) {
            $delete_ids = array_map('intval', $_POST['bulk-delete']); // IDs bereinigen
            $placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));

               foreach ($delete_ids as $id) {
                // Löschung über den Controller statt direkt über $wpdb
                $this->my_ferien_controller->delete_object($id);
                
            }

            echo '<div class="updated"><p>Die ausgewählten Ferien wurden gelöscht.</p></div>';
        } else {
            echo '<div class="error"><p>Keine Einträge ausgewählt.</p></div>';
        }
    }
}

}
