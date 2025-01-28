<?php

use App\Http\Controllers\DownloadController;
use Livewire\Volt\Volt;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Illuminate\Support\Facades\Route;
use App\Http\Resources\InsRtcMetricResource;
use App\Http\Resources\InsRtcRecipeResource;

Volt::route('/', 'home')->name('home');
Volt::route('/latihan',     'latihan-test');
Volt::route('/inventory',   'inventory.index')->name('inventory');
Volt::route('/machines',        'machines.index')->name('machines');
Volt::route('/projects',     'projects.index')->name('projects');
// Route::view('kpi', 'kpi')->name('kpi');
// Route::view('profile', 'profile')->name('profile');
// Route::view('help', 'help')->name('help');

// Insight routes
Route::prefix('insight')->group(function () {

    Route::name('insight.')->group(function () {

        Volt::route('/ss/{id}', 'insight.ss.index')->name('ss'); // slideshow
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

        Volt::route('/ldc/manage/authorizations',   'insight.ldc.manage.auths') ->name('manage.auths');
        Volt::route('/ldc/manage/machines',         'insight.ldc.manage.machines') ->name('manage.machines');
        Volt::route('/ldc/manage',                  'insight.ldc.manage.index') ->name('manage.index');
        Volt::route('/ldc/data',                    'insight.ldc.data.index')->name('data.index');
        Volt::route('/ldc/create',                  'insight.ldc.create.index')->name('create.index');
        Route::get('/ldc', function () {
            if (auth()->check()) {
                return redirect()->route('insight.ldc.create.index');
            }
            return redirect()->route('insight.ldc.data.index');
        })->name('index');
    });

    Route::name('insight.omv.')->group(function () {

        Volt::route('/omv/manage/authorizations',   'insight.omv.manage.auths')     ->name('manage.auths');
        Volt::route('/omv/manage/recipes',          'insight.omv.manage.recipes')   ->name('manage.recipes');
        Volt::route('/omv/manage',                  'insight.omv.manage.index')     ->name('manage.index');
        Volt::route('/omv/data',                    'insight.omv.data.index')       ->name('data.index');
        Volt::route('/omv/create',                  'insight.omv.create.index')     ->name('create.index');
        Route::get('/omv', function () {
            if (auth()->check()) {
                return redirect()->route('insight.omv.create.index');
            }
            return redirect()->route('insight.omv.data.index');
        })->name('index');
    });

    Route::name('insight.rdc.')->group(function () {

        Volt::route('/rdc/manage/authorizations',   'insight.rdc.manage.auths')     ->name('manage.auths');
        Volt::route('/rdc/manage/machines',         'insight.rdc.manage.machines')  ->name('manage.machines');
        Volt::route('/rdc/manage',                  'insight.rdc.manage.index')     ->name('manage.index');
        Volt::route('/rdc/data',                    'insight.rdc.data.index')       ->name('data.index');
        Volt::route('/rdc/queue',                   'insight.rdc.queue.index')      ->name('queue.index');
        Route::get('/rdc', function () {
            if (auth()->check()) {
                return redirect()->route('insight.rdc.queue.index');
            }
            return redirect()->route('insight.rdc.data.index');
        })->name('index');

    });

    Route::name('insight.stc.')->group(function () {

        Volt::route('/stc/manage/authorizations',   'insight.stc.manage.auths')     ->name('manage.auths');
        Volt::route('/stc/manage/machines',         'insight.stc.manage.machines')  ->name('manage.machines');
        Volt::route('/stc/manage/devices',          'insight.stc.manage.devices')   ->name('manage.devices');
        Volt::route('/stc/manage',                  'insight.stc.manage.index')     ->name('manage.index');
        Volt::route('/stc/data',                    'insight.stc.data.index')       ->name('data.index');
        Volt::route('/stc/create',                  'insight.stc.create.index')     ->name('create.index');
        Route::get('/stc', function () {
            if (auth()->check()) {
                return redirect()->route('insight.stc.create.index');
            }
            return redirect()->route('insight.stc.data.index');
        })->name('index');

    });

    Route::name('insight.erd.')->group(function () {

        Volt::route('/erd/manage/authorizations',   'insight.erd.manage.auths')     ->name('manage.auths');
        Volt::route('/erd/manage/machines',         'insight.erd.manage.machines')  ->name('manage.machines');
        Volt::route('/erd/manage/devices',          'insight.erd.manage.devices')   ->name('manage.devices');
        Volt::route('/erd/manage',                  'insight.erd.manage.index')     ->name('manage.index');
        Volt::route('/erd/summary',                 'insight.erd.summary.index')    ->name('summary.index');
        Volt::route('/erd',                         'insight.erd.index')            ->name('index');

    });
    Volt::route('/', 'insight.index')->name('insight');
});

// Download route
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

    // inventory routes
    Route::prefix('inventory')->group(function () {

        Route::name('inventory.items.')->group(function () {

            Volt::route('/items/create',    'inventory.items.create')   ->name('create');
            Volt::route('/items/{id}',      'inventory.items.show')     ->name('show');
            Volt::route('/items/{id}/edit', 'inventory.items.edit')     ->name('edit');
            Volt::route('/items/',          'inventory.items.index')    ->name('index');

        });

        Route::name('inventory.circs.')->group(function () {

            Volt::route('/circs/create',    'inventory.circs.create')   ->name('create');
            Volt::route('/circs/print',     'inventory.circs.print')    ->name('print');
            Volt::route('/circs',           'inventory.circs.index')    ->name('index');

        });

        Route::name('inventory.manage.')->group(function () {

            Volt::route('/manage/areas',             'inventory.manage.areas')        ->name('areas');
            Volt::route('/manage/authorizations',    'inventory.manage.auths')        ->name('auths');
            Volt::route('/manage/currencies',        'inventory.manage.currs')        ->name('currs');
            Volt::route('/manage/locations',         'inventory.manage.locs')         ->name('locs');
            Volt::route('/manage/tags',              'inventory.manage.tags')         ->name('tags');
            Volt::route('/manage/uoms',              'inventory.manage.uoms')         ->name('uoms');
            Volt::route('/manage/circs-create',      'inventory.manage.circs-create') ->name('circs-create');
            Volt::route('/manage/items-update',      'inventory.manage.items-update') ->name('items-update');
            Volt::route('/manage',                   'inventory.manage.index')        ->name('index');
            
        });

    });

    // Administration routes
    Route::prefix('admin')->group(function () {

        Route::name('admin.')->group(function () {

            Volt::route('/account-manage',  'admin.account.manage')     ->name('account-manage');
            Volt::route('/inventory-auths', 'admin.inventory.auths')    ->name('inventory-auths');
            Volt::route('/inventory-areas', 'admin.inventory.areas')    ->name('inventory-areas');
        
        });

        Route::view('/', 'livewire.admin.index')->name('admin');
    });

});


require __DIR__.'/auth.php';
