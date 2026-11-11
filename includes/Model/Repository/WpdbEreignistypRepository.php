<?php
declare(strict_types=1);

namespace MH\Timetable\Model\Repository;

use MH\Timetable\Model\Entity\Ereignistyp;
use wpdb;

class WpdbEreignistypRepository
{
    private wpdb $wpdb;
    private string $table;
    private string $termineTable;

    /** @var array<int, Ereignistyp>|null Request-Cache */
    private ?array $cache = null;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tt_ereignistyp';
        $this->termineTable = $wpdb->prefix . 'tt_termine';
    }

    /** @return Ereignistyp[] aktive zuerst, dann nach Kürzel */
    public function getAll(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            $rows = $this->wpdb->get_results("SELECT * FROM {$this->table} ORDER BY aktiv DESC, kuerzel", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $et = new Ereignistyp((string)$row['kuerzel'], (string)$row['bezeichnung'], (string)$row['farbe'], (bool)$row['aktiv']);
                $et->setId((int)$row['id']);
                $this->cache[(int)$row['id']] = $et;
            }
        }
        return array_values($this->cache);
    }

    public function find(int $id): ?Ereignistyp
    {
        $this->getAll();
        return $this->cache[$id] ?? null;
    }

    public function findByKuerzel(string $kuerzel): ?Ereignistyp
    {
        foreach ($this->getAll() as $et) {
            if (mb_strtolower($et->getKuerzel()) === mb_strtolower($kuerzel)) return $et;
        }
        return null;
    }

    public function save(Ereignistyp $et): bool
    {
        $daten = [
            'kuerzel'     => $et->getKuerzel(),
            'bezeichnung' => $et->getBezeichnung(),
            'farbe'       => $et->getFarbe(),
            'aktiv'       => $et->isAktiv() ? 1 : 0,
        ];
        $formate = ['%s', '%s', '%s', '%d'];

        if ($et->getId()) {
            $ok = false !== $this->wpdb->update($this->table, $daten, ['id' => $et->getId()], $formate, ['%d']);
            $this->wpdb->update($this->termineTable, ['ereignistyp' => $et->getKuerzel()], ['ereignistyp_id' => $et->getId()]);
        } else {
            $ok = (bool)$this->wpdb->insert($this->table, $daten, $formate);
            if ($ok) $et->setId((int)$this->wpdb->insert_id);
        }
        $this->cache = null;
        return $ok;
    }

    public function delete(int $id): bool
    {
        $ok = false !== $this->wpdb->delete($this->table, ['id' => $id], ['%d']);
        $this->cache = null;
        return $ok;
    }

    /** @return array<int,int> Ereignistyp-ID → Anzahl Termine */
    public function countTermine(): array
    {
        $rows = $this->wpdb->get_results("SELECT ereignistyp_id, COUNT(*) n FROM {$this->termineTable} WHERE ereignistyp_id IS NOT NULL GROUP BY ereignistyp_id", ARRAY_A) ?: [];
        return array_column(array_map(fn($r) => [(int)$r['ereignistyp_id'], (int)$r['n']], $rows), 1, 0);
    }

    /**
     * Hängt alle Termine von $quelle an $ziel um und löscht $quelle.
     * (Fristen-Regeln passt der FristenService an.)
     * @return int|null Anzahl umgehängter Termine, null bei Fehler
     */
    public function zusammenfuehren(int $quelleId, int $zielId): ?int
    {
        $ziel = $this->find($zielId);
        if (!$ziel || !$this->find($quelleId) || $quelleId === $zielId) return null;

        $anzahl = (int)$this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->termineTable} SET ereignistyp_id = %d, ereignistyp = %s WHERE ereignistyp_id = %d",
            $zielId, $ziel->getKuerzel(), $quelleId
        ));
        $this->delete($quelleId);
        return $anzahl;
    }
}
