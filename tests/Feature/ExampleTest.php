<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_backend_does_not_expose_a_root_product_page(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
