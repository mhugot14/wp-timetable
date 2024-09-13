/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Other/javascript.js to edit this template
 */


function submitBulkEditForm() {
     console.log('Die Methode submitBulkEditForm() wird gestartet.');
     console.log(document.getElementsByName('timetable_ID')); // Sollte das Element im Array anzeigen
console.log(document.getElementsByName('timetable_ID')[0]); // Sollte das eigentliche Element zeigen
console.log(document.getElementsByName('verantwortlich'));
console.log(document.getElementsByName('verantwortlich')[0].value);
    // Hole die Werte aus dem Editierformular
    var timetableID = document.getElementsByName('timetable_ID')[0].value;
    var bezeichnung = document.getElementsByName('bezeichnung')[0].value;
    var bildungsgang = document.getElementsByName('bildungsgang')[0].value;
    var beginn = document.getElementsByName('beginn')[0].value;
    var ende = document.getElementsByName('ende')[0].value;
    var verantwortlich = document.getElementsByName('verantwortlich')[0].value;
    var ereignistyp = document.getElementsByName('ereignistyp')[0].value;

    // Erstelle versteckte Felder im Bulk-Formular, um diese Werte zu übermitteln
    var bulkForm = document.getElementById('bulkEditForm');

    var fields = {
        'timetable_ID': timetableID,
        'bezeichnung': bezeichnung,
        'bildungsgang': bildungsgang,
        'beginn': beginn,
        'ende': ende,
        'verantwortlich': verantwortlich,
        'ereignistyp': ereignistyp
    };

    // Füge die versteckten Felder zum Bulk-Formular hinzu
    for (var key in fields) {
        if (fields.hasOwnProperty(key) && fields[key] !== "") {
            var hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = key;
            hiddenField.value = fields[key];
            bulkForm.appendChild(hiddenField);
           console.log(key + ": " + fields[key]);
        }
    }
    // Sende das Bulk-Formular ab
    bulkForm.submit();
}
function jstest(){
    console.log('jstest: JavaScript-Datei mh_tt_javascript.js läuft');
}


document.addEventListener('DOMContentLoaded', function() {
    console.log("addEventListener läuft");
    // Finde den Bulk-Actions Submit-Button oben
    var bulkSubmitButtonTop = document.getElementById('doaction');
    
    // Finde den Bulk-Actions Submit-Button unten (falls vorhanden)
    var bulkSubmitButtonBottom = document.getElementById('doaction2');
    
    // Füge den EventListener für den oberen Button hinzu
    if (bulkSubmitButtonTop) {
        bulkSubmitButtonTop.addEventListener('click', function(event) {
            // Hier kannst du deine Funktion aufrufen
            submitBulkEditForm();
        });
    }
    
    // Füge den EventListener für den unteren Button hinzu (falls vorhanden)
    if (bulkSubmitButtonBottom) {
        bulkSubmitButtonBottom.addEventListener('click', function(event) {
            // Hier kannst du deine Funktion aufrufen
            submitBulkEditForm();
        });
    }
});

