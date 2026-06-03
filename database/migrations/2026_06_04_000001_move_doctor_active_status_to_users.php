<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table): void {
      if (! Schema::hasColumn('users', 'is_active')) {
        $table->boolean('is_active')->default(true)->index()->after('role');
      }
    });

    if (Schema::hasColumn('doctor_profiles', 'is_active')) {
      DB::table('doctor_profiles')
        ->select(['user_id', 'is_active'])
        ->orderBy('id')
        ->each(function (object $doctor): void {
          DB::table('users')
            ->where('id', $doctor->user_id)
            ->update(['is_active' => (bool) $doctor->is_active]);
        });
    }

    Schema::table('doctor_profiles', function (Blueprint $table): void {
      if (Schema::hasColumn('doctor_profiles', 'bio')) {
        $table->dropColumn('bio');
      }

      if (Schema::hasColumn('doctor_profiles', 'is_active')) {
        $table->dropIndex(['is_active']);
        $table->dropColumn('is_active');
      }
    });
  }

  public function down(): void
  {
    Schema::table('doctor_profiles', function (Blueprint $table): void {
      if (! Schema::hasColumn('doctor_profiles', 'bio')) {
        $table->text('bio')->nullable()->after('display_name');
      }

      if (! Schema::hasColumn('doctor_profiles', 'is_active')) {
        $table->boolean('is_active')->default(true)->index()->after('license_number');
      }
    });

    if (Schema::hasColumn('users', 'is_active')) {
      DB::table('doctor_profiles')
        ->select(['user_id'])
        ->orderBy('id')
        ->each(function (object $doctor): void {
          $isActive = DB::table('users')->where('id', $doctor->user_id)->value('is_active');

          DB::table('doctor_profiles')
            ->where('user_id', $doctor->user_id)
            ->update(['is_active' => (bool) $isActive]);
        });
    }

    Schema::table('users', function (Blueprint $table): void {
      if (Schema::hasColumn('users', 'is_active')) {
        $table->dropIndex(['is_active']);
        $table->dropColumn('is_active');
      }
    });
  }
};
