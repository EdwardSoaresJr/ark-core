<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Ark\Operations\Vehicles\Vehicle;

final class PortalHomeProjection
{
    public function __construct(
        private readonly CustomerVehicleDetailProjection $vehicleDetail,
    ) {}

    /**
     * @return array{
     *     first_name: string,
     *     shop_name: string,
     *     personality_line: string,
     *     response_time_hint: string|null,
     *     business_hours_label: string|null,
     *     phone_display: string,
     *     phone_tel: string,
     *     sms_href: string,
     *     vehicle_cards: list<array{
     *         id: int,
     *         display_name: string,
     *         url: string,
     *         plate: string|null,
     *         active_visit: array{summary: string, status_label: string, opened_at_label: string|null, repair_order_id: int, needs_attention: bool}|null,
     *         document_count: int,
     *         last_visit_label: string|null,
     *     }>,
     *     has_vehicles: bool,
     *     active_visit_count: int,
     *     help_links: list<array{label: string, href: string, description: string}>,
     * }
     */
    public function forCustomer(Customer $customer): array
    {
        $shop = ShopSettings::current();
        $shopName = $shop->displayName();
        $phoneDisplay = PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
        $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
        $hoursLabel = TelephonyBusinessHoursLabel::fromCallFlow();

        $vehicles = $customer->vehicles()
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->get();

        $vehicleCards = $vehicles
            ->map(fn (Vehicle $vehicle): array => $this->vehicleCard($vehicle, $customer))
            ->all();

        $activeVisitCount = collect($vehicleCards)
            ->filter(fn (array $card): bool => $card['active_visit'] !== null)
            ->count();

        return [
            'first_name' => trim((string) $customer->first_name) ?: 'there',
            'shop_name' => $shopName,
            'personality_line' => 'If a visit needs your approval, it shows under that vehicle.',
            'response_time_hint' => $hoursLabel !== '' && $hoursLabel !== 'Closed' ? $hoursLabel : null,
            'business_hours_label' => $hoursLabel,
            'phone_display' => $phoneDisplay,
            'phone_tel' => $phoneTel,
            'sms_href' => 'sms:'.$phoneTel,
            'vehicle_cards' => $vehicleCards,
            'has_vehicles' => $vehicles->isNotEmpty(),
            'active_visit_count' => $activeVisitCount,
            'help_links' => $this->helpLinks($phoneDisplay, $phoneTel),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     display_name: string,
     *     url: string,
     *     plate: string|null,
     *     active_visit: array{summary: string, status_label: string, opened_at_label: string|null, repair_order_id: int, needs_attention: bool}|null,
     *     document_count: int,
     *     last_visit_label: string|null,
     * }
     */
    private function vehicleCard(Vehicle $vehicle, Customer $customer): array
    {
        $detail = $this->vehicleDetail->forVehicle($vehicle, $customer);
        $activeVisit = $detail['active_visit'];

        if ($activeVisit !== null) {
            $activeVisit['needs_attention'] = $activeVisit['status_label'] === 'Awaiting your approval';
        }

        $lastVisit = $detail['last_visit']['occurred_at_label'] ?? null;

        return [
            'id' => (int) $vehicle->id,
            'display_name' => $vehicle->display_name,
            'url' => route('portal.vehicles.show', $vehicle),
            'plate' => filled($vehicle->plate) ? strtoupper((string) $vehicle->plate) : null,
            'active_visit' => $activeVisit,
            'document_count' => (int) ($detail['documents']['total_count'] ?? 0),
            'last_visit_label' => $lastVisit,
        ];
    }

    /**
     * @return list<array{label: string, href: string, description: string}>
     */
    private function helpLinks(string $phoneDisplay, string $phoneTel): array
    {
        if ($phoneTel === '') {
            return [];
        }

        return [
            [
                'label' => 'Text the shop',
                'href' => 'sms:'.$phoneTel,
                'description' => 'Message '.$phoneDisplay.' if you need help with a visit.',
            ],
        ];
    }
}
