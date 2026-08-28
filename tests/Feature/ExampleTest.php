<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Repair shop operations without the noise.');
});
