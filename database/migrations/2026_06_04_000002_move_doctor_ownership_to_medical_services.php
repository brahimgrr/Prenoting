<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('medical_services', function (Blueprint $table): void {
      if (! Schema::hasColumn('medical_services', 'doctor_profile_id')) {
        $table->foreignId('doctor_profile_id')
          ->nullable()
          ->after('id')
          ->constrained('doctor_profiles')
          ->restrictOnDelete();
      }
    });

    if (Schema::hasColumn('appointments', 'doctor_profile_id')) {
      DB::table('appointments')
        ->select(['service_id', DB::raw('MIN(doctor_profile_id) as doctor_profile_id')])
        ->whereNotNull('doctor_profile_id')
        ->groupBy('service_id')
        ->orderBy('service_id')
        ->each(function (object $ownership): void {
          DB::table('medical_services')
            ->where('id', $ownership->service_id)
            ->whereNull('doctor_profile_id')
            ->update(['doctor_profile_id' => $ownership->doctor_profile_id]);
        });
    }

    $fallbackDoctorId = DB::table('doctor_profiles')->orderBy('id')->value('id');
    if ($fallbackDoctorId) {
      DB::table('medical_services')
        ->whereNull('doctor_profile_id')
        ->update(['doctor_profile_id' => $fallbackDoctorId]);
    }

    Schema::table('appointments', function (Blueprint $table): void {
      if (Schema::hasColumn('appointments', 'doctor_profile_id')) {
        $table->dropForeign(['doctor_profile_id']);
        $table->dropColumn('doctor_profile_id');
      }
    });
  }

  public function down(): void
  {
    Schema::table('appointments', function (Blueprint $table): void {
      if (! Schema::hasColumn('appointments', 'doctor_profile_id')) {
        $table->foreignId('doctor_profile_id')
          ->nullable()
          ->after('patient_id')
          ->constrained('doctor_profiles')
          ->restrictOnDelete();
      }
    });

    if (Schema::hasColumn('medical_services', 'doctor_profile_id')) {
      DB::table('appointments')
        ->join('medical_services', 'appointments.service_id', '=', 'medical_services.id')
        ->whereNull('appointments.doctor_profile_id')
        ->update(['appointments.doctor_profile_id' => DB::raw('medical_services.doctor_profile_id')]);
    }

    Schema::table('medical_services', function (Blueprint $table): void {
      if (Schema::hasColumn('medical_services', 'doctor_profile_id')) {
        $table->dropForeign(['doctor_profile_id']);
        $table->dropColumn('doctor_profile_id');
      }
    });
  }
};
