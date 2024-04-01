<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvItemController;
use App\Http\Controllers\PreferencesController;

Volt::route('/', 'home')->name('home');

// Insight routes
Route::prefix('insight')->group(function () {

    Route::view('/', 'insight')->name('insight');

});

// All routes that needs to be authenticated
Route::middleware('auth')->group(function () {

    // Preferences routes
    Route::prefix('preferences')->group(function () {

        Route::controller(PreferencesController::class)->name('preferences.')->group(function () {
            Route::patch('/language', 'languageUpdate')->name('language.update');
            Route::patch('/theme', 'themeUpdate')->name('theme.update');            
        });

        Route::view('/', 'preferences')->name('preferences');
    });

    // Inventory routes
    Route::prefix('inventory')->group(function () {

        Route::controller(InvItemController::class)->name('inventory.items.')->group(function () {
            Route::get('/items/create', 'create')->name('create');
            Route::get('/items/{id}', 'show')->name('show');
            Route::get('/items/{id}/edit', 'edit')->name('edit');
            Route::patch('/items', 'update')->name('update');
        });

        Route::name('inventory.')->group(function () {
            Route::view('/search', 'inventory.search')->name('inventory');
            Route::view('/circulations', 'inventory.circulations')->name('circulations');
            Route::view('/manage', 'inventory.manage')->name('manage');
        });

        Route::view('/', 'inventory')->name('inventory');

    });

    Route::view('kpi', 'kpi')->name('kpi');
    Route::view('profile', 'profile')->name('profile');
    Route::view('help', 'help')->name('help');
});


require __DIR__.'/auth.php';
