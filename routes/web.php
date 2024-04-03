<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;

Volt::route('/', 'home')->name('home');

// Insight routes
Route::prefix('insight')->group(function () {
    Route::name('insight.')->group(function () {
        Volt::route('/acm',     'insight.acm')                  ->name('acm');
        Volt::route('/rtc',     'insight.rtc')                  ->name('rtc');
        Volt::route('/ldc',     'insight.ldc')                  ->name('ldc');
        Route::view('/ss/{id}', 'livewire.insight.ss.index')    ->name('ss'); // slideshow
    });
    Route::view('/', 'insight')->name('insight');
});

Route::view('kpi', 'kpi')->name('kpi');
Route::view('profile', 'profile')->name('profile');
Route::view('help', 'help')->name('help');

// All routes that needs to be authenticated
Route::middleware('auth')->group(function () {

    // Account routes
    Route::prefix('account')->group(function () {
        Route::name('account.')->group(function () {
            Volt::route('/general',     'account.general')      ->name('general');
            Volt::route('/password',    'account.password')     ->name('password');
            Volt::route('/language',    'account.language')     ->name('language');
            Volt::route('/theme',       'account.theme')        ->name('theme');         
        });
        Route::view('/', 'account')->name('account');
    });

    // Inventory routes
    Route::prefix('inventory')->group(function () {

        Route::name('inventory.items.')->group(function () {
            Volt::route('/items/create',    'inventory.items.create')       ->name('create');
            Volt::route('/items/{id}',      'inventory.items.show')         ->name('show');
            Volt::route('/items/{id}/edit', 'inventory.items.edit')         ->name('edit');
            Volt::route('/items',           'inventory.items')              ->name('index');
        });

        Route::name('inventory.circs.')->group(function () {
            Volt::route('/circulations/create', 'inventory.circs.create')   ->name('create');
            Volt::route('/circulations/print',  'inventory.circs.print')    ->name('print');
            Volt::route('/circulations',        'inventory.circs')          ->name('index');
        });

        Route::name('inventory.manage.')->group(function () {
            Route::view('/manage/areas',            'inventory.manage.areas')               ->name('areas');
            Route::view('/manage/authorization',    'inventory.manage.authorization')       ->name('authorization');
            Route::view('/manage/currencies',       'inventory.manage.currencies')          ->name('currencies');
            Route::view('/manage/locations',        'inventory.manage.locations')           ->name('locations');
            Route::view('/manage/tags',             'inventory.manage.tags')                ->name('tags');
            Route::view('/manage/mass-circulation', 'inventory.manage.mass-circulation')    ->name('mass-circulation');
            Route::view('/manage/mass-edit',        'inventory.manage.mass-edit')           ->name('mass-edit');
            Route::view('/manage',                  'inventory.manage')                     ->name('index');
        });

        Route::view('/', 'inventory')->name('inventory');

    });

});


require __DIR__.'/auth.php';
