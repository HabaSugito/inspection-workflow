<?php

use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionTransitionController;
use Illuminate\Support\Facades\Route;

Route::get('/inspections', [InspectionController::class, 'index']);
Route::post('/inspections', [InspectionController::class, 'store']);
Route::get('/inspections/{inspection}', [InspectionController::class, 'show']);

Route::post('/inspections/{inspection}/assign', [InspectionTransitionController::class, 'assign']);
Route::post('/inspections/{inspection}/start', [InspectionTransitionController::class, 'start']);
Route::post('/inspections/{inspection}/complete', [InspectionTransitionController::class, 'complete']);
