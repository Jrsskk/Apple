<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_landing_page_is_displayed(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Learning that stays connected');
    }
}
