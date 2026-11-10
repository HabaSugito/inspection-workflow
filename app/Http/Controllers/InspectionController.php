<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectionRequest;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;

class InspectionController extends Controller
{
    public function store(StoreInspectionRequest $request): InspectionResource
    {
        return new InspectionResource(Inspection::create($request->validated()));
    }
}
