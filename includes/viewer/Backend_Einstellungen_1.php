<?php

namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

require_once MH_TT_PATH.'includes/controller/Einstellungen_controller.php';

class Backend_Einstellungen{
	
	private $my_einstellungen_controller;
	
	
	public function __construct(){
		
		$this->my_einstellungen_controller = new Einstellungen_controller();
		
	}


	public function generiere_Einstellungsseite(){
	
	?>
		<div>
			<div class="wrap">
				<h2>Kategorien anlegen</h2>
			
			 <form method="post" action="options.php">
            <?php
                settings_fields('timetable_settings_group');
                do_settings_sections('timetable_kategorien');
                submit_button();
            ?>
        </form>
			
				
			</div><!-- comment -->
			<div>
				<h2>Ereignistypen</h2>
			</div>
		</div>
	<?php
	}	
		
}

