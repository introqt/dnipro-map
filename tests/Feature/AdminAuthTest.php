<?php

use App\Models\User;

test('login page is accessible', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
});

test('correct password sets session and redirects', function () {
    config(['app.admin_password' => 'secret123']);
    config(['services.telegram.admin_id' => '99999,88888']);

    $response = $this->post('/admin/login', ['password' => 'secret123']);

    $response->assertRedirect('/admin');
    $this->assertEquals('99999', session('admin_telegram_id'));
});

test('wrong password fails with error', function () {
    config(['app.admin_password' => 'secret123']);

    $response = $this->post('/admin/login', ['password' => 'wrong']);

    $response->assertRedirect();
    $response->assertSessionHasErrors('password');
    $this->assertNull(session('admin_telegram_id'));
});

test('admin redirects to login when unauthenticated', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});

test('admin is accessible with valid session', function () {
    config(['services.telegram.admin_id' => '99999']);

    $response = $this->withSession(['admin_telegram_id' => '99999'])
        ->get('/admin');

    $response->assertOk();
});

test('admin is accessible via telegram for admin user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->get('/admin?telegram_id='.$admin->telegram_id);

    $response->assertOk();
});

test('logout clears session', function () {
    $response = $this->withSession(['admin_telegram_id' => '99999'])
        ->post('/admin/logout');

    $response->assertRedirect('/admin/login');
    $this->assertNull(session('admin_telegram_id'));
});
