/* MH Timetable – Bearbeiten im Frontend (nur für Admin, Abteilungsleitung, Bildungsgangassistenz).
 *
 * Spricht ausschließlich mit der REST-Schnittstelle /wp-json/mh-timetable/v1/.
 * Die Rechte prüft der Server bei jeder Anfrage; dieses Script steuert nur die Oberfläche.
 * Konfiguration kommt per wp_localize_script als window.mhTtEditor = { root, nonce }.
 */
(function () {
    'use strict';

    const cfg = window.mhTtEditor || {};
    const MODUS_KEY = 'mhTtBearbeiten';

    function api(methode, pfad, daten) {
        return fetch(cfg.root + pfad, {
            method: methode,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
            body: daten ? JSON.stringify(daten) : undefined
        }).then(async (antwort) => {
            const json = await antwort.json().catch(() => ({}));
            if (!antwort.ok) {
                const details = (json.data && json.data.details) || [];
                const fehler = new Error(json.message || ('Fehler ' + antwort.status));
                fehler.details = details;
                throw fehler;
            }
            return json;
        });
    }

    function wrapperVon(el) { return el.closest('.mh-tt-frontend-wrapper'); }

    // --- Bearbeiten-Modus umschalten (bleibt nach dem Neuladen erhalten) ---------
    function setzeModus(wrapper, an) {
        wrapper.classList.toggle('tt-edit-mode', an);
        const btn = wrapper.querySelector('.tt-edit-toggle');
        if (btn) btn.setAttribute('aria-pressed', an ? 'true' : 'false');
        try { an ? sessionStorage.setItem(MODUS_KEY, '1') : sessionStorage.removeItem(MODUS_KEY); } catch (e) { /* privat */ }
    }

    document.querySelectorAll('.mh-tt-frontend-wrapper .tt-edit-toggle').forEach((btn) => {
        let gespeichert = false;
        try { gespeichert = sessionStorage.getItem(MODUS_KEY) === '1'; } catch (e) { /* privat */ }
        if (gespeichert) setzeModus(wrapperVon(btn), true);
    });

    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('.tt-edit-toggle');
        if (toggle) {
            const w = wrapperVon(toggle);
            setzeModus(w, !w.classList.contains('tt-edit-mode'));
        }
    });

    // --- Dialog ---------------------------------------------------------------
    function meldung(dialog, art, zeilen) {
        const box = dialog.querySelector('.tt-edit-meldung');
        box.className = 'tt-edit-meldung' + (art ? ' tt-meldung-' + art : '');
        box.innerHTML = '';
        if (!zeilen || !zeilen.length) return;
        const liste = document.createElement('ul');
        zeilen.forEach((z) => { const li = document.createElement('li'); li.textContent = z; liste.appendChild(li); });
        box.appendChild(liste);
    }

    function oeffneDialog(wrapper, termin, bgVorgabe) {
        const dialog = wrapper.querySelector('.tt-edit-dialog');
        const form = dialog.querySelector('form');
        form.reset();
        meldung(dialog, '', []);
        dialog.dataset.fertig = '';
        dialog.querySelector('.tt-edit-titel').textContent = termin ? 'Termin bearbeiten' : 'Neuer Termin';
        dialog.querySelector('.tt-edit-loeschen').hidden = !termin;
        const speichern = dialog.querySelector('.tt-edit-speichern');
        speichern.disabled = false;
        speichern.textContent = 'Speichern';

        const t = termin || { id: '', bildungsgang: bgVorgabe || '', ereignistyp: '', bezeichnung: '', beginn: '', ende: '', verantwortlich: '' };
        ['id', 'bildungsgang', 'ereignistyp', 'bezeichnung', 'beginn', 'ende', 'verantwortlich'].forEach((feld) => {
            if (form.elements[feld]) form.elements[feld].value = t[feld] == null ? '' : t[feld];
        });
        dialog.showModal();
        (form.elements.bezeichnung.value ? form.elements.beginn : form.elements.bildungsgang).focus();
    }

    function neuLaden() { window.location.reload(); }

    // Termin anklicken (nur im Bearbeiten-Modus, nur editierbare)
    function terminGeklickt(el) {
        const wrapper = wrapperVon(el);
        if (!wrapper || !wrapper.classList.contains('tt-edit-mode')) return false;
        api('GET', 'termine/' + el.dataset.terminId)
            .then((t) => oeffneDialog(wrapper, t))
            .catch((err) => window.alert(err.message));
        return true;
    }

    document.addEventListener('click', (e) => {
        const bar = e.target.closest('.tt-editierbar[data-termin-id]');
        if (bar && terminGeklickt(bar)) { e.preventDefault(); return; }

        const plus = e.target.closest('.tt-add-btn');
        if (plus) {
            const wrapper = wrapperVon(plus);
            setzeModus(wrapper, true);
            oeffneDialog(wrapper, null, plus.dataset.bg || '');
        }
    });
    document.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.tt-editierbar[data-termin-id]')) {
            if (terminGeklickt(e.target)) e.preventDefault();
        }
    });

    // Ende automatisch auf Beginn setzen, wenn leer oder davor
    document.addEventListener('change', (e) => {
        if (e.target.name !== 'beginn' || !e.target.closest('.tt-edit-form')) return;
        const ende = e.target.form.elements.ende;
        if (!ende.value || ende.value < e.target.value) ende.value = e.target.value;
    });

    // Speichern
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('.tt-edit-form');
        if (!form) return;
        e.preventDefault();
        const dialog = form.closest('dialog');

        // Zweiter Klick nach Hinweisen: nur noch neu laden
        if (dialog.dataset.fertig === '1') { neuLaden(); return; }

        const wrapper = wrapperVon(form);
        const id = form.elements.id.value;
        const daten = {
            timetable_id: parseInt(wrapper.dataset.timetableId, 10),
            bildungsgang: form.elements.bildungsgang.value,
            ereignistyp: form.elements.ereignistyp.value,
            bezeichnung: form.elements.bezeichnung.value,
            beginn: form.elements.beginn.value,
            ende: form.elements.ende.value,
            verantwortlich: form.elements.verantwortlich.value
        };
        const btn = form.querySelector('.tt-edit-speichern');
        btn.disabled = true;
        meldung(dialog, '', ['Speichern …']);

        api(id ? 'PUT' : 'POST', id ? 'termine/' + id : 'termine', daten)
            .then((res) => {
                const info = (res.hinweise && res.hinweise.info) || [];
                const warn = (res.hinweise && res.hinweise.warning) || [];
                if (!info.length && !warn.length) { neuLaden(); return; }
                // Hinweise zeigen, danach mit „OK“ neu laden
                const zeilen = ['Gespeichert.'];
                if (info.length) zeilen.push('Mitverschoben:', ...info);
                if (warn.length) zeilen.push('Bitte prüfen:', ...warn);
                meldung(dialog, warn.length ? 'warnung' : 'info', zeilen);
                dialog.dataset.fertig = '1';
                btn.textContent = 'OK';
                btn.disabled = false;
            })
            .catch((err) => {
                meldung(dialog, 'fehler', [err.message].concat(err.details || []));
                btn.disabled = false;
            });
    });

    // Löschen
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.tt-edit-loeschen');
        if (!btn) return;
        const form = btn.closest('form');
        const dialog = btn.closest('dialog');
        if (!window.confirm('Diesen Termin wirklich löschen?')) return;
        api('DELETE', 'termine/' + form.elements.id.value)
            .then(neuLaden)
            .catch((err) => meldung(dialog, 'fehler', [err.message]));
    });

    // Abbrechen (bzw. nach Hinweisen: schließen und neu laden)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.tt-edit-abbrechen');
        if (!btn) return;
        const dialog = btn.closest('dialog');
        if (dialog.dataset.fertig === '1') { neuLaden(); return; }
        dialog.close();
    });
})();
