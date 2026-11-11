<?php
declare(strict_types=1);

namespace MH\Timetable\Controller;

use MH\Timetable\Model\Entity\Termin;
use MH\Timetable\Model\Repository\TermineRepositoryInterface;
use MH\Timetable\Model\Repository\TimetableRepositoryInterface;
use MH\Timetable\Service\Berechtigungen;
use MH\Timetable\Service\StammdatenService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST-Schnittstelle für die Bearbeitung im Frontend: /wp-json/mh-timetable/v1/…
 *
 * Jede Anfrage prüft auf dem Server, ob die angemeldete Person den betroffenen
 * Bildungsgang bearbeiten darf (Admin, AL oder BGA). Was der Browser schickt,
 * wird nie ungeprüft übernommen.
 *
 *   GET    /ich             – eigene Rechte
 *   GET    /termine/{id}    – einen Termin lesen (nur mit Bearbeitungsrecht)
 *   POST   /termine         – Termin anlegen
 *   PUT    /termine/{id}    – Termin ändern
 *   DELETE /termine/{id}    – Termin löschen
 */
class RestController
{
    public const NS = 'mh-timetable/v1';

    private TerminController $termine;
    private TermineRepositoryInterface $termineRepo;
    private TimetableRepositoryInterface $timetableRepo;
    private StammdatenService $stammdaten;
    private Berechtigungen $rechte;

    public function __construct(
        TerminController $termine,
        TermineRepositoryInterface $termineRepo,
        TimetableRepositoryInterface $timetableRepo,
        StammdatenService $stammdaten,
        Berechtigungen $rechte
    ) {
        $this->termine = $termine;
        $this->termineRepo = $termineRepo;
        $this->timetableRepo = $timetableRepo;
        $this->stammdaten = $stammdaten;
        $this->rechte = $rechte;
    }

    public function registerRoutes(): void
    {
        $angemeldet = fn() => is_user_logged_in();
        $idArg = ['id' => ['validate_callback' => fn($v) => is_numeric($v) && (int)$v > 0]];

        register_rest_route(self::NS, '/ich', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'ich'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/termine', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'anlegen'],
            'permission_callback' => $angemeldet,
        ]);
        register_rest_route(self::NS, '/termine/(?P<id>\d+)', [
            ['methods' => WP_REST_Server::READABLE,  'callback' => [$this, 'lesen'],   'permission_callback' => $angemeldet, 'args' => $idArg],
            ['methods' => WP_REST_Server::EDITABLE,  'callback' => [$this, 'aendern'], 'permission_callback' => $angemeldet, 'args' => $idArg],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'loeschen'], 'permission_callback' => $angemeldet, 'args' => $idArg],
        ]);
    }

    public function ich(): WP_REST_Response
    {
        $userId = get_current_user_id();
        return new WP_REST_Response([
            'angemeldet'     => $userId > 0,
            'admin'          => $this->rechte->istAdmin($userId),
            'bildungsgaenge' => array_map(fn($bg) => $bg->getKuerzel(), $this->rechte->bearbeitbareBildungsgaenge($userId)),
        ]);
    }

    /** @return WP_REST_Response|WP_Error */
    public function lesen(WP_REST_Request $req)
    {
        $termin = $this->termineRepo->find((int)$req['id']);
        if (!$termin) return $this->fehler(404, 'Der Termin wurde nicht gefunden.');
        if (!$this->rechte->darfTermin(get_current_user_id(), $termin)) return $this->keineRechte($termin->getBildungsgang());
        return new WP_REST_Response(self::terminAlsArray($termin));
    }

    /** @return WP_REST_Response|WP_Error */
    public function anlegen(WP_REST_Request $req)
    {
        $daten = $this->eingabe($req);
        if ($fehler = $this->pruefeEingabe($daten)) return $fehler;
        if (!$this->rechte->darfBildungsgang(get_current_user_id(), $daten['bildungsgang'])) {
            return $this->keineRechte($daten['bildungsgang']);
        }
        return $this->speichern($daten, 0, 201);
    }

    /** @return WP_REST_Response|WP_Error */
    public function aendern(WP_REST_Request $req)
    {
        $userId = get_current_user_id();
        $alt = $this->termineRepo->find((int)$req['id']);
        if (!$alt) return $this->fehler(404, 'Der Termin wurde nicht gefunden.');
        if (!$this->rechte->darfTermin($userId, $alt)) return $this->keineRechte($alt->getBildungsgang());

        // Nicht übergebene Felder behalten ihren bisherigen Wert (PATCH-Verhalten)
        $daten = array_merge(self::terminAlsFormular($alt), array_filter($this->eingabe($req), fn($v) => $v !== null));
        if ($fehler = $this->pruefeEingabe($daten)) return $fehler;

        // Ziel-Bildungsgang muss ebenfalls erlaubt sein (kein Verschieben in fremde Bildungsgänge)
        if (!$this->rechte->darfBildungsgang($userId, $daten['bildungsgang'])) {
            return $this->keineRechte($daten['bildungsgang']);
        }
        // Nur Admins dürfen Termine in eine andere Timetable verschieben
        if ((int)$daten['timetable_ID'] !== $alt->getTimetableId() && !$this->rechte->istAdmin($userId)) {
            return $this->fehler(403, 'Nur Admins dürfen Termine in eine andere Timetable verschieben.');
        }
        return $this->speichern($daten, (int)$alt->getId(), 200);
    }

    /** @return WP_REST_Response|WP_Error */
    public function loeschen(WP_REST_Request $req)
    {
        $termin = $this->termineRepo->find((int)$req['id']);
        if (!$termin) return $this->fehler(404, 'Der Termin wurde nicht gefunden.');
        if (!$this->rechte->darfTermin(get_current_user_id(), $termin)) return $this->keineRechte($termin->getBildungsgang());
        if (!$this->termineRepo->delete((int)$termin->getId())) return $this->fehler(500, 'Der Termin konnte nicht gelöscht werden.');
        return new WP_REST_Response(['geloescht' => true, 'id' => (int)$termin->getId()]);
    }

    // --- Hilfen ------------------------------------------------------------

    /** @return WP_REST_Response|WP_Error */
    private function speichern(array $daten, int $id, int $status)
    {
        $ergebnis = $this->termine->speichern($daten, $id);
        if (!empty($ergebnis['errors'])) {
            return $this->fehler(400, 'Der Termin wurde nicht gespeichert.', self::flach($ergebnis['errors']));
        }
        $termin = $this->termineRepo->find($ergebnis['id']);
        return new WP_REST_Response([
            'termin'   => $termin ? self::terminAlsArray($termin) : null,
            'hinweise' => $this->termine->getHinweise(),
        ], $status);
    }

    /** Eingabe aus JSON/Formular in die Feldnamen des Formulars übersetzen (null = nicht übergeben). */
    private function eingabe(WP_REST_Request $req): array
    {
        $p = $req->get_json_params() ?: $req->get_body_params();
        $wert = fn(string $k) => isset($p[$k]) ? (string)$p[$k] : null;
        return [
            'timetable_ID'   => isset($p['timetable_id']) ? (int)$p['timetable_id'] : null,
            'bildungsgang'   => $wert('bildungsgang'),
            'ereignistyp'    => $wert('ereignistyp'),
            'bezeichnung'    => $wert('bezeichnung'),
            'beginn'         => $wert('beginn'),
            'ende'           => $wert('ende'),
            'verantwortlich' => $wert('verantwortlich'),
        ];
    }

    /** Timetable, Bildungsgang und Ereignistyp müssen existieren. @return WP_Error|null */
    private function pruefeEingabe(array $d): ?WP_Error
    {
        $fehler = [];
        if (!$this->timetableRepo->find((int)($d['timetable_ID'] ?? 0))) $fehler[] = 'Die Timetable existiert nicht.';
        if (!$this->stammdaten->bildungsgaenge()->findByKuerzel((string)($d['bildungsgang'] ?? ''))) $fehler[] = 'Unbekannter Bildungsgang.';
        if (!$this->stammdaten->ereignistypen()->findByKuerzel((string)($d['ereignistyp'] ?? ''))) $fehler[] = 'Unbekannter Ereignistyp.';
        return $fehler ? $this->fehler(400, 'Ungültige Eingabe.', $fehler) : null;
    }

    private function keineRechte(string $bildungsgang): WP_Error
    {
        return $this->fehler(403, sprintf('Keine Berechtigung für Termine des Bildungsgangs „%s“.', $bildungsgang));
    }

    private function fehler(int $status, string $nachricht, array $details = []): WP_Error
    {
        return new WP_Error('mh_tt_fehler', $nachricht, ['status' => $status, 'details' => $details]);
    }

    private static function flach(array $errors): array
    {
        $flat = [];
        array_walk_recursive($errors, function ($m) use (&$flat) { $flat[] = (string)$m; });
        return $flat;
    }

    public static function terminAlsArray(Termin $t): array
    {
        return [
            'id'             => (int)$t->getId(),
            'timetable_id'   => $t->getTimetableId(),
            'bildungsgang'   => $t->getBildungsgang(),
            'ereignistyp'    => $t->getEreignistyp(),
            'bezeichnung'    => $t->getBezeichnung(),
            'beginn'         => $t->getBeginn()->format('Y-m-d'),
            'ende'           => $t->getEnde()->format('Y-m-d'),
            'verantwortlich' => $t->getVerantwortlich(),
        ];
    }

    private static function terminAlsFormular(Termin $t): array
    {
        return [
            'timetable_ID'   => $t->getTimetableId(),
            'bildungsgang'   => $t->getBildungsgang(),
            'ereignistyp'    => $t->getEreignistyp(),
            'bezeichnung'    => $t->getBezeichnung(),
            'beginn'         => $t->getBeginn()->format('Y-m-d'),
            'ende'           => $t->getEnde()->format('Y-m-d'),
            'verantwortlich' => $t->getVerantwortlich(),
        ];
    }
}
