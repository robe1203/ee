<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\UserController;

use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\CompanyManageController;
use App\Http\Controllers\App\CompanyTransferController;

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\PolicyLineController;
use App\Http\Controllers\ReportController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
})->name('welcome');

Route::get('/dashboard', function () {
    return redirect()->route('app.home');
})->middleware(['auth', 'verified', 'is_active'])->name('dashboard');

Route::middleware(['auth', 'is_active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'admin', 'is_active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::resource('users', UserController::class)->except(['show']);
    });

Route::middleware(['auth', 'verified', 'is_active', 'update_quarter'])->group(function () {
    Route::get('/app', HomeController::class)->name('app.home');
});

Route::get('/app/import-blocked', function () {
    return Inertia::render('ImportBlocked');
})->name('app.import.blocked');

Route::middleware(['auth', 'verified', 'is_active', 'update_quarter'])->group(function () {
    Route::get('/app/company', [CompanyManageController::class, 'index'])->name('app.company.index');
    Route::put('/app/company', [CompanyManageController::class, 'saveActive'])->name('app.company.update');

    Route::post('/app/companies', [CompanyManageController::class, 'store'])->name('app.companies.store');
    Route::put('/app/companies/{company}', [CompanyManageController::class, 'update'])->name('app.companies.update');
    Route::delete('/app/companies/{company}', [CompanyManageController::class, 'destroy'])->name('app.companies.destroy');
    Route::post('/app/companies/{company}/select', [CompanyManageController::class, 'select'])->name('app.companies.select');
    Route::post('/app/companies/quick-create', [CompanyManageController::class, 'quickCreate'])->name('app.companies.quickCreate');

    Route::get('/app/companies/{company}/export', [CompanyTransferController::class, 'export'])->name('app.companies.export');
    Route::post('/app/companies/import', [CompanyTransferController::class, 'import'])->name('app.companies.import');

    Route::middleware(['company_selected'])->group(function () {
        Route::get('/app/catalog', [CatalogController::class, 'index'])->name('app.catalog.index');
        Route::post('/app/catalog/accounts', [CatalogController::class, 'storeCustom'])->name('app.catalog.accounts.store');
        Route::delete('/app/catalog/accounts/{account}', [CatalogController::class, 'destroy'])->name('app.catalog.accounts.destroy');

        Route::get('/app/policies', [PolicyController::class, 'index'])->name('app.policies.index');
        Route::get('/app/policies/create', [PolicyController::class, 'create'])->name('app.policies.create');
        Route::post('/app/policies', [PolicyController::class, 'store'])->name('app.policies.store');

        Route::get('/app/policies/{policy}/edit', [PolicyController::class, 'edit'])->name('app.policies.edit');
        Route::put('/app/policies/{policy}', [PolicyController::class, 'update'])->name('app.policies.update');
        Route::delete('/app/policies/{policy}', [PolicyController::class, 'destroy'])->name('app.policies.destroy');

        Route::post('/app/policies/{policy}/lines', [PolicyLineController::class, 'store'])->name('app.policies.lines.store');
        Route::put('/app/policies/{policy}/lines/{line}', [PolicyLineController::class, 'update'])->name('app.policies.lines.update');
        Route::delete('/app/policies/{policy}/lines/{line}', [PolicyLineController::class, 'destroy'])->name('app.policies.lines.destroy');

        Route::get('/app/reports', [ReportController::class, 'policies'])->name('app.reports.policies');
        Route::get('/app/informe', [ReportController::class, 'informe'])->name('app.reports.informe');
        Route::get('/app/informes-reportes', [ReportController::class, 'unified'])->name('app.reports.unified');

        Route::get('/app/reports/balanza', [ReportController::class, 'balanza'])->name('app.reports.balanza');
        Route::get('/app/reports/libro-diario', [ReportController::class, 'libroDiario'])->name('app.reports.libroDiario');
        Route::get('/app/reports/mayor', [ReportController::class, 'mayor'])->name('app.reports.mayor');
        Route::get('/app/reports/estado-resultados', [ReportController::class, 'estadoResultados'])->name('app.reports.estadoResultados');

        Route::get('/app/reports/balance-general', [ReportController::class, 'balanceGeneral'])
            ->middleware('admin')
            ->name('app.reports.balanceGeneral');
    });
});

require __DIR__ . '/auth.php';