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
Version: 0.1 
Author: Michael Hugot
Author URI: Berufsschulwissen.de
License: GPLv2
*/

namespace timetable;

//Plugin Aktivierung
define('MH_uSC_FILE',__FILE__);
//Includes
require_once __DIR__ . '/includes/Plugin_Helpers.php';
require_once __DIR__ . '/includes/Settings.php';

function register_activation_hook(string $file, callable $callback):void{
	
	register_activation_hook(
		MH_uSC_FILE, 
		['untisSchildConverter\Plugin_Helpers' ,'activate']
		);
}