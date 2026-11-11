<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_list_has_pagination(): void
    {
        $this->getJson('/api/notes')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_list_is_paginated_with_latest_first(): void
    {
        Note::factory()->count(12)->create();
        $latest = Note::latest('id')->first();
        $this->getJson('/api/notes?per_page=5&page=1')->assertOk()
            ->assertJsonCount(5, 'data')->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('meta.total', 12)->assertJsonPath('meta.last_page', 3);
        $this->getJson('/api/notes?per_page=5&page=3')->assertJsonCount(2, 'data');
    }

    public function test_create_returns_201_resource_and_location(): void
    {
        $payload = ['title' => 'Планы', 'content' => "Первая строка\nВторая строка"];
        $response = $this->postJson('/api/notes', $payload)->assertCreated()
            ->assertJsonPath('data.title', 'Планы')->assertJsonPath('data.content', $payload['content'])
            ->assertJsonStructure(['data' => ['id', 'title', 'content', 'created_at', 'updated_at']]);
        $response->assertHeader('Location', url('/api/notes/'.$response->json('data.id')));
        $this->assertDatabaseHas('notes', $payload);
    }

    public function test_show_returns_note(): void
    {
        $note = Note::factory()->create();
        $this->getJson('/api/notes/'.$note->id)->assertOk()
            ->assertJsonPath('data.id', $note->id)->assertJsonPath('data.content', $note->content);
    }

    public function test_put_replaces_editable_fields_and_preserves_creation_time(): void
    {
        $note = Note::factory()->create();
        $response = $this->putJson('/api/notes/'.$note->id, ['title' => 'Changed', 'content' => 'New text'])
            ->assertOk()->assertJsonPath('data.title', 'Changed');
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'content' => 'New text']);
        $this->assertSame($note->created_at->toISOString(), $response->json('data.created_at'));
    }

    public function test_patch_updates_one_field(): void
    {
        $note = Note::factory()->create();
        $this->patchJson('/api/notes/'.$note->id, ['title' => 'Changed'])->assertOk()
            ->assertJsonPath('data.content', $note->content)->assertJsonPath('data.title', 'Changed');
    }

    public function test_delete_returns_empty_204_and_removes_note(): void
    {
        $note = Note::factory()->create();
        $this->deleteJson('/api/notes/'.$note->id)->assertNoContent();
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
        $this->getJson('/api/notes/'.$note->id)->assertNotFound();
    }

    #[DataProvider('invalidBodies')]
    public function test_create_and_put_reject_invalid_fields(array $payload, string $field): void
    {
        $note = Note::factory()->create();
        $this->postJson('/api/notes', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson('/api/notes/'.$note->id, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('notes', 1);
        $this->assertSame($note->title, $note->fresh()->title);
    }

    public static function invalidBodies(): array
    {
        return [
            'missing title' => [['content' => 'Text'], 'title'],
            'missing content' => [['title' => 'Title'], 'content'],
            'blank title' => [['title' => '   ', 'content' => 'Text'], 'title'],
            'blank content' => [['title' => 'Title', 'content' => '  '], 'content'],
            'long title' => [['title' => str_repeat('я', 256), 'content' => 'Text'], 'title'],
            'long content' => [['title' => 'Title', 'content' => str_repeat('я', 10001)], 'content'],
            'array title' => [['title' => ['bad'], 'content' => 'Text'], 'title'],
            'numeric content' => [['title' => 'Title', 'content' => 123], 'content'],
            'null content' => [['title' => 'Title', 'content' => null], 'content'],
        ];
    }

    public function test_patch_rejects_empty_body_and_blank_fields(): void
    {
        $note = Note::factory()->create();
        $this->patchJson('/api/notes/'.$note->id, [])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson('/api/notes/'.$note->id, ['title' => null])->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_list_parameters_are_rejected(string $query, string $field): void
    {
        $this->getJson('/api/notes?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidQueries(): array
    {
        return [ ['page=0', 'page'], ['page=abc', 'page'], ['page[]=1', 'page'],
            ['per_page=101', 'per_page'], ['per_page=0', 'per_page'], ['per_page=1.5', 'per_page'] ];
    }

    #[DataProvider('invalidIds')]
    public function test_every_item_method_validates_id(string $id): void
    {
        $this->getJson('/api/notes/'.$id)->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->putJson('/api/notes/'.$id, ['title' => 'Title', 'content' => 'Text'])->assertUnprocessable();
        $this->patchJson('/api/notes/'.$id, ['title' => 'Title'])->assertUnprocessable();
        $this->deleteJson('/api/notes/'.$id)->assertUnprocessable()->assertJsonValidationErrors('id');
    }

    public static function invalidIds(): array
    {
        return [['abc'], ['0'], ['-1'], ['1.5'], ['2147483648']];
    }

    public function test_missing_note_returns_404_for_every_item_method(): void
    {
        $this->getJson('/api/notes/123')->assertNotFound();
        $this->putJson('/api/notes/123', ['title' => 'Title', 'content' => 'Text'])->assertNotFound();
        $this->patchJson('/api/notes/123', ['title' => 'Title'])->assertNotFound();
        $this->deleteJson('/api/notes/123')->assertNotFound();
    }

    public function test_server_managed_fields_cannot_be_overwritten(): void
    {
        $this->postJson('/api/notes', ['id' => 999, 'created_at' => '2000-01-01', 'title' => 'Title', 'content' => 'Text'])
            ->assertCreated()->assertJsonPath('data.id', 1);
        $this->assertNotSame('2000-01-01', Note::first()->created_at->toDateString());
    }

    public function test_limits_accept_multibyte_text_and_api_errors_are_json_without_accept_header(): void
    {
        $this->postJson('/api/notes', ['title' => str_repeat('я', 255), 'content' => str_repeat('я', 10000)])->assertCreated();
        $this->get('/api/notes/not-an-id')->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
    }

    public function test_openapi_contract_contains_all_operations(): void
    {
        $spec = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3.0.3', $spec['openapi']);
        foreach (['get', 'post'] as $method) $this->assertArrayHasKey($method, $spec['paths']['/api/notes']);
        foreach (['get', 'put', 'patch', 'delete'] as $method) $this->assertArrayHasKey($method, $spec['paths']['/api/notes/{id}']);
    }
}
