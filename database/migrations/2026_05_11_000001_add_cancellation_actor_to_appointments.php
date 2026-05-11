<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('appointments', function (Blueprint $table): void {
      $table->string('cancelled_by_role', 24)->nullable()->after('cancellation_reason')->index();
      $table->foreignId('cancelled_by_user_id')
        ->nullable()
        ->after('cancelled_by_role')
        ->constrained('users')
        ->nullOnDelete();
      $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_user_id');
    });

    DB::table('appointments')
      ->where('status', 'cancelled')
      ->whereIn('cancellation_reason', [
        'Cambio orario lavoro medico',
        'Chiusura straordinaria studio',
      ])
      ->update([
        'cancelled_by_role' => 'doctor',
        'cancelled_at' => DB::raw('updated_at'),
      ]);

    DB::table('appointments')
      ->where('status', 'cancelled')
      ->whereNull('cancelled_by_role')
      ->whereNotNull('cancellation_reason')
      ->where('cancellation_reason', '!=', '')
      ->update([
        'cancelled_by_role' => 'patient',
        'cancelled_at' => DB::raw('updated_at'),
      ]);
  }

  public function down(): void
  {
    Schema::table('appointments', function (Blueprint $table): void {
      $table->dropForeign(['cancelled_by_user_id']);
      $table->dropColumn([
        'cancelled_by_role',
        'cancelled_by_user_id',
        'cancelled_at',
      ]);
    });
  }
};
