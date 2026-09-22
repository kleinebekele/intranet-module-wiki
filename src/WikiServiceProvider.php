<?php

namespace Intranet\Modules\Wiki;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
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
            ->item('create', 'Beitrag anlegen', 'module.wiki.create', icon: 'plus');
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
