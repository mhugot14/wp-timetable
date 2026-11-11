<?php
declare(strict_types=1);

namespace MH\Timetable;

use MH\Timetable\Model\Repository\WpdbTermineRepository;
use MH\Timetable\Model\Repository\WpdbTimetableRepository;
use MH\Timetable\Model\Repository\WpdbFerienRepository;
use MH\Timetable\Model\Repository\WpdbBildungsgangRepository;
use MH\Timetable\Model\Repository\WpdbEreignistypRepository;
use MH\Timetable\Service\HolidayApiService;
use MH\Timetable\Service\StammdatenService;
use MH\Timetable\Service\IcalService;
use MH\Timetable\Service\TimetableCopyService;
use MH\Timetable\Service\SchulkalenderService;
use MH\Timetable\Service\FristenService;
use MH\Timetable\Service\KonfliktService;
use MH\Timetable\Controller\TerminController;
use MH\Timetable\Controller\TimetableController;
use MH\Timetable\Controller\FerienController;
use MH\Timetable\Viewer\View;
use MH\Timetable\Viewer\StammdatenAdmin;

class Kernel
{
    public function boot(): void
    {
        global $wpdb;

        // --- 1. REPOSITORIES (Zuerst! Weil alles andere davon abhängt) ---
        $termineRepo      = new WpdbTermineRepository($wpdb);
        $timetableRepo    = new WpdbTimetableRepository($wpdb);
        $ferienRepo       = new WpdbFerienRepository($wpdb);
        $bildungsgangRepo = new WpdbBildungsgangRepository($wpdb);
        $ereignistypRepo  = new WpdbEreignistypRepository($wpdb);

        // --- 2. SERVICES (Danach! Sie brauchen oft Repositories) ---
        $holidayApi      = new HolidayApiService();
        $stammdaten      = new StammdatenService($bildungsgangRepo, $ereignistypRepo);
        $icalService     = new IcalService();
        $schulkalender   = new SchulkalenderService($ferienRepo);
        $copyService     = new TimetableCopyService($timetableRepo, $termineRepo, $schulkalender);
        $fristenService  = new FristenService($schulkalender, $termineRepo, $ereignistypRepo);
        $konfliktService = new KonfliktService($schulkalender, $fristenService, $termineRepo);

        // --- 3. CONTROLLER (Injizieren der Repos und Services) ---
        $terminController    = new TerminController($termineRepo, $fristenService, $konfliktService);
        $timetableController = new TimetableController($timetableRepo, $termineRepo, $copyService);
        $ferienController    = new FerienController($ferienRepo, $holidayApi);

        // --- 4. VIEWS ---
        new View(
            $terminController,
            $timetableController,
            $ferienController,
            $termineRepo,
            $timetableRepo,
            $ferienRepo,
            $stammdaten,
            $icalService,
            $fristenService,
            $konfliktService
        );
        new StammdatenAdmin($stammdaten, $fristenService);

        // --- 5. HOOKS & AJAX ---
        add_action('init', [\MH\Timetable\Install\Schema::class, 'maybeMigrateData'], 20);
        add_action('wp_head', [$stammdaten, 'generateDynamicCss']);
        add_action('admin_head', [$stammdaten, 'generateDynamicCss']);

        add_action('wp_ajax_mh_tt_get_termin', [$terminController, 'getTerminDataAjax']);
        add_action('wp_ajax_mh_tt_get_timetable', [$timetableController, 'getTimetableDataAjax']);
    }
}
