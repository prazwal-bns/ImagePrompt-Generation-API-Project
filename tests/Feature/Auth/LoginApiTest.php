<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function(){
    $throttleKey = Str::transliterate(Str::lower('user1test@gmail.com' . '|' . '127.0.0.1'));

    RateLimiter::clear($throttleKey);
});

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
        'email' => 'wronguser@gmail.com',
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
        'email' => 'user1test.com',
        'password' => 'password'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});

test('login is rate limited after too many attempts',function(){
    $user = User::factory()->create([
        'email' => 'user1test@gmail.com',
        'password' => bcrypt('password')
    ]);

    // make 5 failed attempts
    for($i=0; $i<5; $i++){
        $this->postJson('/api/login', [
            'email' => 'user1test@gmail.com',
            'password' => 'wrongpassword'
        ]);
    }

    // on 6th attempt it should be rate limited
    $response = $this->postJson('/api/login', [
        'email' => 'user1test@gmail.com',
        'password' => 'password'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});


test('login works after rate limit expires',function(){
    $user = User::factory()->create([
        'email' => 'user1test@gmail.com',
        'password' => bcrypt('password')
    ]);

    // make 5 failed attempts
    for($i=0; $i<5; $i++){
        $this->postJson('/api/login', [
            'email' => 'user1test@gmail.com',
            'password' => 'wrongpassword'
        ]);
    }

    $throttleKey = Str::transliterate(Str::lower('user1test@gmail.com' . '|' . '127.0.0.1'));
    RateLimiter::clear($throttleKey);

    // after clearing rate limit login should work
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
})->only();