document.addEventListener('DOMContentLoaded', function() {
    console.log('MH Timetable: JS geladen und bereit.');

    // --- 1. GEMEINSAME LOGIK (Schließen für alle Modals) ---
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('mh-tt-close') || e.target.classList.contains('mh-tt-close-btn')) {
            const modal = e.target.closest('.mh-tt-modal');
            if (modal) modal.style.display = "none";
        }
        // Schließen bei Klick außerhalb des Fensters
        if (e.target.classList.contains('mh-tt-modal')) {
            e.target.style.display = "none";
        }
    });

    // --- 2. TERMIN LOGIK (Wird nur ausgeführt, wenn Termin-Elemente existieren) ---
    const terminModal = document.getElementById('mh-tt-modal');
    const terminForm = document.getElementById('mh-tt-termin-form');
    
    if (terminModal && terminForm) {
        console.log('MH Timetable: Termin-Modul aktiv.');
        initTerminLogic(terminModal, terminForm);
    }

    // --- 3. TIMETABLE LOGIK (Wird nur ausgeführt, wenn Timetable-Elemente existieren) ---
    const ttModal = document.getElementById('mh-tt-timetable-modal');
    const ttForm = document.getElementById('mh-tt-timetable-form');

    if (ttModal && ttForm) {
        console.log('MH Timetable: Timetable-Modul aktiv.');
        initTimetableLogic(ttModal, ttForm);
    }

    // ==========================================
    // FUNKTIONEN FÜR TERMINE
    // ==========================================
function initTerminLogic(modal, form) {
        const title = document.getElementById('modal-title');

        // 1. NEU-Button
        document.querySelectorAll('.mh-tt-add-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                form.reset();
                document.getElementById('f_id').value = '';
                title.innerText = 'Neuen Termin anlegen';
                
                // Info-Box vom Bulk-Edit entfernen, falls vorhanden
                const oldInfo = document.getElementById('bulk-info');
                if (oldInfo) oldInfo.remove();
                
                // Pflichtfelder wieder aktivieren
                form.querySelectorAll('[data-was-required="true"]').forEach(el => el.setAttribute('required', 'required'));
                
                modal.style.display = "flex";
            });
        });

        // 2. EDIT-Button (Event Delegation)
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.mh-tt-edit-btn');
            if (btn) {
                e.preventDefault();
                fetchTerminData(btn.dataset.id, modal);
            }
        });

        // 3. Datum-Synchronisation
        const vonField = document.getElementById('f_beginn');
        const bisField = document.getElementById('f_ende');
        if (vonField && bisField) {
            vonField.addEventListener('change', () => {
                if (!bisField.value || bisField.value < vonField.value) {
                    bisField.value = vonField.value;
                }
            });
        }

        // 4. ZENTRALE BULK-LOGIK (Kombiniert für Edit & Copy)
        // Wir suchen alle Buttons mit der ID doaction (oben) und doaction2 (unten)
        const bulkButtons = document.querySelectorAll('#doaction, #doaction2');
        
        bulkButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Finde das dazugehörige Select-Feld (neben dem geklickten Button)
                const actionSelect = this.parentElement.querySelector('select[name^="action"]');
                if (!actionSelect) return;

                const selectedAction = actionSelect.value;

                // Prüfen, ob eine unserer Aktionen gewählt wurde
                if (selectedAction === 'bulk-edit' || selectedAction === 'bulk-copy') {
                    e.preventDefault();

                    // Markierte IDs sammeln
                    const checkedBoxes = document.querySelectorAll('input[name="bulk-delete[]"]:checked');
                    const ids = Array.from(checkedBoxes).map(cb => cb.value);

                    if (ids.length === 0) {
                        alert('Bitte wählen Sie mindestens einen Termin aus.');
                        return;
                    }

                    if (selectedAction === 'bulk-edit') {
                        // Ruft die Hilfsfunktion für das Massen-Edit-Modal auf
                        if (typeof openBulkModal === 'function') {
                            openBulkModal(ids, modal, form, title);
                        } else {
                            console.error('Funktion openBulkModal nicht gefunden!');
                        }
                    } 
                    else if (selectedAction === 'bulk-copy') {
                        // Öffnet das Kopier-Modal
                        const copyModal = document.getElementById('mh-tt-bulk-copy-modal');
                        const copyIdField = document.getElementById('bulk_copy_ids');
                        
                        if (copyModal && copyIdField) {
                            copyIdField.value = ids.join(',');
                            copyModal.style.display = "flex";
                        } else {
                            console.error('Kopier-Modal oder ID-Feld nicht im HTML gefunden!');
                        }
                    }
                }
            });
        });
    }
    
    function openBulkModal(ids, modal, form, title) {
        form.reset();
        document.getElementById('f_id').value = ids.join(',');
        title.innerText = `Massenbearbeitung (${ids.length} Termine)`;

        const infoBox = document.createElement('div');
        infoBox.id = 'bulk-info';
        infoBox.innerHTML = '<p style="color:orange; font-style:italic; padding:10px; background:#fffbe5; border-left:4px solid #ffba00;">Hinweis: Nur ausgefüllte Felder werden überschrieben.</p>';
        
        const oldInfo = document.getElementById('bulk-info');
        if (oldInfo) oldInfo.remove();
        form.prepend(infoBox);

        // Pflichtfelder im Bulk-Modus deaktivieren
        form.querySelectorAll('[required]').forEach(el => {
            el.removeAttribute('required');
            el.dataset.wasRequired = "true";
        });

        modal.style.display = "flex";
    }

    // ==========================================
    // FUNKTIONEN FÜR TIMETABLES
    // ==========================================
    function initTimetableLogic(modal, form) {
        const title = document.getElementById('tt-modal-title');

        // NEU-Button
        document.querySelectorAll('.mh-tt-timetable-add-btn').forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                form.reset();
                document.getElementById('tt_f_id').value = '';
                title.innerText = 'Neue Zeittafel anlegen';
                modal.style.display = "flex";
            };
        });

        // EDIT-Button
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.mh-tt-timetable-edit-btn');
            if (btn) {
                e.preventDefault();
                fetchTimetableData(btn.dataset.id, modal);
            }
        });
        // Kopier-Button Logik
        document.addEventListener('click', function(e) {
            const copyBtn = e.target.closest('.mh-tt-copy-btn');
            if (copyBtn) {
                e.preventDefault();
                const id = copyBtn.dataset.id;
                const name = copyBtn.dataset.name;

                document.getElementById('copy_source_id').value = id;
                document.getElementById('copy_new_name').value = name + ' (Kopie)';

                document.getElementById('mh-tt-copy-modal').style.display = "flex";
            }
        }); 
    }

  // ==========================================
    // AJAX HELPER (Kugelsicher)
    // ==========================================
    function getBaseUrl() {
        if (typeof mh_tt_params !== 'undefined' && mh_tt_params.ajaxurl) {
            return mh_tt_params.ajaxurl;
        }
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl; // WP Standard-Fallback
        }
        return '/wp-admin/admin-ajax.php'; // Letzter Rettungsanker
    }

    function fetchTerminData(id, modal) {
        const url = getBaseUrl() + '?action=mh_tt_get_termin&id=' + id + '&nonce=' + mh_tt_params.nonce;
        console.log('MH Timetable: Request an:', url);

        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    fillTerminForm(res.data);
                    modal.style.display = "flex";
                } else {
                    alert('Fehler: ' + res.data.message);
                }
            }).catch(err => console.error('AJAX Error:', err));
    }

    function fetchTimetableData(id, modal) {
        const url = getBaseUrl() + '?action=mh_tt_get_timetable&id=' + id + '&nonce=' + mh_tt_params.nonce;
        console.log('MH Timetable: Request an:', url);

        fetch(url)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('tt_f_id').value = res.data.id;
                    document.getElementById('tt_f_bezeichnung').value = res.data.bezeichnung;
                    document.getElementById('tt_f_beschreibung').value = res.data.beschreibung;
                    document.getElementById('tt-modal-title').innerText = 'Zeittafel bearbeiten';
                    modal.style.display = "flex";
                } else {
                    alert('Fehler: ' + res.data.message);
                }
            }).catch(err => console.error('AJAX Error:', err));
    }

    function fillTerminForm(data) {
        document.getElementById('f_id').value = data.id;
        document.getElementById('f_timetable_id').value = data.timetableId;
        document.getElementById('f_bezeichnung').value = data.bezeichnung;
        document.getElementById('f_bildungsgang').value = data.bildungsgang;
        document.getElementById('f_ereignistyp').value = data.ereignistyp;
        document.getElementById('f_beginn').value = data.beginn;
        document.getElementById('f_ende').value = data.ende;
        document.getElementById('f_verantwortlich').value = data.verantwortlich;
        
        const oldInfo = document.getElementById('bulk-info');
        if (oldInfo) oldInfo.remove();
        
        // Pflichtfelder wieder aktivieren
        document.getElementById('mh-tt-termin-form').querySelectorAll('[data-was-required="true"]').forEach(el => el.setAttribute('required', 'required'));
    }
});