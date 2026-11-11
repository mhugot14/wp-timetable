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

    // --- Drag & Drop im GANTT: Termin horizontal verschieben ------------------
    // Einrasten auf ganze Tage; Speichern über dieselbe REST-Route wie der Dialog.
    const WOCHENTAGE = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    function plusTage(iso, tage) {
        const [j, m, t] = iso.split('-').map(Number);
        const d = new Date(Date.UTC(j, m - 1, t + tage)); // UTC: keine Probleme mit Sommerzeit
        return d.toISOString().slice(0, 10);
    }
    function anzeige(iso) {
        const [j, m, t] = iso.split('-').map(Number);
        const d = new Date(Date.UTC(j, m - 1, t));
        return WOCHENTAGE[d.getUTCDay()] + ' ' + String(t).padStart(2, '0') + '.' + String(m).padStart(2, '0') + '.';
    }

    let drag = null;
    let klickUnterdruecken = false;
    let infoBox = null;

    // Tabellenzellen (GANTT) werden per left verschoben, absolut positionierte
    // Balken (Zeitplan) per transform – sonst würde ihr left überschrieben.
    function versetze(bar, px) {
        if (bar.tagName === 'TD') bar.style.left = px ? px + 'px' : '';
        else bar.style.transform = px ? 'translateX(' + px + 'px)' : '';
    }

    function dragZuruecksetzen() {
        if (!drag) return;
        drag.bar.classList.remove('tt-dragging');
        versetze(drag.bar, 0);
        if (infoBox) infoBox.hidden = true;
        drag = null;
    }

    function tageZwischen(von, bis) {
        const a = von.split('-').map(Number), b = bis.split('-').map(Number);
        return Math.round((Date.UTC(b[0], b[1] - 1, b[2]) - Date.UTC(a[0], a[1] - 1, a[2])) / 86400000) + 1;
    }

    document.addEventListener('pointerdown', (e) => {
        const bar = e.target.closest('.tt-bar.tt-editierbar[data-beginn]');
        if (!bar || e.button !== 0) return;
        const wrapper = wrapperVon(bar);
        if (!wrapper || !wrapper.classList.contains('tt-edit-mode')) return;
        // Breite eines Tages: Balkenbreite / Anzahl sichtbarer Tage (Zeitplan: per CSS-Variable)
        const zp = bar.closest('.tt-zp');
        const tagBreite = zp
            ? (parseFloat(getComputedStyle(zp).getPropertyValue('--tt-tag')) || 28)
            : bar.getBoundingClientRect().width / parseInt(bar.getAttribute('colspan') || String(tageZwischen(bar.dataset.beginn, bar.dataset.ende)), 10);
        drag = {
            bar, wrapper,
            startX: e.clientX,
            tagBreite,
            tage: 0,
            aktiv: false,
            pointerId: e.pointerId
        };
    });

    document.addEventListener('pointermove', (e) => {
        if (!drag || e.pointerId !== drag.pointerId) return;
        const dx = e.clientX - drag.startX;
        if (!drag.aktiv) {
            if (Math.abs(dx) < 6) return;          // kleiner Wackler = Klick
            drag.aktiv = true;
            drag.bar.classList.add('tt-dragging');
            try { drag.bar.setPointerCapture(e.pointerId); } catch (err) { /* egal */ }
        }
        e.preventDefault();
        drag.tage = Math.round(dx / drag.tagBreite);
        versetze(drag.bar, drag.tage * drag.tagBreite);

        if (!infoBox) {
            infoBox = document.createElement('div');
            infoBox.className = 'tt-drag-info';
            document.body.appendChild(infoBox);
        }
        const b = plusTage(drag.bar.dataset.beginn, drag.tage);
        const en = plusTage(drag.bar.dataset.ende, drag.tage);
        infoBox.textContent = anzeige(b) + (b !== en ? ' – ' + anzeige(en) : '')
            + (drag.tage ? '  (' + (drag.tage > 0 ? '+' : '') + drag.tage + ' Tag' + (Math.abs(drag.tage) === 1 ? '' : 'e') + ')' : '');
        infoBox.style.left = (e.clientX + 14) + 'px';
        infoBox.style.top = (e.clientY + 14) + 'px';
        infoBox.hidden = false;
    });

    document.addEventListener('pointerup', (e) => {
        if (!drag || e.pointerId !== drag.pointerId) return;
        if (!drag.aktiv) { drag = null; return; }   // war ein Klick → Dialog öffnet der click-Handler

        klickUnterdruecken = true;                   // folgenden click nicht als „Dialog öffnen“ werten
        const { bar, wrapper, tage } = drag;
        if (tage === 0) { dragZuruecksetzen(); return; }

        const daten = {
            beginn: plusTage(bar.dataset.beginn, tage),
            ende: plusTage(bar.dataset.ende, tage)
        };
        if (infoBox) infoBox.textContent = 'Speichern …';
        api('PUT', 'termine/' + bar.dataset.terminId, daten)
            .then((res) => {
                const info = (res.hinweise && res.hinweise.info) || [];
                const warn = (res.hinweise && res.hinweise.warning) || [];
                if (!info.length && !warn.length) { neuLaden(); return; }
                // Hinweise im Dialog zeigen; „OK“ lädt neu
                dragZuruecksetzen();
                oeffneDialog(wrapper, res.termin);
                const dialog = wrapper.querySelector('.tt-edit-dialog');
                const zeilen = ['Verschoben und gespeichert.'];
                if (info.length) zeilen.push('Mitverschoben:', ...info);
                if (warn.length) zeilen.push('Bitte prüfen:', ...warn);
                meldung(dialog, warn.length ? 'warnung' : 'info', zeilen);
                dialog.dataset.fertig = '1';
                dialog.querySelector('.tt-edit-speichern').textContent = 'OK';
            })
            .catch((err) => {
                dragZuruecksetzen();
                window.alert('Nicht verschoben: ' + err.message + (err.details && err.details.length ? '\n' + err.details.join('\n') : ''));
            });
    });

    document.addEventListener('pointercancel', dragZuruecksetzen);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drag) dragZuruecksetzen(); });

    // Klick direkt nach einem Drag nicht als „Dialog öffnen“ werten
    document.addEventListener('click', (e) => {
        if (!klickUnterdruecken) return;
        klickUnterdruecken = false;
        if (e.target.closest('.tt-editierbar')) { e.stopPropagation(); e.preventDefault(); }
    }, true);

    // Abbrechen (bzw. nach Hinweisen: schließen und neu laden)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.tt-edit-abbrechen');
        if (!btn) return;
        const dialog = btn.closest('dialog');
        if (dialog.dataset.fertig === '1') { neuLaden(); return; }
        dialog.close();
    });
})();
