<?php

use App\Models\Appointment;
use App\Models\MedicalService;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('users', function (Blueprint $table): void {
      $table->id();
      $table->string('username')->unique();
      $table->string('email')->nullable()->index();
      $table->string('first_name')->default('');
      $table->string('last_name')->default('');
      $table->string('password');
      $table->string('role', 32)->default(User::ROLE_PATIENT)->index();
      $table->rememberToken();
      $table->timestamps();
    });

    Schema::create('specialties', function (Blueprint $table): void {
      $table->id();
      $table->string('name', 120)->unique();
      $table->text('description')->nullable();
      $table->timestamps();
    });

    Schema::create('patient_profiles', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
      $table->date('date_of_birth')->nullable();
      $table->string('gender', 32)->default('');
      $table->string('phone', 32);
      $table->string('address')->default('');
      $table->string('identity_code', 64)->default('');
      $table->timestamps();
    });

    Schema::create('doctor_profiles', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
      $table->string('display_name', 160);
      $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
      $table->text('bio')->nullable();
      $table->string('license_number', 80)->default('');
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
    });

    Schema::create('clinic_locations', function (Blueprint $table): void {
      $table->id();
      $table->string('name', 160);
      $table->string('address');
      $table->string('phone', 32)->default('');
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
    });

    Schema::create('medical_services', function (Blueprint $table): void {
      $table->id();
      $table->string('name', 160);
      $table->string('category', 16)->default(MedicalService::CATEGORY_VISIT)->index();
      $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
      $table->unsignedInteger('duration_minutes')->default(30);
      $table->decimal('price', 8, 2)->nullable();
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();
    });

    Schema::create('doctor_services', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->foreignId('service_id')->constrained('medical_services')->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['doctor_id', 'service_id']);
    });

    Schema::create('availability_slots', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
      $table->foreignId('clinic_id')->constrained('clinic_locations')->restrictOnDelete();
      $table->dateTime('start_at')->index();
      $table->dateTime('end_at');
      $table->boolean('is_blocked')->default(false)->index();
      $table->boolean('is_booked')->default(false)->index();
      $table->timestamps();
      $table->unique(['doctor_id', 'start_at']);
    });

    Schema::create('appointments', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('patient_id')->constrained('patient_profiles')->restrictOnDelete();
      $table->foreignId('doctor_id')->constrained('doctor_profiles')->restrictOnDelete();
      $table->foreignId('service_id')->constrained('medical_services')->restrictOnDelete();
      $table->foreignId('clinic_id')->constrained('clinic_locations')->restrictOnDelete();
      $table->foreignId('slot_id')->constrained('availability_slots')->restrictOnDelete();
      $table->dateTime('start_at')->index();
      $table->dateTime('end_at');
      $table->string('status', 24)->default(Appointment::STATUS_CONFIRMED)->index();
      $table->text('notes')->nullable();
      $table->text('cancellation_reason')->nullable();
      $table->timestamps();
    });

  }

  public function down(): void
  {
    Schema::dropIfExists('appointments');
    Schema::dropIfExists('availability_slots');
    Schema::dropIfExists('doctor_services');
    Schema::dropIfExists('medical_services');
    Schema::dropIfExists('clinic_locations');
    Schema::dropIfExists('doctor_profiles');
    Schema::dropIfExists('patient_profiles');
    Schema::dropIfExists('specialties');
    Schema::dropIfExists('users');
  }
};
