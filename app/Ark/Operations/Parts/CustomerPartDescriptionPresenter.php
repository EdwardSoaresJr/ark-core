<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;

final class CustomerPartDescriptionPresenter
{
    /** @var list<string> */
    private const BRAND_PRESERVING = [
        'bilstein',
        'brembo',
        'motorcraft',
        'mopar',
        'acdelco',
        'ngk',
        'bosch',
        'oem',
        'genuine manufacturer parts',
    ];

    /**
     * Longest phrases first so "spark plug" wins over "plug".
     *
     * @var array<string, string>
     */
    private const CATEGORY_PATTERNS = [
        'spark plug wire set' => 'Spark Plug Wire Set',
        'spark plug wires' => 'Spark Plug Wire Set',
        'spark plug wire' => 'Spark Plug Wire Set',
        'distributor rotor' => 'Distributor Rotor',
        'distributor cap' => 'Distributor Cap',
        'control arm and ball joint assembly' => 'Control Arm and Ball Joint Assembly',
        'control arm and ball joint' => 'Control Arm and Ball Joint Assembly',
        'thermostat housing assembly' => 'Thermostat Housing',
        'thermostat housing' => 'Thermostat Housing',
        'disc brake pad set' => 'Brake Pad Set',
        'disc brake pad' => 'Brake Pad',
        'brake pad set' => 'Brake Pad Set',
        'brake pad' => 'Brake Pad',
        'brake rotor' => 'Brake Rotor',
        'spark plug' => 'Spark Plug',
        'water pump' => 'Water Pump',
        'pcv valve' => 'PCV Valve',
        'ignition coil' => 'Ignition Coil',
        'coolant temperature sensor' => 'Temperature Sensor',
        'coolant temp sensor' => 'Temperature Sensor',
        'radiator hose' => 'Radiator Hose',
        'coolant hose' => 'Radiator Hose',
        'coolant reservoir' => 'Reservoir',
        'fan clutch' => 'Fan Clutch',
        'serpentine belt' => 'Serpentine Belt',
        'belt tensioner' => 'Belt Tensioner',
        'shock absorber' => 'Shock',
        'thermostat' => 'Thermostat',
        'gasket' => 'Gasket',
        'hose clamp' => 'Hose Clamp',
        'antifreeze/coolant' => 'Coolant',
        'antifreeze' => 'Coolant',
        'coolant' => 'Coolant',
        'battery' => 'Battery',
        'rotor' => 'Brake Rotor',
        'shock' => 'Shock',
        'tensioner' => 'Belt Tensioner',
        'belt' => 'Belt',
        'plug' => 'Spark Plug',
    ];

    /** Catalog / product-line phrases safe to strip after brand removal. */
    private const PRODUCT_LINE_TOKENS = [
        'oe solutions',
        'problem solver',
        'copper plus',
        'select wires',
        'wires',
        'genuine manufacturer parts',
        'gm original equipment',
        'original equipment',
        'blue streak',
        'ultima',
    ];

    /** Trailing merchandising words dropped once a category is known. */
    private const MERCHANDISING_TOKENS = [
        'copper plus',
        'problem solver',
        'oe solutions',
        'select wires',
        'wires',
        'engine coolant',
        'laser',
        'iridium',
        'platinum',
        'gold',
        'professional',
        'ultra',
        'premium',
        'standard',
        'select',
        'suspension',
        'engine',
        'coolant',
    ];

    /** @var list<string> */
    private const GENERIC_BRAKE_COMPONENT_PREFIXES = [
        'brake',
        'disc',
    ];

    /** @var list<string> */
    private const POSITIONAL_PREFIXES = [
        'front',
        'rear',
        'left',
        'right',
        'inner',
        'outer',
        'upper',
        'lower',
    ];

    /**
     * House / catalog brands stripped before surfacing brake pad material and fitment words.
     *
     * @var list<string>
     */
    private const HOUSE_BRAND_TOKENS = [
        'brakebest select',
        'beck/arnley',
        'beck arnley',
        'brakebest',
        'worldpac',
        'carquest',
        'duralast',
        'raybestos',
        'wagner',
        'monroe',
        'moog',
        'timken',
        'continental',
        'denso',
        'gates',
        'champion',
        'dorman',
        'stant',
        'fel-pro',
        'felpro',
        'prestone',
        'select',
    ];

    /** @var list<string> */
    private const PART_NOUN_BLOCK_COOLANT = [
        'sensor',
        'gasket',
        'thermostat',
        'hose',
        'pump',
        'seal',
        'washer',
        'clamp',
        'bolt',
        'reservoir',
        'cap',
        'plug',
        'pulley',
        'fan',
        'radiator',
        'housing',
        'o-ring',
        'oring',
        'grommet',
        'bracket',
        'mount',
        'kit',
        'set',
    ];

    public function present(RepairOrderLine $line): string
    {
        if ($line->type !== RepairOrderLineType::Part) {
            return trim((string) $line->description);
        }

        return $this->resolve(
            customerDescription: $this->manualCustomerDescription($line->customer_description, $line->customer_description_source),
            inventoryDescription: (string) $line->description,
            siblingPartDescriptions: $this->siblingPartDescriptions($line),
        );
    }

    /**
     * Deterministic customer-facing label from procurement identity (no override).
     *
     * @param  list<string>  $siblingPartDescriptions
     */
    public function generate(string $inventoryDescription, ?string $brand = null, array $siblingPartDescriptions = []): string
    {
        return $this->resolve(
            customerDescription: null,
            inventoryDescription: $inventoryDescription,
            siblingPartDescriptions: $siblingPartDescriptions,
            brand: $brand,
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<string>  $siblingPartDescriptions
     */
    public function presentFromSnapshotLine(array $line, array $siblingPartDescriptions = []): string
    {
        if (($line['type'] ?? '') !== RepairOrderLineType::Part->value) {
            return trim((string) ($line['description'] ?? ''));
        }

        return $this->resolve(
            customerDescription: $this->manualCustomerDescription(
                $line['customer_description'] ?? null,
                $line['customer_description_source'] ?? null,
            ),
            inventoryDescription: (string) ($line['description'] ?? ''),
            siblingPartDescriptions: $siblingPartDescriptions,
        );
    }

    private function manualCustomerDescription(mixed $description, mixed $source): ?string
    {
        $explicit = trim((string) $description);

        if ($explicit === '') {
            return null;
        }

        if (CustomerDescriptionSource::tryFromStored($source) === CustomerDescriptionSource::Generated) {
            return null;
        }

        return $explicit;
    }

    /**
     * @param  list<string>  $siblingPartDescriptions
     */
    private function resolve(
        ?string $customerDescription,
        string $inventoryDescription,
        array $siblingPartDescriptions = [],
        ?string $brand = null,
    ): string {
        $explicit = trim((string) $customerDescription);

        if ($explicit !== '') {
            return $explicit;
        }

        $description = trim($inventoryDescription);

        if ($description === '') {
            return '';
        }

        $description = $this->stripStructuredBrand($description, $brand);
        $normalized = mb_strtolower($description);
        $preservedBrand = $this->detectPreservedBrand($normalized);

        if ($preservedBrand !== null) {
            return $this->presentWithPreservedBrand($description, $normalized, $preservedBrand);
        }

        $category = $this->detectCategoryLabel($normalized);

        if ($category === 'Brake Pad' || $category === 'Brake Pad Set') {
            return $this->presentBrakePadLabel($description);
        }

        if ($category === 'Brake Rotor') {
            $label = $this->presentBrakeRotorLabel($description);

            if ($label === 'Brake Rotor') {
                $inferredBrand = $this->inferBrakeKitBrandPrefix($siblingPartDescriptions);

                if ($inferredBrand !== null) {
                    return $this->titleCaseWords($inferredBrand).' Brake Rotor';
                }
            }

            return $label;
        }

        if ($category !== null) {
            return $this->presentCategoryWithFitment($description, $category);
        }

        return $this->fallbackCleanedLabel($description);
    }

    private function stripStructuredBrand(string $description, ?string $brand): string
    {
        $brand = trim((string) $brand);

        if ($brand === '') {
            return $description;
        }

        $pattern = '/^'.preg_quote($brand, '/').'\b[\s\-–\x{2014}:]*/iu';

        return $this->normalizeSpacing((string) preg_replace($pattern, '', $description));
    }

    private function presentCategoryWithFitment(string $description, string $category): string
    {
        $cleaned = $this->stripCatalogNoise($description);
        $normalized = mb_strtolower($cleaned);
        $categoryNormalized = mb_strtolower($category);
        $position = mb_strpos($normalized, $categoryNormalized);

        if ($position === false) {
            return $category;
        }

        $prefix = $this->normalizeSpacing(mb_substr($cleaned, 0, $position));
        $suffix = $this->normalizeSpacing(mb_substr($cleaned, $position + mb_strlen($category)));

        $positional = $this->extractLeadingPositional($prefix);
        $meaningfulSuffix = $this->extractMeaningfulQualifierSuffix($suffix, $category);

        // Bare category unless fitment qualifiers are present - avoids catalog merchandising leakage.
        if ($positional === '' && $meaningfulSuffix === '') {
            return $category;
        }

        return $this->normalizeSpacing(trim($positional.' '.$category.' '.$meaningfulSuffix));
    }

    private function stripCatalogNoise(string $description): string
    {
        $cleaned = $description;

        foreach ($this->houseBrandTokensLongestFirst() as $token) {
            $cleaned = preg_replace('/\b'.preg_quote($token, '/').'\b/i', ' ', $cleaned) ?? $cleaned;
        }

        foreach ($this->productLineTokensLongestFirst() as $token) {
            $cleaned = preg_replace('/\b'.preg_quote($token, '/').'\b/i', ' ', $cleaned) ?? $cleaned;
        }

        $cleaned = $this->stripPartNumberTokens($cleaned);
        $cleaned = preg_replace('/\b\d+(?:\.\d+)?\s*(?:mm|in|inch|inches)\b/i', ' ', $cleaned) ?? $cleaned;
        $cleaned = $this->stripCompatibilityBoilerplateFromOriginal($cleaned);

        return $this->normalizeSpacing($cleaned);
    }

    private function stripCompatibilityBoilerplateFromOriginal(string $description): string
    {
        $stripped = $description;

        $patterns = [
            '/\bcompatible with (?:all )?(?:oat|hoat|dex[- ]?cool|extended[- ]life )?(?:antifreeze\/)?coolant(?: formulations?)?\b/iu',
            '/\b(?:oat|hoat|dex[- ]?cool|extended[- ]life )?(?:antifreeze\/)?coolant compatible\b/iu',
            '/\bfor use with (?:all )?(?:oat|hoat )?(?:antifreeze\/)?coolant\b/iu',
            '/\bmeets (?:oat|hoat )?(?:antifreeze\/)?coolant (?:spec|specification)s?\b/iu',
            '/\bsuitable for (?:all )?(?:oat|hoat )?(?:antifreeze\/)?coolant\b/iu',
            '/\bhoat\b/iu',
            '/\bantifreeze\/coolant compatible\b/iu',
            '/\bhoat coolant compatible(?:\s+seal)?\b/iu',
        ];

        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, ' ', $stripped) ?? $stripped;
        }

        $stripped = preg_replace('/^(?:compatible with|for use with|suitable for)[^-–\x{2014}]+[-–\x{2014}]\s*/iu', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s*[-–\x{2014}]\s*/u', ' ', $stripped) ?? $stripped;

        return $this->normalizeSpacing($stripped);
    }

    private function stripPartNumberTokens(string $value): string
    {
        $cleaned = preg_replace('/\b(?:part\s*#?\s*)?[A-Z]{1,4}\d{2,}[A-Z0-9]*\b/i', ' ', $value) ?? $value;
        $cleaned = preg_replace('/\bH\d+-[A-Z0-9]+\b/i', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\b\d{3,}\b/u', ' ', $cleaned) ?? $cleaned;

        return $cleaned;
    }

    private function extractLeadingPositional(string $prefix): string
    {
        $tokens = preg_split('/\s+/u', mb_strtolower($prefix), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];

        foreach ($tokens as $token) {
            if (! in_array($token, self::POSITIONAL_PREFIXES, true)) {
                break;
            }

            $kept[] = $token;
        }

        return $this->titleCaseWords(implode(' ', $kept));
    }

    private function stripLeadingPositional(string $prefix): string
    {
        $tokens = preg_split('/\s+/u', $prefix, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        while ($tokens !== [] && in_array(mb_strtolower($tokens[0]), self::POSITIONAL_PREFIXES, true)) {
            array_shift($tokens);
        }

        return $this->normalizeSpacing(implode(' ', $tokens));
    }

    private function extractMeaningfulQualifierSuffix(string $suffix, string $category = ''): string
    {
        $normalized = mb_strtolower($suffix);
        $categoryNormalized = mb_strtolower($category);

        if ($normalized === '') {
            return '';
        }

        $allowed = [
            'with sensor',
            'without sensor',
            'w/ sensor',
            'w/o sensor',
            'loaded',
            'remanufactured',
            'reman',
            'used',
            'new',
            'complete',
        ];

        foreach ($allowed as $phrase) {
            if (str_contains($categoryNormalized, $phrase)) {
                continue;
            }

            if ($normalized === $phrase || str_starts_with($normalized, $phrase.' ')) {
                return $this->titleCaseWords($phrase);
            }
        }

        return '';
    }

    private function stripMerchandisingTokens(string $value): string
    {
        $cleaned = $value;

        foreach ($this->merchandisingTokensLongestFirst() as $token) {
            $cleaned = preg_replace('/\b'.preg_quote($token, '/').'\b/i', ' ', $cleaned) ?? $cleaned;
        }

        return $this->normalizeSpacing($cleaned);
    }

    private function isOnlyMerchandising(string $value): bool
    {
        return $this->stripMerchandisingTokens($value) === '';
    }

    /**
     * @return list<string>
     */
    private function productLineTokensLongestFirst(): array
    {
        $tokens = self::PRODUCT_LINE_TOKENS;
        usort($tokens, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function merchandisingTokensLongestFirst(): array
    {
        $tokens = self::MERCHANDISING_TOKENS;
        usort($tokens, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return $tokens;
    }

    private function presentBrakePadLabel(string $description): string
    {
        $cleaned = $description;

        foreach ($this->houseBrandTokensLongestFirst() as $token) {
            $cleaned = preg_replace('/\b'.preg_quote($token, '/').'\b/i', ' ', $cleaned) ?? $cleaned;
        }

        $cleaned = $this->stripPartNumberTokens($cleaned);
        $cleaned = preg_replace('/\b\d+(?:\.\d+)?\s*(?:mm|in|inch|inches)\b/i', ' ', $cleaned) ?? $cleaned;
        $cleaned = $this->normalizeSpacing($cleaned);

        if ($cleaned === '') {
            return 'Brake Pad';
        }

        if (preg_match('/\b(?:disc\s+)?brake\s+pads?\b/i', $cleaned) !== 1) {
            return 'Brake Pad';
        }

        return $this->titleCaseWords($cleaned);
    }

    private function presentBrakeRotorLabel(string $description): string
    {
        $brandPrefix = $this->extractNonHouseBrandPrefix($description, [
            'disc brake rotor',
            'brake rotor',
            'rotor',
        ]);

        if ($brandPrefix !== null) {
            return $this->titleCaseWords($brandPrefix).' Brake Rotor';
        }

        return 'Brake Rotor';
    }

    private function extractNonHouseBrandPrefix(string $description, array $categoryPhrases): ?string
    {
        $normalized = mb_strtolower($description);
        $longestFirst = $categoryPhrases;

        usort($longestFirst, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        foreach ($longestFirst as $phrase) {
            $position = mb_strpos($normalized, $phrase);

            if ($position === false || $position === 0) {
                continue;
            }

            $prefix = trim(mb_substr($description, 0, $position));
            $prefix = $this->cleanBrandPrefix($prefix);

            if ($prefix === '') {
                continue;
            }

            return $prefix;
        }

        return null;
    }

    private function cleanBrandPrefix(string $prefix): string
    {
        $cleaned = $this->normalizeSpacing($prefix);
        $normalized = mb_strtolower($cleaned);

        foreach ($this->houseBrandTokensLongestFirst() as $token) {
            if ($normalized === $token || str_starts_with($normalized, $token.' ')) {
                return '';
            }
        }

        foreach (self::POSITIONAL_PREFIXES as $positional) {
            if ($normalized === $positional) {
                return '';
            }
        }

        foreach (self::GENERIC_BRAKE_COMPONENT_PREFIXES as $generic) {
            if ($normalized === $generic) {
                return '';
            }
        }

        $cleaned = $this->stripPartNumberTokens($cleaned);
        $cleaned = preg_replace('/\b\d+(?:\.\d+)?\s*(?:mm|in|inch|inches)\b/i', ' ', $cleaned) ?? $cleaned;

        return $this->normalizeSpacing($cleaned);
    }

    /**
     * @param  list<string>  $descriptions
     */
    private function inferBrakeKitBrandPrefix(array $descriptions): ?string
    {
        foreach ($descriptions as $description) {
            $brandPrefix = $this->extractNonHouseBrandPrefix($description, [
                'disc brake pad',
                'brake pad',
            ]);

            if ($brandPrefix !== null) {
                return $brandPrefix;
            }
        }

        return null;
    }

    private function siblingPartDescriptions(RepairOrderLine $line): array
    {
        if ($line->repair_order_work_group_id === null) {
            return [];
        }

        if ($line->relationLoaded('workGroup') && $line->workGroup !== null) {
            $lines = $line->workGroup->relationLoaded('lines')
                ? $line->workGroup->lines
                : collect();

            return $lines
                ->filter(fn (RepairOrderLine $candidate): bool => $candidate !== $line && $candidate->type === RepairOrderLineType::Part)
                ->map(fn (RepairOrderLine $candidate): string => (string) $candidate->description)
                ->values()
                ->all();
        }

        $line->loadMissing('workGroup.lines');

        return $line->workGroup?->lines
            ->filter(fn (RepairOrderLine $candidate): bool => $candidate !== $line && $candidate->type === RepairOrderLineType::Part)
            ->map(fn (RepairOrderLine $candidate): string => (string) $candidate->description)
            ->values()
            ->all() ?? [];
    }

    /**
     * @return list<string>
     */
    private function houseBrandTokensLongestFirst(): array
    {
        $tokens = self::HOUSE_BRAND_TOKENS;

        usort($tokens, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return $tokens;
    }

    private function detectPreservedBrand(string $normalizedDescription): ?string
    {
        foreach (self::BRAND_PRESERVING as $brand) {
            if (str_starts_with($normalizedDescription, $brand.' ')
                || $normalizedDescription === $brand
                || str_contains($normalizedDescription, ' '.$brand.' ')) {
                return $brand;
            }
        }

        return null;
    }

    private function presentWithPreservedBrand(string $original, string $normalized, string $brand): string
    {
        $category = $this->detectCategoryLabel($normalized);

        if ($category === 'Spark Plug') {
            return 'Spark Plug';
        }

        if ($category === 'Shock') {
            $preserved = $this->extractBrandPhrase($original, $brand, ['shock absorber', 'shock']);

            if ($preserved !== null) {
                return $preserved;
            }
        }

        if ($category === 'Brake Rotor') {
            return $this->titleCaseWords($brand).' Brake Rotor';
        }

        if ($category === 'Battery') {
            return $this->titleCaseWords($brand).' Battery';
        }

        if ($category !== null) {
            return $this->titleCaseWords($brand).' '.$category;
        }

        return $this->fallbackCleanedLabel($original);
    }

    private function detectCategoryLabel(string $normalizedDescription): ?string
    {
        $subject = $this->forCategoryMatching($normalizedDescription);

        foreach (self::CATEGORY_PATTERNS as $pattern => $label) {
            if ($this->patternMatchesCategory($subject, $pattern, $label)) {
                return $label;
            }
        }

        return null;
    }

    private function forCategoryMatching(string $normalizedDescription): string
    {
        $stripped = $this->stripCompatibilityBoilerplate($normalizedDescription);

        return $stripped !== '' ? $stripped : $normalizedDescription;
    }

    private function stripCompatibilityBoilerplate(string $normalized): string
    {
        $stripped = $normalized;

        $patterns = [
            '/\bcompatible with (?:all )?(?:oat|hoat|dex[- ]?cool|extended[- ]life )?(?:antifreeze\/)?coolant(?: formulations?)?\b/u',
            '/\b(?:oat|hoat|dex[- ]?cool|extended[- ]life )?(?:antifreeze\/)?coolant compatible\b/u',
            '/\bfor use with (?:all )?(?:oat|hoat )?(?:antifreeze\/)?coolant\b/u',
            '/\bmeets (?:oat|hoat )?(?:antifreeze\/)?coolant (?:spec|specification)s?\b/u',
            '/\bsuitable for (?:all )?(?:oat|hoat )?(?:antifreeze\/)?coolant\b/u',
        ];

        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, ' ', $stripped) ?? $stripped;
        }

        $stripped = preg_replace('/^(?:compatible with|for use with|suitable for)[^-–\x{2014}]+[-–\x{2014}]\s*/u', '', $stripped) ?? $stripped;

        return $this->normalizeSpacing($stripped);
    }

    private function patternMatchesCategory(string $subject, string $pattern, string $label): bool
    {
        if ($label === 'Coolant') {
            return $this->isCoolantProduct($subject, $pattern);
        }

        if (in_array($pattern, ['plug', 'belt', 'rotor', 'shock', 'tensioner', 'battery'], true)) {
            return preg_match('/\b'.preg_quote($pattern, '/').'\b/u', $subject) === 1;
        }

        return str_contains($subject, $pattern);
    }

    private function isCoolantProduct(string $subject, string $pattern): bool
    {
        foreach (self::PART_NOUN_BLOCK_COOLANT as $noun) {
            if (preg_match('/\b'.preg_quote($noun, '/').'\b/u', $subject) === 1) {
                return false;
            }
        }

        if ($pattern === 'coolant') {
            if (preg_match('/\bcoolant\b/u', $subject) !== 1) {
                return false;
            }

            return preg_match(
                '/\b(?:prediluted|concentrate|50\/50|dex[- ]?cool|antifreeze|extended[- ]life|flush|refill|top[- ]?off)\b/u',
                $subject,
            ) === 1 || preg_match('/^\s*coolant(?:\s+(?:flush|kit))?\s*$/u', $subject) === 1;
        }

        $escapedPattern = preg_quote(str_replace('/', '\/', $pattern), '/');

        return preg_match('/\b'.$escapedPattern.'\b/u', $subject) === 1;
    }

    /**
     * @param  list<string>  $categoryPhrases
     */
    private function extractBrandPhrase(string $original, string $brand, array $categoryPhrases): ?string
    {
        $escapedBrand = preg_quote($brand, '/');

        foreach ($categoryPhrases as $phrase) {
            $pattern = '/\b('.$escapedBrand.'\b.+?\b'.preg_quote($phrase, '/').')\b/i';

            if (preg_match($pattern, $original, $matches) === 1) {
                return $this->normalizeSpacing($matches[1]);
            }
        }

        return null;
    }

    private function fallbackCleanedLabel(string $description): string
    {
        $cleaned = $description;

        $cleaned = $this->stripPartNumberTokens($cleaned);
        $cleaned = preg_replace('/\b\d+(?:\.\d+)?\s*(?:mm|in|inch|inches)\b/i', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\bH\d+-[A-Z0-9]+\b/i', ' ', $cleaned) ?? $cleaned;

        foreach (array_merge(self::BRAND_PRESERVING, [
            'duralast', 'gates', 'prestone', 'raybestos', 'wagner', 'monroe', 'moog', 'timken',
            'carquest', 'napa', 'worldpac', 'beck/arnley', 'beck arnley', 'denso', 'continental',
            'brakebest', 'ceramic', 'select', 'disc',
            'laser', 'iridium', 'gold', 'platinum', 'professional', 'ultra', 'dex-cool', 'prediluted',
            'extended life', 'premium',
        ]) as $token) {
            $cleaned = preg_replace('/\b'.preg_quote($token, '/').'\b/i', ' ', $cleaned) ?? $cleaned;
        }

        $cleaned = $this->normalizeSpacing($cleaned);

        if ($cleaned === '') {
            return $description;
        }

        $category = $this->detectCategoryLabel(mb_strtolower($cleaned));

        return $category ?? $this->titleCaseWords($cleaned);
    }

    private function normalizeSpacing(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function titleCaseWords(string $value): string
    {
        return mb_convert_case($this->normalizeSpacing($value), MB_CASE_TITLE, 'UTF-8');
    }
}
