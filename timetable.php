<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/*
Plugin Name: Timetable Schulverwaltung
Plugin URI: www.lebk-muenster.de
Description: Das Plugin generiert eine Zeittafel. Diese wird benutzt um z.B. den Zeugnisschreibungsprozess übersichtlich darzustellen.
Version: 0.5.0
Author: Michael Hugot
Author URI: Berufsschulwissen.de
License: GPLv2
*/

namespace timetable;

//Plugin Aktivierung
define('MH_TT_FILE',__FILE__);
define('MH_TT_PATH', plugin_dir_path(__FILE__));
//Includes
require_once __DIR__ . '/includes/Plugin_Helpers.php';
require_once __DIR__ . '/includes/viewer/View.php';
//require_once __DIR__ . '/includes/viewer/View2.php';
 
register_activation_hook(MH_TT_FILE, ['timetable\Backend_Einstellungen', 'on_activate']);

	
	register_activation_hook(
		MH_TT_FILE, 
		['timetable\Plugin_Helpers' ,'activate']
		);
	
	//startet die Ausgabe
	new View();