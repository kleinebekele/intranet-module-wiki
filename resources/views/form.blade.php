@php
    $neu = ! $seite->exists;

    // Nach einem Validierungsfehler zaehlt die Eingabe, sonst der gespeicherte
    // Stand - und wenn beides fehlt, ein leerer Abschnitt zum Loslegen.
    $startAbschnitte = collect(old('abschnitte'))
        ->map(fn ($a) => [
            'ueberschrift' => $a['ueberschrift'] ?? '',
            'inhalt' => $a['inhalt'] ?? '',
            'rollen' => array_values($a['rollen'] ?? []),
        ])
        ->values()
        ->all();

    if ($startAbschnitte === []) {
        $startAbschnitte = $seite->abschnitte
            ->map(fn ($a) => [
                'ueberschrift' => $a->ueberschrift ?? '',
                'inhalt' => $a->inhalt ?? '',
                'rollen' => $a->relationLoaded('rollen') ? $a->rollen->pluck('role_id')->all() : [],
            ])
            ->values()
            ->all();
    }

    if ($startAbschnitte === []) {
        $startAbschnitte = [['ueberschrift' => '', 'inhalt' => '', 'rollen' => []]];
    }

    $rollenListe = $alleRollen->map(fn ($r) => ['id' => $r->role_id, 'name' => $r->name])->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Wiki · {{ $neu ? 'Beitrag anlegen' : 'Bearbeiten: '.$seite->titel }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $fehler)
                            <li>{{ $fehler }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $neu && $seite->ausDatei() && ! $seite->angepasst)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
                    Dieser Hilfetext stammt aus dem Paket <strong>{{ $seite->hilfe_quelle }}</strong>.
                    Sobald Sie hier speichern, gilt er als angepasst und wird von künftigen
                    Aktualisierungen des Pakets nicht mehr überschrieben.
                </div>
            @endif

            <form method="POST"
                  action="{{ $neu ? route('module.wiki.store') : route('module.wiki.update', $seite->slug) }}"
                  x-data="{
                      abschnitte: {{ Js::from($startAbschnitte) }}.map((a, i) => ({ ...a, _id: i })),
                      rollen: {{ Js::from($rollenListe) }},
                      naechsteId: {{ count($startAbschnitte) }},
                      hinzufuegen() {
                          this.abschnitte.push({ ueberschrift: '', inhalt: '', rollen: [], _id: this.naechsteId++ });
                      },
                      entfernen(index) {
                          if (this.abschnitte.length > 1) { this.abschnitte.splice(index, 1); }
                      },
                      umschalten(abschnitt, rolleId, an) {
                          const stelle = abschnitt.rollen.indexOf(rolleId);
                          if (an && stelle === -1) { abschnitt.rollen.push(rolleId); }
                          if (! an && stelle !== -1) { abschnitt.rollen.splice(stelle, 1); }
                      },
                      schieben(index, richtung) {
                          const ziel = index + richtung;
                          if (ziel < 0 || ziel >= this.abschnitte.length) { return; }
                          const [element] = this.abschnitte.splice(index, 1);
                          this.abschnitte.splice(ziel, 0, element);
                      },
                  }"
                  class="space-y-6">
                @csrf
                @unless ($neu)
                    @method('PUT')
                @endunless

                {{-- Kopfdaten --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="titel" value="Titel" />
                            <x-text-input id="titel" name="titel" class="block w-full mt-1"
                                          :value="old('titel', $seite->titel)" required />
                        </div>

                        <div>
                            <x-input-label for="kategorie" value="Kategorie (frei wählbar)" />
                            <x-text-input id="kategorie" name="kategorie" class="block w-full mt-1"
                                          list="wiki-kategorien"
                                          :value="old('kategorie', $seite->kategorie)" />
                            <datalist id="wiki-kategorien">
                                @foreach ($kategorien as $kategorie)
                                    <option value="{{ $kategorie }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    @if ($darfVerwalten)
                        <div class="grid gap-4 md:grid-cols-2 border-t border-gray-100 pt-4">
                            <div>
                                <x-input-label for="art" value="Art" />
                                <select id="art" name="art"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="beitrag" @selected(old('art', $seite->art) === 'beitrag')>Beitrag</option>
                                    <option value="hilfe" @selected(old('art', $seite->art) === 'hilfe')>Hilfetext</option>
                                </select>
                            </div>

                            <div>
                                <x-input-label for="hilfe_fuer_route" value="Kontexthilfe für Route (optional)" />
                                <x-text-input id="hilfe_fuer_route" name="hilfe_fuer_route" class="block w-full mt-1"
                                              placeholder="z. B. admin.mail-vorlagen.index"
                                              :value="old('hilfe_fuer_route', $seite->hilfe_fuer_route)" />
                                <p class="mt-1 text-xs text-gray-500">
                                    Ist hier ein Routenname eingetragen, erscheint auf genau dieser Seite
                                    der Fragezeichen-Knopf in der Kopfzeile und führt hierher.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Abschnitte --}}
                <div class="space-y-4">
                    <template x-for="(abschnitt, index) in abschnitte" :key="abschnitt._id">
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                    Abschnitt <span x-text="index + 1"></span>
                                </span>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="schieben(index, -1)" title="Nach oben"
                                            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                        <x-module-icon name="back" class="text-base rotate-90" />
                                    </button>
                                    <button type="button" @click="schieben(index, 1)" title="Nach unten"
                                            class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                        <x-module-icon name="back" class="text-base -rotate-90" />
                                    </button>
                                    <button type="button" @click="entfernen(index)" title="Abschnitt entfernen"
                                            x-bind:disabled="abschnitte.length === 1"
                                            class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30 disabled:hover:bg-transparent">
                                        <x-module-icon name="trash" class="text-base" />
                                    </button>
                                </div>
                            </div>

                            <input type="text" x-model="abschnitt.ueberschrift"
                                   x-bind:name="'abschnitte[' + index + '][ueberschrift]'"
                                   placeholder="Überschrift (optional)"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium">

                            <textarea x-model="abschnitt.inhalt" rows="6"
                                      x-bind:name="'abschnitte[' + index + '][inhalt]'"
                                      placeholder="Text des Abschnitts – Markdown ist erlaubt (## Überschrift, **fett**, - Liste, [Link](adresse))"
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"></textarea>

                            <div class="border-t border-gray-100 pt-3">
                                <div class="flex items-center gap-1.5 mb-2">
                                    <x-module-icon name="tag" class="text-base text-gray-400" />
                                    <span class="text-xs font-medium text-gray-600">Sichtbar für</span>
                                    <span class="text-xs text-gray-400"
                                          x-show="abschnitt.rollen.length === 0">– keine Auswahl bedeutet: für alle</span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-2">
                                    {{-- Bewusst ohne x-model: Bei einer Mehrfachauswahl mit
                                         gebundenem value haengt das Ergebnis davon ab, in welcher
                                         Reihenfolge Alpine value und Modell auswertet. Mit
                                         :checked und einem eigenen Umschalter ist es eindeutig. --}}
                                    <template x-for="rolle in rollen" :key="rolle.id">
                                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                            <input type="checkbox"
                                                   x-bind:name="'abschnitte[' + index + '][rollen][]'"
                                                   x-bind:value="rolle.id"
                                                   x-bind:checked="abschnitt.rollen.includes(rolle.id)"
                                                   @change="umschalten(abschnitt, rolle.id, $event.target.checked)"
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <span x-text="rolle.name"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="hinzufuegen()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-400 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        <x-module-icon name="plus" class="text-base" />
                        Abschnitt hinzufügen
                    </button>

                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <x-module-icon name="save" class="text-base" />
                        Speichern
                    </button>

                    <a href="{{ $neu ? route('module.wiki.index') : route('module.wiki.show', $seite->slug) }}"
                       class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
