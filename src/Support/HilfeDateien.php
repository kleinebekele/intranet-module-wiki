<?php

namespace Intranet\Modules\Wiki\Support;

use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;

/**
 * Findet und liest die Hilfetexte, die Pakete mitbringen.
 *
 * Warum Dateien und nicht einfach Datenbank-Zeilen: Ein Hilfetext gehört zu
 * einer Programmversion, nicht zu einer Instanz. Als Datei liegt er im selben
 * Repository wie die Funktion, die er erklärt – er wird mit ihr getaggt, mit
 * ihr deployt und muss nicht auf jedem Server einzeln gepflegt werden.
 *
 * Erwarteter Ort:  <paket>/resources/hilfe/*.md   (im Core: resources/hilfe/)
 *
 * Aufbau einer Datei:
 *
 *     ---
 *     titel: Mailvorlagen bearbeiten
 *     route: admin.mail-vorlagen.index
 *     kategorie: Verwaltung
 *     position: 10
 *     ---
 *
 *     Einleitender Text ohne Überschrift (optional).
 *
 *     ## Vorschau und Testmail
 *     rollen: admin, wiki-admin
 *
 *     Markdown-Text des Abschnitts ...
 *
 * `route` verknüpft die Seite mit dem "?"-Knopf der Kopfzeile.
 * `rollen` direkt unter einer Überschrift begrenzt DIESEN Abschnitt.
 */
class HilfeDateien
{
    public function __construct(private ModuleRegistry $registry) {}

    /**
     * Alle gefundenen Hilfedateien, gelesen und zerlegt.
     *
     * @return array<int, array{quelle: string, datei: string, pfad: string, titel: string, route: ?string, kategorie: ?string, position: int, abschnitte: array}>
     */
    public function alle(): array
    {
        $gefunden = [];

        foreach ($this->verzeichnisse() as $quelle => $verzeichnis) {
            foreach ($this->dateienIn($verzeichnis) as $pfad) {
                $seite = $this->lesen($pfad);
                $seite['quelle'] = $quelle;
                $seite['datei'] = basename($pfad);
                $seite['pfad'] = $pfad;

                $gefunden[] = $seite;
            }
        }

        return $gefunden;
    }

    /**
     * Wo darf gesucht werden: die Anwendung selbst und jedes angemeldete Modul.
     *
     * @return array<string, string>  Quelle => Verzeichnis
     */
    public function verzeichnisse(): array
    {
        $verzeichnisse = ['core' => base_path('resources/hilfe')];

        $this->registry->manifests()->each(function (ModuleManifest $manifest) use (&$verzeichnisse): void {
            if ($manifest->basePath) {
                $verzeichnisse['modul:'.$manifest->key] = $manifest->basePath.'/resources/hilfe';
            }
        });

        return array_filter($verzeichnisse, 'is_dir');
    }

    /** @return string[] */
    private function dateienIn(string $verzeichnis): array
    {
        $dateien = glob($verzeichnis.'/*.md') ?: [];
        sort($dateien);

        return $dateien;
    }

    /**
     * Eine Datei in Kopfdaten und Abschnitte zerlegen.
     *
     * @return array{titel: string, route: ?string, kategorie: ?string, position: int, abschnitte: array}
     */
    public function lesen(string $pfad): array
    {
        $roh = file_get_contents($pfad) ?: '';
        $roh = preg_replace('/^\xEF\xBB\xBF/', '', $roh); // BOM
        $roh = str_replace("\r\n", "\n", $roh);

        [$kopf, $rumpf] = $this->kopfTrennen($roh);

        return [
            'titel' => $kopf['titel'] ?? pathinfo($pfad, PATHINFO_FILENAME),
            'route' => $kopf['route'] ?? null,
            'kategorie' => $kopf['kategorie'] ?? null,
            'position' => (int) ($kopf['position'] ?? 0),
            'abschnitte' => $this->abschnitteTrennen($rumpf),
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function kopfTrennen(string $roh): array
    {
        if (! preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $roh, $treffer)) {
            return [[], $roh];
        }

        $kopf = [];

        foreach (explode("\n", $treffer[1]) as $zeile) {
            if (str_contains($zeile, ':')) {
                [$schluessel, $wert] = explode(':', $zeile, 2);
                $kopf[trim(strtolower($schluessel))] = trim($wert);
            }
        }

        return [$kopf, $treffer[2]];
    }

    /**
     * Am "## " aufteilen. Text vor der ersten Überschrift wird ein Abschnitt
     * ohne Überschrift (die Einleitung).
     *
     * @return array<int, array{ueberschrift: ?string, inhalt: string, rollen: string[]}>
     */
    private function abschnitteTrennen(string $rumpf): array
    {
        $abschnitte = [];
        $ueberschrift = null;
        $puffer = [];

        // Zeile fuer Zeile statt in einem Rutsch per Regex: Eine "## "-Zeile
        // INNERHALB eines Code-Blocks ist keine Ueberschrift, sondern Beispiel-
        // Text. Genau daran zerfiel die Anleitung, die Markdown erklaert.
        $imCodeblock = false;

        foreach (explode("\n", $rumpf) as $zeile) {
            if (preg_match('/^\s*(```|~~~)/', $zeile)) {
                $imCodeblock = ! $imCodeblock;
            }

            if (! $imCodeblock && preg_match('/^## +(.*)$/', $zeile, $treffer)) {
                if ($abschnitt = $this->abschnittBauen($ueberschrift, implode("\n", $puffer))) {
                    $abschnitte[] = $abschnitt;
                }

                $ueberschrift = trim($treffer[1]);
                $puffer = [];

                continue;
            }

            $puffer[] = $zeile;
        }

        if ($abschnitt = $this->abschnittBauen($ueberschrift, implode("\n", $puffer))) {
            $abschnitte[] = $abschnitt;
        }

        return $abschnitte;
    }

    /**
     * @return array{ueberschrift: ?string, inhalt: string, rollen: string[]}|null
     */
    private function abschnittBauen(?string $ueberschrift, string $text): ?array
    {
        $rollen = [];
        $zeilen = explode("\n", ltrim($text, "\n"));

        // Eine führende Zeile "rollen: a, b" ist die Tag-Angabe und gehört
        // nicht zum Text.
        if (isset($zeilen[0]) && preg_match('/^(rollen|tags) *:(.*)$/i', $zeilen[0], $treffer)) {
            $rollen = collect(explode(',', $treffer[2]))
                ->map(fn (string $r) => trim($r))
                ->filter()
                ->unique()
                ->values()
                ->all();

            array_shift($zeilen);
        }

        $inhalt = trim(implode("\n", $zeilen));

        if ($inhalt === '' && $ueberschrift === null) {
            return null;
        }

        return [
            'ueberschrift' => $ueberschrift,
            'inhalt' => $inhalt,
            'rollen' => $rollen,
        ];
    }
}
