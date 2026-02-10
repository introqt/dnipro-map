<?php

use App\Models\Point;

test('color is red when created less than 1 hour ago', function () {
    $point = Point::factory()->create(['created_at' => now()->subMinutes(30)]);

    expect($point->color)->toBe('red');
});

test('color is yellow when created between 1 and 2 hours ago', function () {
    $point = Point::factory()->create(['created_at' => now()->subMinutes(90)]);

    expect($point->color)->toBe('yellow');
});

test('color is green when created between 2 and 3 hours ago', function () {
    $point = Point::factory()->create(['created_at' => now()->subMinutes(150)]);

    expect($point->color)->toBe('green');
});

test('color is gray when created more than 3 hours ago', function () {
    $point = Point::factory()->create(['created_at' => now()->subHours(4)]);

    expect($point->color)->toBe('gray');
});
