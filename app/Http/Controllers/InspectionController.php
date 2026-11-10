<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInspectionRequest;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InspectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return InspectionResource::collection(Inspection::query()->orderByDesc('id')->get());
    }

    public function show(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($inspection);
    }

    public function store(StoreInspectionRequest $request): InspectionResource
    {
        return new InspectionResource(Inspection::create($request->validated()));
    }
}
