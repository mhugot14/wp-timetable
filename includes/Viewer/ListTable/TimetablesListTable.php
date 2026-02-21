<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer\ListTable;

use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Model\Entity\Timetable;
use WP_List_Table;

class TimetablesListTable extends WP_List_Table
{
    private TimetableController $controller;

    public function __construct(TimetableController $controller)
    {
        parent::__construct([
            'singular' => 'timetable',
            'plural'   => 'timetables',
            'ajax'     => false
        ]);
        $this->controller = $controller;
    }

    public function get_columns(): array
    {
        return [
            'cb'           => '<input type="checkbox" />',
            'id'           => 'ID',
            'bezeichnung'  => 'Bezeichnung',
            'beschreibung' => 'Beschreibung',
            'erzeugt_am'   => 'Erzeugt am',
            'actions'      => 'Aktionen'
        ];
    }

   
   public function prepare_items(): void
	{
		// 1. Bulk Actions verarbeiten (löschen etc.)
		$this->process_bulk_action();

		// 2. Daten über den Controller laden
		// FIX: Hier stand vorher fälschlicherweise repository_get_all()
		$this->items = $this->controller->getAllTimetables();

		// 3. Header definieren
		$this->_column_headers = [$this->get_columns(), [], []];
	}

    public function column_default($item, $column_name): string
    {
        /** @var Timetable $item */
        switch ($column_name) {
            case 'id':           return (string)$item->getId();
            case 'bezeichnung':  return esc_html($item->getBezeichnung());
            case 'beschreibung': return esc_html($item->getBeschreibung());
            case 'erzeugt_am':   return $item->getErzeugtAm()->format('d.m.Y H:i');
            default:             return 'n/a';
        }
    }

    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%d" />', $item->getId());
    }

    public function column_actions($item): string
    {
        $deleteNonce = wp_create_nonce('delete_timetable_' . $item->getId());
        $deleteUrl = admin_url(sprintf('admin.php?page=mh-timetable&action=delete&id=%d&_wpnonce=%s', $item->getId(), $deleteNonce));

        return sprintf(
            '<a href="#" class="button mh-tt-timetable-edit-btn" data-id="%d">Bearbeiten</a> ' .
            '<a href="%s" class="button" onclick="return confirm(\'Zeittafel wirklich löschen?\')">Löschen</a> ' . 
			'<a href="#" class="button mh-tt-copy-btn" data-id="'.$item->getId().'" data-name="'.$item->getBezeichnung().'">Kopieren</a>',
            $item->getId(),
            esc_url($deleteUrl)
        );
    }
}