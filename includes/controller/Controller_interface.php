<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace timetable;

/**
 * Description of Controller_interface
 *
 * @author micha
 */
interface Controller_interface {
  //  public function find($id); // Suche einen Datensatz anhand seiner ID
  //  public function get_data(); // Hole alle Datensätze
  //  public function create(array $data); // Erstelle einen neuen Datensatz
  //  public function update($id, array $data); // Aktualisiere einen Datensatz
  //  public function delete($id); // Lösche einen Datensatz
    // Weitere Methoden je nach Bedarf
	
public function get_object_by_id($id);
public function add_object();
public function delete_object($id);
public function edit_object($id);
}
