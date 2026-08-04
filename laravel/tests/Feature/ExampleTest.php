<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_expired_web_sessions_return_to_login_with_a_useful_message(): void
    {
        Route::post('/_test/expired-session', function (): never {
            throw new TokenMismatchException();
        });

        $response = $this->from('/login')->post('/_test/expired-session');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('session');
    }

    public function test_expired_json_sessions_keep_the_419_status(): void
    {
        Route::post('/_test/expired-json-session', function (): never {
            throw new TokenMismatchException();
        });

        $response = $this->postJson('/_test/expired-json-session');

        $response
            ->assertStatus(419)
            ->assertJsonPath('message', 'La sesión expiró. Recargá la página e intentá nuevamente.');
    }
}
