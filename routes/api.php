<?php

use App\Http\Controllers\InspectionController;
use Illuminate\Support\Facades\Route;

Route::get('/inspections', [InspectionController::class, 'index']);
Route::post('/inspections', [InspectionController::class, 'store']);
Route::get('/inspections/{inspection}', [InspectionController::class, 'show']);
