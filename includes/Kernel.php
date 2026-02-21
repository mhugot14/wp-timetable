<?php
declare(strict_types=1);

namespace MH\Timetable;

use MH\Timetable\Model\Repository\WpdbTermineRepository;
use MH\Timetable\Controller\TerminController;
use MH\Timetable\Viewer\View;
use MH\Timetable\Model\Repository\WpdbTimetableRepository;
use MH\Timetable\Controller\TimetableController;

/**
 * Der Kernel ist die zentrale Instanz für das Dependency Injection Handling.
 * Er erstellt alle Objekte und reicht sie an die nächste Schicht weiter.
 */
class Kernel
{
    /**
     * Startet das Plugin-System.
     */
	public function boot(): void
	{
		//wp_die('DEBUG: Kernel Boot erreicht!'); 
		global $wpdb;
		// Services
		$holidayApi = new \MH\Timetable\Service\HolidayApiService();
		$taxonomyService = new \MH\Timetable\Service\TaxonomyService();
		
		// Repositories
		$termineRepo = new \MH\Timetable\Model\Repository\WpdbTermineRepository($wpdb);
		$timetableRepo = new \MH\Timetable\Model\Repository\WpdbTimetableRepository($wpdb);
		$ferienRepo = new \MH\Timetable\Model\Repository\WpdbFerienRepository($wpdb);

		// Controller
		$terminController = new \MH\Timetable\Controller\TerminController($termineRepo);
		$timetableController = new \MH\Timetable\Controller\TimetableController($timetableRepo);
		$ferienController = new \MH\Timetable\Controller\FerienController($ferienRepo, $holidayApi);

		// View (Orchestrator)
		 new \MH\Timetable\Viewer\View(
				$terminController,
				$timetableController,
				$ferienController, // Neu
				$termineRepo,
				$timetableRepo,
				$ferienRepo,       // Neu
				$taxonomyService   // Neu
				);
		     // Hooks registrieren, die nicht in der View sitzen
		add_action('init', [$taxonomyService, 'registerTaxonomies']);
		add_action('wp_head', [$taxonomyService, 'generateDynamicCss']);
		add_action('admin_head', [$taxonomyService, 'generateDynamicCss']);
		add_action('wp_ajax_mh_tt_get_timetable', [$timetableController, 'getTimetableDataAjax']);
		 add_action('wp_ajax_mh_tt_get_termin', [$terminController, 'getTerminDataAjax']);
		  add_action('admin_enqueue_scripts', function() {
        wp_localize_script('mh_tt_javascript', 'mh_tt_params', [
            'nonce' => wp_create_nonce('mh_tt_nonce')
        ]);
		
    });
	}
}