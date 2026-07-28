<?php

namespace Intranet\Modules\Wiki\Http\Controllers;

use App\Modules\Support\ModuleRegistry;
use Illuminate\Http\Request;
use Intranet\Modules\Wiki\Models\WikiAbschnitt;
use Intranet\Modules\Wiki\Models\WikiSeite;
use Intranet\Modules\Wiki\Support\Rechte;

/**
 * Die Hilfe-Uebersicht: alle HowTos, gebuendelt nach Bereich.
 *
 * Ein Benutzer sieht nur, wovon er mindestens einen Abschnitt lesen darf -
 * eine Anleitung, von der fuer ihn nichts uebrig bleibt, waere ein toter Link.
 */
class HilfeController
{
    public function index(Request $request, ModuleRegistry $registry)
    {
        $user = $request->user();
        $rollen = Rechte::rollenIds($user);
        $darfBearbeiten = Rechte::darfBearbeiten($user);

        $seiten = WikiSeite::query()
            ->hilfe()
            ->with('abschnitte.rollen')
            ->orderBy('position')
            ->orderBy('titel')
            ->get()
            ->filter(fn (WikiSeite $s) => $darfBearbeiten
                || $s->abschnitte->contains(fn (WikiAbschnitt $a) => $a->sichtbarFuer($rollen)));

        return view('wiki::hilfe', [
            'bereiche' => $seiten->groupBy(fn (WikiSeite $s) => $s->kategorie ?: $this->bereichAusQuelle($s, $registry)),
            'darfBearbeiten' => $darfBearbeiten,
        ]);
    }

    /** Ohne eigene Kategorie einsortieren nach Herkunft: Core oder Modulname. */
    private function bereichAusQuelle(WikiSeite $seite, ModuleRegistry $registry): string
    {
        if ($seite->hilfe_quelle === null || $seite->hilfe_quelle === 'core') {
            return 'Grundlagen';
        }

        $key = str_replace('modul:', '', $seite->hilfe_quelle);

        return $registry->manifest($key)?->name ?? ucfirst($key);
    }
}
