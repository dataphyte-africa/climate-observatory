<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederCredentialPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_create_known_seed_user_outside_local_or_testing(): void
    {
        User::query()
            ->where('email', 'test@example.com')
            ->delete();

        $this->app->detectEnvironment(fn (): string => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertFalse(
            User::query()
                ->where('email', 'test@example.com')
                ->exists()
        );
    }
}
