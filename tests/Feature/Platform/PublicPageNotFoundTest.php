<?php

test('an unknown address is a missing page, not an unpublished website', function (): void {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('We could not find that page.')
        ->assertDontSee('not published');

    $this->get('/services/brakes/old-link.html')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertDontSee('not published');

    $this->get('/')
        ->assertNotFound()
        ->assertSee('This website is not published.');
});
