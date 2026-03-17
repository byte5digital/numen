<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserImportService
{
    private const ROLE_MAP = [
        'administrator' => 'admin',
        'editor' => 'editor',
        'author' => 'editor',
        'contributor' => 'editor',
        'subscriber' => 'viewer',
        'Owner' => 'admin',
        'Administrator' => 'admin',
        'Editor' => 'editor',
        'Author' => 'editor',
        'Contributor' => 'editor',
        'Super Admin' => 'admin',
        'strapi-super-admin' => 'admin',
        'strapi-editor' => 'editor',
        'strapi-author' => 'editor',
        'admin' => 'admin',
        'viewer' => 'viewer',
    ];

    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
    ) {}

    /**
     * Import all users for the given migration session.
     *
     * @return Collection<string, int> source user ID => Numen user ID
     */
    public function importUsers(MigrationSession $session): Collection
    {
        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        $sourceUsers = $connector->getUsers();
        $mapping = collect();

        foreach ($sourceUsers as $sourceUser) {
            if (! is_array($sourceUser)) {
                continue;
            }

            $sourceId = (string) ($sourceUser['id'] ?? $sourceUser['_id'] ?? '');
            $email = (string) ($sourceUser['email'] ?? '');
            $name = (string) ($sourceUser['name'] ?? $sourceUser['display_name'] ?? $sourceUser['username'] ?? 'Unknown');
            $sourceRole = (string) ($sourceUser['role'] ?? $sourceUser['roles'][0] ?? 'author');

            if ($sourceId === '') {
                continue;
            }

            try {
                $numenUser = $this->findOrCreateUser($email, $name, $sourceRole);
                $mapping->put($sourceId, $numenUser->id);
            } catch (\Throwable $e) {
                Log::warning('UserImportService: failed to import user', [
                    'source_id' => $sourceId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mapping;
    }

    private function findOrCreateUser(string $email, string $name, string $sourceRole): User
    {
        if ($email !== '') {
            $existing = User::where('email', $email)->first();

            if ($existing) {
                return $existing;
            }
        }

        $numenRole = self::ROLE_MAP[$sourceRole] ?? 'editor';

        return User::create([
            'name' => $name,
            'email' => $email !== '' ? $email : $this->generatePlaceholderEmail($name),
            'password' => Hash::make(Str::random(32)),
            'role' => $numenRole,
        ]);
    }

    /**
     * Map a source role to a Numen role.
     */
    public function mapRole(string $sourceRole): string
    {
        return self::ROLE_MAP[$sourceRole] ?? 'editor';
    }

    private function generatePlaceholderEmail(string $name): string
    {
        $slug = Str::slug($name);

        return sprintf('migrated-%s-%s@numen.local', $slug, Str::random(6));
    }
}
