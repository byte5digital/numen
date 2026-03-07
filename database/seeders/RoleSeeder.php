<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Seed the 4 built-in system roles.
     *
     * All are marked is_system=true (editable but not deletable).
     * These are global roles (space_id = null).
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full access to everything. All permissions granted via wildcard.',
                'permissions' => ['*'],
                'ai_limits' => null, // Unlimited
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can manage content, media, run and approve pipelines, and use AI generation.',
                'permissions' => [
                    'content.*',
                    'media.*',
                    'pipeline.run',
                    'pipeline.approve',
                    'pipeline.reject',
                    'ai.generate',
                    'ai.model.sonnet',
                    'ai.model.haiku',
                    'ai.image.generate',
                    'settings.personas',
                ],
                'ai_limits' => [
                    'daily_generations' => 100,
                    'daily_image_generations' => 20,
                    'monthly_cost_limit_usd' => 200.00,
                    'allowed_models' => ['claude-haiku-4-5', 'claude-sonnet-4-6'],
                    'max_tokens_per_request' => 8192,
                    'require_approval_above_cost_usd' => 1.00,
                ],
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Can create and edit content, upload media, and run pipelines with Haiku.',
                'permissions' => [
                    'content.create',
                    'content.read',
                    'content.update',
                    'pipeline.run',
                    'media.upload',
                    'ai.generate',
                    'ai.model.haiku',
                ],
                'ai_limits' => [
                    'daily_generations' => 20,
                    'daily_image_generations' => 5,
                    'monthly_cost_limit_usd' => 50.00,
                    'allowed_models' => ['claude-haiku-4-5'],
                    'max_tokens_per_request' => 4096,
                    'require_approval_above_cost_usd' => 0.50,
                ],
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to content and media. No AI generation.',
                'permissions' => [
                    'content.read',
                    'media.read',
                ],
                'ai_limits' => [
                    'daily_generations' => 0,
                    'daily_image_generations' => 0,
                    'monthly_cost_limit_usd' => 0,
                    'allowed_models' => [],
                    'max_tokens_per_request' => 0,
                    'require_approval_above_cost_usd' => null,
                ],
            ],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(
                ['slug' => $data['slug'], 'space_id' => null],
                array_merge($data, ['is_system' => true])
            );
        }

        $this->command->info('✅ 4 built-in roles seeded: Admin, Editor, Author, Viewer');
    }
}
