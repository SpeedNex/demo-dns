<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProfilePublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_profile_creation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $token = $response->json('data.token');

        // Create a profile
        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/user/profiles', [
            'name' => 'Home Profile',
        ]);

        $profileResponse->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name']]);

        $profileId = $profileResponse->json('data.id');

        // List profiles
        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user/profiles');

        $listResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);

        // Add a rule
        $ruleResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/user/profiles/{$profileId}/rules", [
            'list_type' => 'block',
            'match_type' => 'exact',
            'domain' => 'ads.example.com',
            'action' => 'block',
        ]);

        $ruleResponse->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'domain', 'list_type']]);
    }
}
