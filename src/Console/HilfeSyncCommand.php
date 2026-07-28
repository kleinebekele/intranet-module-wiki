<?php

namespace Intranet\Modules\Wiki\Console;

use Illuminate\Console\Command;
use Intranet\Modules\Wiki\Models\WikiSeite;
use Intranet\Modules\Wiki\Support\HilfeDateien;
use Intranet\Modules\Wiki\Support\HilfeUebernahme;

/**
 * Uebernimmt die Hilfetexte aus den Paketen in die Datenbank.
 *
 * Das Vorbild sind die Mailvorlagen: Der Standard liegt im Paket, gespeichert
 * wird in der Instanz nur, was davon abweicht. Wer eine Hilfeseite im Backend
 * bearbeitet, setzt damit `angepasst` - und der naechste Abgleich fasst sie
 * nicht mehr an. Ohne diese Regel wuerde das naechste Deploy die Arbeit eines
 * Kollegen stillschweigend ueberschreiben.
 *
 * Gehoert in den Deploy-Ablauf, direkt hinter `modules:sync`.
 */
class HilfeSyncCommand extends Command
{
    protected $signature = 'wiki:hilfe-sync
                            {--erzwingen : Auch angepasste Seiten auf den Paket-Stand zuruecksetzen}
                            {--pruefen : Nur zeigen, was passieren wuerde}';

    protected $description = 'Hilfetexte aus den Paketen (resources/hilfe/*.md) ins Wiki uebernehmen';

    public function handle(HilfeDateien $dateien, HilfeUebernahme $uebernahme): int
    {
        $verzeichnisse = $dateien->verzeichnisse();

        if ($verzeichnisse === []) {
            $this->warn('Keine Hilfe-Verzeichnisse gefunden (erwartet: resources/hilfe/ im Core oder in einem Modul).');

            return self::SUCCESS;
        }

        $this->line('Durchsucht: '.implode(', ', array_keys($verzeichnisse)));

        $ergebnis = $uebernahme->alles(
            erzwingen: (bool) $this->option('erzwingen'),
            nurPruefen: (bool) $this->option('pruefen'),
        );

        $this->table(
            ['Quelle', 'Datei', 'Ergebnis'],
            $ergebnis['zeilen'] ?: [['-', '-', 'nichts gefunden']],
        );

        foreach ($ergebnis['verwaiste'] as $seite) {
            /** @var WikiSeite $seite */
            $this->warn(sprintf(
                'Zur Seite "%s" gibt es keine Datei mehr (%s). Sie bleibt stehen - bei Bedarf im Backend loeschen.',
                $seite->titel,
                $seite->hilfe_quelle.'/'.$seite->hilfe_datei,
            ));
        }

        foreach ($ergebnis['unbekannteRollen'] as $rolle => $dateiNamen) {
            $this->warn(sprintf(
                'Rolle "%s" gibt es in dieser Instanz nicht (%s). Die betroffenen Abschnitte bleiben vorsichtshalber verborgen.',
                $rolle,
                implode(', ', array_unique($dateiNamen)),
            ));
        }

        if ($this->option('pruefen')) {
            $this->info('Probelauf - es wurde nichts geschrieben.');
        }

        return self::SUCCESS;
    }
}
