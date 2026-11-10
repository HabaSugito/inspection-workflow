<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Inspection;
use App\Services\InspectionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const array VALID_ACTION = [
        'pending' => 'assign',
        'assigned' => 'start',
        'in_progress' => 'complete',
        'completed' => null,
    ];

    private function inspectionIn(string $status): Inspection
    {
        $factory = Inspection::factory();

        return match ($status) {
            'pending' => $factory->create(),
            'assigned' => $factory->assigned()->create(),
            'in_progress' => $factory->inProgress()->create(),
            'completed' => $factory->completed()->create(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRow(Inspection $inspection): array
    {
        return (array) DB::table('inspections')->find($inspection->id);
    }

    public function test_ac301_assigning_a_pending_inspection_stores_the_inspector(): void
    {
        $inspection = $this->inspectionIn('pending');

        $response = $this->postJson("/api/inspections/{$inspection->id}/assign", ['inspector_name' => 'Sam Carter']);

        $response->assertOk()
            ->assertJsonPath('data.id', $inspection->id)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.inspector_name', 'Sam Carter')
            ->assertJsonPath('data.next_action', 'start');
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'status' => 'assigned',
            'inspector_name' => 'Sam Carter',
        ]);
    }

    #[DataProvider('unusableInspectorNames')]
    public function test_ac302_assigning_without_a_usable_inspector_name_is_rejected(array $payload): void
    {
        $inspection = $this->inspectionIn('pending');
        $before = $this->storedRow($inspection);

        $response = $this->postJson("/api/inspections/{$inspection->id}/assign", $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors(['inspector_name']);
        $this->assertSame($before, $this->storedRow($inspection));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function unusableInspectorNames(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['inspector_name' => '']],
            'spaces only' => [['inspector_name' => '   ']],
            'longer than 255 characters' => [['inspector_name' => str_repeat('a', 256)]],
        ];
    }

    public function test_ac302_validation_runs_before_the_status_check(): void
    {
        $inspection = $this->inspectionIn('completed');
        $before = $this->storedRow($inspection);

        $this->postJson("/api/inspections/{$inspection->id}/assign", [])->assertUnprocessable();
        $this->assertSame($before, $this->storedRow($inspection));
    }

    public function test_ac303_starting_an_assigned_inspection_sets_it_in_progress(): void
    {
        $inspection = $this->inspectionIn('assigned');

        $response = $this->postJson("/api/inspections/{$inspection->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.next_action', 'complete');
        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'status' => 'in_progress']);
    }

    public function test_ac304_completing_an_in_progress_inspection_sets_it_completed(): void
    {
        $inspection = $this->inspectionIn('in_progress');

        $response = $this->postJson("/api/inspections/{$inspection->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.next_action', null);
        $this->assertDatabaseHas('inspections', ['id' => $inspection->id, 'status' => 'completed']);
    }

    #[DataProvider('invalidStatusAndActionPairs')]
    public function test_ac305_and_ac311_action_that_does_not_match_the_status_is_refused_and_changes_nothing(
        string $status,
        string $action,
    ): void {
        $inspection = $this->inspectionIn($status);
        $before = $this->storedRow($inspection);

        $this->travel(1)->hours();
        $response = $this->postJson("/api/inspections/{$inspection->id}/{$action}", ['inspector_name' => 'Someone Else']);

        $response->assertConflict()
            ->assertJsonPath('current_status', $status)
            ->assertJsonPath('allowed_action', self::VALID_ACTION[$status]);
        $this->assertSame($before, $this->storedRow($inspection));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidStatusAndActionPairs(): array
    {
        $pairs = [];

        foreach (self::VALID_ACTION as $status => $validAction) {
            foreach (['assign', 'start', 'complete'] as $action) {
                if ($action !== $validAction) {
                    $pairs["{$action} when {$status}"] = [$status, $action];
                }
            }
        }

        return $pairs;
    }

    public function test_ac305_the_provider_covers_the_nine_invalid_pairs(): void
    {
        $this->assertCount(9, self::invalidStatusAndActionPairs());
    }

    public function test_ac306_starting_is_refused_when_no_inspector_is_set(): void
    {
        $inspection = Inspection::factory()->assigned()->create(['inspector_name' => null]);
        $before = $this->storedRow($inspection);

        $response = $this->postJson("/api/inspections/{$inspection->id}/start");

        $response->assertConflict()
            ->assertJsonPath('current_status', 'assigned')
            ->assertJsonPath('allowed_action', null);
        $this->assertSame($before, $this->storedRow($inspection));
    }

    public function test_ac308_a_refusal_is_json_with_message_current_status_and_allowed_action(): void
    {
        $inspection = $this->inspectionIn('pending');

        $this->post("/api/inspections/{$inspection->id}/start")
            ->assertConflict()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'message' => 'This inspection is pending. The only action allowed is: assign.',
                'current_status' => 'pending',
                'allowed_action' => 'assign',
            ]);
    }

    public function test_ac308_a_refusal_on_a_completed_inspection_says_no_action_is_allowed(): void
    {
        $inspection = $this->inspectionIn('completed');

        $this->postJson("/api/inspections/{$inspection->id}/complete")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This inspection is completed. No further action is allowed.',
                'current_status' => 'completed',
                'allowed_action' => null,
            ]);
    }

    #[DataProvider('actions')]
    public function test_ac309_action_on_an_unknown_id_returns_the_json_404(string $action): void
    {
        $this->postJson("/api/inspections/999999/{$action}", ['inspector_name' => 'Sam Carter'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Inspection not found.']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function actions(): array
    {
        return ['assign' => ['assign'], 'start' => ['start'], 'complete' => ['complete']];
    }

    public function test_ac310_each_step_records_its_time_and_leaves_earlier_times_unchanged(): void
    {
        $this->travelTo('2026-11-10 09:00:00');
        $id = Inspection::factory()->create()->id;

        $this->travelTo('2026-11-10 10:00:00');
        $this->postJson("/api/inspections/{$id}/assign", ['inspector_name' => 'Sam Carter'])
            ->assertOk()
            ->assertJsonPath('data.assigned_at', '2026-11-10T10:00:00Z')
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.completed_at', null);

        $this->travelTo('2026-11-11 11:30:00');
        $this->postJson("/api/inspections/{$id}/start")
            ->assertOk()
            ->assertJsonPath('data.assigned_at', '2026-11-10T10:00:00Z')
            ->assertJsonPath('data.started_at', '2026-11-11T11:30:00Z')
            ->assertJsonPath('data.completed_at', null);

        $this->travelTo('2026-11-12 16:45:00');
        $this->postJson("/api/inspections/{$id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assigned_at', '2026-11-10T10:00:00Z')
            ->assertJsonPath('data.started_at', '2026-11-11T11:30:00Z')
            ->assertJsonPath('data.completed_at', '2026-11-12T16:45:00Z');

        $this->assertDatabaseHas('inspections', [
            'id' => $id,
            'assigned_at' => '2026-11-10 10:00:00',
            'started_at' => '2026-11-11 11:30:00',
            'completed_at' => '2026-11-12 16:45:00',
        ]);
    }

    public function test_ac310_a_new_inspection_has_no_step_times(): void
    {
        $response = $this->postJson('/api/inspections', [
            'customer_name' => 'Jane Smith',
            'vehicle_registration' => 'AB12 CDE',
            'requested_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.next_action', 'assign')
            ->assertJsonPath('data.assigned_at', null)
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.completed_at', null);
    }

    public function test_a_step_made_from_a_stale_copy_is_refused_and_does_not_overwrite(): void
    {
        $workflow = app(InspectionWorkflow::class);
        $id = Inspection::factory()->create()->id;
        $first = Inspection::findOrFail($id);
        $stale = Inspection::findOrFail($id);

        $workflow->assign($first, 'Sam Carter');

        try {
            $workflow->assign($stale, 'Someone Else');
            $this->fail('The stale assign was not refused.');
        } catch (InvalidTransition $e) {
            $this->assertSame('assigned', $e->currentStatus->value);
            $this->assertSame('start', $e->allowedAction);
        }

        $this->assertDatabaseHas('inspections', ['id' => $id, 'status' => 'assigned', 'inspector_name' => 'Sam Carter']);
    }
}
