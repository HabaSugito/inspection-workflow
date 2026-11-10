<?php

namespace Tests\Unit;

use App\Enums\InspectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InspectionStatusTest extends TestCase
{
    private const array ALLOWED = [
        'pending' => 'assigned',
        'assigned' => 'in_progress',
        'in_progress' => 'completed',
    ];

    #[DataProvider('allStatusPairs')]
    public function test_ac307_only_the_three_workflow_steps_are_allowed_transitions(
        InspectionStatus $from,
        InspectionStatus $to,
    ): void {
        $expected = (self::ALLOWED[$from->value] ?? null) === $to->value;

        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{InspectionStatus, InspectionStatus}>
     */
    public static function allStatusPairs(): array
    {
        $pairs = [];

        foreach (InspectionStatus::cases() as $from) {
            foreach (InspectionStatus::cases() as $to) {
                $pairs["{$from->value} to {$to->value}"] = [$from, $to];
            }
        }

        return $pairs;
    }

    public function test_ac307_the_provider_covers_all_sixteen_pairs_and_three_are_allowed(): void
    {
        $pairs = self::allStatusPairs();
        $allowed = array_filter($pairs, fn (array $pair) => $pair[0]->canTransitionTo($pair[1]));

        $this->assertCount(16, $pairs);
        $this->assertSame(
            ['pending to assigned', 'assigned to in_progress', 'in_progress to completed'],
            array_keys($allowed),
        );
    }

    #[DataProvider('nextSteps')]
    public function test_ac307_each_status_names_its_next_status_and_action(
        InspectionStatus $status,
        ?InspectionStatus $next,
        ?string $action,
    ): void {
        $this->assertSame($next, $status->next());
        $this->assertSame($action, $status->nextAction());
    }

    /**
     * @return array<string, array{InspectionStatus, ?InspectionStatus, ?string}>
     */
    public static function nextSteps(): array
    {
        return [
            'pending' => [InspectionStatus::Pending, InspectionStatus::Assigned, 'assign'],
            'assigned' => [InspectionStatus::Assigned, InspectionStatus::InProgress, 'start'],
            'in progress' => [InspectionStatus::InProgress, InspectionStatus::Completed, 'complete'],
            'completed' => [InspectionStatus::Completed, null, null],
        ];
    }
}
