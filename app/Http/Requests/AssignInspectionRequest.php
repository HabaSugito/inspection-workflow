<?php

namespace App\Http\Requests;

use App\Services\InspectionWorkflow;
use Illuminate\Foundation\Http\FormRequest;

class AssignInspectionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'inspector_name' => ['required', 'string', 'max:'.InspectionWorkflow::INSPECTOR_NAME_MAX],
        ];
    }
}
