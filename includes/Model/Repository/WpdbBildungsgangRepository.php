<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Bildungsgang;
use wpdb;

/**
 * Bildungsgänge samt Zuordnung von Abteilungsleitung (AL) und Bildungsgangassistenzen (BGA).
 */
class WpdbBildungsgangRepository
{
    private wpdb $wpdb;
    private string $table;
    private string $personTable;
    private string $termineTable;

    /** @var array<int, Bildungsgang>|null Request-Cache */
    private ?array $cache = null;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tt_bildungsgang';
        $this->personTable = $wpdb->prefix . 'tt_bildungsgang_person';
        $this->termineTable = $wpdb->prefix . 'tt_termine';
    }

    /** @return Bildungsgang[] aktive zuerst, dann nach Kürzel */
    public function getAll(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            $rows = $this->wpdb->get_results("SELECT * FROM {$this->table} ORDER BY aktiv DESC, kuerzel", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $bg = new Bildungsgang((string)$row['kuerzel'], (string)$row['bezeichnung'], (bool)$row['aktiv']);
                $bg->setId((int)$row['id']);
                $this->cache[(int)$row['id']] = $bg;
            }
            $bga = [];
            foreach ($this->wpdb->get_results("SELECT * FROM {$this->personTable}", ARRAY_A) ?: [] as $p) {
                $bg = $this->cache[(int)$p['bildungsgang_id']] ?? null;
                if (!$bg) continue;
                if ($p['rolle'] === Bildungsgang::ROLLE_AL) {
                    $bg->setAlUserId((int)$p['user_id']);
                } else {
                    $bga[(int)$p['bildungsgang_id']][] = (int)$p['user_id'];
                }
            }
            foreach ($bga as $id => $userIds) {
                $this->cache[$id]->setBgaUserIds($userIds);
            }
        }
        return array_values($this->cache);
    }

    public function find(int $id): ?Bildungsgang
    {
        $this->getAll();
        return $this->cache[$id] ?? null;
    }

    public function findByKuerzel(string $kuerzel): ?Bildungsgang
    {
        foreach ($this->getAll() as $bg) {
            if (mb_strtolower($bg->getKuerzel()) === mb_strtolower($kuerzel)) return $bg;
        }
        return null;
    }

    /** Legt an oder aktualisiert (inkl. AL/BGA). */
    public function save(Bildungsgang $bg): bool
    {
        $daten = ['kuerzel' => $bg->getKuerzel(), 'bezeichnung' => $bg->getBezeichnung(), 'aktiv' => $bg->isAktiv() ? 1 : 0];

        if ($bg->getId()) {
            $ok = false !== $this->wpdb->update($this->table, $daten, ['id' => $bg->getId()], ['%s', '%s', '%d'], ['%d']);
            // Namens-Fallback in den Terminen aktuell halten
            $this->wpdb->update($this->termineTable, ['bildungsgang' => $bg->getKuerzel()], ['bildungsgang_id' => $bg->getId()]);
        } else {
            $ok = (bool)$this->wpdb->insert($this->table, $daten, ['%s', '%s', '%d']);
            if ($ok) $bg->setId((int)$this->wpdb->insert_id);
        }
        if (!$ok) return false;

        $id = (int)$bg->getId();
        $this->wpdb->delete($this->personTable, ['bildungsgang_id' => $id], ['%d']);
        if ($bg->getAlUserId()) {
            $this->wpdb->insert($this->personTable, ['bildungsgang_id' => $id, 'user_id' => $bg->getAlUserId(), 'rolle' => Bildungsgang::ROLLE_AL]);
        }
        foreach ($bg->getBgaUserIds() as $userId) {
            if ($userId === $bg->getAlUserId()) continue; // AL hat die Rechte ohnehin
            $this->wpdb->insert($this->personTable, ['bildungsgang_id' => $id, 'user_id' => $userId, 'rolle' => Bildungsgang::ROLLE_BGA]);
        }

        $this->cache = null;
        return true;
    }

    public function delete(int $id): bool
    {
        $this->wpdb->delete($this->personTable, ['bildungsgang_id' => $id], ['%d']);
        $ok = false !== $this->wpdb->delete($this->table, ['id' => $id], ['%d']);
        $this->cache = null;
        return $ok;
    }

    /** @return array<int,int> Bildungsgang-ID → Anzahl Termine */
    public function countTermine(): array
    {
        $rows = $this->wpdb->get_results("SELECT bildungsgang_id, COUNT(*) n FROM {$this->termineTable} WHERE bildungsgang_id IS NOT NULL GROUP BY bildungsgang_id", ARRAY_A) ?: [];
        return array_column(array_map(fn($r) => [(int)$r['bildungsgang_id'], (int)$r['n']], $rows), 1, 0);
    }

    /**
     * Bildungsgänge, in denen die Person AL oder BGA ist.
     * @return array<int,string> Bildungsgang-ID → Rolle
     */
    public function getRollenFuerUser(int $userId): array
    {
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT bildungsgang_id, rolle FROM {$this->personTable} WHERE user_id = %d",
            $userId
        ), ARRAY_A) ?: [];
        return array_column(array_map(fn($r) => [(int)$r['bildungsgang_id'], (string)$r['rolle']], $rows), 1, 0);
    }

    /**
     * Führt $quelle in $ziel zusammen: Termine wandern zum Ziel, Personen der
     * Quelle werden beim Ziel ergänzt (AL nur, wenn das Ziel keine AL hat,
     * sonst als BGA – so behalten alle ihre bisherigen Bearbeitungsrechte).
     * Danach wird die Quelle gelöscht.
     *
     * @return array{termine:int, personen:string[]}|null null bei Fehler
     */
    public function zusammenfuehren(int $quelleId, int $zielId): ?array
    {
        $quelle = $this->find($quelleId);
        $ziel = $this->find($zielId);
        if (!$quelle || !$ziel || $quelleId === $zielId) return null;

        $termine = (int)$this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->termineTable} SET bildungsgang_id = %d, bildungsgang = %s WHERE bildungsgang_id = %d",
            $zielId, $ziel->getKuerzel(), $quelleId
        ));

        $personen = [];
        if ($quelle->getAlUserId()) {
            if (!$ziel->getAlUserId()) {
                $ziel->setAlUserId($quelle->getAlUserId());
                $personen[] = sprintf('%s wird Abteilungsleitung von %s', self::userName($quelle->getAlUserId()), $ziel->getKuerzel());
            } elseif ($ziel->getAlUserId() !== $quelle->getAlUserId()) {
                $ziel->setBgaUserIds(array_merge($ziel->getBgaUserIds(), [$quelle->getAlUserId()]));
                $personen[] = sprintf('%s (bisher AL von %s) wird Bildungsgangassistenz von %s', self::userName($quelle->getAlUserId()), $quelle->getKuerzel(), $ziel->getKuerzel());
            }
        }
        foreach ($quelle->getBgaUserIds() as $userId) {
            if (!$ziel->darfBearbeiten($userId)) {
                $personen[] = sprintf('%s wird Bildungsgangassistenz von %s', self::userName($userId), $ziel->getKuerzel());
            }
        }
        $ziel->setBgaUserIds(array_merge($ziel->getBgaUserIds(), $quelle->getBgaUserIds()));

        $this->save($ziel);
        $this->delete($quelleId);

        return ['termine' => $termine, 'personen' => $personen];
    }

    private static function userName(int $userId): string
    {
        $user = get_userdata($userId);
        return $user ? $user->display_name : '#' . $userId;
    }
}
