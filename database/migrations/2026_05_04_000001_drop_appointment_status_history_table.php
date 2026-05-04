<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::dropIfExists('appointment_status_history');
  }

  public function down(): void
  {
    // Tabella rimossa definitivamente: nessun rollback.
  }
};
