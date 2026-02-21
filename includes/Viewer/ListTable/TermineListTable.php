<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer\ListTable;

use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Service\TaxonomyService;
use MH\Timetable\Model\Entity\Termin;
use WP_List_Table;

/**
 * Vollständige List Table für Termine.
 */
class TermineListTable extends WP_List_Table
{
    private TerminController $terminController;
    private TimetableController $timetableController;
    private TaxonomyService $taxonomyService;
	private array $timetableNames = [];

    public function __construct(
        TerminController $terminController,
        TimetableController $timetableController,
        TaxonomyService $taxonomyService
    ) {
        parent::__construct([
            'singular' => 'termin',
            'plural'   => 'termine',
            'ajax'     => false
        ]);

        $this->terminController = $terminController;
        $this->timetableController = $timetableController;
        $this->taxonomyService = $taxonomyService;
    }

    /**
     * ZWINGEND ERFORDERLICH: Definiert die Spalten der Tabelle.
     */
    public function get_columns(): array
    {
        return [
            'cb'             => '<input type="checkbox" />',
            'id'             => 'ID',
            'bildungsgang'   => 'Bildungsgang',
            'bezeichnung'    => 'Bezeichnung',
            'ereignistyp'    => 'Ereignistyp',
            'beginn'         => 'Beginn',
            'ende'           => 'Ende',
            'verantwortlich' => 'Verantwortlich',
            'timetable_ID'   => 'Timetable',
            'actions'        => 'Aktionen'
        ];
    }

    public function prepare_items(): void
    {
        $userId = get_current_user_id();

        // 1. Bulk Actions
        $this->process_bulk_action();

        // 2. Filter
        if (!empty($_REQUEST['reset_filter'])) {
            $this->clearFilters($userId);
        } elseif (isset($_REQUEST['filter_action'])) {
            $this->saveFilters($userId, $_REQUEST);
        }

        $filters = $this->getFilters($userId);

        // 3. Daten laden
		// Timetable-Namen für die Anzeige cachen
		$allTimetables = $this->timetableController->getTimetablesForDropdown();
		foreach ($allTimetables as $tt) {
			$this->timetableNames[$tt['id']] = $tt['bezeichnung'];
		}
		
        $this->items = $this->terminController->getFilteredTermine(
            (int)$filters['timetable'],
            $filters['bildungsgang'],
            $filters['ereignistyp']
        );

        // 4. Sortierung
        usort($this->items, [$this, 'sortData']);

        // 5. Header
        $this->_column_headers = [
            $this->get_columns(),
            [], 
            $this->get_sortable_columns()
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'id'           => ['id', true],
            'bildungsgang' => ['bildungsgang', false],
            'beginn'       => ['beginn', false],
            'timetable_ID' => ['timetable_ID', false]
        ];
    }

    /**
     * Standard-Ausgabe für jede Zelle.
     */
    public function column_default($item, $column_name): string
    {
        /** @var Termin $item */
        switch ($column_name) {
            case 'id':             return (string)$item->getId();
            case 'bildungsgang':   return esc_html($item->getBildungsgang());
            case 'bezeichnung':    return esc_html($item->getBezeichnung());
            case 'ereignistyp':    return esc_html($item->getEreignistyp());
            case 'beginn':         return $item->getBeginn()->format('d.m.Y');
            case 'ende':           return $item->getEnde()->format('d.m.Y');
            case 'verantwortlich': return esc_html($item->getVerantwortlich());
            case 'timetable_ID':   $ttId = $item->getTimetableId();
									$name = $this->timetableNames[$ttId] ?? 'Unbekannt';
									 return sprintf('<strong>%d</strong> | %s', $ttId, esc_html($name));
            default:               return 'n/a';
        }
    }

    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%d" />', $item->getId());
    }

    public function column_actions($item): string
    {
        $deleteNonce = wp_create_nonce('delete_termin_' . $item->getId());
        $deleteUrl = admin_url(sprintf('admin.php?page=termine&action=delete&id=%d&_wpnonce=%s', $item->getId(), $deleteNonce));

        return sprintf(
            '<a href="#" class="button mh-tt-edit-btn" data-id="%d">Bearbeiten</a> ' .
            '<a href="%s" class="button" onclick="return confirm(\'Wirklich löschen?\')">Löschen</a>',
            $item->getId(),
            esc_url($deleteUrl)
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which !== 'top') return;

        $filters = $this->getFilters(get_current_user_id());
        $timetables = $this->timetableController->getTimetablesForDropdown();
        $bildungsgaenge = $this->taxonomyService->getBildungsgaenge();
        $ereignistypen = $this->taxonomyService->getEreignistypen();

        echo '<div class="alignleft actions">';
        
        echo '<select name="filter_timetable"><option value="">Alle Timetables</option>';
        foreach ($timetables as $tt) {
            printf('<option value="%d" %s>%s</option>', $tt['id'], selected((string)$filters['timetable'], (string)$tt['id'], false), esc_html($tt['bezeichnung']));
        }
        echo '</select>';

        echo '<select name="filter_bildungsgang"><option value="">Alle Bildungsgänge</option>';
        foreach ($bildungsgaenge as $bg) {
            printf('<option value="%s" %s>%s</option>', esc_attr($bg->name), selected($filters['bildungsgang'], $bg->name, false), esc_html($bg->name));
        }
        echo '</select>';

        echo '<select name="filter_ereignistyp"><option value="">Alle Ereignistypen</option>';
        foreach ($ereignistypen as $et) {
            printf('<option value="%s" %s>%s</option>', esc_attr($et->name), selected($filters['ereignistyp'], $et->name, false), esc_html($et->name));
        }
        echo '</select>';

        submit_button('Filtern', 'button', 'filter_action', false);
        submit_button('Reset', 'button', 'reset_filter', false);
        echo '</div>';
    }

    public function get_bulk_actions(): array
    {
        return ['bulk-delete' => 'Löschen', 'bulk-edit' => 'Mehrfachänderung'];
    }

    public function process_bulk_action(): void
    {
        if ($this->current_action() === 'bulk-delete' && !empty($_REQUEST['bulk-delete'])) {
            foreach ($_REQUEST['bulk-delete'] as $id) {
                $this->terminController->deleteObject((int)$id);
            }
            echo "<div class='updated'><p>Ausgewählte Termine gelöscht.</p></div>";
        }
    }

    private function sortData(Termin $a, Termin $b): int
    {
        $orderby = $_REQUEST['orderby'] ?? 'id';
        $order = $_REQUEST['order'] ?? 'asc';
        $res = ($orderby === 'id' || $orderby === 'timetable_ID') 
            ? ($a->getId() <=> $b->getId()) 
            : strcmp((string)$a->getBeginn()->getTimestamp(), (string)$b->getBeginn()->getTimestamp());
        return ($order === 'asc') ? $res : -$res;
    }

    private function saveFilters(int $userId, array $data): void {
        update_user_meta($userId, 'filter_timetable', sanitize_text_field($data['filter_timetable'] ?? ''));
        update_user_meta($userId, 'filter_bildungsgang', sanitize_text_field($data['filter_bildungsgang'] ?? ''));
        update_user_meta($userId, 'filter_ereignistyp', sanitize_text_field($data['filter_ereignistyp'] ?? ''));
    }

    private function clearFilters(int $userId): void {
        delete_user_meta($userId, 'filter_timetable');
        delete_user_meta($userId, 'filter_bildungsgang');
        delete_user_meta($userId, 'filter_ereignistyp');
    }

    private function getFilters(int $userId): array {
        return [
            'timetable' => get_user_meta($userId, 'filter_timetable', true) ?: '',
            'bildungsgang' => get_user_meta($userId, 'filter_bildungsgang', true) ?: '',
            'ereignistyp' => get_user_meta($userId, 'filter_ereignistyp', true) ?: '',
        ];
    }
}