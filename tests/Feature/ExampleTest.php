<?php

it('returns a successful response', function () {
    $response = $this->get('/api');

    $response->assertStatus(200);
});
