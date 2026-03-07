# Permissions Architecture — Numen RBAC & AI Governance

> **ADR-005** · Blueprint 🏗️ · 2026-03-07
> Status: **Proposed** · Branch: `feature/permissions`

---

## 1. Overview

Numen currently stores a `role` string on the `users` table and has a single `EnsureUserIsAdmin` middleware. This is insufficient for multi-user teams. This document defines a full RBAC system with AI-specific governance: budget controls, model access restrictions, and pipeline approval workflows.

### Design Principles

1. **No external packages** — Numen ships its own RBAC (no Spatie dependency). Keeps the dependency tree small and gives us full control over AI-specific permission semantics.
2. **Permissions are strings** — e.g. `content.publish`, `ai.model.opus`. Flat, greppable, composable.
3. **Roles are permission bundles** — a role is just a named set of permissions. Built-in roles are seeded but editable.
4. **Space-scoped** — a user can be Editor in Space A and Viewer in Space B.
5. **Tokens inherit** — Sanctum tokens and API keys are scoped to a subset of the user's effective permissions.
6. **AI governance is first-class** — model access, budget limits, and pipeline approvals are permissions, not afterthoughts.

---

## 2. Data Model

### 2.1 New Tables

```
roles
├── id (ULID)
├── space_id (FK, nullable — null = global/system role)
├── name (string, unique per space)
├── slug (string, unique per space)
├── description (text, nullable)
├── permissions (JSON — array of permission strings)
├── ai_limits (JSON, nullable — see §4)
├── is_system (bool, default false — protects built-in roles from deletion)
├── created_at
└── updated_at

role_user (pivot)
├── id (bigint auto)
├── user_id (FK)
├── role_id (FK)
├── space_id (FK, nullable — null = global assignment)
├── created_at
└── updated_at
UNIQUE(user_id, role_id, space_id)

audit_logs
├── id (ULID)
├── user_id (FK, nullable — null for system actions)
├── space_id (FK, nullable)
├── action (string — e.g. 'content.publish', 'role.assign', 'ai.generation')
├── resource_type (string, nullable — e.g. 'App\Models\Content')
├── resource_id (string, nullable)
├── metadata (JSON — context-dependent payload)
├── ip_address (string, nullable)
├── user_agent (string, nullable)
├── created_at
INDEX(user_id, created_at)
INDEX(space_id, action, created_at)
INDEX(resource_type, resource_id)
```

### 2.2 Modified Tables

**`users`** — Drop the `role` string column after migration. During transition, a migration seeds `role_user` entries based on existing `role` values, then removes the column.

**`api_keys`** — Add `permissions` (JSON array) column. Tokens can only contain permissions that are a subset of the creating user's effective permissions in that space.

### 2.3 Entity Relationships

```
User —M:N— Role (via role_user, scoped by space_id)
Role —belongs to— Space (nullable: global roles have no space)
Space —has many— Roles
User —has many— AuditLogs
```

---

## 3. Permission Taxonomy

Permissions follow a `domain.action` or `domain.sub.action` pattern. All are strings stored in the role's `permissions` JSON array.

### 3.1 Content Permissions

| Permission | Description |
|---|---|
| `content.create` | Create new content entries |
| `content.read` | View content (draft + published) |
| `content.update` | Edit existing content |
| `content.delete` | Delete content |
| `content.publish` | Publish / schedule content |
| `content.unpublish` | Unpublish live content |
| `content.type.manage` | Create/edit/delete content types |

### 3.2 Pipeline Permissions

| Permission | Description |
|---|---|
| `pipeline.run` | Trigger pipeline execution |
| `pipeline.configure` | Create/edit pipeline configurations |
| `pipeline.approve` | Approve AI-generated content (override quality scores) |
| `pipeline.reject` | Reject pipeline output |

### 3.3 Media Permissions

| Permission | Description |
|---|---|
| `media.upload` | Upload media assets |
| `media.delete` | Delete media assets |
| `media.organize` | Create/manage media folders/tags |

### 3.4 User & Team Permissions

| Permission | Description |
|---|---|
| `users.manage` | Invite, edit, deactivate users |
| `users.roles.assign` | Assign roles to users |
| `users.roles.manage` | Create/edit/delete custom roles |

### 3.5 Settings Permissions

| Permission | Description |
|---|---|
| `settings.system` | Modify system configuration |
| `settings.personas` | Create/edit personas |
| `settings.api_tokens` | Manage API tokens |
| `settings.webhooks` | Manage webhooks |

### 3.6 Space Permissions

| Permission | Description |
|---|---|
| `spaces.manage` | Create/edit/delete spaces |
| `spaces.switch` | Switch between spaces (implicit for all authenticated users) |

### 3.7 AI Governance Permissions

| Permission | Description |
|---|---|
| `ai.generate` | Trigger AI content generation |
| `ai.model.opus` | Use Opus-tier models (expensive) |
| `ai.model.sonnet` | Use Sonnet-tier models (standard) |
| `ai.model.haiku` | Use Haiku-tier models (cheap) |
| `ai.image.generate` | Trigger AI image generation |
| `ai.budget.unlimited` | Bypass daily generation limits |
| `ai.persona.configure` | Edit persona prompts & model assignments |

### 3.8 Wildcard Convention

`*` as a permission grants everything. `content.*` grants all content permissions. Wildcard expansion happens at check-time, not storage-time (so new permissions added in future versions are automatically included).

---

## 4. AI Governance: Budget & Model Limits

The `ai_limits` JSON on the `roles` table controls AI resource consumption per role:

```json
{
  "daily_generations": 50,
  "daily_image_generations": 10,
  "monthly_cost_limit_usd": 100.00,
  "allowed_models": ["claude-haiku-4-5", "claude-sonnet-4-6"],
  "max_tokens_per_request": 4096,
  "require_approval_above_cost_usd": 0.50
}
```

### Resolution Rules

1. User's effective AI limits = **most permissive** across all assigned roles in the active space (union of `allowed_models`, highest `daily_generations`, etc.)
2. `ai.budget.unlimited` permission bypasses all numeric limits
3. Budget tracking uses the existing `ai_generation_logs` table — a `BudgetGuard` service queries daily/monthly aggregates before allowing generation
4. When a generation would exceed a cost threshold (`require_approval_above_cost_usd`), the pipeline pauses and emits a `PipelineApprovalRequired` event. A user with `pipeline.approve` resolves it.

---

## 5. Built-in Roles (Seeded)

| Role | Key Permissions | AI Limits |
|---|---|---|
| **Admin** | `*` (wildcard) | Unlimited |
| **Editor** | `content.*`, `pipeline.run`, `pipeline.approve`, `pipeline.reject`, `media.*`, `ai.generate`, `ai.model.sonnet`, `ai.model.haiku`, `ai.image.generate`, `settings.personas` | 100 generations/day, all models except Opus |
| **Author** | `content.create`, `content.read`, `content.update`, `pipeline.run`, `media.upload`, `ai.generate`, `ai.model.haiku` | 20 generations/day, Haiku only |
| **Viewer** | `content.read`, `media.read` | No AI generation |

All built-in roles have `is_system = true` — they can be edited (permissions changed) but not deleted.

---

## 6. Implementation Architecture

### 6.1 New Models

```
app/Models/Role.php
app/Models/AuditLog.php
```

**`Role`** — ULIDs, belongs to Space (nullable), many-to-many with User. Has `hasPermission(string): bool` method with wildcard support.

**`AuditLog`** — ULIDs, polymorphic `resource` relationship. Append-only (no update/delete).

### 6.2 Core Services

```
app/Services/Authorization/
├── PermissionRegistrar.php    — defines the canonical permission list
├── AuthorizationService.php   — main gate: can(user, permission, space?)
├── BudgetGuard.php            — checks AI generation limits
└── AuditLogger.php            — writes audit log entries
```

**`PermissionRegistrar`** — Single source of truth for all valid permissions. Returns the full taxonomy. Used by admin UI for the permission editor checklist.

**`AuthorizationService`** — The brain. Resolves effective permissions:
```php
public function can(User $user, string $permission, ?Space $space = null): bool
{
    // 1. Get roles for user in this space (+ global roles)
    // 2. Collect all permissions from those roles
    // 3. Expand wildcards
    // 4. Check if requested permission is in the set
    // Results are cached per request (not across requests — permissions can change)
}
```

**`BudgetGuard`** — Before any AI generation:
```php
public function canGenerate(User $user, Space $space, string $model, float $estimatedCostUsd): BudgetCheckResult
{
    // 1. Resolve AI limits from user's roles
    // 2. Check daily generation count from ai_generation_logs
    // 3. Check monthly cost from ai_generation_logs
    // 4. Check if model is in allowed_models (or user has ai.model.* permission)
    // 5. Return allow/deny/needs-approval
}
```

### 6.3 Middleware

```
app/Http/Middleware/
├── CheckPermission.php         — replaces EnsureUserIsAdmin
└── ResolveActiveSpace.php      — sets space context for permission checks
```

**`CheckPermission`** — Route middleware, registered as `can`:
```php
// routes/web.php
Route::post('/content', [ContentController::class, 'store'])
    ->middleware('can:content.create');

// Multiple permissions (AND):
->middleware('can:content.create,content.publish')
```

**`ResolveActiveSpace`** — Reads space from route parameter, session, or header (`X-Space-Id`). Sets it on the request for downstream permission checks.

### 6.4 Laravel Gate Integration

Register a `before` callback in `AuthServiceProvider` that delegates to `AuthorizationService`:

```php
Gate::before(function (User $user, string $ability) {
    return app(AuthorizationService::class)->can(
        $user,
        $ability,
        request()->attributes->get('active_space')
    ) ?: null; // null = fall through to other gates
});
```

This means standard Laravel `$user->can('content.publish')`, `@can` Blade directives, and `$this->authorize()` in controllers all work natively.

### 6.5 Sanctum Token Scoping

Sanctum's `tokenCan()` is already supported. When creating a personal access token:

```php
$token = $user->createToken('api-token', abilities: ['content.read', 'content.create']);
```

The `CheckPermission` middleware checks **both** the user's role permissions **and** the token's abilities. Access requires the intersection: the user must have the permission via roles AND the token must include it in its abilities.

For the `ApiKey` model (space-scoped delivery API keys), the same principle applies: the `permissions` JSON array is checked alongside the creating user's permissions at key-creation time.

### 6.6 Policy Classes (Optional, Per-Resource)

For resource-level authorization (e.g., "can this user edit *this specific* content?"):

```
app/Policies/
├── ContentPolicy.php
├── SpacePolicy.php
└── PipelinePolicy.php
```

Policies call `AuthorizationService` internally but can add resource-specific logic (e.g., "Authors can only edit their own content").

---

## 7. Migration Strategy

### Phase 1: Schema (non-breaking)

1. **New migration**: Create `roles`, `role_user`, `audit_logs` tables
2. **New migration**: Add `permissions` JSON column to `api_keys`
3. **Seeder**: Create 4 built-in roles with default permissions
4. **Data migration**: Map existing `users.role` values → `role_user` entries
   - `'admin'` → Admin role
   - Any other value → Author role (safe default)

### Phase 2: Service Layer

5. Implement `PermissionRegistrar`, `AuthorizationService`, `BudgetGuard`, `AuditLogger`
6. Register Gate callback
7. Create `CheckPermission` middleware
8. Replace `EnsureUserIsAdmin` references with `can:*` middleware

### Phase 3: Integration

9. Add permission checks to all existing controllers
10. Integrate `BudgetGuard` into `GenerateContent` and `GenerateImage` jobs
11. Write `AuditLogger` calls into critical actions (content publish, user management, pipeline runs, AI generations)

### Phase 4: Cleanup

12. **New migration**: Drop `role` column from `users` table
13. Delete `EnsureUserIsAdmin` middleware

---

## 8. API Surface

### Admin API (Inertia + REST)

```
GET    /api/v1/roles                    — list roles (space-scoped)
POST   /api/v1/roles                    — create custom role
PUT    /api/v1/roles/{role}             — update role permissions/limits
DELETE /api/v1/roles/{role}             — delete role (not system roles)
GET    /api/v1/roles/{role}/users       — list users with this role

POST   /api/v1/users/{user}/roles       — assign role to user
DELETE /api/v1/users/{user}/roles/{role} — revoke role from user

GET    /api/v1/audit-logs               — query audit logs (filterable)
GET    /api/v1/permissions              — list all available permissions (from PermissionRegistrar)

GET    /api/v1/ai/budget/{user}         — current AI budget usage for user
```

### Permission Checks on Existing Endpoints

All existing API endpoints get permission middleware. Example:

```
POST   /api/v1/content          → can:content.create
PUT    /api/v1/content/{id}     → can:content.update
DELETE /api/v1/content/{id}     → can:content.delete
POST   /api/v1/pipeline/run     → can:pipeline.run
POST   /api/v1/media            → can:media.upload
```

---

## 9. Caching Strategy

- **Per-request cache** — User's resolved permissions are computed once per HTTP request and stored on the request object. No persistent cache.
- **Why not Redis/cache?** — Permission changes must be immediate. A user whose role is revoked should lose access on the next request, not after a TTL expires. Per-request computation is fast enough (1-2 roles × small permission arrays).
- **Budget queries** — `BudgetGuard` uses simple `COUNT`/`SUM` queries on `ai_generation_logs` with date filters. Index on `(user_id, created_at)` keeps this fast.

---

## 10. Audit Log Design

Every auditable action writes to `audit_logs`:

```php
AuditLogger::log(
    action: 'content.publish',
    resource: $content,              // polymorphic
    metadata: ['version' => 3],
    user: auth()->user(),
    space: $activeSpace,
);
```

### What Gets Logged

| Category | Actions |
|---|---|
| Content | create, update, delete, publish, unpublish |
| Pipeline | run, approve, reject, configure |
| AI | generation.start, generation.complete, generation.failed, budget.exceeded |
| Users | create, update, deactivate, role.assign, role.revoke |
| Roles | create, update, delete |
| Settings | update |
| Auth | login, logout, token.create, token.revoke |

### Retention

Audit logs are append-only. A configurable retention policy (default: 90 days) is enforced by a scheduled command: `numen:audit:prune`.

---

## 11. Security Considerations

1. **Principle of least privilege** — New users get no roles by default. Admin must explicitly assign.
2. **Self-escalation prevention** — `users.roles.assign` does not allow assigning roles with more permissions than the assigning user has. Enforced in `AuthorizationService`.
3. **API token ceiling** — Tokens can never exceed the creating user's permissions. If a user's role is later reduced, existing tokens with now-revoked permissions are effectively narrowed (intersection check at runtime).
4. **Audit immutability** — `AuditLog` model has no `update` or `delete` methods. Database-level: the app user should not have DELETE on `audit_logs` in production (recommended).
5. **Rate limiting on AI** — `BudgetGuard` is the last line of defense before LLM API calls. Even if a permission check is missed, budget limits catch runaway generation.

---

## 12. File Manifest

New files to be created:

```
database/migrations/
├── 2026_03_07_000001_create_roles_table.php
├── 2026_03_07_000002_create_role_user_table.php
├── 2026_03_07_000003_create_audit_logs_table.php
├── 2026_03_07_000004_add_permissions_to_api_keys_table.php
└── 2026_03_07_000005_migrate_user_roles_data.php

database/seeders/
└── RoleSeeder.php

app/Models/
├── Role.php
└── AuditLog.php

app/Services/Authorization/
├── PermissionRegistrar.php
├── AuthorizationService.php
├── BudgetGuard.php
├── BudgetCheckResult.php          (enum: Allowed, Denied, NeedsApproval)
└── AuditLogger.php

app/Http/Middleware/
├── CheckPermission.php
└── ResolveActiveSpace.php

app/Policies/
├── ContentPolicy.php
├── SpacePolicy.php
└── PipelinePolicy.php

app/Http/Controllers/Api/
├── RoleController.php
└── AuditLogController.php

app/Console/Commands/
└── PruneAuditLogs.php

tests/Feature/
├── PermissionTest.php
├── RoleManagementTest.php
├── BudgetGuardTest.php
└── AuditLogTest.php
```

---

## 13. Trade-offs & Decisions

| Decision | Alternative Considered | Why This Way |
|---|---|---|
| No Spatie/laravel-permission | Use the popular package | Spatie doesn't support AI-specific concepts (budget limits, model access). Rolling our own is ~500 LOC for the core and gives full control. |
| Permissions as flat strings in JSON | Normalized permission table with pivot | JSON is simpler, faster to read, easy to version-control defaults. We don't need to query "which roles have permission X" often enough to justify a pivot table. |
| Per-request permission resolution | Cached in Redis | Immediate consistency > performance. Permission arrays are small. |
| Space-scoped roles | Global-only roles | Multi-tenant is a core Numen concept. A user should be able to be Admin in their test space and Author in production. |
| Budget on roles, not users | Per-user budget config | Roles are the unit of administration. Per-user overrides can be added later as role-level exceptions if needed. |
| Wildcard expansion at check-time | Store expanded permissions | Future-proof. Adding a new permission in a release automatically applies to `*` and `content.*` roles. |

---

## 14. Open Questions

1. **Should Viewer be the default role for new users, or should they have no role?** Current recommendation: no role (explicit assignment required). Open to changing for self-signup flows.
2. **Per-content-type permissions** (e.g., "can publish Blog Posts but not Landing Pages") — deferred to v2. The `content.publish` permission currently applies to all content types in a space.
3. **Role hierarchy / inheritance** — not implemented in v1. Roles are flat sets of permissions. If needed later, a `parent_role_id` on the `roles` table would handle it.

---

*— Blueprint 🏗️, Numen Software Architect*
