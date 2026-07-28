<?php

namespace Intranet\Modules\Wiki\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Intranet\Modules\Wiki\Models\WikiAbschnitt;
use Intranet\Modules\Wiki\Models\WikiSeite;
use Intranet\Modules\Wiki\Models\WikiVersion;
use Intranet\Modules\Wiki\Support\HilfeUebernahme;
use Intranet\Modules\Wiki\Support\Rechte;

class WikiController
{
    /** Uebersicht der freien Beitraege, mit Volltextsuche ueber alles Sichtbare. */
    public function index(Request $request)
    {
        $user = $request->user();
        $rollen = Rechte::rollenIds($user);
        $darfBearbeiten = Rechte::darfBearbeiten($user);
        $suche = trim((string) $request->query('q', ''));

        if ($suche !== '') {
            return view('wiki::index', [
                'suche' => $suche,
                'treffer' => $this->suchen($suche, $rollen, $darfBearbeiten),
                'kategorien' => collect(),
                'darfBearbeiten' => $darfBearbeiten,
            ]);
        }

        $seiten = WikiSeite::query()
            ->beitraege()
            ->with(['abschnitte.rollen', 'autor'])
            ->orderBy('position')
            ->orderBy('titel')
            ->get()
            // Wer bearbeiten darf, braucht auch die Seiten in der Liste, von
            // denen er selbst keinen Abschnitt sehen wuerde - sonst koennte er
            // sie nie wieder aufrufen.
            ->filter(fn (WikiSeite $s) => $darfBearbeiten || $this->lesbar($s, $rollen));

        return view('wiki::index', [
            'suche' => '',
            'treffer' => collect(),
            'kategorien' => $seiten->groupBy(fn (WikiSeite $s) => $s->kategorie ?: 'Ohne Kategorie'),
            'darfBearbeiten' => $darfBearbeiten,
        ]);
    }

    public function show(Request $request, WikiSeite $seite)
    {
        $user = $request->user();
        $darfBearbeiten = Rechte::darfBearbeiten($user);

        $seite->load(['abschnitte.rollen', 'autor']);

        // Vorschau: "So sieht die Seite fuer einen Lehrer aus." Nur fuer
        // Bearbeiter - fuer alle anderen waere es eine Umgehung.
        $vorschauRolle = $darfBearbeiten ? $request->query('als') : null;

        $rollen = $vorschauRolle
            ? collect([$vorschauRolle])
            : Rechte::rollenIds($user);

        // Ein Bearbeiter sieht ohne Vorschau alles - versteckte Abschnitte
        // aber deutlich gekennzeichnet, damit ihm der Unterschied bewusst ist.
        $alleZeigen = $darfBearbeiten && $vorschauRolle === null;

        abort_unless($alleZeigen || $this->lesbar($seite, $rollen), 404);

        return view('wiki::seite', [
            'seite' => $seite,
            'rollen' => $rollen,
            'alleZeigen' => $alleZeigen,
            'vorschauRolle' => $vorschauRolle,
            'alleRollen' => $darfBearbeiten ? Role::orderBy('name')->get() : collect(),
            'darfBearbeiten' => $darfBearbeiten,
            'darfVerwalten' => Rechte::darfVerwalten($user),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(Rechte::darfBearbeiten($request->user()), 403);

        $seite = new WikiSeite(['art' => WikiSeite::ART_BEITRAG]);
        $seite->setRelation('abschnitte', collect([new WikiAbschnitt(['inhalt' => ''])]));

        return view('wiki::form', $this->formularDaten($request, $seite));
    }

    public function store(Request $request)
    {
        abort_unless(Rechte::darfBearbeiten($request->user()), 403);

        $daten = $this->pruefen($request);

        $seite = DB::transaction(function () use ($daten, $request) {
            $seite = WikiSeite::create([
                'titel' => $daten['titel'],
                'slug' => WikiSeite::freierSlug($daten['titel']),
                'kategorie' => $daten['kategorie'] ?? null,
                'art' => $daten['art'] ?? WikiSeite::ART_BEITRAG,
                'hilfe_fuer_route' => $daten['hilfe_fuer_route'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $this->abschnitteSetzen($seite, $daten['abschnitte']);

            return $seite;
        });

        WikiSeite::hilfeMemoLeeren();

        return redirect()
            ->route('module.wiki.show', $seite->slug)
            ->with('status', 'Seite wurde angelegt.');
    }

    public function edit(Request $request, WikiSeite $seite)
    {
        abort_unless(Rechte::darfBearbeiten($request->user()), 403);

        $seite->load('abschnitte.rollen');

        return view('wiki::form', $this->formularDaten($request, $seite));
    }

    public function update(Request $request, WikiSeite $seite)
    {
        abort_unless(Rechte::darfBearbeiten($request->user()), 403);

        $daten = $this->pruefen($request);

        DB::transaction(function () use ($seite, $daten, $request): void {
            // Erst den alten Stand wegsichern, dann ueberschreiben.
            WikiVersion::sichern($seite, $request->user());

            $seite->update([
                'titel' => $daten['titel'],
                'kategorie' => $daten['kategorie'] ?? null,
                // Art und Zielroute nur anfassen, wenn sie ueberhaupt
                // mitgeschickt werden durften. Ein Moderator sieht die Felder
                // gar nicht - sein Speichern darf die Kontexthilfe einer Seite
                // deshalb nicht stillschweigend abklemmen.
                'art' => array_key_exists('art', $daten) ? $daten['art'] : $seite->art,
                'hilfe_fuer_route' => array_key_exists('hilfe_fuer_route', $daten)
                    ? $daten['hilfe_fuer_route']
                    : $seite->hilfe_fuer_route,
                // Eine Datei-Hilfeseite gilt ab jetzt als angepasst und wird
                // vom naechsten Abgleich in Ruhe gelassen.
                'angepasst' => $seite->ausDatei() ? true : $seite->angepasst,
            ]);
            // Die Adresse (slug) bleibt bewusst, wie sie ist - sonst zeigen
            // verschickte Links ins Leere, nur weil jemand den Titel schaerft.

            $seite->abschnitte()->delete();
            $this->abschnitteSetzen($seite, $daten['abschnitte']);
        });

        WikiSeite::hilfeMemoLeeren();

        return redirect()
            ->route('module.wiki.show', $seite->slug)
            ->with('status', 'Seite wurde gespeichert.');
    }

    public function destroy(Request $request, WikiSeite $seite)
    {
        abort_unless(Rechte::darfVerwalten($request->user()), 403);

        $seite->delete();

        WikiSeite::hilfeMemoLeeren();

        return redirect()
            ->route($seite->istHilfe() ? 'module.wiki.hilfe' : 'module.wiki.index')
            ->with('status', 'Seite wurde geloescht.');
    }

    /** Eine Datei-Hilfeseite auf den Stand des Pakets zurueckholen. */
    public function zuruecksetzen(Request $request, WikiSeite $seite, HilfeUebernahme $uebernahme)
    {
        abort_unless(Rechte::darfVerwalten($request->user()), 403);

        if (! $uebernahme->einzeln($seite)) {
            return back()->with('error', 'Zu dieser Seite gibt es keine Paket-Datei (mehr) - es gibt also keinen Stand, auf den zurueckgesetzt werden koennte.');
        }

        return redirect()
            ->route('module.wiki.show', $seite->slug)
            ->with('status', 'Seite wurde auf den Stand des Pakets zurueckgesetzt.');
    }

    public function verlauf(Request $request, WikiSeite $seite)
    {
        abort_unless(Rechte::darfBearbeiten($request->user()), 403);

        return view('wiki::verlauf', [
            'seite' => $seite,
            'versionen' => $seite->versionen()->with('autor')->get(),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function formularDaten(Request $request, WikiSeite $seite): array
    {
        return [
            'seite' => $seite,
            'alleRollen' => Role::orderBy('name')->get(),
            'darfVerwalten' => Rechte::darfVerwalten($request->user()),
            'kategorien' => WikiSeite::query()
                ->whereNotNull('kategorie')
                ->distinct()
                ->orderBy('kategorie')
                ->pluck('kategorie'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pruefen(Request $request): array
    {
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'kategorie' => ['nullable', 'string', 'max:255'],
            'art' => ['nullable', Rule::in([WikiSeite::ART_BEITRAG, WikiSeite::ART_HILFE])],
            'hilfe_fuer_route' => ['nullable', 'string', 'max:255'],
            'abschnitte' => ['required', 'array', 'min:1'],
            'abschnitte.*.ueberschrift' => ['nullable', 'string', 'max:255'],
            'abschnitte.*.inhalt' => ['required', 'string'],
            'abschnitte.*.rollen' => ['nullable', 'array'],
            'abschnitte.*.rollen.*' => ['string', Rule::exists('roles', 'role_id')],
        ], [
            'abschnitte.required' => 'Eine Seite braucht mindestens einen Abschnitt.',
            'abschnitte.*.inhalt.required' => 'Ein Abschnitt ohne Text ergibt keinen Sinn - Text ergaenzen oder Abschnitt entfernen.',
        ]);

        // Art und Zielroute darf nur ein Wiki-Administrator setzen.
        if (! Rechte::darfVerwalten($request->user())) {
            unset($daten['art'], $daten['hilfe_fuer_route']);
        }

        return $daten;
    }

    /**
     * @param  array<int, array<string, mixed>>  $abschnitte
     */
    private function abschnitteSetzen(WikiSeite $seite, array $abschnitte): void
    {
        $position = 0;

        foreach ($abschnitte as $eingabe) {
            $rollen = array_values(array_filter($eingabe['rollen'] ?? []));

            $abschnitt = $seite->abschnitte()->create([
                'position' => $position++,
                'ueberschrift' => $eingabe['ueberschrift'] ?? null,
                'inhalt' => $eingabe['inhalt'],
                'rollen_gefordert' => $rollen !== [],
            ]);

            if ($rollen !== []) {
                $abschnitt->rollen()->sync($rollen);
            }
        }
    }

    /**
     * Eine Seite ist lesbar, sobald mindestens ein Abschnitt sichtbar ist.
     * Eine Seite, von der ein Benutzer keine Zeile sehen darf, existiert fuer
     * ihn nicht - sonst verriete schon der Titel etwas.
     *
     * @param  Collection<int, string>  $rollen
     */
    private function lesbar(WikiSeite $seite, Collection $rollen): bool
    {
        return $seite->abschnitte->contains(fn (WikiAbschnitt $a) => $a->sichtbarFuer($rollen));
    }

    /**
     * @param  Collection<int, string>  $rollen
     * @return Collection<int, array{seite: WikiSeite, abschnitt: ?WikiAbschnitt}>
     */
    private function suchen(string $suche, Collection $rollen, bool $alleSehen = false): Collection
    {
        $muster = '%'.str_replace(['%', '_'], ['\%', '\_'], $suche).'%';

        return WikiSeite::query()
            ->with(['abschnitte.rollen'])
            ->where(function ($q) use ($muster): void {
                $q->where('titel', 'like', $muster)
                    // Auch die Zwischenueberschriften durchsuchen: Ein Begriff
                    // steht oft nur dort ("Stundenlimit") und im Text darunter
                    // gar nicht mehr.
                    ->orWhereHas('abschnitte', fn ($a) => $a
                        ->where('inhalt', 'like', $muster)
                        ->orWhere('ueberschrift', 'like', $muster));
            })
            ->orderBy('titel')
            ->get()
            ->map(function (WikiSeite $seite) use ($rollen, $suche, $alleSehen) {
                $sichtbare = $alleSehen ? $seite->abschnitte : $seite->sichtbareAbschnitte($rollen);

                if ($sichtbare->isEmpty()) {
                    return null;
                }

                // Der erste sichtbare Abschnitt, der den Begriff enthaelt.
                $treffer = $sichtbare->first(
                    fn (WikiAbschnitt $a) => mb_stripos($a->inhalt, $suche) !== false
                        || mb_stripos((string) $a->ueberschrift, $suche) !== false
                );

                // Kein sichtbarer Abschnitt und auch der Titel passt nicht?
                // Dann hat nur ein gesperrter Absatz getroffen - und selbst die
                // Zeile "Treffer in dieser Seite" waere schon eine Auskunft
                // ueber Inhalte, die dieser Benutzer nicht sehen darf.
                if ($treffer === null && mb_stripos($seite->titel, $suche) === false) {
                    return null;
                }

                $treffer ??= $sichtbare->first();

                return ['seite' => $seite, 'abschnitt' => $treffer];
            })
            ->filter()
            ->values();
    }
}
