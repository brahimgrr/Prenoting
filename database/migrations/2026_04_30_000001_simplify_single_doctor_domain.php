<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('doctor_services')) {
      Schema::drop('doctor_services');
    }

    Schema::table('appointments', function (Blueprint $table): void {
      if (Schema::hasColumn('appointments', 'doctor_id')) {
        $table->dropForeign(['doctor_id']);
        $table->dropColumn('doctor_id');
      }
      if (Schema::hasColumn('appointments', 'clinic_id')) {
        $table->dropForeign(['clinic_id']);
        $table->dropColumn('clinic_id');
      }
    });

    Schema::table('availability_slots', function (Blueprint $table): void {
      if (Schema::hasColumn('availability_slots', 'doctor_id')) {
        $table->dropForeign(['doctor_id']);
        $table->dropUnique(['doctor_id', 'start_at']);
        $table->dropColumn('doctor_id');
      }
      if (Schema::hasColumn('availability_slots', 'clinic_id')) {
        $table->dropForeign(['clinic_id']);
        $table->dropColumn('clinic_id');
      }
      $table->unique('start_at');
    });

    Schema::table('doctor_treatment_offerings', function (Blueprint $table): void {
      if (Schema::hasColumn('doctor_treatment_offerings', 'doctor_id')) {
        $table->dropForeign(['doctor_id']);
        $table->dropUnique(['doctor_id', 'name']);
        $table->dropColumn('doctor_id');
      }
      if (Schema::hasColumn('doctor_treatment_offerings', 'specialty_id')) {
        $table->dropForeign(['specialty_id']);
        $table->dropColumn('specialty_id');
      }
      $table->unique('name');
    });

    Schema::table('medical_services', function (Blueprint $table): void {
      if (Schema::hasColumn('medical_services', 'specialty_id')) {
        $table->dropForeign(['specialty_id']);
        $table->dropColumn('specialty_id');
      }
    });

    Schema::table('doctor_profiles', function (Blueprint $table): void {
      if (Schema::hasColumn('doctor_profiles', 'specialty_id')) {
        $table->dropForeign(['specialty_id']);
        $table->dropColumn('specialty_id');
      }
    });

    if (Schema::hasTable('clinic_locations')) {
      Schema::drop('clinic_locations');
    }

    if (Schema::hasTable('specialties')) {
      Schema::drop('specialties');
    }
  }

  public function down(): void
  {
    Schema::create('specialties', function (Blueprint $table): void {
      $table->id();
      $table->string('name', 120)->unique();
      $table->text('description')->nullable();
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
  }
};
