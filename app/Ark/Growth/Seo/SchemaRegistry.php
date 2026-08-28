<?php

namespace App\Ark\Growth\Seo;

use App\Ark\Growth\Seo\Schemas\ArticleSchema;
use App\Ark\Growth\Seo\Schemas\AutoRepairSchema;
use App\Ark\Growth\Seo\Schemas\BreadcrumbSchema;
use App\Ark\Growth\Seo\Schemas\FaqSchema;
use App\Ark\Growth\Seo\Schemas\LocalBusinessSchema;
use App\Ark\Growth\Seo\Schemas\OrganizationSchema;
use App\Ark\Growth\Seo\Schemas\ReviewSchema;
use App\Ark\Growth\Seo\Schemas\ServiceSchema;
use App\Ark\Growth\Seo\Schemas\VehicleSchema;
use App\Ark\Growth\Seo\Schemas\WebsiteSchema;
use InvalidArgumentException;

final class SchemaRegistry
{
    /** @var array<string, SchemaBuilder> */
    private array $builders = [];

    public function __construct()
    {
        foreach ($this->defaultBuilders() as $builder) {
            $this->register($builder);
        }
    }

    public function register(SchemaBuilder $builder): void
    {
        $this->builders[$builder->type()] = $builder;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->builders);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function build(string $type, array $context): array
    {
        $builder = $this->builders[$type] ?? null;

        if ($builder === null) {
            throw new InvalidArgumentException("Unknown schema type [{$type}].");
        }

        return $builder->build($context);
    }

    /**
     * @param  list<string>  $types
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function buildMany(array $types, array $context): array
    {
        $schemas = [];

        foreach ($types as $type) {
            $schemas[] = $this->build($type, $context);
        }

        return $schemas;
    }

    /**
     * @return list<SchemaBuilder>
     */
    private function defaultBuilders(): array
    {
        return [
            new AutoRepairSchema,
            new LocalBusinessSchema,
            new OrganizationSchema,
            new WebsiteSchema,
            new BreadcrumbSchema,
            new FaqSchema,
            new ArticleSchema,
            new ServiceSchema,
            new ReviewSchema,
            new VehicleSchema,
        ];
    }
}
