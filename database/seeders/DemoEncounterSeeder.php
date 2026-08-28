<?php

namespace Database\Seeders;

use App\Ark\Operations\Encounters\Encounter;
use App\Ark\Operations\Encounters\EncounterOperationalState;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoEncounterSeeder extends Seeder
{
    public function __construct(private readonly OperationalEventRecorder $events) {}

    public function run(): void
    {
        $advisor = User::query()->where('email', 'advisor@ark.test')->first()
            ?? User::query()->where('email', 'admin@ark.test')->first();

        foreach ($this->encounters() as $record) {
            DB::transaction(function () use ($advisor, $record): void {
                $movementAt = now()->subMinutes($record['movement_minutes_ago']);

                $encounter = Encounter::query()->updateOrCreate(
                    [
                        'source' => $record['source'],
                        'callback_phone' => $record['callback_phone'],
                        'concern' => $record['concern'],
                    ],
                    [
                        'callback_name' => $record['callback_name'],
                        'uuid' => $record['uuid'],
                        'rough_vehicle' => $record['rough_vehicle'],
                        'operational_state' => $record['operational_state'],
                        'created_by' => $advisor?->id,
                        'tow_incoming' => $record['tow_incoming'] ?? false,
                        'waiting_here' => $record['waiting_here'] ?? false,
                        'appointment' => $record['appointment'] ?? false,
                    ],
                );

                $encounter->forceFill([
                    'last_operational_movement_at' => $movementAt,
                    'created_at' => $movementAt,
                    'updated_at' => $movementAt,
                ])->save();

                if (! OperationalEvent::query()
                    ->where('event_name', OperationalEventName::EncounterCreated->value)
                    ->where('aggregate_type', Encounter::class)
                    ->where('aggregate_id', $encounter->id)
                    ->exists()) {
                    $this->events->record(
                        OperationalEventName::EncounterCreated,
                        $encounter,
                        actor: $advisor,
                        payload: [
                            'source' => $encounter->source->value,
                            'operational_state' => $encounter->operational_state->value,
                            'tow_incoming' => $encounter->tow_incoming,
                            'waiting_here' => $encounter->waiting_here,
                            'appointment' => $encounter->appointment,
                        ],
                    );
                }
            });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function encounters(): array
    {
        return [
            [
                'uuid' => '7dc60ad5-4f73-4e81-bb84-9cf336180101',
                'concern' => 'No crank after grocery stop. Customer says dash lights come on but starter does not click.',
                'callback_name' => 'Robert',
                'callback_phone' => '7195550101',
                'rough_vehicle' => 'Jeep Wrangler maybe 2015',
                'source' => EncounterSource::Phone,
                'operational_state' => EncounterOperationalState::New,
                'tow_incoming' => true,
                'movement_minutes_ago' => 18,
            ],
            [
                'uuid' => '7dc60ad5-4f73-4e81-bb84-9cf336180102',
                'concern' => 'Website inquiry asking if we can inspect a brake grinding noise before weekend travel.',
                'callback_name' => 'Mia',
                'callback_phone' => '7195550102',
                'rough_vehicle' => 'Silver Honda maybe CRV',
                'source' => EncounterSource::Website,
                'operational_state' => EncounterOperationalState::New,
                'appointment' => true,
                'movement_minutes_ago' => 54,
            ],
            [
                'uuid' => '7dc60ad5-4f73-4e81-bb84-9cf336180103',
                'concern' => 'Walk-in waiting in lobby. Oil light flickered once on Powers during lunch traffic.',
                'callback_name' => 'Nora',
                'callback_phone' => '7195550103',
                'rough_vehicle' => 'Blue Subaru Outback, older',
                'source' => EncounterSource::WalkIn,
                'operational_state' => EncounterOperationalState::New,
                'waiting_here' => true,
                'movement_minutes_ago' => 9,
            ],
            [
                'uuid' => '7dc60ad5-4f73-4e81-bb84-9cf336180104',
                'concern' => 'RepairPal lead for coolant leak. Customer uploaded note saying puddle is near passenger front tire.',
                'callback_name' => 'Evan',
                'callback_phone' => '7195550104',
                'rough_vehicle' => 'Toyota Tacoma around 2018',
                'source' => EncounterSource::RepairPal,
                'operational_state' => EncounterOperationalState::New,
                'movement_minutes_ago' => 137,
            ],
        ];
    }
}
