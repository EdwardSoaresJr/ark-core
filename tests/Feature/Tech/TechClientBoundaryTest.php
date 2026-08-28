<?php

test('shop glass does not depend on ark tech', function (): void {
    $pubspec = file_get_contents(base_path('apps/advisor_station/pubspec.yaml'));

    expect($pubspec)->not->toContain('ark_tech');
});

test('ark tech is a standalone flutter application', function (): void {
    expect(is_file(base_path('apps/ark_tech/pubspec.yaml')))->toBeTrue();
    expect(file_get_contents(base_path('apps/ark_tech/pubspec.yaml')))->toContain('ARK Tech');
    expect(file_get_contents(base_path('apps/ark_tech/lib/main.dart')))->not->toContain('Coming In');
    expect(file_get_contents(base_path('apps/ark_tech/lib/main.dart')))->not->toContain('approvals');
});
