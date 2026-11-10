<?php

namespace Tests\Unit;

use App\Enums\InspectionStatus;
use App\Exceptions\InvalidTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvalidTransitionTest extends TestCase
{
    #[DataProvider('refusals')]
    public function test_ac308_a_refusal_names_the_status_and_the_only_allowed_action(
        InspectionStatus $status,
        ?string $allowedAction,
        string $message,
    ): void {
        $exception = InvalidTransition::notAllowedIn($status);

        $this->assertSame($status, $exception->currentStatus);
        $this->assertSame($allowedAction, $exception->allowedAction);
        $this->assertSame($message, $exception->getMessage());
    }

    /**
     * @return array<string, array{InspectionStatus, ?string, string}>
     */
    public static function refusals(): array
    {
        return [
            'pending' => [
                InspectionStatus::Pending,
                'assign',
                'This inspection is pending. The only action allowed is: assign.',
            ],
            'assigned' => [
                InspectionStatus::Assigned,
                'start',
                'This inspection is assigned. The only action allowed is: start.',
            ],
            'in progress' => [
                InspectionStatus::InProgress,
                'complete',
                'This inspection is in progress. The only action allowed is: complete.',
            ],
            'completed' => [
                InspectionStatus::Completed,
                null,
                'This inspection is completed. No further action is allowed.',
            ],
        ];
    }

    public function test_ac306_a_refusal_for_a_missing_inspector_allows_no_action(): void
    {
        $exception = InvalidTransition::noInspector(InspectionStatus::Assigned);

        $this->assertSame(InspectionStatus::Assigned, $exception->currentStatus);
        $this->assertNull($exception->allowedAction);
        $this->assertSame('This inspection has no inspector, so it cannot be started.', $exception->getMessage());
    }
}
