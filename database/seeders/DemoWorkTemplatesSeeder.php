<?php

namespace Database\Seeders;

use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\WorkTemplates\WorkTemplate;
use Illuminate\Database\Seeder;

/**
 * LOCAL / testing fixtures only — not registered in DatabaseSeeder.
 * php artisan db:seed --class=DemoWorkTemplatesSeeder
 */
class DemoWorkTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed('Front Brake Service', 'Pads and rotors — common front brake job.', [
            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace front brake pads and rotors', 'quantity' => '1.50'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Front brake pads', 'quantity' => '1.00'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Front brake rotor', 'quantity' => '2.00'],
        ]);

        $this->seed('Rear Brake Service', 'Pads and rotors — common rear brake job.', [
            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace rear brake pads and rotors', 'quantity' => '1.50'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Rear brake pads', 'quantity' => '1.00'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Rear brake rotor', 'quantity' => '2.00'],
        ]);

        $this->seed('Spark Plug Replacement', 'Ignition plugs — hours vary by access.', [
            ['type' => RepairOrderLineType::Labor, 'description' => 'Replace spark plugs', 'quantity' => '1.00'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Spark plugs', 'quantity' => '4.00'],
        ]);

        $this->seed('Transmission Drain & Fill', 'Fluid service — filter if equipped.', [
            ['type' => RepairOrderLineType::Labor, 'description' => 'Transmission drain and fill', 'quantity' => '1.00'],
            ['type' => RepairOrderLineType::Part, 'description' => 'Transmission fluid', 'quantity' => '1.00'],
            ['type' => RepairOrderLineType::Fee, 'description' => 'Shop supplies', 'quantity' => '1.00', 'unit_price_cents' => 1500],
        ]);
    }

    /**
     * @param  list<array{type: RepairOrderLineType, description: string, quantity: string, unit_price_cents?: int}>  $lines
     */
    private function seed(string $title, string $description, array $lines): void
    {
        $existing = WorkTemplate::query()->where('title', $title)->first();

        if ($existing !== null) {
            return;
        }

        $template = WorkTemplate::query()->create([
            'title' => $title,
            'description' => $description,
            'position' => ((int) WorkTemplate::query()->max('position')) + 1,
        ]);

        foreach ($lines as $index => $line) {
            $template->lines()->create([
                'type' => $line['type'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price_cents' => $line['unit_price_cents'] ?? null,
                'part_cost_cents' => null,
                'position' => $index + 1,
            ]);
        }
    }
}
