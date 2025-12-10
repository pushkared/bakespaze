<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'Bakespaze',
            'slug' => 'bakespaze',
            'plan' => 'free',
        ]);

        $workspace = Workspace::create([
            'organization_id' => $org->id,
            'name' => 'HQ',
            'slug' => 'hq',
            'is_default' => true,
        ]);

        $team = Team::create([
            'workspace_id' => $workspace->id,
            'name' => 'Core',
            'description' => 'Core team',
        ]);

        $user = User::create([
            'name' => 'Bakespaze Admin',
            'email' => 'admin@bakespaze.test',
            'email_verified_at' => now(),
            'google_id' => 'demo-google-id-' . Str::uuid(),
            'provider' => 'google',
            'avatar_url' => null,
            'password' => null,
        ]);

        Membership::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'team_id' => $team->id,
            'role' => 'admin',
        ]);
    }
}
