<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the 4 built-in system roles: Admin, Editor, Author, Viewer.
 *
 * All have is_system = true — they can be edited (permissions changed)
 * but never deleted via the API.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full system access. Can do everything.',
                'permissions' => ['*'],
                'ai_limits' => null, // Unlimited — ai.budget.unlimited via wildcard
                'is_system' => true,
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Content management and pipeline oversight. Standard AI access.',
                'permissions' => [
                    'content.create', 'content.read', 'content.update', 'content.delete',
                    'content.publish', 'content.unpublish',
                    'pipeline.run', 'pipeline.approve', 'pipeline.reject',
                    'media.upload', 'media.delete', 'media.organize', 'media.read',
                    'ai.generate', 'ai.model.sonnet', 'ai.model.haiku',
                    'ai.image.generate',
                    'settings.personas',
                ],
                'ai_limits' => [
                    'daily_generations' => 100,
                    'daily_image_generations' => 20,
                    'monthly_cost_limit_usd' => 200.00,
                    'allowed_models' => ['claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-haiku-4-5-20251001'],
                    'max_tokens_per_request' => 8192,
                    'require_approval_above_cost_usd' => 1.00,
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Creates and edits content. Limited AI access (Haiku only).',
                'permissions' => [
                    'content.create', 'content.read', 'content.update',
                    'pipeline.run',
                    'media.upload', 'media.read',
                    'ai.generate', 'ai.model.haiku',
                ],
                'ai_limits' => [
                    'daily_generations' => 20,
                    'daily_image_generations' => 5,
                    'monthly_cost_limit_usd' => 20.00,
                    'allowed_models' => ['claude-haiku-4-5', 'claude-haiku-4-5-20251001'],
                    'max_tokens_per_request' => 4096,
                    'require_approval_above_cost_usd' => 0.50,
                ],
                'is_system' => true,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access. No AI generation.',
                'permissions' => [
                    'content.read',
                    'media.read',
                ],
                'ai_limits' => [
                    'daily_generations' => 0,
                    'daily_image_generations' => 0,
                    'monthly_cost_limit_usd' => 0.0,
                    'allowed_models' => [],
                    'max_tokens_per_request' => 0,
                    'require_approval_above_cost_usd' => null,
                ],
                'is_system' => true,
            ],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(
                ['slug' => $data['slug'], 'space_id' => null],
                array_merge($data, ['id' => Str::ulid()->toBase32()]),
            );
        }
    }
}
