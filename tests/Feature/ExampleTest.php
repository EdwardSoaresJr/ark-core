<?php

it('shows an unpublished website until a publication exists', function () {
    $this->get('/')->assertNotFound()->assertSee('This website is not published.');
});
