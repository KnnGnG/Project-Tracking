<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_sent_to_login_from_home(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }
}
