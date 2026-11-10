<?php

namespace App\Services;

use App\Enums\InspectionStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Inspection;

final class InspectionWorkflow
{
    /**
     * @throws InvalidTransition
     */
    public function assign(Inspection $inspection, string $inspectorName): Inspection
    {
        return $this->transition($inspection, InspectionStatus::Assigned, [
            'inspector_name' => $inspectorName,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @throws InvalidTransition
     */
    public function start(Inspection $inspection): Inspection
    {
        if ($inspection->status === InspectionStatus::Assigned && $inspection->inspector_name === null) {
            throw InvalidTransition::noInspector($inspection->status);
        }

        return $this->transition($inspection, InspectionStatus::InProgress, ['started_at' => now()]);
    }

    /**
     * @throws InvalidTransition
     */
    public function complete(Inspection $inspection): Inspection
    {
        return $this->transition($inspection, InspectionStatus::Completed, ['completed_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws InvalidTransition
     */
    private function transition(Inspection $inspection, InspectionStatus $to, array $changes): Inspection
    {
        $from = $inspection->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransition::notAllowedIn($from);
        }

        // The status is part of the WHERE clause, so a step only succeeds from
        // the status it expects, even if another request changed the row first.
        $updated = Inspection::query()
            ->whereKey($inspection->getKey())
            ->where('status', $from->value)
            ->update(['status' => $to->value, ...$changes]);

        $inspection->refresh();

        if ($updated === 0) {
            throw InvalidTransition::notAllowedIn($inspection->status);
        }

        return $inspection;
    }
}
