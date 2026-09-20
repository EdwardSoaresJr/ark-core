<?php

test('portal estimate token reads are rate limited per ip', function () {
    $token = str_repeat('z', 64);

    for ($attempt = 0; $attempt < 60; $attempt++) {
        $this->get(route('portal.estimates.show', ['token' => $token]))
            ->assertNotFound();
    }

    $this->get(route('portal.estimates.show', ['token' => $token]))
        ->assertStatus(429);
});

test('portal pay token reads are rate limited per ip', function () {
    $token = str_repeat('y', 64);

    for ($attempt = 0; $attempt < 60; $attempt++) {
        $this->get(route('portal.invoice-pay.show', ['token' => $token]))
            ->assertNotFound();
    }

    $this->get(route('portal.invoice-pay.show', ['token' => $token]))
        ->assertStatus(429);
});

test('portal estimate authorization posts are rate limited per ip', function () {
    $token = str_repeat('x', 64);

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->post(route('portal.estimates.authorize', ['token' => $token]), [
            'decisions' => [],
        ])->assertNotFound();
    }

    $this->post(route('portal.estimates.authorize', ['token' => $token]), [
        'decisions' => [],
    ])->assertStatus(429);
});
