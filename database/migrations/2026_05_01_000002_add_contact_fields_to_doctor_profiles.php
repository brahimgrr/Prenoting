<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('doctor_profiles', function (Blueprint $table): void {
      if (! Schema::hasColumn('doctor_profiles', 'phone')) {
        $table->string('phone', 32)->default('')->after('license_number');
      }

      if (! Schema::hasColumn('doctor_profiles', 'clinic_address')) {
        $table->string('clinic_address')->default('')->after('phone');
      }
    });
  }

  public function down(): void
  {
    Schema::table('doctor_profiles', function (Blueprint $table): void {
      if (Schema::hasColumn('doctor_profiles', 'clinic_address')) {
        $table->dropColumn('clinic_address');
      }

      if (Schema::hasColumn('doctor_profiles', 'phone')) {
        $table->dropColumn('phone');
      }
    });
  }
};
