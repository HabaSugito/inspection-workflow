<?php

use App\Http\Controllers\InspectionController;
use Illuminate\Support\Facades\Route;

Route::post('/inspections', [InspectionController::class, 'store']);
