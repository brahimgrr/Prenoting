<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('doctor_treatment_offerings', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->string('name', 160);
      $table->string('category', 16)->index();
      $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
      $table->unsignedInteger('duration_minutes')->default(30);
      $table->decimal('price', 8, 2)->nullable();
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
      $table->unique(['doctor_id', 'name']);
    });

    $now = now();
    $offerings = DB::table('doctor_services')
      ->join('medical_services', 'doctor_services.service_id', '=', 'medical_services.id')
      ->select([
        'doctor_services.doctor_id',
        'medical_services.name',
        'medical_services.category',
        'medical_services.specialty_id',
        'medical_services.duration_minutes',
        'medical_services.price',
        'medical_services.is_active',
      ])
      ->get()
      ->map(fn ($service) => [
        'doctor_id' => $service->doctor_id,
        'name' => $service->name,
        'category' => $service->category,
        'specialty_id' => $service->specialty_id,
        'duration_minutes' => $service->duration_minutes,
        'price' => $service->price,
        'is_active' => $service->is_active,
        'created_at' => $now,
        'updated_at' => $now,
      ])
      ->all();

    if ($offerings !== []) {
      DB::table('doctor_treatment_offerings')->insertOrIgnore($offerings);
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('doctor_treatment_offerings');
  }
};
