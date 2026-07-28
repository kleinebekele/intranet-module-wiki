<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Wiki · Verlauf: {{ $seite->titel }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 text-sm text-gray-500">
                Jeder Eintrag zeigt den Stand, der beim Speichern <em>ersetzt</em> wurde –
                der jüngste Eintrag ist also die Fassung direkt vor der aktuellen.
            </div>

            @forelse ($versionen as $version)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
                     x-data="{ offen: false }">
                    <button type="button" @click="offen = ! offen"
                            class="w-full px-4 sm:px-6 py-3 flex items-center justify-between gap-3 text-left hover:bg-gray-50">
                        <span class="text-sm text-gray-700">
                            <strong>{{ $version->created_at?->format('d.m.Y H:i') }}</strong>
                            @if ($version->autor)
                                · ersetzt durch {{ $version->autor->name }}
                            @endif
                        </span>
                        <span class="text-xs text-gray-400">
                            {{ count($version->abschnitte) }} Abschnitte ·
                            <span x-text="offen ? 'zuklappen' : 'ansehen'"></span>
                        </span>
                    </button>

                    <div x-show="offen" x-cloak class="border-t border-gray-100 divide-y divide-gray-100">
                        <div class="px-4 sm:px-6 py-2 text-xs text-gray-500">
                            Titel damals: {{ $version->titel }}
                        </div>
                        @foreach ($version->abschnitte as $abschnitt)
                            <div class="px-4 sm:px-6 py-4">
                                @if (! empty($abschnitt['ueberschrift']))
                                    <h4 class="text-sm font-semibold text-gray-800">{{ $abschnitt['ueberschrift'] }}</h4>
                                @endif
                                @if (! empty($abschnitt['rollen']))
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($abschnitt['rollen'] as $rolle)
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                                {{ $rolle }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <pre class="mt-2 whitespace-pre-wrap text-sm text-gray-600 font-sans">{{ $abschnitt['inhalt'] }}</pre>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-sm text-gray-500">
                    Diese Seite wurde seit dem Anlegen nicht geändert.
                </div>
            @endforelse

            <div>
                <a href="{{ route('module.wiki.show', $seite->slug) }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="back" class="text-base" />
                    Zurück zur Seite
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
