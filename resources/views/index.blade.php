<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Wiki</h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" action="{{ route('module.wiki.index') }}" class="flex items-center gap-2 flex-1 min-w-[16rem]">
                        <x-text-input name="q" :value="$suche" placeholder="Im Wiki suchen ..." class="block w-full" />
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <x-module-icon name="search" class="text-base" />
                            Suchen
                        </button>
                        @if ($suche !== '')
                            <a href="{{ route('module.wiki.index') }}" class="text-sm text-gray-500 hover:text-gray-700">zurücksetzen</a>
                        @endif
                    </form>

                    <a href="{{ route('module.wiki.hilfe') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <x-module-icon name="help" class="text-base" />
                        Hilfe und HowTos
                    </a>

                    @if ($darfBearbeiten)
                        <a href="{{ route('module.wiki.create') }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            <x-module-icon name="plus" class="text-base" />
                            Beitrag anlegen
                        </a>
                    @endif
                </div>
            </div>

            @if ($suche !== '')
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50 text-sm text-gray-600">
                        {{ $treffer->count() }} Treffer für: {{ $suche }}
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($treffer as $eintrag)
                            <li class="px-4 sm:px-6 py-4">
                                <a href="{{ route('module.wiki.show', $eintrag['seite']->slug) }}"
                                   class="font-medium text-indigo-700 hover:underline">
                                    {{ $eintrag['seite']->titel }}
                                </a>
                                @if ($eintrag['seite']->istHilfe())
                                    <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Hilfe</span>
                                @endif
                                @if ($eintrag['abschnitt'])
                                    <p class="mt-1 text-sm text-gray-600">{{ $eintrag['abschnitt']->auszug() }}</p>
                                @endif
                            </li>
                        @empty
                            <li class="px-4 sm:px-6 py-8 text-center text-sm text-gray-500">
                                Nichts gefunden. Vielleicht steht es unter einem anderen Begriff im Wiki.
                            </li>
                        @endforelse
                    </ul>
                </div>
            @else
                @forelse ($kategorien as $kategorie => $seiten)
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                            <x-module-icon name="folder" class="text-base text-gray-400" />
                            <h3 class="text-sm font-semibold text-gray-700">{{ $kategorie }}</h3>
                            <span class="text-xs text-gray-400">{{ $seiten->count() }}</span>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($seiten as $seite)
                                <li class="px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-2">
                                    <a href="{{ route('module.wiki.show', $seite->slug) }}"
                                       class="font-medium text-indigo-700 hover:underline">
                                        {{ $seite->titel }}
                                    </a>
                                    <span class="text-xs text-gray-400">
                                        @if ($seite->autor)
                                            {{ $seite->autor->name }} ·
                                        @endif
                                        {{ $seite->updated_at?->format('d.m.Y') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-sm text-gray-500">
                        Noch keine Beiträge.
                        @if ($darfBearbeiten)
                            <a href="{{ route('module.wiki.create') }}" class="text-indigo-700 hover:underline">Den ersten anlegen.</a>
                        @endif
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</x-app-layout>
