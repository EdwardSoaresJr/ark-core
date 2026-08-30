<?php

namespace App\Ark\Operations\Settings;

use App\Ark\Operations\Appointments\AppointmentCapacityBasis;
use App\Ark\Operations\Appointments\AppointmentCapacityEnforcement;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
use App\Ark\Operations\Appointments\SchedulingHours;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Parts\CustomerPartPresentationProfile;
use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

#[Fillable([
    'shop_name',
    'shop_timezone',
    'phone',
    'email',
    'website',
    'logo_path',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'postal_code',
    'default_labor_rate_cents',
    'labor_categories',
    'tax_enabled',
    'tax_label',
    'default_tax_rate',
    'taxable_labor',
    'taxable_parts',
    'taxable_shop_fees',
    'shop_fee_enabled',
    'shop_fee_rate',
    'shop_fee_cap_cents',
    'default_deposit_enabled',
    'default_deposit_include_parts',
    'default_deposit_include_diagnostics',
    'default_deposit_diagnostic_labor_category_keys',
    'parts_matrix',
    'parts_matrices',
    'customer_types',
    'estimate_disclaimer',
    'invoice_disclaimer',
    'recommendation_disclaimer',
    'authorization_language',
    'portal_signature_required',
    'customer_type_disclaimers',
    'estimate_validity_days',
    'default_recommendation_intent',
    'default_notes_private',
    'default_visit_mode',
    'default_estimate_state',
    'qz_printing_enabled',
    'qz_printing_key_tag_printer',
    'qz_printing_oil_sticker_printer',
    'qz_key_tag_label_width_mm',
    'qz_key_tag_label_height_mm',
    'qz_key_tag_vin_display',
    'qz_key_tag_media_type',
    'qz_key_tag_orientation',
    'qz_raster_dpi',
    'oil_change_sticker_next_due_months',
    'oil_change_interval_miles',
    'maintenance_engine_oil',
    'square_enabled',
    'square_terminal_device_id',
    'square_terminal_enabled',
    'square_keyed_enabled',
    'square_portal_pay_enabled',
    'square_email_pay_enabled',
    'telephony_enabled',
    'telephony_inbound_number',
    'telephony_provider',
    'telephony_call_flow',
    'asterisk_voice',
    'mobile_push',
    'mobile_push_firebase_service_account',
    'communications_channels',
    'message_actions',
    'twilio_account_sid',
    'twilio_api_key_sid',
    'twilio_api_key_secret',
    'twilio_voice_twiml_app_sid',
    'twilio_fcm_credential_sid',
    'twilio_apns_voip_credential_sid',
    'square_application_id',
    'square_location_id',
    'square_environment',
    'partstech_base_url',
    'partstech_catalog_path',
    'partstech_username',
    'postmark_reply_to',
    'postmark_reply_to_name',
    'postmark_message_stream_id',
    'ark_mail_status',
    'ark_mail_tenant_public_id',
    'ark_mail_from_email',
    'ark_mail_service_url',
    'openai_transcription_model',
    'openai_analysis_model',
    'shop_overhead_state',
    'shop_overhead_per_hour_cents',
    'shop_excellence_targets',
    'public_surface_settings',
    'growth_integrations',
    'growth_google_service_account',
    'learn_training_gate_enabled',
    'appointments_enabled',
    'shop_memory',
    'inspection_comparison',
    'appointment_slot_minutes',
    'appointment_capacity_basis',
    'appointment_scheduling_target_percent',
    'appointment_capacity_enforcement',
    'operational_profile',
    'scheduling_hours',
    'appointment_request_availability',
    'workstation_idle_lock_minutes',
])]
class ShopSettings extends Model
{
    /** Used only for first install / migration backfill — not a runtime display fallback. */
    public const INSTALL_DEFAULT_TIMEZONE = 'America/Denver';

    public const DEFAULT_PARTS_MATRIX = [
        ['from' => '0', 'to' => '50', 'multiplier' => '2.20'],
        ['from' => '50', 'to' => '100', 'multiplier' => '1.90'],
        ['from' => '100', 'to' => '250', 'multiplier' => '1.60'],
        ['from' => '250', 'to' => null, 'multiplier' => '1.40'],
    ];

    public const DEFAULT_PARTS_MATRIX_KEY = 'aft-parts';

    public const DEFAULT_LABOR_CATEGORY_KEY = 'mechanical';

    public const WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY = 'repairpal';

    public const WARRANTY_OTHER_LABOR_CATEGORY_KEY = 'warranty-other';

    /** @var list<string> */
    public const LABOR_MODIFIER_LOCKED_BY_DEFAULT_CATEGORY_KEYS = [
        self::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
        self::WARRANTY_OTHER_LABOR_CATEGORY_KEY,
    ];

    public const COMEBACK_LABOR_CATEGORY_KEY = 'comeback';

    /** @var list<string> */
    public const PRIMARY_BILLING_CLASSES = [
        'Retail',
        'Fleet',
        'Warranty',
        'RepairPal',
        'Internal',
        'Wholesale',
        'Comeback',
    ];

    public const DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS = [
        'diagnostic',
    ];

    public const DEFAULT_LABOR_CATEGORIES = [
        [
            'key' => 'mechanical',
            'name' => 'Mechanical',
            'rate_cents' => 16500,
            'minimum_hours' => '0.50',
            'rounding_rule' => 'quarter',
            'is_default' => true,
            'allows_modifiers' => true,
        ],
        [
            'key' => 'diagnostic',
            'name' => 'Diagnostic',
            'rate_cents' => 16500,
            'minimum_hours' => '1.00',
            'rounding_rule' => 'quarter',
            'is_default' => false,
            'allows_modifiers' => true,
        ],
        [
            'key' => 'programming',
            'name' => 'Programming',
            'rate_cents' => 16500,
            'minimum_hours' => '0.50',
            'rounding_rule' => 'quarter',
            'is_default' => false,
            'allows_modifiers' => true,
        ],
        [
            'key' => 'fabrication',
            'name' => 'Fabrication',
            'rate_cents' => 16500,
            'minimum_hours' => '0.50',
            'rounding_rule' => 'quarter',
            'is_default' => false,
            'allows_modifiers' => true,
        ],
        [
            'key' => 'courtesy',
            'name' => 'Courtesy',
            'rate_cents' => 0,
            'minimum_hours' => '0.00',
            'rounding_rule' => 'tenth',
            'is_default' => false,
            'allows_modifiers' => true,
        ],
        [
            'key' => self::COMEBACK_LABOR_CATEGORY_KEY,
            'name' => 'Comeback',
            'rate_cents' => 0,
            'minimum_hours' => '0.00',
            'rounding_rule' => 'tenth',
            'is_default' => false,
            'allows_modifiers' => true,
        ],
        [
            'key' => self::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
            'name' => 'RepairPal',
            'rate_cents' => 15000,
            'minimum_hours' => '1.00',
            'rounding_rule' => 'quarter',
            'is_default' => false,
            'allows_modifiers' => false,
        ],
        [
            'key' => self::WARRANTY_OTHER_LABOR_CATEGORY_KEY,
            'name' => 'Warranty — Other',
            'rate_cents' => 16500,
            'minimum_hours' => '1.00',
            'rounding_rule' => 'quarter',
            'is_default' => false,
            'allows_modifiers' => false,
        ],
    ];

    public const SURVIVABILITY_TARGETS = [
        'parts_gp' => '45-60%',
        'labor_gp' => '70-80%+',
        'blended_ro_gp' => '55-65%',
        'diagnostics' => 'Protected high-value labor',
    ];

    public const DEFAULT_PARTS_MATRICES = [
        [
            'key' => 'oem-parts',
            'name' => 'OEM Parts',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '25.00', 'markup_percentage' => '90.00', 'sort_order' => 1],
                ['min_cost' => '25.01', 'max_cost' => '100.00', 'markup_percentage' => '70.00', 'sort_order' => 2],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '55.00', 'sort_order' => 3],
                ['min_cost' => '250.01', 'max_cost' => '500.00', 'markup_percentage' => '45.00', 'sort_order' => 4],
                ['min_cost' => '500.01', 'max_cost' => '1000.00', 'markup_percentage' => '40.00', 'sort_order' => 5],
                ['min_cost' => '1000.01', 'max_cost' => '2500.00', 'markup_percentage' => '35.00', 'sort_order' => 6],
                ['min_cost' => '2500.01', 'max_cost' => '99999.99', 'markup_percentage' => '32.00', 'sort_order' => 7],
            ],
        ],
        [
            'key' => 'aft-parts',
            'name' => 'AFT Parts',
            'is_default' => true,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '5.00', 'markup_percentage' => '180.00', 'sort_order' => 1],
                ['min_cost' => '5.01', 'max_cost' => '10.00', 'markup_percentage' => '150.00', 'sort_order' => 2],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '120.00', 'sort_order' => 3],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '110.00', 'sort_order' => 4],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '95.00', 'sort_order' => 5],
                ['min_cost' => '100.01', 'max_cost' => '200.00', 'markup_percentage' => '85.00', 'sort_order' => 6],
                ['min_cost' => '200.01', 'max_cost' => '500.00', 'markup_percentage' => '70.00', 'sort_order' => 7],
                ['min_cost' => '500.01', 'max_cost' => '1000.00', 'markup_percentage' => '55.00', 'sort_order' => 8],
                ['min_cost' => '1000.01', 'max_cost' => '2500.00', 'markup_percentage' => '45.00', 'sort_order' => 9],
                ['min_cost' => '2500.01', 'max_cost' => '99999.99', 'markup_percentage' => '38.00', 'sort_order' => 10],
            ],
        ],
        [
            'key' => 'fluids',
            'name' => 'Fluids',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '10.00', 'markup_percentage' => '150.00', 'sort_order' => 1],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '110.00', 'sort_order' => 2],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '80.00', 'sort_order' => 3],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '65.00', 'sort_order' => 4],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '55.00', 'sort_order' => 5],
                ['min_cost' => '250.01', 'max_cost' => '99999.99', 'markup_percentage' => '45.00', 'sort_order' => 6],
            ],
        ],
        [
            'key' => 'warranty-no-markup',
            'name' => 'Warranty (No Markup)',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => null, 'markup_percentage' => '0.00', 'sort_order' => 1],
            ],
        ],
        [
            'key' => 'filters',
            'name' => 'Filters',
            'is_default' => false,
            'rows' => [
                ['min_cost' => '0.00', 'max_cost' => '10.00', 'markup_percentage' => '130.00', 'sort_order' => 1],
                ['min_cost' => '10.01', 'max_cost' => '25.00', 'markup_percentage' => '95.00', 'sort_order' => 2],
                ['min_cost' => '25.01', 'max_cost' => '50.00', 'markup_percentage' => '75.00', 'sort_order' => 3],
                ['min_cost' => '50.01', 'max_cost' => '100.00', 'markup_percentage' => '60.00', 'sort_order' => 4],
                ['min_cost' => '100.01', 'max_cost' => '250.00', 'markup_percentage' => '50.00', 'sort_order' => 5],
                ['min_cost' => '250.01', 'max_cost' => '99999.99', 'markup_percentage' => '45.00', 'sort_order' => 6],
            ],
        ],
    ];

    public const DEFAULT_CUSTOMER_TYPES = [
        ['name' => 'Retail', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
        ['name' => 'Fleet', 'document_presentation_profile' => 'fleet', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
        ['name' => 'Warranty', 'document_presentation_profile' => 'warranty', 'shop_fees_enabled' => false, 'shop_fee_rate_override' => null, 'fee_override' => 'none', 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'warranty-no-markup'],
        ['name' => 'RepairPal', 'document_presentation_profile' => 'repairpal', 'shop_fees_enabled' => false, 'shop_fee_rate_override' => null, 'fee_override' => 'none', 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'warranty-no-markup'],
        ['name' => 'Internal', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => false, 'shop_fee_rate_override' => null, 'fee_override' => 'none', 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'warranty-no-markup'],
        ['name' => 'Wholesale', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
        ['name' => 'Comeback', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => false, 'shop_fee_rate_override' => null, 'fee_override' => 'none', 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => 'warranty-no-markup'],
        ['name' => 'Business', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'none', 'discount_amount' => null, 'default_parts_matrix_key' => null],
        ['name' => 'Military', 'document_presentation_profile' => 'retail', 'shop_fees_enabled' => true, 'shop_fee_rate_override' => null, 'fee_override' => null, 'discount_type' => 'labor', 'discount_amount' => '10.00', 'default_parts_matrix_key' => null],
    ];

    public const DEFAULT_ESTIMATE_DISCLAIMER = <<<'TEXT'
Diagnostic findings are based on conditions observed at the time of inspection. Additional repairs may be required if further issues are discovered during disassembly. Parts and labor are covered by the shop warranty unless otherwise noted. Customer-supplied and used parts may carry no warranty. Pricing is valid for a limited time and may change due to supplier availability. Vehicle may require road testing before and after repairs.
TEXT;

    public const DEFAULT_INVOICE_DISCLAIMER = <<<'TEXT'
Diagnostic findings are based on conditions observed at the time of inspection. Additional repairs may be required if further issues are discovered during disassembly. Parts and labor are covered by the shop warranty unless otherwise noted. Customer-supplied and used parts may carry no warranty. Vehicle may require road testing before and after repairs.
TEXT;

    public const DEFAULT_RECOMMENDATION_DISCLAIMER = 'Recommendations are based on verified findings and current operating conditions. Further testing or disassembly may change the recommended repair path.';

    public const DEFAULT_AUTHORIZATION_LANGUAGE = <<<'TEXT'
By approving this estimate, I authorize {shop_name} to perform the approved repairs and services described above.

I acknowledge that additional repairs may require separate authorization and agree to pay for authorized work performed, including diagnostic charges where applicable.
TEXT;

    public const DEFAULT_CUSTOMER_TYPE_DISCLAIMERS = [
        'retail' => 'This estimate reflects repairs recommended based on our inspection. Additional concerns may exist that were not apparent during initial evaluation.',
        'fleet' => 'Repairs will not begin until authorization is received from the fleet management company. Additional repairs requiring authorization may affect completion time.',
        'warranty' => 'Estimate subject to third-party warranty authorization. Customer remains responsible for any non-covered charges.',
        'internal' => 'Internal repair order. Customer authorization not required.',
        'dealer' => 'Pricing and warranty terms are governed by the dealer agreement on file.',
        'wholesale' => 'Pricing and warranty terms are governed by the wholesale agreement on file.',
        'business' => 'This estimate reflects repairs recommended based on our inspection. Additional concerns may exist that were not apparent during initial evaluation.',
        'repairpal' => 'RepairPal program estimate. Customer remains responsible for any non-covered charges.',
        'comeback' => 'Comeback repair order for shop warranty rework. Labor is not billed unless authorized separately.',
        'military' => 'This estimate reflects repairs recommended based on our inspection. Additional concerns may exist that were not apparent during initial evaluation.',
    ];

    protected $casts = [
        'default_notes_private' => 'boolean',
        'qz_printing_enabled' => 'boolean',
        'qz_key_tag_label_width_mm' => 'decimal:2',
        'qz_key_tag_label_height_mm' => 'decimal:2',
        'qz_raster_dpi' => 'integer',
        'oil_change_sticker_next_due_months' => 'integer',
        'oil_change_interval_miles' => 'integer',
        'maintenance_engine_oil' => 'array',
        'tax_enabled' => 'boolean',
        'taxable_labor' => 'boolean',
        'taxable_parts' => 'boolean',
        'taxable_shop_fees' => 'boolean',
        'shop_fee_enabled' => 'boolean',
        'default_deposit_enabled' => 'boolean',
        'default_deposit_include_parts' => 'boolean',
        'default_deposit_include_diagnostics' => 'boolean',
        'default_deposit_diagnostic_labor_category_keys' => 'array',
        'square_enabled' => 'boolean',
        'telephony_enabled' => 'boolean',
        'telephony_call_flow' => 'array',
        'asterisk_voice' => 'array',
        'mobile_push' => 'array',
        'mobile_push_firebase_service_account' => 'encrypted',
        'communications_channels' => 'array',
        'message_actions' => 'array',
        'messenger_app_secret' => 'encrypted',
        'messenger_page_access_token' => 'encrypted',
        'twilio_auth_token' => 'encrypted',
        'twilio_api_key_secret' => 'encrypted',
        'square_access_token' => 'encrypted',
        'square_webhook_signature_key' => 'encrypted',
        'partstech_api_key' => 'encrypted',
        'partstech_password' => 'encrypted',
        'postmark_token' => 'encrypted',
        'ark_mail_credential' => 'encrypted',
        'ark_mail_connected_at' => 'datetime',
        'openai_api_key' => 'encrypted',
        'default_tax_rate' => 'decimal:3',
        'shop_fee_rate' => 'decimal:3',
        'shop_fee_cap_cents' => 'integer',
        'parts_matrix' => 'array',
        'parts_matrices' => 'array',
        'labor_categories' => 'array',
        'customer_types' => 'array',
        'customer_type_disclaimers' => 'array',
        'shop_overhead_state' => 'array',
        'shop_overhead_per_hour_cents' => 'integer',
        'shop_excellence_targets' => 'array',
        'public_surface_settings' => 'array',
        'growth_integrations' => 'array',
        'growth_google_service_account' => 'encrypted',
        'learn_training_gate_enabled' => 'boolean',
        'appointments_enabled' => 'boolean',
        'shop_memory' => 'array',
        'inspection_comparison' => 'array',
        'appointment_slot_minutes' => 'integer',
        'appointment_capacity_basis' => 'string',
        'appointment_scheduling_target_percent' => 'integer',
        'appointment_capacity_enforcement' => 'string',
        'scheduling_hours' => 'array',
        'appointment_request_availability' => 'array',
        'portal_signature_required' => 'boolean',
    ];

    private static ?self $current = null;

    public static function current(): self
    {
        if (self::$current !== null) {
            return self::$current;
        }

        if (! Schema::hasTable('shop_settings')) {
            return self::unsavedInstallDefaults();
        }

        $existing = self::query()->first();

        if ($existing !== null) {
            return self::$current = $existing;
        }

        $defaults = self::installDefaultsForExistingColumns();

        if ($defaults === []) {
            return self::unsavedInstallDefaults();
        }

        return self::$current = self::query()->create($defaults);
    }

    public static function reloadCurrent(): self
    {
        self::forgetCurrent();

        return self::current();
    }

    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    /**
     * Street line formatted to match Google Business Profile prominence (suite as trailing token).
     */
    public function googleMatchedStreetAddress(): string
    {
        $line1 = rtrim(trim((string) $this->address_line_1), '.');
        $line2 = trim((string) $this->address_line_2);

        if ($line1 === '') {
            return $line2;
        }

        if ($line2 === '') {
            return $line1;
        }

        $unit = preg_replace('/^(?:unit|suite|ste|#)\s*/iu', '', $line2) ?? $line2;
        $unit = trim($unit);

        return $unit !== '' ? trim($line1.' '.$unit) : $line1;
    }

    /**
     * Street line for public NAP / JSON-LD when shop settings have no street yet.
     */
    public function publicationStreetAddress(): string
    {
        $matched = $this->googleMatchedStreetAddress();

        return $matched !== '' ? $matched : '100 Main Street Suite A';
    }

    public function displayName(): string
    {
        $name = trim((string) $this->shop_name);

        return $name !== '' ? $name : 'Demo Auto Repair';
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            self::forgetCurrent();
        });

        static::deleted(static function (): void {
            self::forgetCurrent();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function installDefaults(): array
    {
        return [
            'shop_timezone' => self::INSTALL_DEFAULT_TIMEZONE,
            'default_labor_rate_cents' => 16500,
            'labor_categories' => self::DEFAULT_LABOR_CATEGORIES,
            'tax_enabled' => false,
            'tax_label' => 'Tax',
            'default_tax_rate' => 0,
            'taxable_labor' => false,
            'taxable_parts' => true,
            'taxable_shop_fees' => false,
            'shop_fee_enabled' => false,
            'shop_fee_rate' => 0,
            'shop_fee_cap_cents' => null,
            'default_deposit_enabled' => true,
            'default_deposit_include_parts' => true,
            'default_deposit_include_diagnostics' => true,
            'default_deposit_diagnostic_labor_category_keys' => self::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS,
            'parts_matrix' => self::DEFAULT_PARTS_MATRIX,
            'parts_matrices' => self::DEFAULT_PARTS_MATRICES,
            'customer_types' => self::DEFAULT_CUSTOMER_TYPES,
            'estimate_disclaimer' => self::DEFAULT_ESTIMATE_DISCLAIMER,
            'invoice_disclaimer' => self::DEFAULT_INVOICE_DISCLAIMER,
            'recommendation_disclaimer' => self::DEFAULT_RECOMMENDATION_DISCLAIMER,
            'authorization_language' => self::DEFAULT_AUTHORIZATION_LANGUAGE,
            'portal_signature_required' => false,
            'customer_type_disclaimers' => self::defaultCustomerTypeDisclaimerMap(),
            'estimate_validity_days' => 30,
            'default_recommendation_intent' => 'maintenance',
            'default_notes_private' => true,
            'default_visit_mode' => RepairOrderVisitMode::DropOff->value,
            'default_estimate_state' => RepairOrderStatus::Estimate->value,
            'learn_training_gate_enabled' => true,
            'appointments_enabled' => false,
            'shop_memory' => \App\Ark\ShopMemory\ShopMemoryProviderCatalog::defaultSettings(),
            'appointment_slot_minutes' => 30,
            'appointment_capacity_basis' => AppointmentCapacityBasis::LimitingResource->value,
            'appointment_scheduling_target_percent' => 100,
            'appointment_capacity_enforcement' => AppointmentCapacityEnforcement::Warn->value,
            'scheduling_hours' => null,
            'appointment_request_availability' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function installDefaultsForExistingColumns(): array
    {
        return collect(self::installDefaults())
            ->filter(fn (mixed $value, string $column): bool => Schema::hasColumn('shop_settings', $column))
            ->all();
    }

    private static function unsavedInstallDefaults(): self
    {
        $settings = new self;
        $settings->forceFill(self::installDefaults());

        return $settings;
    }

    public function appointmentsEnabled(): bool
    {
        return (bool) $this->appointments_enabled;
    }

    public function appointmentSlotMinutes(): int
    {
        return AppointmentSlotMinutes::resolve($this);
    }

    public function operationalProfile(): ?OperationalProfile
    {
        return OperationalProfile::tryFrom((string) ($this->operational_profile ?? ''));
    }

    /**
     * Effective staff booking windows: custom overrides, else Business Hours.
     *
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public function schedulingHours(): array
    {
        if ($this->usesCustomSchedulingHours()) {
            return SchedulingHours::normalize($this->scheduling_hours);
        }

        return SchedulingHours::fromBusinessHours(
            \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($this)->weeklyHours(),
        );
    }

    /**
     * True when the shop blacklisted days or narrowed hours for scheduling.
     */
    public function usesCustomSchedulingHours(): bool
    {
        return SchedulingHours::isCustom(
            is_array($this->scheduling_hours) ? $this->scheduling_hours : null,
        );
    }

    /**
     * @return array{
     *     weekly: array<string, array{enabled: bool}>,
     *     horizon_days: int,
     *     minimum_notice_days: int
     * }
     */
    public function appointmentRequestAvailability(): array
    {
        return AppointmentRequestAvailability::forShop($this);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultTelephonyCallFlow(): array
    {
        return [
            'timezone' => self::INSTALL_DEFAULT_TIMEZONE,
            'weekly_hours' => [
                'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
                'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
                'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '18:00'],
            ],
            'closed_dates' => [],
            'hours_bypass_numbers' => [],
            'voicemail_greeting' => 'Thank you for calling. We are unable to take your call right now. Please leave a message after the tone.',
            'closed_greeting' => 'Thank you for calling. We are currently closed. Please leave a message after the tone.',
            'recording_disclaimer' => 'This call may be recorded for quality and training purposes.',
            'cell_whisper_prompt' => '',
            'caller_ring_tone' => 'us',
            'record_inbound_calls' => false,
            'record_outbound_calls' => false,
            'dial_timeout_seconds' => 25,
            'presence_timeout_minutes' => 30,
            'owned_popup_timeout_seconds' => 8,
            'comms_attention_gate_enabled' => true,
            'comms_escalation_enabled' => true,
            'comms_escalation_delay_minutes' => 3,
            'comms_escalation_cooldown_minutes' => 30,
            'comms_browser_notifications_enabled' => true,
            'missed_call_rescue_enabled' => false,
            'missed_call_rescue_delay_seconds' => 120,
            'missed_call_rescue_cooldown_minutes' => 60,
            'missed_call_rescue_text_open' => '',
            'missed_call_rescue_text_closed' => '',
        ];
    }

    public function defaultVisitMode(): RepairOrderVisitMode
    {
        return RepairOrderVisitMode::tryFrom((string) $this->default_visit_mode)
            ?? RepairOrderVisitMode::DropOff;
    }

    public function defaultLaborRate(): string
    {
        return number_format($this->defaultLaborRateCents() / 100, 2, '.', '');
    }

    public function defaultLaborRateCents(): int
    {
        return (int) ($this->defaultLaborCategory()['rate_cents'] ?? $this->default_labor_rate_cents);
    }

    public function laborCategoryAllowsModifiers(string $key): bool
    {
        $stored = collect($this->labor_categories ?: [])
            ->firstWhere('key', $key);

        if (is_array($stored)) {
            return $this->resolveAllowsModifiers($stored);
        }

        $default = collect(self::DEFAULT_LABOR_CATEGORIES)
            ->firstWhere('key', $key);

        if (is_array($default)) {
            return $this->resolveAllowsModifiers($default);
        }

        return $this->defaultAllowsModifiersForCategoryKey($key);
    }

    public function defaultAllowsModifiersForCategoryKey(string $key): bool
    {
        return ! in_array($key, self::LABOR_MODIFIER_LOCKED_BY_DEFAULT_CATEGORY_KEYS, true);
    }

    /**
     * @param  array<string, mixed>  $category
     */
    public function resolveAllowsModifiers(array $category): bool
    {
        if (array_key_exists('allows_modifiers', $category)) {
            return (bool) $category['allows_modifiers'];
        }

        return $this->defaultAllowsModifiersForCategoryKey((string) $category['key']);
    }

    /**
     * @return array<int, array{key: string, name: string, rate_cents: int, minimum_hours: string, rounding_rule: string, is_default: bool, allows_modifiers: bool}>
     */
    public function laborCategories(): array
    {
        return collect($this->labor_categories ?: self::DEFAULT_LABOR_CATEGORIES)
            ->map(fn (array $category): array => [
                'key' => (string) $category['key'],
                'name' => (string) $category['name'],
                'rate_cents' => (int) $category['rate_cents'],
                'minimum_hours' => (string) $category['minimum_hours'],
                'rounding_rule' => (string) $category['rounding_rule'],
                'is_default' => (bool) ($category['is_default'] ?? false),
                'allows_modifiers' => $this->resolveAllowsModifiers($category),
            ])
            ->sortBy(fn (array $category): string => mb_strtolower($category['name']))
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, name: string, rate_cents: int, minimum_hours: string, rounding_rule: string, is_default: bool}
     */
    public function defaultLaborCategory(): array
    {
        return collect($this->laborCategories())
            ->firstWhere('is_default', true)
            ?? $this->laborCategories()[0];
    }

    /**
     * Flip which labor category is the shop default for new labor lines / Operation Class fallback.
     */
    public function setDefaultLaborCategoryKey(string $categoryKey): void
    {
        $categories = $this->laborCategories();
        $keys = collect($categories)->pluck('key')->all();

        if (! in_array($categoryKey, $keys, true)) {
            throw new InvalidArgumentException("Unknown labor category [{$categoryKey}].");
        }

        $updated = collect($categories)
            ->map(function (array $category) use ($categoryKey): array {
                $category['is_default'] = $category['key'] === $categoryKey;

                return $category;
            })
            ->values()
            ->all();

        $defaultRateCents = (int) collect($updated)->firstWhere('is_default', true)['rate_cents'];

        $this->update([
            'labor_categories' => $updated,
            'default_labor_rate_cents' => $defaultRateCents,
        ]);
    }

    /**
     * @return array{key: string, name: string, rate_cents: int, minimum_hours: string, rounding_rule: string, is_default: bool}|null
     */
    public function laborCategoryByKey(?string $key): ?array
    {
        if (! $key) {
            return $this->defaultLaborCategory();
        }

        return collect($this->laborCategories())->firstWhere('key', $key);
    }

    /**
     * @return array{category_key: string, rate: string, rate_cents: int}
     */
    public function laborDefaultsForBillingPosture(ConcernBillingPosture $posture): array
    {
        $categoryKey = match ($posture) {
            ConcernBillingPosture::RepairPal, ConcernBillingPosture::WarrantyRepairPal => self::WARRANTY_REPAIRPAL_LABOR_CATEGORY_KEY,
            ConcernBillingPosture::WarrantyOther, ConcernBillingPosture::Warranty => self::WARRANTY_OTHER_LABOR_CATEGORY_KEY,
            ConcernBillingPosture::Comeback => self::COMEBACK_LABOR_CATEGORY_KEY,
            default => $this->defaultLaborCategory()['key'],
        };

        $category = $this->laborCategoryByKey($categoryKey) ?? $this->defaultLaborCategory();
        $rateCents = (int) $category['rate_cents'];

        return [
            'category_key' => (string) $category['key'],
            'rate_cents' => $rateCents,
            'rate' => number_format($rateCents / 100, 2, '.', ''),
        ];
    }

    /**
     * @return array{category_key: string, rate: string, rate_cents: int}
     */
    public function laborDefaultsForConcern(ConcernBillingPosture $posture, ?Customer $customer = null): array
    {
        return $this->laborDefaultsForBillingPosture($posture);
    }

    /**
     * @return array<string, string> billing posture value => formatted hourly rate
     */
    public function laborRatesByAdvisorBillingPosture(?Customer $customer = null): array
    {
        $rates = [];

        foreach (ConcernBillingPosture::advisorSelectableCases() as $posture) {
            $rates[$posture->value] = $this->laborDefaultsForBillingPosture($posture)['rate'];
        }

        return $rates;
    }

    /**
     * @return array{
     *     label: string,
     *     title: string,
     *     rate: string,
     *     matrix_name: string,
     *     shop_fee_summary: string
     * }
     */
    public function billingPostureOptionPresentation(ConcernBillingPosture $posture): array
    {
        $labor = $this->laborDefaultsForBillingPosture($posture);
        $rate = $labor['rate'];
        $matrix = $posture->defaultPartsMatrix($this);
        $matrixName = (string) ($matrix['name'] ?? 'Shop matrix');
        $shopFee = $posture->shopFeePolicy($this);

        // decimal:3 cast stringifies as e.g. "5.000" — present "5" / "5.25".
        $feeRate = filled($shopFee['rate'])
            ? rtrim(rtrim(number_format((float) $shopFee['rate'], 3, '.', ''), '0'), '.')
            : null;

        $shopFeeSummary = match (true) {
            ! $shopFee['enabled'] => 'No shop fees',
            $feeRate !== null => $feeRate.'% shop fee',
            default => 'Shop fees apply',
        };

        $shopFeeInline = match (true) {
            ! $shopFee['enabled'] => 'No fees',
            $feeRate !== null => $feeRate.'% fees',
            default => 'Fees apply',
        };

        $label = implode(' · ', [
            $posture->label(),
            '$'.$rate.'/hr',
            $matrixName,
            $shopFeeInline,
        ]);

        $title = implode(' · ', [
            $posture->helpText(),
            '$'.$rate.'/hr labor',
            $matrixName.' parts',
            $shopFeeSummary,
        ]);

        return [
            'label' => $label,
            'title' => $title,
            'rate' => $rate,
            'matrix_name' => $matrixName,
            'shop_fee_summary' => $shopFeeSummary,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array{key: string, name: string, rate_cents: int, minimum_hours: string, rounding_rule: string, is_default: bool, allows_modifiers: bool}>
     */
    public function normalizeLaborCategories(array $categories, string $defaultCategoryKey, float $defaultLaborRate): array
    {
        $existingCategories = collect($this->labor_categories ?: self::DEFAULT_LABOR_CATEGORIES)->keyBy('key');
        $defaultRateCents = (int) round($defaultLaborRate * 100);

        return collect($categories)
            ->filter(fn (array $category): bool => filled($category['key'] ?? null))
            ->map(function (array $category) use ($existingCategories, $defaultCategoryKey, $defaultRateCents): array {
                $key = (string) $category['key'];
                $existing = $existingCategories->get($key, []);
                $isDefault = $key === $defaultCategoryKey;
                $rateCents = $isDefault
                    ? $defaultRateCents
                    : (int) round(((float) $category['rate']) * 100);

                return [
                    'key' => $key,
                    'name' => trim((string) $category['name']),
                    'rate_cents' => $rateCents,
                    'minimum_hours' => number_format((float) $category['minimum_hours'], 2, '.', ''),
                    'rounding_rule' => (string) ($category['rounding_rule'] ?? $existing['rounding_rule'] ?? 'quarter'),
                    'is_default' => $isDefault,
                    'allows_modifiers' => filter_var(
                        $category['allows_modifiers'] ?? $existing['allows_modifiers'] ?? $this->defaultAllowsModifiersForCategoryKey($key),
                        FILTER_VALIDATE_BOOLEAN,
                    ),
                ];
            })
            ->sortBy(fn (array $category): string => mb_strtolower($category['name']))
            ->values()
            ->all();
    }

    public function salesTaxRate(): string
    {
        return rtrim(rtrim(number_format((float) $this->default_tax_rate, 2, '.', ''), '0'), '.');
    }

    public function taxLabel(): string
    {
        return filled($this->tax_label) ? $this->tax_label : 'Tax';
    }

    public function shopFeeCap(): string
    {
        return $this->shop_fee_cap_cents === null
            ? ''
            : number_format($this->shop_fee_cap_cents / 100, 2, '.', '');
    }

    public function defaultDepositEnabled(): bool
    {
        return (bool) ($this->default_deposit_enabled ?? true);
    }

    public function defaultDepositIncludeParts(): bool
    {
        return (bool) ($this->default_deposit_include_parts ?? true);
    }

    public function defaultDepositIncludeDiagnostics(): bool
    {
        return (bool) ($this->default_deposit_include_diagnostics ?? true);
    }

    /**
     * @return list<string>
     */
    public function defaultDepositDiagnosticLaborCategoryKeys(): array
    {
        $keys = collect($this->default_deposit_diagnostic_labor_category_keys ?: self::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS)
            ->filter(fn (mixed $key): bool => filled($key))
            ->map(fn (mixed $key): string => (string) $key)
            ->unique()
            ->values()
            ->all();

        return $keys === [] ? self::DEFAULT_DEPOSIT_DIAGNOSTIC_LABOR_CATEGORY_KEYS : $keys;
    }

    /**
     * @param  list<string>  $selectedKeys
     * @return list<string>
     */
    public function normalizeDefaultDepositDiagnosticLaborCategoryKeys(array $selectedKeys): array
    {
        $allowedKeys = collect($this->laborCategories())->pluck('key')->all();

        return collect($selectedKeys)
            ->filter(fn (mixed $key): bool => filled($key))
            ->map(fn (mixed $key): string => (string) $key)
            ->filter(fn (string $key): bool => in_array($key, $allowedKeys, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{from: string, to: string|null, multiplier: string}>
     */
    public function partsMatrixRows(): array
    {
        return $this->defaultPartsMatrix()['rows'];
    }

    /**
     * @return array<int, array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}>
     */
    public function partsMatrices(): array
    {
        return collect($this->parts_matrices ?: self::DEFAULT_PARTS_MATRICES)
            ->map(function (array $matrix): array {
                $matrix['rows'] = collect($matrix['rows'])
                    ->map(function (array $row): array {
                        $row['margin_percentage'] = $this->marginPercentageForMarkup($row['markup_percentage'] ?? null);

                        return $row;
                    })
                    ->sortBy(fn (array $row): float => (float) $row['min_cost'])
                    ->values()
                    ->all();

                return $matrix;
            })
            ->sortBy(fn (array $matrix): string => mb_strtolower($matrix['name']))
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}
     */
    public function defaultPartsMatrix(): array
    {
        return collect($this->partsMatrices())
            ->firstWhere('is_default', true)
            ?? $this->partsMatrices()[0];
    }

    /**
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}|null
     */
    public function partsMatrixByKey(?string $key): ?array
    {
        if (! $key) {
            return $this->defaultPartsMatrix();
        }

        return collect($this->partsMatrices())->firstWhere('key', $key);
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     shop_fees_enabled: bool,
     *     shop_fee_rate_override: string|null,
     *     fee_override: string|null,
     *     discount_type: string,
     *     discount_amount: string|null,
     *     default_parts_matrix_key: string|null
     * }>
     */
    public function customerTypesWithoutPartsMatrix(string $matrixKey): array
    {
        return collect($this->customer_types ?: self::DEFAULT_CUSTOMER_TYPES)
            ->map(function (array $row) use ($matrixKey): array {
                if (($row['default_parts_matrix_key'] ?? null) === $matrixKey) {
                    $row['default_parts_matrix_key'] = null;
                }

                return $this->normalizeCustomerTypeRow($row);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}
     */
    public function defaultPartsMatrixForCustomerType(?string $customerType): array
    {
        return $this->partsMatrixForCustomerTypeProfile($customerType)
            ?? $this->defaultPartsMatrix();
    }

    /**
     * Billing profile role for named billing classes used by scope billing postures.
     *
     * @return 'fleet'|'warranty'|null
     */
    public function customerTagBillingProfile(?string $tagName): ?string
    {
        $normalized = mb_strtolower(trim((string) $tagName));

        return match ($normalized) {
            'fleet' => 'fleet',
            'warranty' => 'warranty',
            'repairpal' => 'repairpal',
            'internal' => 'internal',
            'wholesale' => 'wholesale',
            'comeback' => 'comeback',
            default => null,
        };
    }

    /**
     * @return list<array{
     *     name: string,
     *     shop_fees_enabled: bool,
     *     shop_fee_rate_override: string|null,
     *     fee_override: string|null,
     *     discount_type: string,
     *     discount_amount: string|null,
     *     default_parts_matrix_key: string|null
     * }>
     */
    public function primaryBillingClassRows(): array
    {
        $primary = collect(self::PRIMARY_BILLING_CLASSES)
            ->map(fn (string $name): string => mb_strtolower($name));

        return collect($this->customerTypeRows())
            ->filter(fn (array $row): bool => $primary->contains(mb_strtolower($row['name'])))
            ->values()
            ->all();
    }

    public function isRepairPalBillingProfileTag(?string $tagName): bool
    {
        return $this->customerTagBillingProfile($tagName) === 'repairpal';
    }

    public function isComebackBillingProfileTag(?string $tagName): bool
    {
        return $this->customerTagBillingProfile($tagName) === 'comeback';
    }

    public function isFleetBillingProfileTag(?string $tagName): bool
    {
        return $this->customerTagBillingProfile($tagName) === 'fleet';
    }

    public function isWarrantyBillingProfileTag(?string $tagName): bool
    {
        return $this->customerTagBillingProfile($tagName) === 'warranty';
    }

    /**
     * @return list<string>
     */
    public static function standingDiscountTagNames(): array
    {
        return collect(self::DEFAULT_CUSTOMER_TYPES)
            ->filter(fn (array $row): bool => self::standingDiscountPolicyFromRow($row) !== null)
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Standing discount from billing class identity (not scope billing).
     *
     * @return array{type: string, percent: string}|null
     */
    public function standingDiscountPolicy(?string $customerTag): ?array
    {
        return self::standingDiscountPolicyFromRow($this->customerTypeFor($customerTag));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{type: string, percent: string}|null
     */
    public static function standingDiscountPolicyFromRow(array $row): ?array
    {
        $type = (string) ($row['discount_type'] ?? 'none');
        $amount = $row['discount_amount'] ?? null;

        if (! in_array($type, ['labor', 'parts', 'both'], true) || ! filled($amount)) {
            return null;
        }

        $percent = number_format((float) $amount, 2, '.', '');

        if ((float) $percent <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'percent' => $percent,
        ];
    }

    /**
     * @return array{key: string, name: string, is_default: bool, rows: array<int, array<string, mixed>>}|null
     */
    public function partsMatrixForCustomerTypeProfile(?string $profileName): ?array
    {
        if (! filled($profileName)) {
            return null;
        }

        $row = collect($this->customerTypeRows())
            ->first(fn (array $row): bool => strcasecmp($row['name'], (string) $profileName) === 0);

        if ($row === null) {
            return null;
        }

        return $this->partsMatrixByKey($row['default_parts_matrix_key'] ?? null);
    }

    /**
     * Shop-standard billing defaults for customer-pay scopes (not customer tag driven).
     *
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForBillingDefault(): array
    {
        if (! $this->shop_fee_enabled) {
            return ['enabled' => false, 'rate' => null];
        }

        return [
            'enabled' => true,
            'rate' => filled($this->shop_fee_rate) ? (string) $this->shop_fee_rate : null,
        ];
    }

    public function marginPercentageForMarkup(string|float|int|null $markupPercentage): ?string
    {
        if ($markupPercentage === null || $markupPercentage === '') {
            return null;
        }

        $markup = (float) $markupPercentage;

        if ($markup < 0) {
            return null;
        }

        return (string) (int) round(($markup / (100 + $markup)) * 100);
    }

    /**
     * @return array{
     *     name: string,
     *     shop_fees_enabled: bool,
     *     shop_fee_rate_override: string|null,
     *     fee_override: string|null,
     *     discount_type: string,
     *     discount_amount: string|null,
     *     default_parts_matrix_key: string|null
     * }
     */
    public function customerTypeFor(?string $customerType): array
    {
        $normalized = mb_strtolower(trim((string) $customerType));

        return collect($this->customerTypeRows())
            ->first(fn (array $row): bool => mb_strtolower($row['name']) === $normalized)
            ?? $this->customerTypeRows()[0];
    }

    public function documentPresentationProfileFor(?string $customerType): CustomerPartPresentationProfile
    {
        $row = $this->customerTypeFor($customerType);

        return CustomerPartPresentationProfile::fromStored(
            $row['document_presentation_profile']
                ?? CustomerPartPresentationProfile::defaultForBillingClassName($row['name'] ?? null)->value,
        );
    }

    /**
     * @return array{enabled: bool, rate: string|null}
     */
    public function shopFeePolicyForCustomerType(?string $customerType): array
    {
        $row = $this->customerTypeFor($customerType);

        if (! $row['shop_fees_enabled']) {
            return ['enabled' => false, 'rate' => null];
        }

        return [
            'enabled' => true,
            'rate' => filled($row['shop_fee_rate_override'] ?? null)
                ? (string) $row['shop_fee_rate_override']
                : null,
        ];
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     shop_fees_enabled: bool,
     *     shop_fee_rate_override: string|null,
     *     fee_override: string|null,
     *     discount_type: string,
     *     discount_amount: string|null,
     *     default_parts_matrix_key: string|null
     * }>
     */
    public function customerTypeRows(): array
    {
        return collect($this->customer_types ?: self::DEFAULT_CUSTOMER_TYPES)
            ->map(fn (array $row): array => $this->normalizeCustomerTypeRow($row))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function defaultCustomerTypeDisclaimerMap(): array
    {
        return self::DEFAULT_CUSTOMER_TYPE_DISCLAIMERS;
    }

    public function globalDocumentDisclaimerFor(FinancialDocumentType $documentType): string
    {
        $disclaimer = $documentType === FinancialDocumentType::Invoice
            ? $this->invoice_disclaimer
            : $this->estimate_disclaimer;

        if (! filled($disclaimer)) {
            return $documentType === FinancialDocumentType::Invoice
                ? self::DEFAULT_INVOICE_DISCLAIMER
                : self::DEFAULT_ESTIMATE_DISCLAIMER;
        }

        return trim((string) $disclaimer);
    }

    public function customerTypeDocumentDisclaimerFor(?string $customerType): ?string
    {
        $key = mb_strtolower(trim((string) $customerType));

        if ($key === '') {
            return null;
        }

        $disclaimer = $this->customerTypeDisclaimerMap()[$key] ?? null;

        return filled($disclaimer) ? trim((string) $disclaimer) : null;
    }

    public function authorizationLanguage(): ?string
    {
        if (! filled($this->authorization_language)) {
            return self::DEFAULT_AUTHORIZATION_LANGUAGE;
        }

        return trim((string) $this->authorization_language);
    }

    public function portalSignatureRequired(): bool
    {
        return (bool) $this->portal_signature_required;
    }

    /**
     * @return array<string, string>
     */
    public function customerTypeDisclaimerMap(): array
    {
        $stored = collect($this->customer_type_disclaimers ?: [])
            ->mapWithKeys(fn (mixed $disclaimer, string|int $key): array => [
                is_string($key) ? mb_strtolower(trim($key)) : mb_strtolower(trim((string) $key)) => trim((string) $disclaimer),
            ])
            ->filter(fn (string $disclaimer): bool => $disclaimer !== '')
            ->all();

        return [
            ...self::defaultCustomerTypeDisclaimerMap(),
            ...$stored,
        ];
    }

    /**
     * @param  array<string, string|null>  $disclaimers
     * @return array<string, string>
     */
    public function normalizeCustomerTypeDisclaimerInput(array $disclaimers): array
    {
        $allowed = collect($this->customerTypeRows())
            ->map(fn (array $row): string => mb_strtolower($row['name']))
            ->all();

        return collect($disclaimers)
            ->mapWithKeys(fn (mixed $disclaimer, string|int $key): array => [
                mb_strtolower(trim((string) $key)) => filled($disclaimer) ? trim((string) $disclaimer) : '',
            ])
            ->only($allowed)
            ->filter(fn (string $disclaimer): bool => $disclaimer !== '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     name: string,
     *     shop_fees_enabled: bool,
     *     shop_fee_rate_override: string|null,
     *     fee_override: string|null,
     *     discount_type: string,
     *     discount_amount: string|null,
     *     default_parts_matrix_key: string|null
     * }
     */
    public function normalizeCustomerTypeRow(array $row): array
    {
        $name = trim((string) ($row['name'] ?? 'Retail'));
        $defaults = collect(self::DEFAULT_CUSTOMER_TYPES)
            ->keyBy(fn (array $default): string => mb_strtolower($default['name']))
            ->get(mb_strtolower($name), []);
        $shopFeesEnabled = $row['shop_fees_enabled'] ?? $defaults['shop_fees_enabled'] ?? null;

        if ($shopFeesEnabled === null) {
            $feeOverride = $row['fee_override'] ?? null;
            $shopFeesEnabled = ! in_array($feeOverride, ['none', '0', '0.0', '0.00'], true);
        }

        $shopFeesEnabled = (bool) $shopFeesEnabled;
        $rateOverride = $shopFeesEnabled && filled($row['shop_fee_rate_override'] ?? null)
            ? number_format((float) $row['shop_fee_rate_override'], 3, '.', '')
            : null;

        $resolvedName = $name !== '' ? $name : 'Retail';

        return [
            'name' => $resolvedName,
            'document_presentation_profile' => CustomerPartPresentationProfile::fromStored(
                $row['document_presentation_profile']
                    ?? $defaults['document_presentation_profile']
                    ?? null,
            )->value,
            'shop_fees_enabled' => $shopFeesEnabled,
            'shop_fee_rate_override' => $rateOverride,
            'fee_override' => $shopFeesEnabled ? $rateOverride : 'none',
            'discount_type' => $discountType = $row['discount_type'] ?? $defaults['discount_type'] ?? 'none',
            'discount_amount' => $discountType === 'none'
                ? null
                : (filled($row['discount_amount'] ?? $defaults['discount_amount'] ?? null)
                    ? number_format((float) ($row['discount_amount'] ?? $defaults['discount_amount']), 2, '.', '')
                    : null),
            'default_parts_matrix_key' => $row['default_parts_matrix_key'] ?? $defaults['default_parts_matrix_key'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shopOverheadStateArray(): array
    {
        return is_array($this->shop_overhead_state) ? $this->shop_overhead_state : [];
    }

    public function shopOverheadPerHour(): ?float
    {
        if ($this->shop_overhead_per_hour_cents === null) {
            return null;
        }

        return $this->shop_overhead_per_hour_cents / 100;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function persistTrusted(array $attributes): bool
    {
        return $this->forceFill($attributes)->save();
    }
}
