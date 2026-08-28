<?php

namespace App\Ark\Growth\Sitemap;

enum SitemapSection: string
{
    case Pages = 'pages';
    case Services = 'services';
    case CommonProblems = 'common_problems';
    case Manufacturers = 'manufacturers';
    case Vehicles = 'vehicles';
    case Blog = 'blog';
    case Images = 'images';

    public function label(): string
    {
        return match ($this) {
            self::Pages => 'Pages',
            self::Services => 'Services',
            self::CommonProblems => 'Common Problems',
            self::Manufacturers => 'Manufacturers',
            self::Vehicles => 'Vehicles',
            self::Blog => 'Blog',
            self::Images => 'Images',
        };
    }
}
