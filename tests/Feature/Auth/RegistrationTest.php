<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_registration_is_disabled_for_public_users(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
