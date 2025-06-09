<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create article', function () {
    $response = $this->postJson('/api/articles', [
        'title' => 'Test',
        'content' => 'Content',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('articles', ['title' => 'Test']);
});
