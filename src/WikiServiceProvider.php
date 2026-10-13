<?php

namespace Intranet\Modules\Wiki;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use App\Modules\Support\Zugriffsstufe;
use App\Support\Hilfe;
use Intranet\Modules\Wiki\Console\HilfeSyncCommand;
use Intranet\Modules\Wiki\Models\WikiSeite;
use Throwable;

/**
 * Anmelde-Klasse des Wiki-Moduls.
 *
 * Zwei Dinge ueber das Uebliche hinaus:
 *  - der Konsolenbefehl `wiki:hilfe-sync`
 *  - die Antwort auf die Hilfe-Frage des Cores (der "?"-Knopf in der Kopfzeile)
 */
class WikiServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('wiki', 'Wiki', icon: 'book')
            ->rolle('wiki-admin', 'Wiki-Administrator')
            ->rolle('wiki-moderator', 'Wiki-Moderator')
            ->item('index', 'Uebersicht', 'module.wiki.index', icon: 'book')
            ->item('hilfe', 'Hilfe und HowTos', 'module.wiki.hilfe', icon: 'help')
            ->item('create', 'Beitrag anlegen', 'module.wiki.create', icon: 'plus')
            // Die Schreibrouten haben keinen eigenen Menuepunkt. Ohne Zuordnung
            // bekaemen sie im Core nur die niedrigste Stufe des Benutzers im
            // Modul - sie haengen deshalb an "Beitrag anlegen", dem Punkt der
            // Redaktion. Die Wiki-Rollen prueft der Controller weiterhin selbst.
            // `show` bleibt frei: Lesen reicht, auch fuer reine Hilfe-Leser.
            ->gehoertZu('create', 'store', 'edit', 'update', 'destroy', 'verlauf', 'zuruecksetzen')
            // Anlegen und Bearbeiten sind im Wiki dieselbe Befugnis (Rechte::darfBearbeiten):
            // Speichern eines neuen Beitrags reicht mit "bearbeiten" - bestehende
            // Zuordnungen muessen dafuer nicht auf "verwalten" hoch. Loeschen (DELETE)
            // bleibt bei verwalten; Zuruecksetzen ist ein POST und wird eigens
            // auf verwalten gehoben (passt zu Rechte::darfVerwalten).
            ->stufe(Zugriffsstufe::Bearbeiten, 'create', 'store')
            ->stufe(Zugriffsstufe::Verwalten, 'zuruecksetzen');
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([HilfeSyncCommand::class]);
        }

        // Der Core fragt bei jedem Seitenaufruf, ob es zur aktuellen Route
        // eine Hilfe gibt. Steht die Tabelle noch nicht (frisch installiert,
        // migrate kommt erst), darf das die Seite nicht mitreissen - dann
        // fehlt eben nur der "?"-Knopf.
        Hilfe::anbieten(function (string $routeName, ?User $user): ?string {
            try {
                return WikiSeite::urlFuerRoute($routeName, $user);
            } catch (Throwable) {
                return null;
            }
        });

        // Das Wiki als Wissensquelle der KI im Teams-Chat (Core ab 22.09.2026).
        // Aeltere Cores kennen die Klasse nicht - dann eben keine Quelle.
        if (class_exists(\App\Ekkon\Support\Wissensquellen::class)) {
            \App\Ekkon\Support\Wissensquellen::anmelden(
                'Wiki',
                fn (string $frage, ?User $benutzer, int $limit): array => \Intranet\Modules\Wiki\Support\WikiWissen::suchen($frage, $benutzer, $limit),
            );
        }
    }
}
