<?php
declare(strict_types=1);

namespace MH\Timetable;

use MH\Timetable\Model\Repository\WpdbTermineRepository;
use MH\Timetable\Model\Repository\WpdbTimetableRepository;
use MH\Timetable\Model\Repository\WpdbFerienRepository;
use MH\Timetable\Service\HolidayApiService;
use MH\Timetable\Service\TaxonomyService;
use MH\Timetable\Service\IcalService;
use MH\Timetable\Service\TimetableCopyService;
use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Controller\FerienController;
use MH\Timetable\Viewer\View;

class Kernel
{
    public function boot(): void
    {
        global $wpdb;

        // --- 1. REPOSITORIES (Zuerst! Weil alles andere davon abhängt) ---
        $termineRepo   = new WpdbTermineRepository($wpdb);
        $timetableRepo = new WpdbTimetableRepository($wpdb);
        $ferienRepo    = new WpdbFerienRepository($wpdb);

        // --- 2. SERVICES (Danach! Sie brauchen oft Repositories) ---
        $holidayApi      = new HolidayApiService();
        $taxonomyService = new TaxonomyService();
        $icalService     = new IcalService();
        
        // Jetzt sind $timetableRepo und $termineRepo bekannt:
        $copyService     = new TimetableCopyService($timetableRepo, $termineRepo);

        // --- 3. CONTROLLER (Injizieren der Repos und Services) ---
        $terminController    = new TerminController($termineRepo);
        $timetableController = new TimetableController($timetableRepo, $copyService);
        $ferienController    = new FerienController($ferienRepo, $holidayApi);

        // --- 4. VIEW (Orchestrator) ---
        new View(
            $terminController,
            $timetableController,
            $ferienController,
            $termineRepo,
            $timetableRepo,
            $ferienRepo,
            $taxonomyService
        );

        // --- 5. HOOKS & AJAX ---
        add_action('init', [$taxonomyService, 'registerTaxonomies']);
        add_action('wp_head', [$taxonomyService, 'generateDynamicCss']);
        add_action('admin_head', [$taxonomyService, 'generateDynamicCss']);
        
        add_action('wp_ajax_mh_tt_get_termin', [$terminController, 'getTerminDataAjax']);
        add_action('wp_ajax_mh_tt_get_timetable', [$timetableController, 'getTimetableDataAjax']);
    }
}