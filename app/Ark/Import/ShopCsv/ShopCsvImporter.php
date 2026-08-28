<?php

namespace App\Ark\Import\ShopCsv;

use App\Ark\Import\LegacyArkSmsValueMapper;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Str;

/**
 * Sellable shop onboarding: CSV → Customer (+ Vehicle) authority.
 * No staging table. Preview is read-only; commit writes Customer/Vehicle only.
 */
final class ShopCsvImporter
{
    public const MAX_ROWS = 5000;

    /** @var array<string, list<string>> */
    private const CUSTOMER_ALIASES = [
        'first_name' => ['first_name', 'firstname', 'first', 'first name', 'given_name', 'given name'],
        'last_name' => ['last_name', 'lastname', 'last', 'last name', 'surname', 'family_name'],
        'phone' => ['phone', 'mobile', 'cell', 'cellphone', 'cell_phone', 'primary_phone', 'phone_number'],
        'email' => ['email', 'e-mail', 'email_address', 'email address'],
        'address_line_1' => ['address_line_1', 'address1', 'address', 'street', 'street_address'],
        'address_line_2' => ['address_line_2', 'address2', 'apt', 'suite', 'unit'],
        'city' => ['city'],
        'state' => ['state', 'province', 'region'],
        'postal_code' => ['postal_code', 'postal', 'zip', 'zipcode', 'zip_code'],
        'customer_type' => ['customer_type', 'type', 'billing_class'],
        'notes' => ['notes', 'note', 'customer_notes'],
    ];

    /** @var array<string, list<string>> */
    private const VEHICLE_ALIASES = [
        'vin' => ['vin', 'vehicle_vin'],
        'plate' => ['plate', 'license', 'license_plate', 'vehicle_plate', 'tag'],
        'plate_state' => ['plate_state', 'license_state', 'vehicle_plate_state'],
        'year' => ['year', 'vehicle_year'],
        'make' => ['make', 'vehicle_make'],
        'model' => ['model', 'vehicle_model'],
        'trim' => ['trim', 'vehicle_trim'],
        'color' => ['color', 'vehicle_color'],
        'engine' => ['engine', 'vehicle_engine'],
        'transmission' => ['transmission', 'vehicle_transmission'],
        'drive' => ['drive', 'drivetrain', 'vehicle_drive'],
        'nickname' => ['nickname', 'vehicle_nickname'],
    ];

    public function __construct(
        private readonly LegacyArkSmsValueMapper $mapper = new LegacyArkSmsValueMapper,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     headers: list<string>,
     *     mapped: array<string, string>,
     *     row_count: int,
     *     create: int,
     *     update: int,
     *     skip: int,
     *     vehicles: int,
     *     warnings: list<string>,
     *     sample: list<array{row: int, action: string, customer: string, vehicle: ?string, warning: ?string}>
     * }
     */
    public function preview(string $path): array
    {
        return $this->run($path, commit: false);
    }

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     headers: list<string>,
     *     mapped: array<string, string>,
     *     row_count: int,
     *     create: int,
     *     update: int,
     *     skip: int,
     *     vehicles: int,
     *     warnings: list<string>,
     *     sample: list<array{row: int, action: string, customer: string, vehicle: ?string, warning: ?string}>
     * }
     */
    public function commit(string $path): array
    {
        return $this->run($path, commit: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function run(string $path, bool $commit): array
    {
        $empty = [
            'ok' => false,
            'error' => null,
            'headers' => [],
            'mapped' => [],
            'row_count' => 0,
            'create' => 0,
            'update' => 0,
            'skip' => 0,
            'vehicles' => 0,
            'warnings' => [],
            'sample' => [],
        ];

        if (! is_readable($path)) {
            $empty['error'] = 'CSV file could not be read.';

            return $empty;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $empty['error'] = 'CSV file could not be opened.';

            return $empty;
        }

        try {
            $headerRow = fgetcsv($handle);
            if ($headerRow === false || $headerRow === [null] || $headerRow === []) {
                $empty['error'] = 'CSV has no header row.';

                return $empty;
            }

            $headers = array_map(fn ($h) => trim((string) $h), $headerRow);
            $map = $this->mapHeaders($headers);

            if (! isset($map['first_name']) && ! isset($map['last_name']) && ! isset($map['phone']) && ! isset($map['email'])) {
                $empty['error'] = 'Need at least a name, phone, or email column.';
                $empty['headers'] = $headers;
                $empty['mapped'] = $map;

                return $empty;
            }

            $create = 0;
            $update = 0;
            $skip = 0;
            $vehicles = 0;
            $warnings = [];
            $sample = [];
            $rowNumber = 1;

            while (($raw = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowEmpty($raw)) {
                    continue;
                }

                if (($create + $update + $skip) >= self::MAX_ROWS) {
                    $warnings[] = 'Stopped at '.self::MAX_ROWS.' data rows (limit).';
                    break;
                }

                $assoc = $this->associate($headers, $raw);
                $plan = $this->planRow($assoc, $map, $rowNumber);

                if ($plan['action'] === 'skip') {
                    $skip++;
                    if ($plan['warning'] !== null) {
                        $warnings[] = $plan['warning'];
                    }
                    if (count($sample) < 12) {
                        $sample[] = $this->sampleRow($plan);
                    }
                    continue;
                }

                if ($commit) {
                    $customer = $this->writeCustomer($plan);
                    if ($plan['vehicle_data'] !== null) {
                        $this->writeVehicle($customer, $plan['vehicle_data']);
                        $vehicles++;
                    }
                } elseif ($plan['vehicle_data'] !== null) {
                    $vehicles++;
                }

                if ($plan['action'] === 'create') {
                    $create++;
                } else {
                    $update++;
                }

                if (count($sample) < 12) {
                    $sample[] = $this->sampleRow($plan);
                }
            }

            return [
                'ok' => true,
                'error' => null,
                'headers' => $headers,
                'mapped' => $map,
                'row_count' => $create + $update + $skip,
                'create' => $create,
                'update' => $update,
                'skip' => $skip,
                'vehicles' => $vehicles,
                'warnings' => array_slice(array_values(array_unique($warnings)), 0, 40),
                'sample' => $sample,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string> field => header
     */
    private function mapHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $key = $this->normalizeHeader($header);
            if ($key !== '') {
                $normalized[$key] = $header;
            }
        }

        $mapped = [];
        foreach ([...self::CUSTOMER_ALIASES, ...self::VEHICLE_ALIASES] as $field => $aliases) {
            foreach ($aliases as $alias) {
                $aliasKey = $this->normalizeHeader($alias);
                if (isset($normalized[$aliasKey])) {
                    $mapped[$field] = $normalized[$aliasKey];
                    break;
                }
            }
        }

        return $mapped;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[\s\-]+/', '_', $header) ?? $header;

        return preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;
    }

    /**
     * @param  list<string|null>  $raw
     */
    private function rowEmpty(array $raw): bool
    {
        foreach ($raw as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $raw
     * @return array<string, string>
     */
    private function associate(array $headers, array $raw): array
    {
        $assoc = [];
        foreach ($headers as $i => $header) {
            $assoc[$header] = trim((string) ($raw[$i] ?? ''));
        }

        return $assoc;
    }

    /**
     * @param  array<string, string>  $assoc
     * @param  array<string, string>  $map
     * @return array{
     *     row: int,
     *     action: string,
     *     customer: string,
     *     vehicle: ?string,
     *     warning: ?string,
     *     attributes: array<string, mixed>,
     *     vehicle_data: ?array<string, mixed>,
     *     existing_id: ?int
     * }
     */
    private function planRow(array $assoc, array $map, int $rowNumber): array
    {
        $get = function (string $field) use ($assoc, $map): string {
            $header = $map[$field] ?? null;

            return $header === null ? '' : ($assoc[$header] ?? '');
        };

        $first = trim($get('first_name'));
        $last = trim($get('last_name'));
        $phone = $this->mapper->normalizePhone($get('phone') !== '' ? $get('phone') : null);
        $email = $this->mapper->normalizeEmail($get('email') !== '' ? $get('email') : null);

        if ($first === '' && $last === '' && $phone === null && $email === null) {
            return [
                'row' => $rowNumber,
                'action' => 'skip',
                'customer' => '(empty)',
                'vehicle' => null,
                'warning' => "Row {$rowNumber}: skipped — no name, phone, or email.",
                'attributes' => [],
                'vehicle_data' => null,
                'existing_id' => null,
            ];
        }

        if ($first === '' && $last === '') {
            $first = $phone !== null ? 'Customer' : 'Imported';
            $last = $phone !== null ? substr($phone, -4) : 'Customer';
        }

        $attributes = [
            'first_name' => Str::limit($first, 100, ''),
            'last_name' => Str::limit($last !== '' ? $last : '', 100, ''),
            'phone' => $phone,
            'email' => $email,
            'address_line_1' => Str::limit($get('address_line_1'), 255, '') ?: null,
            'address_line_2' => Str::limit($get('address_line_2'), 255, '') ?: null,
            'city' => Str::limit($get('city'), 100, '') ?: null,
            'state' => Str::limit($get('state'), 50, '') ?: null,
            'postal_code' => Str::limit($get('postal_code'), 20, '') ?: null,
            'customer_type' => $this->mapper->mapCustomerType($get('customer_type') !== '' ? $get('customer_type') : 'Retail'),
            'notes' => $get('notes') !== '' ? Str::limit($get('notes'), 5000, '') : null,
        ];

        $existing = $this->findCustomer($phone, $email);
        $action = $existing === null ? 'create' : 'update';
        $label = trim($attributes['first_name'].' '.$attributes['last_name']);
        if ($phone) {
            $label .= ' · '.$phone;
        }

        $vehicleData = $this->vehiclePayload($get);
        $vehicleLabel = null;
        if ($vehicleData !== null) {
            $vehicleLabel = trim(implode(' ', array_filter([
                $vehicleData['year'] ?? null,
                $vehicleData['make'] ?? null,
                $vehicleData['model'] ?? null,
                isset($vehicleData['plate']) ? '· '.$vehicleData['plate'] : null,
            ], static fn ($v) => $v !== null && $v !== '')));
            if ($vehicleLabel === '') {
                $vehicleLabel = null;
            }
        }

        return [
            'row' => $rowNumber,
            'action' => $action,
            'customer' => $label,
            'vehicle' => $vehicleLabel,
            'warning' => null,
            'attributes' => $attributes,
            'vehicle_data' => $vehicleData,
            'existing_id' => $existing?->id,
        ];
    }

    /**
     * @param  callable(string): string  $get
     * @return array<string, mixed>|null
     */
    private function vehiclePayload(callable $get): ?array
    {
        $vin = $this->mapper->normalizeVin(
            $get('vin') !== '' ? $get('vin') : null,
            new \App\Ark\Import\LegacyImportReport,
            'csv',
        );
        $yearRaw = $get('year');
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;
        $make = Str::limit($get('make'), 100, '') ?: null;
        $model = Str::limit($get('model'), 100, '') ?: null;
        $plate = Str::limit($get('plate'), 32, '') ?: null;

        if ($vin === null && $plate === null && $year === null && $make === null && $model === null) {
            return null;
        }

        if ($vin === null && $plate === null && ($year === null || $make === null || $model === null)) {
            return null;
        }

        return [
            'vin' => $vin,
            'plate' => $plate,
            'plate_state' => Str::limit($get('plate_state'), 10, '') ?: null,
            'year' => $year,
            'make' => $make,
            'model' => $model,
            'trim' => Str::limit($get('trim'), 100, '') ?: null,
            'color' => Str::limit($get('color'), 50, '') ?: null,
            'engine' => Str::limit($get('engine'), 100, '') ?: null,
            'transmission' => Str::limit($get('transmission'), 100, '') ?: null,
            'drive' => Str::limit($get('drive'), 50, '') ?: null,
            'nickname' => Str::limit($get('nickname'), 100, '') ?: null,
        ];
    }

    /**
     * @param  array{row: int, action: string, customer: string, vehicle: ?string, warning: ?string}  $plan
     * @return array{row: int, action: string, customer: string, vehicle: ?string, warning: ?string}
     */
    private function sampleRow(array $plan): array
    {
        return [
            'row' => $plan['row'],
            'action' => $plan['action'],
            'customer' => $plan['customer'],
            'vehicle' => $plan['vehicle'],
            'warning' => $plan['warning'],
        ];
    }

    private function findCustomer(?string $phone, ?string $email): ?Customer
    {
        if ($phone !== null) {
            $byPhone = Customer::query()->where('phone', $phone)->first();
            if ($byPhone !== null) {
                return $byPhone;
            }
        }

        if ($email !== null) {
            return Customer::query()->where('email', $email)->first();
        }

        return null;
    }

    /**
     * @param  array{attributes: array<string, mixed>, existing_id?: int|null}  $plan
     */
    private function writeCustomer(array $plan): Customer
    {
        $attributes = $plan['attributes'];
        $id = $plan['existing_id'] ?? null;

        if ($id !== null) {
            $customer = Customer::query()->findOrFail($id);
            $customer->fill(array_filter(
                $attributes,
                static fn ($value) => $value !== null && $value !== '',
            ));
            $customer->save();

            return $customer;
        }

        return Customer::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $vehicleData
     */
    private function writeVehicle(Customer $customer, array $vehicleData): Vehicle
    {
        $query = Vehicle::query()->where('customer_id', $customer->id);

        if (! empty($vehicleData['vin'])) {
            $existing = (clone $query)->where('vin', $vehicleData['vin'])->first();
            if ($existing !== null) {
                $existing->fill($vehicleData);
                $existing->save();

                return $existing;
            }
        }

        if (! empty($vehicleData['plate'])) {
            $existing = (clone $query)->where('plate', $vehicleData['plate'])->first();
            if ($existing !== null) {
                $existing->fill($vehicleData);
                $existing->save();

                return $existing;
            }
        }

        return Vehicle::query()->create([
            ...$vehicleData,
            'customer_id' => $customer->id,
        ]);
    }
}
