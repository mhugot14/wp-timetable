/* MH Timetable – Frontend: Bildungsgang-Filter und Ansichts-Umschalter.
 * Wird nur auf Seiten mit [insertTimetable] geladen (im Footer).
 * Alle Handler hängen per Event-Delegation am document und funktionieren
 * daher für beliebig viele Timetables auf einer Seite. */
(function () {
    'use strict';

    // 1. Multi-Filter: Bildungsgänge ein-/ausblenden
    document.addEventListener('change', function (e) {
        const checkbox = e.target.closest('.tt-bg-checkbox, .tt-select-all');
        if (!checkbox) return;

        const wrapper = checkbox.closest('.mh-tt-frontend-wrapper');
        if (!wrapper) return;
        const allCheckboxes = wrapper.querySelectorAll('.tt-bg-checkbox');
        const selectAllCheckbox = wrapper.querySelector('.tt-select-all');

        if (checkbox.classList.contains('tt-select-all')) {
            allCheckboxes.forEach(cb => { cb.checked = checkbox.checked; });
        } else if (selectAllCheckbox) {
            // "Alle auswählen" nur angehakt lassen, wenn wirklich alle aktiv sind
            selectAllCheckbox.checked = Array.from(allCheckboxes).every(cb => cb.checked);
        }

        const selectedValues = Array.from(wrapper.querySelectorAll('.tt-bg-checkbox:checked'))
            .map(cb => cb.value.trim());

        wrapper.querySelectorAll('[data-bg]').forEach(el => {
            el.classList.toggle('tt-hidden', !selectedValues.includes(el.dataset.bg.trim()));
        });

        // Datums-Trenner der Liste ausblenden, wenn darunter keine Karte sichtbar ist
        wrapper.querySelectorAll('.tt-list-date-divider').forEach(div => {
            let hasVisible = false;
            let next = div.nextElementSibling;
            while (next && next.classList.contains('tt-list-card')) {
                if (!next.classList.contains('tt-hidden')) { hasVisible = true; break; }
                next = next.nextElementSibling;
            }
            div.classList.toggle('tt-hidden', !hasVisible);
        });
    });

    // 2. Umschalter GANTT / Liste / Monat
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.tt-switch-btn');
        if (!btn) return;

        const wrapper = btn.closest('.mh-tt-frontend-wrapper');
        if (!wrapper) return;
        wrapper.querySelectorAll('.tt-switch-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');

        wrapper.querySelectorAll('.tt-view-container').forEach(c => { c.style.display = 'none'; });
        const target = document.getElementById(btn.dataset.view);
        if (target) {
            target.style.display = 'block';
            zuHeuteScrollen(target);
        }
    });

    // 3. Zeitplan: beim Darüberfahren die Tage des Termins im Kopf markieren
    function markiere(balken, an) {
        const zp = balken.closest('.tt-zp');
        if (!zp) return;
        zp.querySelectorAll('.tt-zp-tag.is-markiert').forEach(t => t.classList.remove('is-markiert'));
        if (!an) return;
        const von = parseInt(balken.dataset.tagVon, 10);
        const tage = parseInt(balken.dataset.tage, 10) || 1;
        for (let i = von; i < von + tage; i++) {
            const tag = zp.querySelector('.tt-zp-tag[data-i="' + i + '"]');
            if (tag) tag.classList.add('is-markiert');
        }
    }
    ['mouseover', 'focusin'].forEach(ev => document.addEventListener(ev, (e) => {
        const b = e.target.closest && e.target.closest('.tt-zp-balken[data-tag-von]');
        if (b) markiere(b, true);
    }));
    ['mouseout', 'focusout'].forEach(ev => document.addEventListener(ev, (e) => {
        const b = e.target.closest && e.target.closest('.tt-zp-balken[data-tag-von]');
        if (b && !(e.relatedTarget && b.contains(e.relatedTarget))) markiere(b, false);
    }));

    // 4. Zeitplan: beim ersten Anzeigen zum heutigen Tag scrollen
    function zuHeuteScrollen(bereich) {
        bereich.querySelectorAll('.tt-zp-scroll[data-heute-px]:not([data-gescrollt])').forEach(scroll => {
            if (scroll.offsetParent === null) return;           // (noch) unsichtbar
            const label = parseInt(getComputedStyle(scroll.closest('.tt-zp')).getPropertyValue('--tt-label'), 10) || 0;
            const ziel = parseInt(scroll.dataset.heutePx, 10) - (scroll.clientWidth - label) / 3;
            scroll.scrollLeft = Math.max(0, ziel);
            scroll.dataset.gescrollt = '1';
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => zuHeuteScrollen(document));
    } else {
        zuHeuteScrollen(document);
    }
})();
