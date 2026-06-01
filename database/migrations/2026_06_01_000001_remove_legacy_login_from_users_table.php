<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $legacyLoginColumn = 'user'.'name';

    Schema::table('users', function (Blueprint $table) use ($legacyLoginColumn): void {
      if (Schema::hasColumn('users', $legacyLoginColumn)) {
        if ($this->indexExists('users', 'users_'.$legacyLoginColumn.'_unique')) {
          $table->dropUnique('users_'.$legacyLoginColumn.'_unique');
        }

        $table->dropColumn($legacyLoginColumn);
      }

      if ($this->indexExists('users', 'users_email_index')) {
        $table->dropIndex('users_email_index');
      }

      if (! $this->indexExists('users', 'users_email_unique')) {
        $table->unique('email');
      }
    });
  }

  public function down(): void
  {
    $legacyLoginColumn = 'user'.'name';

    Schema::table('users', function (Blueprint $table) use ($legacyLoginColumn): void {
      if ($this->indexExists('users', 'users_email_unique')) {
        $table->dropUnique('users_email_unique');
      }

      if (! $this->indexExists('users', 'users_email_index')) {
        $table->index('email');
      }

      if (! Schema::hasColumn('users', $legacyLoginColumn)) {
        $table->string($legacyLoginColumn)->nullable()->unique();
      }
    });
  }

  private function indexExists(string $table, string $name): bool
  {
    return collect(Schema::getIndexes($table))
      ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
  }
};
