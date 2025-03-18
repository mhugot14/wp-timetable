<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

/**
 * Description of Ferien_respository
 *
 * @author micha
 */
class Ferien_repository implements Repository_interface {
	
	private $wpdb;
	private $tabellenname;
	
	 function __construct(){
		 global $wpdb;
		 $this->wpdb=$wpdb;
		 $this->tabellenname = $this->wpdb->prefix.'tt_ferien';
	 }

	//put your code here
	public function create(array $ferien) {
    try {
        // Prüfen, ob der Eintrag bereits existiert
        $existing_entry = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabellenname} WHERE name = %s AND startdatum = %s AND enddatum = %s AND typ = %s",
            $ferien['name'], 
            date('Y-m-d', strtotime($ferien['startdatum'])), 
            date('Y-m-d', strtotime($ferien['enddatum'])), 
            $ferien['typ']
        ));

        if ($existing_entry > 0) {
            error_log("❌ Doppelter Eintrag: {$ferien['name']} ({$ferien['startdatum']} - {$ferien['enddatum']})");
            return false;
        }

        // Einfügen des neuen Eintrags
        $result = $this->wpdb->insert(
            $this->tabellenname,
            array(
                'name'       => $ferien['name'],
                'startdatum' => date('Y-m-d', strtotime($ferien['startdatum'])),
                'enddatum'   => date('Y-m-d', strtotime($ferien['enddatum'])),
                'typ'        => $ferien['typ'],
            )
        );

        if ($result === false) {
            error_log("❌ Fehler beim Einfügen: {$ferien['name']} ({$ferien['startdatum']} - {$ferien['enddatum']})");
            return false;
        }

        error_log("✅ Neuer Eintrag gespeichert: {$ferien['name']} ({$ferien['startdatum']} - {$ferien['enddatum']})");
        return true;

    } catch (Exception $ex) {
        error_log("❌ Ausnahme aufgetreten: " . $ex->getMessage());
        return false;
    }
}


	public function delete( $id ) {
		try{
					$query=$this->wpdb->prepare("DELETE FROM ".$this->tabellenname." WHERE id = %d;", $id);
					$this->wpdb->query($query);
				} catch (Exception $ex) {
					echo "Objekt konnte nicht gelöscht werden: ".$ex;
				} 
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
