<?php

use App\Http\Controllers\DownloadController;
use Livewire\Volt\Volt;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\InsRtcMetricResource;
use App\Http\Resources\InsRtcRecipeResource;

Volt::route('/', 'home')->name('home');

// Insight routes
Route::prefix('insight')->group(function () {

    Route::name('insight.')->group(function () {

        Volt::route('/ss/{id}', 'insight.ss.index')->name('ss'); // slideshow
        Volt::route('/acm',     'insight.acm.index')->name('acm.index');
        Volt::route('/ldc',     'insight.ldc.index')->name('ldc.index');

    });

    Route::name('insight.rtc.')->group(function () {

        Volt::route('/rtc/manage/authorizations',   'insight.rtc.manage.auths')     ->name('manage.auths');
        Volt::route('/rtc/manage/devices',          'insight.rtc.manage.devices')   ->name('manage.devices');
        Volt::route('/rtc/manage/recipes',          'insight.rtc.manage.recipes')   ->name('manage.recipes');
        Volt::route('/rtc/manage',                  'insight.rtc.manage.index')     ->name('manage.index');
        Volt::route('/rtc/slideshows',              'insight.rtc.slideshows')       ->name('slideshows');
        Volt::route('/rtc',                         'insight.rtc.index')            ->name('index');

        Route::get('/rtc/metric/{device_id}', function (string $device_id) {
            $metric = InsRtcMetric::join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
                ->where('ins_rtc_clumps.ins_rtc_device_id', $device_id)
                ->latest('dt_client')
                ->first();
            return $metric ? new InsRtcMetricResource($metric) : abort(404);
        })->name('metric');

        Route::get('/rtc/recipe/{recipe_id}', function (string $recipe_id) {
            return new InsRtcRecipeResource(InsRtcRecipe::findOrFail($recipe_id));
        })->name('recipe');

    });

    Route::name('insight.ldc.')->group(function () {

        Volt::route('/ldc/hides',   'insight.ldc.hides')        ->name('hides');
        Volt::route('/ldc',         'insight.ldc.index')        ->name('index');

        // Volt::route('/rtc/manage/authorizations',   'insight.rtc.manage.auths')     ->name('manage.auths');
        // Volt::route('/rtc/manage/devices',          'insight.rtc.manage.devices')   ->name('manage.devices');
        // Volt::route('/rtc/manage/recipes',          'insight.rtc.manage.recipes')   ->name('manage.recipes');
        // Volt::route('/rtc/manage',                  'insight.rtc.manage.index')     ->name('manage.index');
        // Volt::route('/rtc/slideshows',              'insight.rtc.slideshows')       ->name('slideshows');
        // Volt::route('/rtc',                         'insight.rtc.index')            ->name('index');

        // Route::get('/rtc/metric/{device_id}', function (string $device_id) {
        //     $metric = InsRtcMetric::join('ins_rtc_clumps', 'ins_rtc_clumps.id', '=', 'ins_rtc_metrics.ins_rtc_clump_id')
        //         ->where('ins_rtc_clumps.ins_rtc_device_id', $device_id)
        //         ->latest('dt_client')
        //         ->first();
        //     return $metric ? new InsRtcMetricResource($metric) : abort(404);
        // })->name('metric');

        // Route::get('/rtc/recipe/{recipe_id}', function (string $recipe_id) {
        //     return new InsRtcRecipeResource(InsRtcRecipe::findOrFail($recipe_id));
        // })->name('recipe');

    });

    Route::view('/', 'livewire.insight.index')->name('insight');
});

// Route::view('kpi', 'kpi')->name('kpi');
Route::view('profile', 'profile')->name('profile');
Route::view('help', 'help')->name('help');

// DOWNLOAD download
Route::name('download.')->group(function () {

    Route::get('/download/ins-rtc-metrics', [DownloadController::class, 'insRtcMetrics'])->name('ins-rtc-metrics');
    Route::get('/download/ins-rtc-clumps', [DownloadController::class, 'insRtcClumps'])->name('ins-rtc-clumps');
    Route::get('/download/ins-ldc-hides', [DownloadController::class, 'insLdcHides'])->name('ins-ldc-hides');

});

// All routes that needs to be authenticated
Route::middleware('auth')->group(function () {

    // Account routes
    Route::prefix('account')->group(function () {

        Route::name('account.')->group(function () {

            Volt::route('/general',     'account.general')      ->name('general');
            Volt::route('/password',    'account.password')     ->name('password');
            Volt::route('/language',    'account.language')     ->name('language');
            Volt::route('/theme',       'account.theme')        ->name('theme');
            Volt::route('/edit',        'account.edit')         ->name('edit');

        });

        Volt::route('/', 'account.index')->name('account');

    });

    // Inventory routes
    Route::prefix('inventory')->group(function () {

        Route::name('inventory.items.')->group(function () {

            Volt::route('/items/create',    'inventory.items.create')   ->name('create');
            Volt::route('/items/{id}',      'inventory.items.show')     ->name('show');
            Volt::route('/items/{id}/edit', 'inventory.items.edit')     ->name('edit');
            Volt::route('/items',           'inventory.items.index')    ->name('index');

        });

        Route::name('inventory.circs.')->group(function () {

            Volt::route('/circs/create',    'inventory.circs.create')   ->name('create');
            Volt::route('/circs/print',     'inventory.circs.print')    ->name('print');
            Volt::route('/circs',           'inventory.circs.index')    ->name('index');

        });

        Route::name('inventory.admin.')->group(function () {

            Volt::route('/admin/areas',             'inventory.admin.areas')        ->name('areas');
            Volt::route('/admin/authorizations',    'inventory.admin.auths')        ->name('auths');
            Volt::route('/admin/currencies',        'inventory.admin.currs')        ->name('currs');
            Volt::route('/admin/locations',         'inventory.admin.locs')         ->name('locs');
            Volt::route('/admin/tags',              'inventory.admin.tags')         ->name('tags');
            Volt::route('/admin/uoms',              'inventory.admin.uoms')         ->name('uoms');
            Volt::route('/admin/circs-create',      'inventory.admin.circs-create') ->name('circs-create');
            Volt::route('/admin/items-update',      'inventory.admin.items-update') ->name('items-update');
            Volt::route('/admin',                   'inventory.admin.index')        ->name('index');
            
        });

        Route::view('/', 'inventory')->name('inventory');

    });

});


require __DIR__.'/auth.php';
