<?php
namespace timetable;
/** 
 * Diese Klasse binahltet eine Sammlunng an statischen Helfer-Funktionen
 * Statisch sind diese, da sie ohne 
 */

class Plugin_Helpers{
	public static function activate(): void{
		/*Hier passiert das, was passiert, wenn das Plugin aktiviert wird
		  */
		
		wp_schedule_event(time() - DAY_IN_SECONDS,'weekly','timetable/weekly_cron');
		
		//Tabellen anlegen
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		//tabellennamen
		$tt_termine = $wpdb->prefix.'tt_termine';
		$tt_timetable = $wpdb->prefix.'tt_timetable';
		$sql_tt_termine = "CREATE TABLE `$tt_termine` (
    `id` int(20) NOT NULL AUTO_INCREMENT,
    `bildungsgang` varchar(30) NOT NULL,
    `bezeichnung` varchar(50) NOT NULL,
    `ereignistyp` varchar(20) NOT NULL,
    `beginn` date NOT NULL,
    `ende` date NOT NULL,
    `verantwortlich` varchar(30) NOT NULL,
    `timetable_ID` int(10) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 $charset_collate;";
		
		$sql_tt_timetable = "CREATE TABLE `$tt_timetable` (
    `id` int(10) NOT NULL ,
    `bezeichnung` text NOT NULL,
    `beschreibung` text NOT NULL,
    `erzeugt_am` date NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 $charset_collate;";
		$sql_tt_timetable_data = 
				"INSERT INTO `wp_tt_timetable` (`id`, `bezeichnung`, `beschreibung`, `erzeugt_am`) VALUES
				(1, 'Winter 2023/2024', 'Organisation der Zeugnisschreibung im Winter 2023/2024', '2023-09-01'),
				(3, 'Sommer 2024', 'Organisation der Zeugnisschreibung im Sommer 2024', '2024-02-26'),
				(4, 'Winter 2024/25', 'Organisation der Zeugnisschreibung im Winter 2024/25', '2024-02-26'),
				(5, 'Sommer 2025', 'Organisation der Zeugnisschreibung im Sommer 2025', '2024-02-26');";
		
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta($sql_tt_termine);
			dbDelta($sql_tt_timetable);
			dbDelta($sql_tt_timetable_data);	
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
	public static function create_upload_folder($folder_path) {
		// Holen des Upload-Verzeichnisses
		$upload_dir = wp_upload_dir();

		// Pfad zum neuen Ordner
		$new_folder_path = $upload_dir['basedir'] . '/' . $folder_path;

		// Prüfen, ob der Ordner bereits vorhanden ist
		if (!file_exists($new_folder_path)) {
			// Ordner erstellen, wenn er nicht vorhanden ist
			wp_mkdir_p($new_folder_path);

			}
		return $new_folder_path;
	}
	
	public static function get_download_path($folder_path){
		$upload_dir = wp_upload_dir();
		
		$download_path=$upload_dir['baseurl'].'/'.$folder_path;
		
		
		return $download_path;
	}
}
