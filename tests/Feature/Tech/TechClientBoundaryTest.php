<?php

test('mobile api contract routes remain available for third party clients', function (): void {
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->uri(), 'api/mobile/'))
        ->map(fn ($route) => $route->uri())
        ->unique()
        ->values()
        ->all();

    expect($routes)->not->toBeEmpty();
});

test('public core does not ship first party app implementation source', function (): void {
    expect(is_dir(base_path('apps')))->toBeFalse();
});
