<?php

use App\Models\User;

test('users can login with valid credentials via api.', function () {
    $user = User::factory()->create([
        'email' => 'user1test@gmail.com',
        'password' => bcrypt('password')
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'user1test@gmail.com',
        'password' => 'password'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id', 'name', 'email'
            ],
            'token'
        ])
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);

    expect($response->json('token'))->not->toBeEmpty();
    $this->assertAuthenticated();
});

test('users cannot login with invalid credentials via api.', function () {
    $user = User::factory()->create([
        'email' => 'user1test@gmail.com',
        'password' => bcrypt('password')
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'rambahdurtest@gmail.com',
        'password' => 'password'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});


test('login requires valid email format.', function () {
    $user = User::factory()->create([
        'email' => 'user1test@gmail.com',
        'password' => bcrypt('password')
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'aforapple.com',
        'password' => 'password'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest();
})->only();