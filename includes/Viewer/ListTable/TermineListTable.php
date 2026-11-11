<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer\ListTable;

use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Service\TaxonomyService;
use MH\Timetable\Service\KonfliktService;
use MH\Timetable\Model\Entity\Termin;
use WP_List_Table;

/**
 * Vollständige List Table für Termine.
 */
class TermineListTable extends WP_List_Table
{
    private const PER_PAGE = 50;

    private TerminController $terminController;
    private TimetableController $timetableController;
    private TaxonomyService $taxonomyService;
	private array $timetableNames = [];
    private ?KonfliktService $konfliktService;
    /** @var array<int, string[]> Termin-ID → Hinweise */
    private array $hinweise = [];

    public function __construct(
        TerminController $terminController,
        TimetableController $timetableController,
        TaxonomyService $taxonomyService,
        ?KonfliktService $konfliktService = null
    ) {
        $this->konfliktService = $konfliktService;
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
            'hinweise'       => '<span class="dashicons dashicons-warning" title="Hinweise"></span>',
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

        // Hinweise (Konflikte, Fristen) für alle gefilterten Termine
        if ($this->konfliktService) {
            $this->hinweise = $this->konfliktService->pruefeListe($this->items);
            if ($filters['nur_hinweise'] === '1') {
                $this->items = array_values(array_filter($this->items, fn(Termin $t) => isset($this->hinweise[(int)$t->getId()])));
            }
        }

        // 4. Sortierung
        usort($this->items, [$this, 'sortData']);

        // 5. Seitenaufteilung
        $perPage = self::PER_PAGE;
        $total = count($this->items);
        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ]);
        $currentPage = min($this->get_pagenum(), max(1, (int)ceil($total / $perPage)));
        $this->items = array_slice($this->items, ($currentPage - 1) * $perPage, $perPage);

        // 6. Header
        $this->_column_headers = [
            $this->get_columns(),
            [], 
            $this->get_sortable_columns()
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'id'             => ['id', true],
            'bildungsgang'   => ['bildungsgang', false],
            'bezeichnung'    => ['bezeichnung', false],
            'ereignistyp'    => ['ereignistyp', false],
            'beginn'         => ['beginn', false],
            'ende'           => ['ende', false],
            'verantwortlich' => ['verantwortlich', false],
            'timetable_ID'   => ['timetable_ID', false]
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

    public function column_hinweise($item): string
    {
        $liste = $this->hinweise[(int)$item->getId()] ?? [];
        if (!$liste) return '';
        return sprintf(
            '<span class="dashicons dashicons-warning" style="color:#dba617; cursor:help;" title="%s"></span><span class="screen-reader-text">%s</span>',
            esc_attr(implode("\n", $liste)),
            esc_html(implode(' ', $liste))
        );
    }

    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%d" />', $item->getId());
    }

    public function column_actions($item): string
    {
        $deleteUrl = wp_nonce_url(
            admin_url(sprintf('admin-post.php?action=mh_tt_delete_termin&id=%d', $item->getId())),
            'delete_termin_' . $item->getId()
        );

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

        if ($this->konfliktService) {
            printf(
                '<label style="margin:0 8px; line-height:30px;"><input type="checkbox" name="filter_nur_hinweise" value="1" %s> nur mit Hinweisen</label>',
                checked($filters['nur_hinweise'], '1', false)
            );
        }

        submit_button('Filtern', 'button', 'filter_action', false);
        submit_button('Reset', 'button', 'reset_filter', false);
        echo '</div>';
    }

    public function get_bulk_actions(): array
    {
        return ['bulk-delete' => 'Löschen', 
                'bulk-edit' => 'Mehrfachänderung',
                'bulk-copy'   => 'Kopieren'];
    }

    public function process_bulk_action(): void
    {
        if ($this->current_action() === 'bulk-delete' && !empty($_REQUEST['bulk-delete'])) {
            check_admin_referer('bulk-' . $this->_args['plural']);
            if (!current_user_can('manage_options')) return;

            $count = 0;
            foreach ((array)$_REQUEST['bulk-delete'] as $id) {
                if ($this->terminController->deleteObject((int)$id)) $count++;
            }
            // Die URL behält die Parameter (GET-Formular); beim Blättern würden bereits
            // gelöschte IDs erneut übergeben – dann ist $count 0 und es gibt keine Meldung.
            if ($count > 0) {
                printf("<div class='notice notice-success is-dismissible'><p>%d Termin(e) gelöscht.</p></div>", $count);
            }
        }
    }

    private function sortData(Termin $a, Termin $b): int
    {
        $orderby = sanitize_key($_REQUEST['orderby'] ?? 'id');
        $order = strtolower((string)($_REQUEST['order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        switch ($orderby) {
            case 'bildungsgang':   $res = strnatcasecmp($a->getBildungsgang(), $b->getBildungsgang()); break;
            case 'bezeichnung':    $res = strnatcasecmp($a->getBezeichnung(), $b->getBezeichnung()); break;
            case 'ereignistyp':    $res = strnatcasecmp($a->getEreignistyp(), $b->getEreignistyp()); break;
            case 'verantwortlich': $res = strnatcasecmp($a->getVerantwortlich(), $b->getVerantwortlich()); break;
            case 'beginn':         $res = $a->getBeginn() <=> $b->getBeginn(); break;
            case 'ende':           $res = $a->getEnde() <=> $b->getEnde(); break;
            case 'timetable_id':   $res = $a->getTimetableId() <=> $b->getTimetableId(); break;
            default:               $res = 0;
        }
        // Bei Gleichstand stabil nach Beginn und ID sortieren
        if ($res === 0 && $orderby !== 'id') $res = $a->getBeginn() <=> $b->getBeginn();
        if ($res === 0) $res = $a->getId() <=> $b->getId();

        return ($order === 'asc') ? $res : -$res;
    }

    private function saveFilters(int $userId, array $data): void {
        update_user_meta($userId, 'filter_timetable', sanitize_text_field($data['filter_timetable'] ?? ''));
        update_user_meta($userId, 'filter_bildungsgang', sanitize_text_field($data['filter_bildungsgang'] ?? ''));
        update_user_meta($userId, 'filter_ereignistyp', sanitize_text_field($data['filter_ereignistyp'] ?? ''));
        update_user_meta($userId, 'filter_nur_hinweise', empty($data['filter_nur_hinweise']) ? '' : '1');
    }

    private function clearFilters(int $userId): void {
        delete_user_meta($userId, 'filter_timetable');
        delete_user_meta($userId, 'filter_bildungsgang');
        delete_user_meta($userId, 'filter_ereignistyp');
        delete_user_meta($userId, 'filter_nur_hinweise');
    }

    private function getFilters(int $userId): array {
        return [
            'timetable' => get_user_meta($userId, 'filter_timetable', true) ?: '',
            'bildungsgang' => get_user_meta($userId, 'filter_bildungsgang', true) ?: '',
            'ereignistyp' => get_user_meta($userId, 'filter_ereignistyp', true) ?: '',
            'nur_hinweise' => get_user_meta($userId, 'filter_nur_hinweise', true) ?: '',
        ];
    }
}