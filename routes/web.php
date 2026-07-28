<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Wiki\Http\Controllers\HilfeController;
use Intranet\Modules\Wiki\Http\Controllers\WikiController;

/*
 | Routen des Wiki-Moduls (Konvention: Praefix modules/wiki, Namen module.wiki.*).
 |
 | Achtung auf die Reihenfolge: /{seite:slug} faengt am Ende alles ab, was
 | vorher nicht getroffen hat - feste Pfade muessen also davor stehen.
 |
 | Die schreibenden Routen haben absichtlich KEINEN eigenen Menuepunkt (ausser
 | "anlegen"). Fuer sie greift im Core die milde Auffangregel, deshalb prueft
 | der Controller die Wiki-Rollen zusaetzlich selbst.
*/
Route::middleware(['web', 'auth'])
    ->prefix('modules/wiki')
    ->name('module.wiki.')
    ->group(function () {
        Route::get('/', [WikiController::class, 'index'])->name('index');
        Route::get('/hilfe', [HilfeController::class, 'index'])->name('hilfe');
        Route::get('/anlegen', [WikiController::class, 'create'])->name('create');
        Route::post('/', [WikiController::class, 'store'])->name('store');

        Route::get('/{seite:slug}', [WikiController::class, 'show'])->name('show');
        Route::get('/{seite:slug}/bearbeiten', [WikiController::class, 'edit'])->name('edit');
        Route::put('/{seite:slug}', [WikiController::class, 'update'])->name('update');
        Route::delete('/{seite:slug}', [WikiController::class, 'destroy'])->name('destroy');
        Route::get('/{seite:slug}/verlauf', [WikiController::class, 'verlauf'])->name('verlauf');
        Route::post('/{seite:slug}/zuruecksetzen', [WikiController::class, 'zuruecksetzen'])->name('zuruecksetzen');
    });
