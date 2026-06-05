<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->upsert([
            [
                'name' => User::ROLE_PATIENT,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => User::ROLE_DOCTOR,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name', 'guard_name'], ['updated_at']);

        if (Schema::hasColumn('users', 'role')) {
            $roles = DB::table('roles')
                ->whereIn('name', [User::ROLE_PATIENT, User::ROLE_DOCTOR])
                ->where('guard_name', 'web')
                ->pluck('id', 'name');

            DB::table('users')
                ->whereIn('role', [User::ROLE_PATIENT, User::ROLE_DOCTOR])
                ->orderBy('id')
                ->get()
                ->each(function (object $user) use ($roles): void {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $roles[$user->role],
                        'model_type' => User::class,
                        'model_id' => $user->id,
                    ]);
                });

            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 32)->default(User::ROLE_PATIENT)->index();
            });
        }

        $userRoles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', [User::ROLE_PATIENT, User::ROLE_DOCTOR])
            ->select('model_has_roles.model_id', 'roles.name')
            ->orderBy('model_has_roles.model_id')
            ->get();

        foreach ($userRoles as $userRole) {
            DB::table('users')
                ->where('id', $userRole->model_id)
                ->update(['role' => $userRole->name]);
        }
    }
};
