<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_user_can_upload_and_delete_identity_document(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/me/documents', [
            'type' => 'nid',
            'number' => '1234567890123',
            'file' => UploadedFile::fake()->image('nid.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.type', 'nid');

        $document = $user->verificationDocuments()->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertStringNotContainsString('1234567890123', json_encode($response->json()));

        $this->deleteJson('/api/v1/me/documents/'.$document->id)->assertOk();
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_vehicle_document_upload_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $owner->id,
            'type' => 'car',
            'make' => 'Toyota',
            'model' => 'Axio',
            'registration_no' => 'DHAKA-GA-55-1234',
            'seat_capacity' => 4,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/vehicles/'.$vehicle->id.'/documents', [
            'type' => 'registration',
            'file' => UploadedFile::fake()->image('registration.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/vehicles/'.$vehicle->id.'/documents', [
            'type' => 'registration',
            'file' => UploadedFile::fake()->image('registration.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $document = $vehicle->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);

        $this->deleteJson('/api/v1/vehicle-documents/'.$document->id)->assertOk();
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/me/documents', [
            'type' => 'nid',
            'file' => UploadedFile::fake()->create('script.exe', 10),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
