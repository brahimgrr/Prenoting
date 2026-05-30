<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('working_hours')) {
      DB::table('working_hours')->delete();
    }
  }

  public function down(): void
  {
    // Deleted working-hour templates cannot be reconstructed.
  }
};
