<?php
declare(strict_types=1);

namespace MH\Timetable\Viewer;

use MH\Timetable\Install\Schema;
use MH\Timetable\Model\Entity\Bildungsgang;
use MH\Timetable\Model\Entity\Ereignistyp;
use MH\Timetable\Service\FristenService;
use MH\Timetable\Service\StammdatenService;

/**
 * Verwaltungsseiten für die Stammdaten:
 *  - Bildungsgänge mit Abteilungsleitung (AL) und Bildungsgangassistenzen (BGA)
 *  - Ereignistypen mit Farbe
 * jeweils mit Anlegen, Bearbeiten, Löschen (nur ohne Termine) und Zusammenführen.
 */
class StammdatenAdmin
{
    private const CAP = 'manage_options';
    private const PAGE_BG = 'mh-tt-bildungsgaenge';
    private const PAGE_ET = 'mh-tt-ereignistypen';

    private StammdatenService $stammdaten;
    private FristenService $fristen;

    public function __construct(StammdatenService $stammdaten, FristenService $fristen)
    {
        $this->stammdaten = $stammdaten;
        $this->fristen = $fristen;

        add_action('admin_menu', [$this, 'createMenu'], 20);
        foreach (['save_bildungsgang', 'delete_bildungsgang', 'merge_bildungsgang', 'save_ereignistyp', 'delete_ereignistyp', 'merge_ereignistyp'] as $aktion) {
            add_action('admin_post_mh_tt_' . $aktion, [$this, 'handle' . str_replace('_', '', ucwords($aktion, '_'))]);
        }
    }

    public function createMenu(): void
    {
        add_submenu_page('mh-timetable', 'Bildungsgänge', 'Bildungsgänge', self::CAP, self::PAGE_BG, [$this, 'renderBildungsgaenge']);
        add_submenu_page('mh-timetable', 'Ereignistypen', 'Ereignistypen', self::CAP, self::PAGE_ET, [$this, 'renderEreignistypen']);
    }

    // =====================================================================
    // Bildungsgänge
    // =====================================================================

    public function renderBildungsgaenge(): void
    {
        $repo = $this->stammdaten->bildungsgaenge();
        $alle = $repo->getAll();
        $anzahl = $repo->countTermine();
        $autoId = (int)($_GET['edit'] ?? 0);
        $users = $this->getUsers();

        echo '<div class="wrap"><h1 class="wp-heading-inline">Bildungsgänge</h1> ';
        echo AdminModal::button('mh-tt-bg-modal', '+ Neuer Bildungsgang', ['aktiv' => true], 'Neuer Bildungsgang');
        if (count($alle) > 1) {
            echo ' ' . AdminModal::button('mh-tt-bg-merge', 'Zusammenführen', [], 'Bildungsgänge zusammenführen');
        }
        echo '<hr class="wp-header-end">';
        echo '<p>Abteilungsleitung (AL) und Bildungsgangassistenzen (BGA) dürfen im Frontend die Termine ihrer Bildungsgänge bearbeiten.</p>';
        ?>
        <table class="widefat striped" style="max-width:1200px;">
            <thead><tr><th>Kürzel</th><th>Bezeichnung</th><th>Abteilungsleitung</th><th>Bildungsgangassistenz</th><th>Termine</th><th>Status</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php if (!$alle): ?><tr><td colspan="7"><em>Noch keine Bildungsgänge angelegt.</em></td></tr><?php endif; ?>
            <?php foreach ($alle as $eintrag):
                $id = (int)$eintrag->getId();
                $n = $anzahl[$id] ?? 0;
                $werte = [
                    'id' => $id, 'kuerzel' => $eintrag->getKuerzel(), 'bezeichnung' => $eintrag->getBezeichnung(),
                    'al_user_id' => (int)$eintrag->getAlUserId(), 'bga_user_ids' => $eintrag->getBgaUserIds(), 'aktiv' => $eintrag->isAktiv(),
                ]; ?>
                <tr<?php echo $eintrag->isAktiv() ? '' : ' style="opacity:.6"'; ?>>
                    <td><strong><?php echo esc_html($eintrag->getKuerzel()); ?></strong></td>
                    <td><?php echo esc_html($eintrag->getBezeichnung()); ?></td>
                    <td><?php echo esc_html($this->userName($eintrag->getAlUserId())); ?></td>
                    <td><?php echo esc_html(implode(', ', array_map([$this, 'userName'], $eintrag->getBgaUserIds()))); ?></td>
                    <td><?php echo $n; ?></td>
                    <td><?php echo $eintrag->isAktiv() ? 'aktiv' : 'inaktiv'; ?></td>
                    <td><div class="mh-tt-aktionen">
                        <?php echo AdminModal::button('mh-tt-bg-modal', 'Bearbeiten', $werte, 'Bildungsgang bearbeiten: ' . $eintrag->getKuerzel(), 'button button-small', $autoId === $id); ?>
                        <?php if ($n === 0): ?>
                            <a class="button button-small mh-tt-btn-loeschen" onclick="return confirm('Bildungsgang wirklich löschen?')" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mh_tt_delete_bildungsgang&id=' . $id), 'mh_tt_delete_bg_' . $id)); ?>">Löschen</a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        echo '</div>';

        // --- Dialog: Anlegen/Bearbeiten ---
        $alOptionen = '<option value="0">– keine –</option>';
        $bgaOptionen = '';
        foreach ($users as $u) {
            $alOptionen .= sprintf('<option value="%d">%s</option>', $u->ID, esc_html($u->display_name));
            $bgaOptionen .= sprintf('<option value="%d">%s</option>', $u->ID, esc_html($u->display_name));
        }
        echo AdminModal::start('mh-tt-bg-modal', 'Bildungsgang', 'mh_tt_save_bildungsgang', 'mh_tt_bildungsgang', 'mh_tt_nonce');
        echo AdminModal::zeile('Kürzel', sprintf('<input type="text" id="bg_kuerzel" name="kuerzel" maxlength="%d" required class="regular-text">', Schema::LEN_STAMM_KUERZEL), 'Wird in Terminen, Filtern und im GANTT angezeigt, z. B. „IFK“.', 'bg_kuerzel');
        echo AdminModal::zeile('Bezeichnung', sprintf('<input type="text" id="bg_bezeichnung" name="bezeichnung" maxlength="%d" class="regular-text">', Schema::LEN_STAMM_BEZEICHNUNG), 'optional, Langform', 'bg_bezeichnung');
        echo AdminModal::zeile('Abteilungsleitung', '<select id="bg_al" name="al_user_id">' . $alOptionen . '</select>', '', 'bg_al');
        echo AdminModal::zeile('Bildungsgangassistenz', '<select id="bg_bga" name="bga_user_ids[]" multiple size="8" style="min-width:280px;">' . $bgaOptionen . '</select>', 'Mehrere auswählen mit gedrückter Strg-Taste (Mac: ⌘).', 'bg_bga');
        echo AdminModal::zeile('Status', '<label><input type="checkbox" name="aktiv" value="1"> aktiv</label>', 'Inaktive Bildungsgänge stehen in Auswahllisten am Ende und sind im Frontend nicht bearbeitbar; bestehende Termine bleiben erhalten.');
        echo AdminModal::ende();

        $this->renderMergeForm('bildungsgang', 'mh-tt-bg-merge', 'Bildungsgänge zusammenführen', $alle, $anzahl,
            'Alle Termine der Quelle werden dem Ziel zugeordnet, die Quelle wird gelöscht. AL und BGA der Quelle werden beim Ziel ergänzt (die AL der Quelle wird BGA, falls das Ziel schon eine AL hat) – so behalten alle ihre Bearbeitungsrechte.');
    }

    public function handleSaveBildungsgang(): void
    {
        $this->pruefen('mh_tt_bildungsgang');
        $repo = $this->stammdaten->bildungsgaenge();
        $id = (int)($_POST['id'] ?? 0);
        $kuerzel = sanitize_text_field(wp_unslash($_POST['kuerzel'] ?? ''));

        $fehler = $this->pruefeKuerzel($kuerzel, $repo->findByKuerzel($kuerzel), $id);
        $bg = $id ? $repo->find($id) : new Bildungsgang($kuerzel);
        if (!$bg) $fehler[] = 'Der Bildungsgang wurde nicht gefunden.';
        if ($fehler) {
            AdminNotices::add('error', $fehler);
            $this->zurueck(self::PAGE_BG, $id);
        }

        $bg->setKuerzel($kuerzel);
        $bg->setBezeichnung(mb_substr(sanitize_text_field(wp_unslash($_POST['bezeichnung'] ?? '')), 0, Schema::LEN_STAMM_BEZEICHNUNG));
        $bg->setAktiv(!empty($_POST['aktiv']));
        $bg->setAlUserId($this->gueltigeUserId((int)($_POST['al_user_id'] ?? 0)));
        $bg->setBgaUserIds(array_filter(array_map([$this, 'gueltigeUserId'], array_map('intval', (array)($_POST['bga_user_ids'] ?? [])))));

        if ($repo->save($bg)) {
            AdminNotices::add('success', [sprintf('Bildungsgang „%s“ wurde gespeichert.', $kuerzel)]);
            $this->zurueck(self::PAGE_BG);
        }
        AdminNotices::add('error', ['Speichern fehlgeschlagen (Datenbankfehler).']);
        $this->zurueck(self::PAGE_BG, $id);
    }

    public function handleDeleteBildungsgang(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $this->pruefen('mh_tt_delete_bg_' . $id, '_wpnonce');
        $repo = $this->stammdaten->bildungsgaenge();

        if (($repo->countTermine()[$id] ?? 0) > 0) {
            AdminNotices::add('error', ['Der Bildungsgang hat noch Termine. Bitte zusammenführen oder auf „inaktiv“ setzen.']);
        } elseif ($repo->delete($id)) {
            AdminNotices::add('success', ['Der Bildungsgang wurde gelöscht.']);
        }
        $this->zurueck(self::PAGE_BG);
    }

    public function handleMergeBildungsgang(): void
    {
        $this->pruefen('mh_tt_merge_bildungsgang');
        $quelle = $this->stammdaten->bildungsgaenge()->find((int)($_POST['quelle'] ?? 0));
        $ziel = $this->stammdaten->bildungsgaenge()->find((int)($_POST['ziel'] ?? 0));
        $ergebnis = ($quelle && $ziel) ? $this->stammdaten->bildungsgaenge()->zusammenfuehren((int)$quelle->getId(), (int)$ziel->getId()) : null;

        if ($ergebnis === null) {
            AdminNotices::add('error', ['Bitte zwei verschiedene Bildungsgänge auswählen.']);
        } else {
            AdminNotices::add('success', array_merge(
                [sprintf('„%s“ wurde in „%s“ zusammengeführt, %d Termin(e) umgehängt.', $quelle->getKuerzel(), $ziel->getKuerzel(), $ergebnis['termine'])],
                $ergebnis['personen']
            ));
        }
        $this->zurueck(self::PAGE_BG);
    }

    // =====================================================================
    // Ereignistypen
    // =====================================================================

    public function renderEreignistypen(): void
    {
        $repo = $this->stammdaten->ereignistypen();
        $alle = $repo->getAll();
        $anzahl = $repo->countTermine();
        $autoId = (int)($_GET['edit'] ?? 0);

        echo '<div class="wrap"><h1 class="wp-heading-inline">Ereignistypen</h1> ';
        echo AdminModal::button('mh-tt-et-modal', '+ Neuer Ereignistyp', ['aktiv' => true, 'farbe' => '#cccccc'], 'Neuer Ereignistyp');
        if (count($alle) > 1) {
            echo ' ' . AdminModal::button('mh-tt-et-merge', 'Zusammenführen', [], 'Ereignistypen zusammenführen');
        }
        echo '<hr class="wp-header-end">';
        ?>
        <table class="widefat striped" style="max-width:1000px;">
            <thead><tr><th style="width:40px;">Farbe</th><th>Kürzel</th><th>Bezeichnung</th><th>Termine</th><th>Status</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($alle as $eintrag):
                $id = (int)$eintrag->getId();
                $n = $anzahl[$id] ?? 0;
                $werte = ['id' => $id, 'kuerzel' => $eintrag->getKuerzel(), 'bezeichnung' => $eintrag->getBezeichnung(), 'farbe' => $eintrag->getFarbe(), 'aktiv' => $eintrag->isAktiv()]; ?>
                <tr<?php echo $eintrag->isAktiv() ? '' : ' style="opacity:.6"'; ?>>
                    <td><span style="display:inline-block; width:24px; height:24px; border-radius:4px; background:<?php echo esc_attr($eintrag->getFarbe()); ?>"></span></td>
                    <td><strong><?php echo esc_html($eintrag->getKuerzel()); ?></strong></td>
                    <td><?php echo esc_html($eintrag->getBezeichnung()); ?></td>
                    <td><?php echo $n; ?></td>
                    <td><?php echo $eintrag->isAktiv() ? 'aktiv' : 'inaktiv'; ?></td>
                    <td><div class="mh-tt-aktionen">
                        <?php echo AdminModal::button('mh-tt-et-modal', 'Bearbeiten', $werte, 'Ereignistyp bearbeiten: ' . $eintrag->getKuerzel(), 'button button-small', $autoId === $id); ?>
                        <?php if ($n === 0): ?>
                            <a class="button button-small mh-tt-btn-loeschen" onclick="return confirm('Ereignistyp wirklich löschen? Fristen-Regeln mit diesem Typ werden ebenfalls gelöscht.')" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mh_tt_delete_ereignistyp&id=' . $id), 'mh_tt_delete_et_' . $id)); ?>">Löschen</a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        echo '</div>';

        echo AdminModal::start('mh-tt-et-modal', 'Ereignistyp', 'mh_tt_save_ereignistyp', 'mh_tt_ereignistyp', 'mh_tt_nonce');
        echo AdminModal::zeile('Kürzel', sprintf('<input type="text" id="et_kuerzel" name="kuerzel" maxlength="%d" required class="regular-text">', Schema::LEN_STAMM_KUERZEL), 'Kurzform für GANTT und Filter, z. B. „ZK“.', 'et_kuerzel');
        echo AdminModal::zeile('Bezeichnung', sprintf('<input type="text" id="et_bezeichnung" name="bezeichnung" maxlength="%d" class="regular-text">', Schema::LEN_STAMM_BEZEICHNUNG), 'Erklärung, z. B. „Zeugniskonferenz“ – erscheint in der Legende im Frontend.', 'et_bezeichnung');
        echo AdminModal::zeile('Farbe', '<input type="color" id="et_farbe" name="farbe" value="#cccccc">', 'für GANTT, Liste und Monatsansicht', 'et_farbe');
        echo AdminModal::zeile('Status', '<label><input type="checkbox" name="aktiv" value="1"> aktiv</label>');
        echo AdminModal::ende();

        $this->renderMergeForm('ereignistyp', 'mh-tt-et-merge', 'Ereignistypen zusammenführen', $alle, $anzahl,
            'Alle Termine der Quelle erhalten den Ziel-Typ, die Quelle wird gelöscht. Fristen-Regeln werden auf den Ziel-Typ umgestellt. Beispiel: „Zeugniskonferenz“ in „ZK“ zusammenführen.');
    }

    public function handleSaveEreignistyp(): void
    {
        $this->pruefen('mh_tt_ereignistyp');
        $repo = $this->stammdaten->ereignistypen();
        $id = (int)($_POST['id'] ?? 0);
        $kuerzel = sanitize_text_field(wp_unslash($_POST['kuerzel'] ?? ''));

        $fehler = $this->pruefeKuerzel($kuerzel, $repo->findByKuerzel($kuerzel), $id);
        $et = $id ? $repo->find($id) : new Ereignistyp($kuerzel);
        if (!$et) $fehler[] = 'Der Ereignistyp wurde nicht gefunden.';
        if ($fehler) {
            AdminNotices::add('error', $fehler);
            $this->zurueck(self::PAGE_ET, $id);
        }

        $et->setKuerzel($kuerzel);
        $et->setBezeichnung(mb_substr(sanitize_text_field(wp_unslash($_POST['bezeichnung'] ?? '')), 0, Schema::LEN_STAMM_BEZEICHNUNG));
        $et->setFarbe((string)sanitize_hex_color($_POST['farbe'] ?? ''));
        $et->setAktiv(!empty($_POST['aktiv']));

        if ($repo->save($et)) {
            AdminNotices::add('success', [sprintf('Ereignistyp „%s“ wurde gespeichert.', $kuerzel)]);
            $this->zurueck(self::PAGE_ET);
        }
        AdminNotices::add('error', ['Speichern fehlgeschlagen (Datenbankfehler).']);
        $this->zurueck(self::PAGE_ET, $id);
    }

    public function handleDeleteEreignistyp(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $this->pruefen('mh_tt_delete_et_' . $id, '_wpnonce');
        $repo = $this->stammdaten->ereignistypen();

        if (($repo->countTermine()[$id] ?? 0) > 0) {
            AdminNotices::add('error', ['Der Ereignistyp hat noch Termine. Bitte zusammenführen oder auf „inaktiv“ setzen.']);
        } elseif ($repo->delete($id)) {
            $regeln = $this->fristen->entferneEreignistyp($id);
            AdminNotices::add('success', array_filter([
                'Der Ereignistyp wurde gelöscht.',
                $regeln ? sprintf('%d Fristen-Regel(n) mit diesem Typ wurden ebenfalls gelöscht.', $regeln) : '',
            ]));
        }
        $this->zurueck(self::PAGE_ET);
    }

    public function handleMergeEreignistyp(): void
    {
        $this->pruefen('mh_tt_merge_ereignistyp');
        $repo = $this->stammdaten->ereignistypen();
        $quelle = $repo->find((int)($_POST['quelle'] ?? 0));
        $ziel = $repo->find((int)($_POST['ziel'] ?? 0));
        $anzahl = ($quelle && $ziel) ? $repo->zusammenfuehren((int)$quelle->getId(), (int)$ziel->getId()) : null;

        if ($anzahl === null) {
            AdminNotices::add('error', ['Bitte zwei verschiedene Ereignistypen auswählen.']);
        } else {
            $meldungen = $this->fristen->ersetzeEreignistyp((int)$quelle->getId(), (int)$ziel->getId());
            AdminNotices::add('success', array_merge(
                [sprintf('„%s“ wurde in „%s“ zusammengeführt, %d Termin(e) umgehängt.', $quelle->getKuerzel(), $ziel->getKuerzel(), $anzahl)],
                $meldungen
            ));
        }
        $this->zurueck(self::PAGE_ET);
    }

    // =====================================================================
    // Gemeinsames
    // =====================================================================

    /**
     * Dialog „Zusammenführen“ (Quelle → Ziel).
     * @param Bildungsgang[]|Ereignistyp[] $eintraege
     * @param array<int,int> $anzahl
     */
    private function renderMergeForm(string $art, string $modalId, string $titel, array $eintraege, array $anzahl, string $hinweis): void
    {
        if (count($eintraege) < 2) return;
        $optionen = '';
        foreach ($eintraege as $e) {
            $optionen .= sprintf('<option value="%d">%s (%d Termine)</option>', $e->getId(), esc_html($e->getKuerzel()), $anzahl[(int)$e->getId()] ?? 0);
        }
        echo AdminModal::start($modalId, $titel, 'mh_tt_merge_' . $art, 'mh_tt_merge_' . $art, 'mh_tt_nonce', false);
        echo AdminModal::zeile('Quelle', '<select name="quelle" required><option value="">– Quelle –</option>' . $optionen . '</select>', 'wird danach gelöscht');
        echo AdminModal::zeile('Ziel', '<select name="ziel" required><option value="">– Ziel –</option>' . $optionen . '</select>', 'bleibt erhalten');
        echo AdminModal::ende('Zusammenführen', '<p class="description" style="max-width:520px;">' . esc_html($hinweis) . '</p>');
    }

    /** @return string[] Fehlermeldungen */
    private function pruefeKuerzel(string $kuerzel, ?object $vorhanden, int $eigeneId): array
    {
        $fehler = [];
        if ($kuerzel === '') $fehler[] = 'Bitte ein Kürzel angeben.';
        if (mb_strlen($kuerzel) > Schema::LEN_STAMM_KUERZEL) $fehler[] = sprintf('Das Kürzel ist zu lang (höchstens %d Zeichen).', Schema::LEN_STAMM_KUERZEL);
        if ($vorhanden && (int)$vorhanden->getId() !== $eigeneId) {
            $fehler[] = sprintf('Das Kürzel „%s“ ist bereits vergeben. Zum Vereinen zweier Einträge bitte „Zusammenführen“ nutzen.', $kuerzel);
        }
        return $fehler;
    }

    private function pruefen(string $nonceAktion, string $nonceFeld = 'mh_tt_nonce'): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Sie haben keine Berechtigung für diese Aktion.', 'Keine Berechtigung', ['response' => 403]);
        }
        check_admin_referer($nonceAktion, $nonceFeld);
    }

    private function zurueck(string $page, int $editId = 0): void
    {
        $url = admin_url('admin.php?page=' . $page . ($editId ? '&edit=' . $editId : ''));
        wp_safe_redirect($url);
        exit;
    }

    /** @return \WP_User[] */
    private function getUsers(): array
    {
        return get_users(['orderby' => 'display_name', 'order' => 'ASC', 'fields' => ['ID', 'display_name']]);
    }

    private function gueltigeUserId(int $userId): ?int
    {
        return ($userId > 0 && get_userdata($userId)) ? $userId : null;
    }

    public function userName(?int $userId): string
    {
        if (!$userId) return '–';
        $user = get_userdata($userId);
        return $user ? $user->display_name : '#' . $userId;
    }
}
