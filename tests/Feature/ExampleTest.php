<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
