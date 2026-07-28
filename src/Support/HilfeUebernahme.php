<?php

namespace Intranet\Modules\Wiki\Support;

use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Wiki\Models\WikiSeite;

/**
 * Schreibt Hilfetexte aus Paket-Dateien in die Datenbank.
 *
 * Zwei Aufrufer teilen sich das: der Konsolenbefehl `wiki:hilfe-sync` (beim
 * Deploy) und der Knopf "Auf Paket-Stand zuruecksetzen" im Backend.
 */
class HilfeUebernahme
{
    /** @var array<string, string[]>  Rolle => Dateien, in denen sie vorkam */
    private array $unbekannteRollen = [];

    public function __construct(private HilfeDateien $dateien) {}

    /**
     * Alle gefundenen Dateien uebernehmen.
     *
     * @return array{zeilen: array<int, array{0: string, 1: string, 2: string}>, unbekannteRollen: array<string, string[]>, verwaiste: Collection<int, WikiSeite>}
     */
    public function alles(bool $erzwingen = false, bool $nurPruefen = false): array
    {
        $this->unbekannteRollen = [];

        $vorhandene = $this->vorhandeneRollen();
        $zeilen = [];
        $gesehen = [];

        foreach ($this->dateien->alle() as $datei) {
            $seite = $this->seiteZu($datei['quelle'], $datei['datei']);
            $gesehen[] = $datei['quelle'].'/'.$datei['datei'];

            if ($seite && $seite->angepasst && ! $erzwingen) {
                $zeilen[] = [$datei['quelle'], $datei['datei'], 'uebersprungen (angepasst)'];

                continue;
            }

            $this->rollenPruefen($datei, $vorhandene);

            if (! $nurPruefen) {
                $this->schreiben($seite, $datei, $vorhandene);
            }

            $zeilen[] = [$datei['quelle'], $datei['datei'], $seite ? 'aktualisiert' : 'angelegt'];
        }

        return [
            'zeilen' => $zeilen,
            'unbekannteRollen' => $this->unbekannteRollen,
            'verwaiste' => $this->verwaiste($gesehen),
        ];
    }

    /**
     * Eine einzelne Seite auf den Stand ihrer Datei zurueckholen.
     * Liefert false, wenn es die Datei nicht (mehr) gibt.
     */
    public function einzeln(WikiSeite $seite): bool
    {
        if (! $seite->ausDatei()) {
            return false;
        }

        foreach ($this->dateien->alle() as $datei) {
            if ($datei['quelle'] === $seite->hilfe_quelle && $datei['datei'] === $seite->hilfe_datei) {
                $this->schreiben($seite, $datei, $this->vorhandeneRollen());

                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, string> */
    private function vorhandeneRollen(): Collection
    {
        return Role::query()->pluck('role_id');
    }

    private function seiteZu(string $quelle, string $datei): ?WikiSeite
    {
        return WikiSeite::query()
            ->where('hilfe_quelle', $quelle)
            ->where('hilfe_datei', $datei)
            ->first();
    }

    /** @param  Collection<int, string>  $vorhandene */
    private function rollenPruefen(array $datei, Collection $vorhandene): void
    {
        foreach ($datei['abschnitte'] as $abschnitt) {
            foreach ($abschnitt['rollen'] as $rolle) {
                if (! $vorhandene->contains($rolle)) {
                    $this->unbekannteRollen[$rolle][] = $datei['datei'];
                }
            }
        }
    }

    /** @param  Collection<int, string>  $vorhandene */
    private function schreiben(?WikiSeite $seite, array $datei, Collection $vorhandene): void
    {
        DB::transaction(function () use ($seite, $datei, $vorhandene): void {
            $werte = [
                'titel' => $datei['titel'],
                'kategorie' => $datei['kategorie'],
                'art' => WikiSeite::ART_HILFE,
                'hilfe_fuer_route' => $datei['route'],
                'hilfe_quelle' => $datei['quelle'],
                'hilfe_datei' => $datei['datei'],
                'position' => $datei['position'],
                'angepasst' => false,
            ];

            if ($seite) {
                $seite->update($werte);
            } else {
                $seite = WikiSeite::create($werte + ['slug' => WikiSeite::freierSlug($datei['titel'])]);
            }

            // Die Abschnitte kommen vollstaendig aus der Datei. Sie neu zu
            // setzen ist ehrlicher als zeilenweise abzugleichen - sonst
            // ueberlebt ein geloeschter Absatz die naechste Version.
            $seite->abschnitte()->delete();

            foreach ($datei['abschnitte'] as $position => $abschnitt) {
                $neu = $seite->abschnitte()->create([
                    'position' => $position,
                    'ueberschrift' => $abschnitt['ueberschrift'],
                    'inhalt' => $abschnitt['inhalt'],
                    'rollen_gefordert' => $abschnitt['rollen'] !== [],
                ]);

                $bekannte = array_values(array_intersect($abschnitt['rollen'], $vorhandene->all()));

                if ($bekannte !== []) {
                    $neu->rollen()->sync($bekannte);
                }
            }
        });
    }

    /**
     * Seiten, deren Datei verschwunden ist. Nur melden - geloescht wird nichts
     * ungefragt, es koennte inzwischen jemand daran gearbeitet haben.
     *
     * @param  string[]  $gesehen
     * @return Collection<int, WikiSeite>
     */
    private function verwaiste(array $gesehen): Collection
    {
        return WikiSeite::query()
            ->whereNotNull('hilfe_quelle')
            ->get()
            ->reject(fn (WikiSeite $s) => in_array($s->hilfe_quelle.'/'.$s->hilfe_datei, $gesehen, true))
            ->values();
    }
}
