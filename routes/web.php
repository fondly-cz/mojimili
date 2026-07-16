<?php

use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'hasRole'])->group(function () {
    Route::get('/', [CrmController::class, 'welcome'])->name('dashboard');
    Route::resource('companies', CompanyController::class);
    Route::post('companies/bulk-delete', [CompanyController::class, 'bulkDelete'])->name('companies.bulk-delete');

    Route::post('companies/{company}/employees', [\App\Http\Controllers\CompanyEmployeeController::class, 'store'])->name('companies.employees.store');
    Route::delete('companies/{company}/employees/{employee}', [\App\Http\Controllers\CompanyEmployeeController::class, 'destroy'])->name('companies.employees.destroy');
    Route::get('/api/ares', [\App\Http\Controllers\AresController::class, 'getCompanyData'])->name('ares.lookup');
    Route::get('/api/search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search');
    Route::get('/api/companies/search', \App\Http\Controllers\Api\CompanySearchController::class)->name('api.companies.search');
    Route::get('/api/companies/{company}/employees/search', \App\Http\Controllers\Api\CompanyEmployeeSearchController::class)->name('api.companies.employees.search');

    Route::resource('calculations', CalculationController::class);
    Route::post('calculations/bulk-delete', [CalculationController::class, 'bulkDelete'])->name('calculations.bulk-delete');

    // Only admins can manage services and users
    Route::post('services/bulk', [ServiceController::class, 'bulkStore'])->name('services.bulk');
    Route::post('services/bulk-delete', [ServiceController::class, 'bulkDelete'])->name('services.bulk-delete');
    Route::resource('services', ServiceController::class)->middleware('role:admin');
    Route::resource('users', \App\Http\Controllers\UserController::class)->middleware('role:admin');

    // MCP / API OAuth klíče (Passport) – generování a stav
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/api-keys', [\App\Http\Controllers\PassportKeyController::class, 'edit'])->name('passport-keys.edit');
        Route::post('/settings/api-keys', [\App\Http\Controllers\PassportKeyController::class, 'regenerate'])->name('passport-keys.regenerate');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/my-company', [\App\Http\Controllers\MyCompanyController::class, 'edit'])->name('my-company.edit');
    Route::patch('/my-company', [\App\Http\Controllers\MyCompanyController::class, 'update'])->name('my-company.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/c/{token}', [CalculationController::class, 'showPublic'])->name('calculations.public');
Route::post('/c/{token}/confirm', [CalculationController::class, 'acceptPublic'])->name('calculations.confirm');

require __DIR__.'/auth.php';
