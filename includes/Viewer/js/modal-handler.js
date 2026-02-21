document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('mh-tt-modal');
    const closeBtn = document.querySelector('.mh-tt-close');

    // Öffnen für "Neu"
    document.querySelectorAll('.mh-tt-add-button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(); // Leeres Formular
        });
    });

    // Öffnen für "Edit"
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('mh-tt-edit-btn')) {
            e.preventDefault();
            const id = e.target.dataset.id;
            fetchTerminData(id);
        }
    });

    closeBtn.onclick = () => modal.style.display = "none";
    window.onclick = (event) => { if (event.target == modal) modal.style.display = "none"; };

    function fetchTerminData(id) {
        fetch(ajaxurl + '?action=mh_tt_get_termin&id=' + id + '&nonce=' + mh_tt_params.nonce)
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    openModal(res.data);
                }
            });
    }

    function openModal(data = null) {
        modal.style.display = "flex";
        // Hier befüllst du die Felder deines Formulars im Modal
        document.getElementsByName('bezeichnung')[0].value = data ? data.bezeichnung : '';
        document.getElementsByName('id')[0].value = data ? data.id : '';
        // ... alle weiteren Felder ...
    }
});