<?php

use App\Http\Controllers\DownloadController;
use App\Http\Resources\InsRtcMetricResource;
use App\Http\Resources\InsRtcRecipeResource;
use App\Models\InsRtcMetric;
use App\Models\InsRtcRecipe;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->name('home');
Volt::route('/inventory', 'inventory.index')->name('inventory');
Volt::route('/inventory/help', 'inventory.help')->name('inventory.help');

Volt::route('/announcements/{id}', 'announcements.show')->name('announcements.show');

// Insights routes
Route::prefix('insights')->group(function () {

    Route::name('insights.')->group(function () {

        Volt::route('/ss/{id}', 'insights.ss.index')->name('ss'); // slideshow
    });

    Route::name('insights.rtc.')->group(function () {

        Volt::route('/rtc/manage/authorizations', 'insights.rtc.manage.auths')->name('manage.auths');
        Volt::route('/rtc/manage/devices', 'insights.rtc.manage.devices')->name('manage.devices');
        Volt::route('/rtc/manage/recipes', 'insights.rtc.manage.recipes')->name('manage.recipes');
        Volt::route('/rtc/manage', 'insights.rtc.manage.index')->name('manage.index');
        Volt::route('/rtc/slideshows', 'insights.rtc.slideshows')->name('slideshows');
        Volt::route('/rtc', 'insights.rtc.index')->name('index');

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

    Route::name('insights.ctc.')->group(function () {

        Volt::route('/ctc/manage/authorizations', 'insights.ctc.manage.auths')->name('manage.auths');
        Volt::route('/ctc/manage/machines', 'insights.ctc.manage.machines')->name('manage.machines');
        Volt::route('/ctc/manage/recipes', 'insights.ctc.manage.recipes')->name('manage.recipes');
        Volt::route('/ctc/manage', 'insights.ctc.manage.index')->name('manage.index');
        Volt::route('/ctc/data/realtime', 'insights.ctc.data.realtime')->name('data.realtime');
        Volt::route('/ctc/data/batch', 'insights.ctc.data.batch')->name('data.batch');
        Volt::route('/ctc/data', 'insights.ctc.data.index')->name('data.index');
        Volt::route('/ctc/slideshows', 'insights.ctc.slides.slideshows')->name('slideshows');
        Route::get('/ctc', function () {
            return redirect()->route('insights.ctc.data.index');
        })->name('index');

        // API routes for CTC (similar to RTC pattern)
        Route::get('/ctc/metric/{device_id}', function (string $device_id) {
            // TODO: Replace with actual CTC model when backend is ready
            // $metric = InsCtcMetric::join('ins_ctc_batches', 'ins_ctc_batches.id', '=', 'ins_ctc_metrics.ins_ctc_batch_id')
            //     ->where('ins_ctc_batches.ins_ctc_device_id', $device_id)
            //     ->latest('dt_client')
            //     ->first();
            // return $metric ? new InsCtcMetricResource($metric) : abort(404);

            // Mock response for development
            return response()->json([
                'device_id' => $device_id,
                'sensor_left' => 3.05,
                'sensor_right' => 3.02,
                'is_correcting' => true,
                'dt_client' => now()->toISOString(),
            ]);
        })->name('metric');

        Route::get('/ctc/recipe/{recipe_id}', function (string $recipe_id) {
            // TODO: Replace with actual CTC recipe model when backend is ready
            // return new InsCtcRecipeResource(InsCtcRecipe::findOrFail($recipe_id));

            // Mock response for development
            return response()->json([
                'id' => $recipe_id,
                'name' => 'AF1 GS (ONE COLOR)',
                'std_min' => 3.0,
                'std_max' => 3.1,
                'std_mid' => 3.05,
            ]);
        })->name('recipe');

    });

    Route::name('insights.ldc.')->group(function () {

        Volt::route('/ldc/manage/authorizations', 'insights.ldc.manage.auths')->name('manage.auths');
        Volt::route('/ldc/manage/machines', 'insights.ldc.manage.machines')->name('manage.machines');
        Volt::route('/ldc/manage', 'insights.ldc.manage.index')->name('manage.index');
        Volt::route('/ldc/data', 'insights.ldc.data.index')->name('data.index');
        Volt::route('/ldc/create', 'insights.ldc.create.index')->name('create.index');
        Route::get('/ldc', function () {
            if (auth()->check()) {
                return redirect()->route('insights.ldc.create.index');
            }

            return redirect()->route('insights.ldc.data.index');
        })->name('index');
    });

    Route::name('insights.omv.')->group(function () {

        Volt::route('/omv/manage/authorizations', 'insights.omv.manage.auths')->name('manage.auths');
        Volt::route('/omv/manage/recipes', 'insights.omv.manage.recipes')->name('manage.recipes');
        Volt::route('/omv/manage', 'insights.omv.manage.index')->name('manage.index');
        Volt::route('/omv/data', 'insights.omv.data.index')->name('data.index');
        Volt::route('/omv/create', 'insights.omv.create.index')->name('create.index');
        Route::get('/omv', function () {
            if (auth()->check()) {
                return redirect()->route('insights.omv.create.index');
            }

            return redirect()->route('insights.omv.data.index');
        })->name('index');
    });

    Route::name('insights.rdc.')->group(function () {

        Volt::route('/rdc/manage/authorizations', 'insights.rdc.manage.auths')->name('manage.auths');
        Volt::route('/rdc/manage/machines', 'insights.rdc.manage.machines')->name('manage.machines');
        Volt::route('/rdc/manage', 'insights.rdc.manage.index')->name('manage.index');
        Volt::route('/rdc/data', 'insights.rdc.data.index')->name('data.index');
        Volt::route('/rdc/queue', 'insights.rdc.queue.index')->name('queue.index');
        Route::get('/rdc', function () {
            if (auth()->check()) {
                return redirect()->route('insights.rdc.queue.index');
            }

            return redirect()->route('insights.rdc.data.index');
        })->name('index');

    });

    Route::name('insights.clm.')->group(function () {

        Volt::route('/clm/', 'insights.clm.index')->name('index');
    });

    Route::name('insights.stc.')->group(function () {

        Volt::route('/stc/manage/authorizations', 'insights.stc.manage.auths')->name('manage.auths');
        Volt::route('/stc/manage/machines', 'insights.stc.manage.machines')->name('manage.machines');
        Volt::route('/stc/manage/devices', 'insights.stc.manage.devices')->name('manage.devices');
        Volt::route('/stc/manage', 'insights.stc.manage.index')->name('manage.index');
        Volt::route('/stc/data/adjustments', 'insights.stc.data.adjustments')->name('data.adjustments');
        Volt::route('/stc/data', 'insights.stc.data.index')->name('data.index');
        Volt::route('/stc/create', 'insights.stc.create.index')->name('create.index');
        Route::get('/stc', function () {
            if (auth()->check()) {
                return redirect()->route('insights.stc.create.index');
            }

            return redirect()->route('insights.stc.data.index');
        })->name('index');

    });

    Route::name('insights.erd.')->group(function () {

        Volt::route('/erd/manage/authorizations', 'insights.erd.manage.auths')->name('manage.auths');
        Volt::route('/erd/manage/machines', 'insights.erd.manage.machines')->name('manage.machines');
        Volt::route('/erd/manage/devices', 'insights.erd.manage.devices')->name('manage.devices');
        Volt::route('/erd/manage', 'insights.erd.manage.index')->name('manage.index');
        Volt::route('/erd/summary', 'insights.erd.summary.index')->name('summary.index');
        Volt::route('/erd', 'insights.erd.index')->name('index');

    });

    Route::name('insights.dwp.')->group(function () {

        Volt::route('/dwp/manage/authorizations', 'insights.dwp.manage.auths')->name('manage.auths');
        Volt::route('/dwp/manage/devices', 'insights.dwp.manage.devices')->name('manage.devices');
        Volt::route('/dwp/manage', 'insights.dwp.manage.index')->name('manage.index');
        Volt::route('/dwp/data', 'insights.dwp.data.index')->name('data.index');
        Route::get('/dwp', function () {
            return redirect()->route('insights.dwp.data.index');
        })->name('index');

    });
    Volt::route('/', 'insights.index')->name('insights');
});

// Download route
Route::name('download.')->group(function () {

    Route::get('/download/ins-stc-d-logs/{token}', [DownloadController::class, 'insStcDLogs'])->name('ins-stc-d-logs');
    Route::get('/download/inv-stocks/{token}', [DownloadController::class, 'invStocks'])->name('inv-stocks');
    Route::get('/download/inv-circs/{token}', [DownloadController::class, 'invCircs'])->name('inv-circs');
    Route::get('/download/inv-items/{token}', [DownloadController::class, 'invItems'])->name('inv-items');
    Route::get('/download/inv-items-backup/{token}', [DownloadController::class, 'invItemsBackup'])->name('inv-items-backup');
    Route::get('/download/ins-rtc-metrics', [DownloadController::class, 'insRtcMetrics'])->name('ins-rtc-metrics');
    Route::get('/download/ins-rtc-clumps', [DownloadController::class, 'insRtcClumps'])->name('ins-rtc-clumps');
    Route::get('/download/ins-ldc-hides', [DownloadController::class, 'insLdcHides'])->name('ins-ldc-hides');
});

// All routes that needs to be authenticated
Route::middleware('auth')->group(function () {

    Volt::route('/notifications', 'notifications')->name('notifications');

    // Account routes
    Route::prefix('account')->group(function () {

        Route::name('account.')->group(function () {

            Volt::route('/general', 'account.general')->name('general');
            Volt::route('/password', 'account.password')->name('password');
            Volt::route('/language', 'account.language')->name('language');
            Volt::route('/theme', 'account.theme')->name('theme');
            Volt::route('/edit', 'account.edit')->name('edit');
            Volt::route('/insecure-password', 'account.insecure-password')->name('insecure-password');

        });

        Volt::route('/', 'account.index')->name('account');

    });

    // inventory routes
    Route::prefix('inventory')->group(function () {

        Route::name('inventory.items.')->group(function () {

            Route::middleware('can:create,'.\App\Models\InvItem::class)->group(function () {
                Volt::route('/items/create', 'inventory.items.create')->name('create');
            });
            Volt::route('/items/bulk-operation', 'inventory.items.bulk-operation.index')->name('bulk-operation.index');
            Volt::route('/items/bulk-operation/create-new', 'inventory.items.bulk-operation.create-new')->name('bulk-operation.create-new');
            Volt::route('/items/bulk-operation/update-basic', 'inventory.items.bulk-operation.update-basic')->name('bulk-operation.update-basic');
            Volt::route('/items/bulk-operation/update-location', 'inventory.items.bulk-operation.update-location')->name('bulk-operation.update-location');
            Volt::route('/items/bulk-operation/update-stock', 'inventory.items.bulk-operation.update-stock')->name('bulk-operation.update-stock');
            Volt::route('/items/bulk-operation/update-limit', 'inventory.items.bulk-operation.update-limit')->name('bulk-operation.update-limit');
            Volt::route('/items/bulk-operation/update-status', 'inventory.items.bulk-operation.update-status')->name('bulk-operation.update-status');
            Volt::route('/items/bulk-operation/pull-photos', 'inventory.items.bulk-operation.pull-photos')->name('bulk-operation.pull-photos');
            Volt::route('/items/summary', 'inventory.items.summary')->name('summary');
            Volt::route('/items/{id}', 'inventory.items.show')->name('show');
            Volt::route('/items/{id}/edit', 'inventory.items.edit')->name('edit');
            Volt::route('/items/', 'inventory.items.index')->name('index');

        });

        Route::name('inventory.circs.')->group(function () {

            Volt::route('/circs/bulk-operation', 'inventory.circs.bulk-operation.index')->name('bulk-operation.index');
            Volt::route('/circs/bulk-operation/circ-only', 'inventory.circs.bulk-operation.circ-only')->name('bulk-operation.circ-only');
            Volt::route('/circs/bulk-operation/with-item', 'inventory.circs.bulk-operation.with-item')->name('bulk-operation.with-item');
            Volt::route('/circs/summary/', 'inventory.circs.summary')->name('summary');
            Volt::route('/circs/create', 'inventory.circs.create')->name('create');
            Volt::route('/circs/print', 'inventory.circs.print')->name('print');
            Volt::route('/circs', 'inventory.circs.index')->name('index');

        });

        Route::name('inventory.manage.')->group(function () {
            Volt::route('/manage', 'inventory.manage.index')->name('index');
            Volt::route('/manage/auths', 'inventory.manage.auths')->name('auths');
            Volt::route('/manage/areas', 'inventory.manage.areas')->name('areas');
            Volt::route('/manage/currs', 'inventory.manage.currs')->name('currs');
        });

    });

    // Administration routes
    Route::prefix('admin')->middleware('can:superuser')->group(function () {

        Route::name('admin.')->group(function () {

            Volt::route('/account-manage', 'admin.account.manage')->name('account-manage');
            Volt::route('/daemon-manage', 'admin.daemon.manage')->name('daemon-manage');

        });

        Route::view('/', 'livewire.admin.index')->name('admin');
    });

});

require __DIR__.'/auth.php';
