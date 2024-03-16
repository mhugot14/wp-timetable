<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */


namespace timetable;

class View2 {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_timetable_page']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
    }
    
    public function add_timetable_page() {
        add_menu_page(
            'Timetable',
            'Timetable',
            'manage_options',
            'timetable',
            [$this, 'render_timetable_page'],
            'dashicons-clock',
            30
        );
    }
    
    public function render_timetable_page() {
        // Hier können Sie den Inhalt Ihrer Backend-Seite "Timetable" rendern
        echo '<div class="wrap">';
        echo '<h1>Timetable</h1>';
        echo '</div>';
    }
    
    public function add_metabox() {
        add_meta_box(
            'timetable_metabox',
            'Timetable Metabox',
            [$this, 'render_metabox'],
            'timetable',
            'normal',
            'default'
        );
    }
    
    public function render_metabox() {
        // Hier können Sie den Inhalt Ihrer Metabox rendern
        echo '<p>Hier ist der Inhalt Ihrer Metabox.</p>';
    }
}


