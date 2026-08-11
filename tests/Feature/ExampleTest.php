<?php

test('redirects guests to the login page', function () {
    $response = $this->get(route('welcome'));

    $response->assertRedirect(route('login'));
});
