<?php

/** 
 * Diese Klasse binahltet eine Sammlunng an statischen Helfer-Funktionen
 * Statisch sind diese, da sie ohne 
 */

class Plugin_Helpers{
	public static function activate(): void{
		/*Hier passiert das, was passiert, wenn das Plugin aktiviert wird
		  */
		
		wp_schedule_event(time() - DAY_IN_SECONDS,'weekly','untisSchildConverter/weekly_cron');
		/*
		//Tabellen anlegen
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		//tabellennamen
		$usc_loesch_faecher = $wpdb->prefix.'usc_loesch_faecher';
		$usc_loesch_klassen = $wpdb->prefix.'usc_loesch_klassen';
		$usc_schildimport = $wpdb->prefix.'usc_schildimport';
		$sql_usc_loesch_faecher = "CREATE TABLE `$usc_loesch_faecher` (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`fach_untis` varchar(50) NOT NULL,
									`fach_schild` varchar(50) NOT NULL,
									`klasse` varchar(10) NOT NULL,
									`bemerkung` varchar(100) NOT NULL,
									`importdatum` datetime NOT NULL,
									PRIMARY KEY (`id`)
									)ENGINE=InnoDB AUTO_INCREMENT=28 $charset_collate;";
		
		$sql_usc_loesch_klassen = "CREATE TABLE `$usc_loesch_klassen` (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`klasse_untis` varchar(10) NOT NULL,
									`klasse_schild` varchar(10) NOT NULL,
									`bemerkung` varchar(100) NOT NULL,
									`importdatum` datetime NOT NULL,
									PRIMARY KEY (`id`)
								   ) ENGINE=InnoDB AUTO_INCREMENT=28 $charset_collate;";
		
		$sql_usc_schildimport = "CREATE TABLE  `$usc_schildimport`(
									`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
									`schuljahr` smallint(6) NOT NULL,
									`halbjahr` varchar(3) NOT NULL,
									`klasse` varchar(10) NOT NULL,
									`fach` varchar(10) NOT NULL,
									`lehrer` varchar(10) NOT NULL,
									`giltfuerHalbjahr` varchar(5) NOT NULL,
									`importID` int(11) NOT NULL,
									PRIMARY KEY (`id`)
								   ) ENGINE=InnoDB AUTO_INCREMENT=1474 $charset_collate;";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql_usc_loesch_faecher);
			dbDelta($sql_usc_loesch_klassen);
			dbDelta($sql_usc_schildimport);
			
	*/
			
	}
		
	
	public  static function strichSeparator(string $text): array{
		 $arraytext = array();
		 
		if (strpos($text, '|') !== false) {
				  $arraytext= explode('|',$text);
			  }
			  else{
				  $arraytext[0]=$text;
			  }
			  
			return $arraytext;
	}
	
	public static function resultsetToTable(array $resultSet){	
    // Tabelleninhalte aus dem ResultSet
		foreach ($resultSet as $row) {
			echo '<tr>';
			foreach ($row as $columnValue) {
				echo '<td>' . $columnValue . '</td>';
			}
			echo '</tr>';
		}
	}
	
}

?>