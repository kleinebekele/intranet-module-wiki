<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Wiki besteht aus Seiten, und eine Seite besteht aus Abschnitten.
 *
 * Die Aufteilung ist kein Selbstzweck: Nur weil ein Abschnitt eine eigene
 * Zeile ist, kann er eigene Rollen tragen – und nur dann verlässt ein für
 * andere Rollen gedachter Text den Server gar nicht erst. Steckten die
 * Abschnitte in einem einzigen HTML-Feld, wäre jede Filterung reine Optik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_seiten', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->string('slug')->unique();
            $table->string('kategorie')->nullable();

            // 'beitrag' = selbst verfasst, 'hilfe' = HowTo (kommt in der Regel
            // aus einer Datei des zugehörigen Pakets, siehe wiki:hilfe-sync).
            $table->string('art', 16)->default('beitrag')->index();

            // Route, zu der diese Seite die Kontexthilfe ist (der "?"-Knopf in
            // der Kopfzeile). Null = Hilfe ohne feste Seite bzw. freier Beitrag.
            $table->string('hilfe_fuer_route')->nullable()->index();

            // Herkunft einer Datei-Hilfeseite: Paketname + Dateiname. Daran
            // erkennt der Abgleich seine eigenen Seiten wieder.
            $table->string('hilfe_quelle')->nullable();
            $table->string('hilfe_datei')->nullable();

            // Wurde eine Datei-Hilfeseite hier im Backend bearbeitet? Dann
            // fasst der Abgleich sie nicht mehr an (Muster der Mailvorlagen:
            // der Standard liegt im Paket, die Abweichung in der Instanz).
            $table->boolean('angepasst')->default(false);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['hilfe_quelle', 'hilfe_datei']);
        });

        Schema::create('wiki_abschnitte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->string('ueberschrift')->nullable();
            $table->text('inhalt'); // Markdown-Quelle, gerendert wird erst beim Anzeigen

            // Hatte dieser Abschnitt Rollen-Tags? Wichtig für Hilfetexte aus
            // Dateien: Nennt eine Datei eine Rolle, die es in DIESER Instanz
            // nicht gibt, bliebe die Tag-Liste leer – und ein leerer Tag
            // bedeutet sonst "für alle sichtbar". Genau das darf nicht
            // passieren, sonst rutscht ein Lehrer-Absatz in einer Firma an
            // alle durch. Mit dem Merker bleibt er stattdessen verborgen.
            $table->boolean('rollen_gefordert')->default(false);

            $table->timestamps();

            $table->index(['wiki_seite_id', 'position']);
        });

        // Die Tags: welche Rollen dürfen diesen Abschnitt sehen?
        // Keine Zeile + rollen_gefordert = false -> alle.
        Schema::create('wiki_abschnitt_rolle', function (Blueprint $table) {
            $table->foreignId('wiki_abschnitt_id')->constrained('wiki_abschnitte')->cascadeOnDelete();
            $table->string('role_id', 64);
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
            $table->primary(['wiki_abschnitt_id', 'role_id'], 'wiki_abschnitt_rolle_primary');
        });

        // Änderungsverlauf: bei jedem Speichern wandert der VORHERIGE Stand
        // hierher. Ein Wiki ohne "wer hat das wann geändert" ist keins.
        Schema::create('wiki_versionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_seite_id')->constrained('wiki_seiten')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titel');
            $table->json('abschnitte');
            $table->timestamp('created_at')->nullable();

            $table->index(['wiki_seite_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_versionen');
        Schema::dropIfExists('wiki_abschnitt_rolle');
        Schema::dropIfExists('wiki_abschnitte');
        Schema::dropIfExists('wiki_seiten');
    }
};
