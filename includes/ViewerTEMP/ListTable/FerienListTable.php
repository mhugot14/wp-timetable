<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer\ListTable;

use MH\Timetable\Controller\FerienController;
use MH\Timetable\Model\Entity\Ferien;
use WP_List_Table;

/**
 * List Table für die Anzeige von Ferien und Feiertagen im Backend.
 */
class FerienListTable extends WP_List_Table
{
    private FerienController $ferienController;

    public function __construct(FerienController $ferienController)
    {
        parent::__construct([
            'singular' => 'ferien',
            'plural'   => 'ferien',
            'ajax'     => false
        ]);

        $this->ferienController = $ferienController;
    }

    /**
     * Bereitet die Items für die Anzeige vor.
     */
    public function prepare_items(): void
    {
        // Bulk Actions verarbeiten
        $this->process_bulk_action();

        // Spalten-Definitionen
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Daten über den Controller laden (gibt Entities zurück)
        $this->items = $this->ferienController->getAllFerien();

        // Sortierung (einfache Implementierung für Entities)
        usort($this->items, function (Ferien $a, Ferien $b) {
            $orderby = $_GET['orderby'] ?? 'startdatum';
            $order = $_GET['order'] ?? 'asc';
            
            if ($orderby === 'startdatum') {
                $result = $a->getStartdatum() <=> $b->getStartdatum();
            } else {
                $result = strcmp($a->getName(), $b->getName());
            }

            return ($order === 'asc') ? $result : -$result;
        });
    }

    public function get_columns(): array
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'name'       => 'Name',
            'startdatum' => 'Startdatum',
            'enddatum'   => 'Enddatum',
            'typ'        => 'Typ',
            'actions'    => 'Aktionen'
        ];
    }

    /**
     * Standard-Spaltenausgabe für Ferien-Entities.
     * @param Ferien $item
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'name':
                return esc_html($item->getName());
            case 'startdatum':
                return $item->getStartdatum()->format('d.m.Y');
            case 'enddatum':
                return $item->getEnddatum()->format('d.m.Y');
            case 'typ':
                return esc_html($item->getTyp());
            default:
                return 'N/A';
        }
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%d" />',
            $item->getId()
        );
    }

    public function column_actions($item): string
    {
        $nonce = wp_create_nonce('delete_ferien_' . $item->getId());
        $url = admin_url(sprintf(
            'admin.php?page=Einstellungen&action=delete_ferien&id=%d&_wpnonce=%s',
            $item->getId(),
            $nonce
        ));

        return sprintf(
            '<a href="%s" class="button" onclick="return confirm(\'Eintrag wirklich löschen?\')">Löschen</a>',
            esc_url($url)
        );
    }

    public function get_sortable_columns(): array
    {
        return [
            'name'       => ['name', false],
            'startdatum' => ['startdatum', true],
            'typ'        => ['typ', false]
        ];
    }

    public function get_bulk_actions(): array
    {
        return [
            'bulk-delete' => 'Löschen'
        ];
    }

    public function process_bulk_action(): void
    {
        if ($this->current_action() === 'bulk-delete' && !empty($_POST['bulk-delete'])) {
            $deleteIds = array_map('intval', $_POST['bulk-delete']);
            $count = 0;

            foreach ($deleteIds as $id) {
                if ($this->ferienController->deleteFerien($id)) {
                    $count++;
                }
            }

            echo "<div class='updated'><p>{$count} Einträge wurden gelöscht.</p></div>";
        }
    }
}