<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

require_once MH_TT_PATH. 'includes/model/Timetable_repository.php';

class Backend_List_Table_Timetables extends \WP_List_Table {

    private $data;

    public function __construct() {
        parent::__construct([
            'singular' => 'custom_item',
            'plural'   => 'custom_items',
            'ajax'     => false,
            'screen'   => null
        ]);
    }

    public function set_data($data) {
        // Erstelle Timetable-Objekte und berechne die zusätzlichen Spalten
        $this->data = array_map(function($item) {
            $timetable = new Timetable($item['id']);
            return [
                'id' => $timetable->get_id(),
                'bezeichnung' => $timetable->get_bezeichnung(),
                'beschreibung' => $timetable->get_beschreibung(),
                'erzeugt_am' => $timetable->get_erzeugt_am()->format('Y-m-d'),
                'earliest_date' => $timetable->get_earliest_date() ? $timetable->get_earliest_date()->format('Y-m-d') : '',
                'last_date' => $timetable->get_last_date() ? $timetable->get_last_date()->format('Y-m-d') : '',
                'laenge' => $timetable->get_laenge()
            ];
        }, $data);
    }

    public function prepare_items() {
        $this->items = $this->data;
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        usort($this->items, array(&$this, 'sort_data'));
    }

    public function get_columns() {
        $columns = array(
            'id' => 'ID',
            'bezeichnung' => 'Bezeichnung',
            'beschreibung' => 'Beschreibung',
            'erzeugt_am' => 'Erzeugt am',
            'earliest_date' => 'Frühestes Datum',
            'last_date' => 'Letztes Datum',
            'laenge' => 'Länge in Tagen',
            'actions' => 'Aktionen'
        );
        return $columns;
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'bezeichnung':
            case 'beschreibung':
            case 'erzeugt_am':
            case 'earliest_date':
            case 'last_date':
            case 'laenge':
                return $item[$column_name];
            case 'actions':
            default:
                return "no value";
        }
    }

    public function column_actions($item) {
       $nonce_field_edit=wp_nonce_field( 'timetable_edit_nonce', 'timetable_edit_nonce' );
    echo sprintf(
        '<form enctype="multipart/form-data" method="post" action="" style="display: inline-block;">
				<input type="hidden" name="id" value="%s">
			%s	
            <button type="submit" name="timetable_edit">Edit</button>
        </form>',
        $item['id'],
		$nonce_field_edit
		
    );
	$nonce_field_loeschen=wp_nonce_field('timetable_loeschen_nonce','timetable_loeschen_nonce');
    echo sprintf(
        '<form enctype="multipart/form-data" method="post" action="" style="display: inline-block;">
			<input type="hidden" name="id" value="%s">
			%s	
            <button type="submit">Löschen</button>
        </form>',
        $item['id'],
		$nonce_field_loeschen
    );
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '</div>';
        }
    }

    public function get_sortable_columns() {
        return array(
            'id' => array('id', true),
            'bezeichnung' => array('bezeichnung', false),
            'erzeugt_am' => array('erzeugt_am', false)
        );
    }

    public function get_hidden_columns() {
        return array();
    }

    private function sort_data($a, $b) {
        $orderby = 'id';
        $order = 'asc';

        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
        }

        if (!empty($_GET['order'])) {
            $order = $_GET['order'];
        }

        if ($orderby === 'id') {
            $result = intval($a['id']) - intval($b['id']);
        } else {
            $result = strcmp($a[$orderby], $b[$orderby]);
        }

        return ($order === 'asc') ? $result : -$result;
    }
}
