<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignInspectionRequest;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;
use App\Services\InspectionWorkflow;

class InspectionTransitionController extends Controller
{
    public function __construct(private readonly InspectionWorkflow $workflow) {}

    public function assign(AssignInspectionRequest $request, Inspection $inspection): InspectionResource
    {
        return new InspectionResource(
            $this->workflow->assign($inspection, $request->validated('inspector_name')),
        );
    }

    public function start(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($this->workflow->start($inspection));
    }

    public function complete(Inspection $inspection): InspectionResource
    {
        return new InspectionResource($this->workflow->complete($inspection));
    }
}
