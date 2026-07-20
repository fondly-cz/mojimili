<?php

use App\Http\Controllers\Api\CalculationItemsController;
use App\Http\Controllers\Api\CompanyEmployeeSearchController;
use App\Http\Controllers\Api\CompanySearchController;
use App\Http\Controllers\AresController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculationTodolistController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyEmployeeController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\MyCompanyController;
use App\Http\Controllers\PassportKeyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\TodolistController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'hasRole'])->group(function () {
    Route::get('/', [CrmController::class, 'welcome'])->name('dashboard');
    Route::resource('companies', CompanyController::class);
    Route::post('companies/bulk-delete', [CompanyController::class, 'bulkDelete'])->name('companies.bulk-delete');

    Route::post('companies/{company}/employees', [CompanyEmployeeController::class, 'store'])->name('companies.employees.store');
    Route::delete('companies/{company}/employees/{employee}', [CompanyEmployeeController::class, 'destroy'])->name('companies.employees.destroy');
    Route::get('/api/ares', [AresController::class, 'getCompanyData'])->name('ares.lookup');
    Route::get('/api/search', [SearchController::class, 'index'])->name('search');
    Route::get('/api/companies/search', CompanySearchController::class)->name('api.companies.search');
    Route::get('/api/companies/{company}/employees/search', CompanyEmployeeSearchController::class)->name('api.companies.employees.search');
    Route::get('/api/calculations/{calculation}/items', CalculationItemsController::class)->name('api.calculations.items');

    Route::resource('calculations', CalculationController::class);
    Route::post('calculations/bulk-delete', [CalculationController::class, 'bulkDelete'])->name('calculations.bulk-delete');

    // Projekty, seznamy úkolů a úkoly
    Route::post('projects/bulk-delete', [ProjectController::class, 'bulkDelete'])->name('projects.bulk-delete');
    Route::resource('projects', ProjectController::class);

    Route::post('projects/{project}/todolists', [TodolistController::class, 'store'])->name('projects.todolists.store');
    Route::patch('todolists/{todolist}', [TodolistController::class, 'update'])->name('todolists.update');
    Route::delete('todolists/{todolist}', [TodolistController::class, 'destroy'])->name('todolists.destroy');
    Route::post('todolists/{todolist}/reorder', [TodolistController::class, 'reorder'])->name('todolists.reorder');

    Route::post('todolists/{todolist}/todos', [TodoController::class, 'store'])->name('todolists.todos.store');
    Route::patch('todos/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::delete('todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');

    // Převod položek kalkulace na seznam úkolů v projektu
    Route::post('calculations/{calculation}/todolist', [CalculationTodolistController::class, 'store'])->name('calculations.todolist.store');

    // Only admins can manage services and users
    Route::post('services/bulk', [ServiceController::class, 'bulkStore'])->name('services.bulk');
    Route::post('services/bulk-delete', [ServiceController::class, 'bulkDelete'])->name('services.bulk-delete');
    Route::resource('services', ServiceController::class)->middleware('role:admin');
    Route::resource('users', UserController::class)->middleware('role:admin');

    // MCP / API OAuth klíče (Passport) – generování a stav
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/api-keys', [PassportKeyController::class, 'edit'])->name('passport-keys.edit');
        Route::post('/settings/api-keys', [PassportKeyController::class, 'regenerate'])->name('passport-keys.regenerate');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/my-company', [MyCompanyController::class, 'edit'])->name('my-company.edit');
    Route::patch('/my-company', [MyCompanyController::class, 'update'])->name('my-company.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/c/{token}', [CalculationController::class, 'showPublic'])->name('calculations.public');
Route::post('/c/{token}/confirm', [CalculationController::class, 'acceptPublic'])->name('calculations.confirm');

require __DIR__.'/auth.php';
