<?php

namespace Intranet\Modules\Wiki\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Intranet\Modules\Wiki\Models\WikiAbschnitt;
use Intranet\Modules\Wiki\Models\WikiSeite;

/**
 * Das Wiki als Wissensquelle für die KI im Teams-Chat (Core:
 * App\Ekkon\Support\Wissensquellen).
 *
 * Stichwortsuche über Titel, Zwischenüberschriften und Absätze: Die Frage
 * wird in Wörter zerlegt, jeder Abschnitt zählt, wie viele davon er trifft;
 * die besten Abschnitte gehen als Markdown mit Herkunft („Wiki › Seite ›
 * Überschrift") zurück.
 *
 * ⚠️ Sichtbarkeit wie in der Wiki-Oberfläche: Die Rollen der fragenden Person
 * entscheiden (`Rechte::rollenIds`). Ohne Intranet-Konto gibt es nur, was
 * ohne Rollen-Tag für alle gedacht ist – ein gesperrter Absatz darf auch über
 * den Umweg KI nicht nach außen.
 */
final class WikiWissen
{
    /** Wörter kürzer als das tragen nichts zur Suche bei („der", „und", „wie"). */
    private const MIN_WORT = 4;

    private const MAX_WOERTER = 8;

    private const MAX_ZEICHEN_JE_ABSCHNITT = 2500;

    /**
     * @return array<int, array{quelle: string, text: string}>
     */
    public static function suchen(string $frage, ?User $benutzer, int $limit = 6): array
    {
        $woerter = self::woerter($frage);
        if ($woerter === []) {
            return [];
        }

        $rollen = Rechte::rollenIds($benutzer);

        $seiten = WikiSeite::query()
            ->with(['abschnitte.rollen'])
            ->where(function ($q) use ($woerter): void {
                foreach ($woerter as $w) {
                    $muster = '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%';
                    $q->orWhere('titel', 'like', $muster)
                        ->orWhereHas('abschnitte', fn ($a) => $a->where('inhalt', 'like', $muster)->orWhere('ueberschrift', 'like', $muster));
                }
            })
            ->get();

        $kandidaten = [];
        foreach ($seiten as $seite) {
            $titelTreffer = self::treffer($seite->titel, $woerter);
            foreach ($seite->sichtbareAbschnitte($rollen) as $abschnitt) {
                /** @var WikiAbschnitt $abschnitt */
                $punkte = self::treffer((string) $abschnitt->ueberschrift.' '.$abschnitt->inhalt, $woerter) * 2 + $titelTreffer;
                if ($punkte === 0) {
                    continue;
                }
                $kandidaten[] = ['punkte' => $punkte, 'seite' => $seite, 'abschnitt' => $abschnitt];
            }
        }

        usort($kandidaten, fn ($a, $b) => $b['punkte'] <=> $a['punkte']);

        $ergebnis = [];
        foreach (array_slice($kandidaten, 0, max(1, $limit)) as $k) {
            $text = trim((string) $k['abschnitt']->inhalt);
            if (mb_strlen($text) > self::MAX_ZEICHEN_JE_ABSCHNITT) {
                $text = mb_substr($text, 0, self::MAX_ZEICHEN_JE_ABSCHNITT).' …';
            }
            $quelle = 'Wiki › '.$k['seite']->titel.(filled($k['abschnitt']->ueberschrift) ? ' › '.$k['abschnitt']->ueberschrift : '');
            $ergebnis[] = ['quelle' => $quelle, 'text' => $text];
        }

        return $ergebnis;
    }

    /**
     * Die tragenden Wörter der Frage: Kleinbuchstaben, ohne Satzzeichen, ohne
     * Kurzwörter, doppelte raus, höchstens MAX_WOERTER (die längsten zuerst –
     * Fachbegriffe sind meist lang).
     *
     * @return array<int, string>
     */
    private static function woerter(string $frage): array
    {
        $roh = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($frage)) ?: [];
        $woerter = array_values(array_unique(array_filter($roh, fn (string $w) => mb_strlen($w) >= self::MIN_WORT)));
        usort($woerter, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($woerter, 0, self::MAX_WOERTER);
    }

    /** @param  array<int, string>  $woerter */
    private static function treffer(string $text, array $woerter): int
    {
        $text = mb_strtolower($text);
        $n = 0;
        foreach ($woerter as $w) {
            if (mb_strpos($text, $w) !== false) {
                $n++;
            }
        }

        return $n;
    }
}
