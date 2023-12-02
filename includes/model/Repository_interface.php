<?php
namespace timetable;
/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

interface Repository_interface {
    public function find($id); // Suche einen Datensatz anhand seiner ID
    public function get_data(); // Hole alle Datensätze
    public function create(array $data); // Erstelle einen neuen Datensatz
    public function update($id, array $data); // Aktualisiere einen Datensatz
    public function delete($id); // Lösche einen Datensatz
    // Weitere Methoden je nach Bedarf
   
}