<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::table('medical_services')
      ->where('category', 'visit')
      ->update(['category' => 'VISITA']);

    DB::table('medical_services')
      ->where('category', 'exam')
      ->update(['category' => 'ESAME']);
  }

  public function down(): void
  {
    DB::table('medical_services')
      ->where('category', 'VISITA')
      ->update(['category' => 'visit']);

    DB::table('medical_services')
      ->where('category', 'ESAME')
      ->update(['category' => 'exam']);
  }
};
