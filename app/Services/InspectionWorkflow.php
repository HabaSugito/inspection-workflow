<?php

namespace App\Services;

use App\Enums\InspectionStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Inspection;
use InvalidArgumentException;

final class InspectionWorkflow
{
    public const int INSPECTOR_NAME_MAX = 255;

    /**
     * The action that would succeed now, or null when none would. The resource
     * and the refusals both read it from here, so they cannot disagree.
     */
    public function nextAction(Inspection $inspection): ?string
    {
        if ($inspection->status === InspectionStatus::Assigned && ! $this->hasInspector($inspection)) {
            return null;
        }

        return $inspection->status->nextAction();
    }

    /**
     * @throws InvalidTransition
     * @throws InvalidArgumentException when the name is blank or too long.
     *                                  The HTTP layer validates it first, so
     *                                  this is reached only by another caller.
     */
    public function assign(Inspection $inspection, string $inspectorName): Inspection
    {
        $inspectorName = trim($inspectorName);

        if ($inspectorName === '' || mb_strlen($inspectorName) > self::INSPECTOR_NAME_MAX) {
            throw new InvalidArgumentException(
                'An inspector name must have between 1 and '.self::INSPECTOR_NAME_MAX.' characters.',
            );
        }

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
        if ($inspection->status === InspectionStatus::Assigned && ! $this->hasInspector($inspection)) {
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

    private function hasInspector(Inspection $inspection): bool
    {
        return trim((string) $inspection->inspector_name) !== '';
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
