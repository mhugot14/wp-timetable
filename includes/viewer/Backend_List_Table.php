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
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		usort( $this->items, array( &$this, 'sort_data' ) );
		
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
			'timetable_ID' => 'Timetable',
			'actions' => 'Aktionen'
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
			case 'actions':
			default:
				return "no value";
		}
	}
	
	public function column_actions($item){
    // Ein CSRF-Token generieren und in einer Session speichern
    $token = md5(uniqid(rand(), true));
    $_SESSION['csrf_token'] = $token;

    // Den CSRF-Token als verstecktes Feld im Formular einfügen und die Buttons nebeneinander anzeigen
    echo sprintf(
        '<form method="post" action="admin.php?page=mh-timetable&action=edit&id=%s" style="display: inline-block;">
            <input type="hidden" name="csrf_token" value="%s">
            <button type="submit">Edit</button>
        </form>',
        $item['id'],
        $token
    );

    echo sprintf(
        '<form method="post" action="admin.php?page=mh-timetable&action=delete&id=%s" style="display: inline-block;">
            <input type="hidden" name="csrf_token" value="%s">
            <button type="submit">Löschen</button>
        </form>',
        $item['id'],
        $token
    );
}
	
	   // Diese Methode fügt eine benutzerdefinierte Aktion für das Hinzufügen eines neuen Datensatzes hinzu
    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            echo '</div>';
        }
	}
	
	 public function get_sortable_columns()
    {
       return array(	'id' => array('id',true),
						'bildungsgang' => array('bildungsgang', false),
						'timetable_ID' => array('timetable_ID',false),
						'beginn'	=> array('beginn',false)
			);
	    	    
		
    }
	
	 /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

	  /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
	{
		// Set defaults
		$orderby = 'id';
		$order = 'asc';

		// If orderby is set, use this as the sort column
		if(!empty($_GET['orderby']))
		{
			$orderby = $_GET['orderby'];
		}

		// If order is set use this as the order
		if(!empty($_GET['order']))
		{
			$order = $_GET['order'];
		}

		// Convert string IDs to integers for numerical comparison
		$idA = intval($a['id']);
		$idB = intval($b['id']);

		// Perform numerical comparison
		if($orderby === 'id') {
			$result = $idA - $idB;
		} else {
			$result = strcmp( $a[$orderby], $b[$orderby] );
		}

		// Adjust result based on sort order
		if($order === 'asc')
		{
			return $result;
		}

		return -$result;
	}
		
}