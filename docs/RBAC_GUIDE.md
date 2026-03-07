# Roles & Permissions Guide

> **v0.5.0** · Role-Based Access Control (RBAC) system with AI governance, space-scoped authorization, and audit logging.

---

## Overview

Numen includes a full RBAC system that lets you manage team access with granular permissions, budget limits on AI generation, and an immutable audit log of all sensitive actions.

### Key Features

- **No external dependencies** — Numen's own RBAC, not a third-party package
- **Permissions as strings** — flat, greppable, composable (e.g., `content.publish`, `ai.generate`)
- **Space-scoped roles** — a user can be Editor in Space A and Author in Space B
- **API token scoping** — personal access tokens and API keys inherit a subset of user permissions
- **AI governance** — budget limits, model access restrictions, and per-token scoping
- **Audit logs** — immutable records of all sensitive actions (content publish, role assignment, etc.)
- **Built-in roles** — Admin, Editor, Author, Viewer with sensible defaults (all editable)

---

## Built-In Roles

Every space includes four system roles, seeded on first migration. They're editable but not deletable.

| Role | Key Permissions | AI Limits | Use Case |
|---|---|---|---|
| **Admin** | `*` (wildcard — everything) | Unlimited | Full access; manage users, roles, settings |
| **Editor** | `content.*`, `pipeline.*`, `media.*`, `ai.generate`, `settings.personas` | 100 gen/day, all models except Opus | Manage content, run pipelines, approve content |
| **Author** | `content.create/read/update`, `pipeline.run`, `media.upload`, `ai.generate` | 20 gen/day, Haiku only | Write and submit content for review |
| **Viewer** | `content.read`, `media.read` | No AI access | View-only access to published content |

New users start with **no role** — you must explicitly assign one.

---

## Permission Taxonomy

Permissions follow a `domain.action` or `domain.sub.action` pattern. Use the `GET /api/v1/permissions` endpoint to fetch the full list.

### Content Permissions

| Permission | Description |
|---|---|
| `content.create` | Create new content entries |
| `content.read` | View all content (draft + published) |
| `content.update` | Edit existing content |
| `content.delete` | Delete content |
| `content.publish` | Publish / unpublish content |
| `content.restore` | Restore deleted content |

### Pipeline Permissions

| Permission | Description |
|---|---|
| `pipeline.run` | Trigger pipeline execution |
| `pipeline.approve` | Approve pending runs for publication |
| `pipeline.reject` | Reject pipeline output |

### User & Team Permissions

| Permission | Description |
|---|---|
| `users.manage` | Invite, edit, deactivate users |
| `users.roles.assign` | Assign and revoke roles |
| `users.invite` | Invite new users |
| `users.delete` | Delete user accounts |

### Role Management

| Permission | Description |
|---|---|
| `roles.manage` | Create, edit, delete custom roles |

### Space Permissions

| Permission | Description |
|---|---|
| `spaces.manage` | Create and configure spaces |
| `spaces.delete` | Delete spaces |

### Audit & Settings

| Permission | Description |
|---|---|
| `audit.view` | View audit logs |
| `settings.general` | Modify general settings |
| `settings.api_tokens` | Manage API tokens |

### AI & Components

| Permission | Description |
|---|---|
| `ai.generate` | Trigger AI content generation |
| `component.manage` | Register and update custom component types |
| `persona.view` | View persona configurations |

### Wildcard Convention

- `*` grants **everything**
- `content.*` grants **all content permissions** (`content.create`, `content.publish`, etc.)
- Wildcard expansion happens at check-time — new permissions added in future releases automatically apply to existing `*` and `content.*` roles

---

## Assigning Roles to Users

### Via API

```bash
# Assign a role to a user
POST /api/v1/users/{userId}/roles
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "role_id": "018e1234-5678-7abc-def0-aaaaaaaaaaaa",
  "space_id": null  # Optional: null = global assignment
}
```

Response:
```json
{
  "user_id": "...",
  "role_id": "...",
  "space_id": null,
  "created_at": "2026-03-07T12:00:00Z"
}
```

### Via Admin UI

1. Go to **Settings → Users**
2. Click on a user
3. In the "Roles" section, click **+ Add Role**
4. Select a role and optional space
5. Click **Assign**

### Effective Permissions

A user's effective permissions are the **union** of all roles assigned to them (in the active space + global):

```
User's roles in Space A:  [Editor, CustomRole]
User's global roles:      [Viewer]
─────────────────────────────────────
Effective permissions:    Editor ∪ CustomRole ∪ Viewer
```

If multiple roles have the same AI limits, the **most permissive** applies:
- `daily_generations`: max across all roles
- `allowed_models`: union across all roles
- `daily_cost_limit_usd`: max across all roles

---

## API Token Scoping

### Personal Access Tokens

Create a token with a subset of your user's permissions:

```bash
POST /api/v1/api-tokens
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "name": "CI/CD Bot",
  "abilities": ["content.read", "content.create", "pipeline.run"]  # Subset only
}
```

The token can **only** use the abilities you specify — it can never exceed your own permissions.

### How Token Checks Work

When you include a token in a request:

```bash
Authorization: Bearer token_abc123
GET /api/v1/content/create
```

The authorization system checks:

1. **Your user role permissions** — do you have `content.create` via a role?
2. **Token ability scope** — does the token include `content.create` in its abilities?

Both must be true. This is an **intersection check**:

```
User permissions: [content.read, content.create, content.delete, pipeline.run]
Token abilities:  [content.read, content.create]  ← subset
────────────────────────────────────────────────────────
Allowed to use:   [content.read, content.create]
```

### Wildcard Support in Token Abilities

Tokens support wildcard abilities:

```bash
{
  "abilities": ["content.*", "pipeline.run"]
}
```

The same wildcard expansion rules apply:
- `*` grants everything
- `content.*` grants all `content.*` permissions
- `pipeline.*` grants all `pipeline.*` permissions

---

## Creating Custom Roles

### Via API

```bash
POST /api/v1/roles
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "space_id": "018e1234-5678-7abc-def0-aaaaaaaaaaaa",
  "name": "Content Reviewer",
  "slug": "content-reviewer",
  "description": "Reviews and approves AI-generated content",
  "permissions": [
    "content.read",
    "content.update",
    "pipeline.approve",
    "pipeline.reject"
  ],
  "ai_limits": {
    "daily_generations": 0,
    "allowed_models": []  # No AI generation for this role
  }
}
```

### Updating a Role

```bash
PUT /api/v1/roles/{roleId}
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "permissions": ["content.read", "content.update", "pipeline.approve"],
  "ai_limits": { ... }
}
```

### Deleting a Role

```bash
DELETE /api/v1/roles/{roleId}
Authorization: Bearer YOUR_TOKEN
```

System roles (`is_system: true`) can be edited but not deleted.

---

## AI Budget & Limits

Each role can have AI generation limits configured in the `ai_limits` JSON field:

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

### How Limits Apply

When a user attempts to generate content:

1. **Effective limits** are computed from all their assigned roles (union of most-permissive values)
2. **Checks happen before the API call**:
   - Is the requested model in `allowed_models`?
   - Have we hit `daily_generations` today?
   - Would this exceed `monthly_cost_limit_usd`?
3. **If within limits** → generation proceeds
4. **If above cost threshold** → requires human approval (pipeline pauses)

### Special Permission: `ai.budget.unlimited`

A role with `ai.budget.unlimited` permission **bypasses all numeric limits**:

```json
{
  "permissions": ["ai.generate", "ai.budget.unlimited"]
}
```

---

## Audit Logs

### Viewing Audit Logs

```bash
GET /api/v1/audit-logs
Authorization: Bearer YOUR_TOKEN
```

Query parameters:

| Param | Type | Description |
|---|---|---|
| `user_id` | string | Filter by user |
| `action` | string | Filter by action (e.g., `content.publish`, `role.assign`) |
| `resource_type` | string | Filter by resource model (e.g., `App\Models\Content`) |
| `from` | ISO-8601 | Earliest timestamp |
| `to` | ISO-8601 | Latest timestamp |
| `per_page` | integer | Results per page (default: 50) |
| `page` | integer | Page number |

### Example: What Gets Logged

| Action | Logged When |
|---|---|
| `content.publish` | Content is published |
| `content.delete` | Content is permanently deleted |
| `pipeline.run` | Pipeline execution starts |
| `pipeline.approve` | Human approves a run |
| `role.assign` | Role is assigned to a user |
| `role.revoke` | Role is revoked from a user |
| `ai.generation` | AI text generation completes |
| `ai.generation.failed` | AI generation fails |
| `ai.budget.exceeded` | User exceeds budget limit |
| `users.create` | New user is invited |
| `users.delete` | User account is deleted |
| `permission.denied` | Permission check fails (attempted unauthorized action) |

### Audit Log Schema

```json
{
  "id": "018e1234-5678-7abc-def0-cccccccccccc",
  "user_id": "018e1234-5678-7abc-def0-aaaaaaaaaaaa",
  "space_id": "018e1234-5678-7abc-def0-bbbbbbbbbbbb",
  "action": "content.publish",
  "resource_type": "App\\Models\\Content",
  "resource_id": "018e1234-5678-7abc-def0-dddddddddddd",
  "metadata": {
    "version": 3,
    "previous_status": "draft",
    "scheduled_for": "2026-03-10T09:00:00Z"
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "created_at": "2026-03-07T12:34:56Z"
}
```

### Retention

Audit logs are **append-only** (no updates or deletes). A configurable retention policy (default: 90 days) is enforced by:

```bash
php artisan numen:audit:prune --days=90
```

In production, your database user should **not have DELETE privileges** on the `audit_logs` table.

---

## Authorization in Code

### Checking Permissions

#### In Controllers

```php
use App\Services\AuthorizationService;

class ContentController extends Controller
{
    public function store(StoreContentRequest $request, AuthorizationService $authz)
    {
        $authz->authorize(auth()->user(), 'content.create', $request->space_id);
        
        // Proceed with content creation
    }
}
```

#### Using Middleware

```php
Route::post('/content', [ContentController::class, 'store'])
    ->middleware('permission:content.create');

// Multiple permissions (AND logic)
Route::delete('/content/{id}', [ContentController::class, 'destroy'])
    ->middleware('permission:content.delete');
```

#### Using Laravel Gates

```php
if ($user->can('content.publish')) {
    // User has permission
}

@can('pipeline.approve')
    <button>Approve</button>
@endcan
```

### Getting User Permissions

```php
$authz = app(AuthorizationService::class);
$perms = $authz->userPermissions(auth()->user(), $spaceId);

// Returns: ['content.read', 'content.create', 'pipeline.run', ...]
```

### Logging Actions

```php
AuditLog::create([
    'user_id' => auth()->user()->id,
    'space_id' => $space->id,
    'action' => 'content.publish',
    'resource_type' => Content::class,
    'resource_id' => $content->id,
    'metadata' => ['version' => 3],
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'created_at' => now(),
]);
```

Or use the helper:

```php
app(AuthorizationService::class)->log(
    user: auth()->user(),
    action: 'content.publish',
    resource: $content,
    metadata: ['version' => 3],
);
```

---

## Self-Escalation Prevention

The RBAC system prevents users from assigning roles with **more permissions** than they have.

Example:

```
User A has: [Editor role]
User A tries to assign [Admin role] to User B
→ DENIED: User A cannot escalate beyond their own permissions
```

This is enforced in `AuthorizationService::authorize()`.

---

## API Reference — Full

### Roles

```
GET    /api/v1/roles                   — List roles in current space
POST   /api/v1/roles                   — Create a custom role
PUT    /api/v1/roles/{roleId}          — Update role permissions/limits
DELETE /api/v1/roles/{roleId}          — Delete role (system roles excepted)
GET    /api/v1/roles/{roleId}/users    — List users with this role
```

### User Roles

```
GET    /api/v1/users/{userId}/roles    — List roles assigned to user
POST   /api/v1/users/{userId}/roles    — Assign role to user
DELETE /api/v1/users/{userId}/roles/{roleId} — Revoke role
```

### Audit Logs

```
GET    /api/v1/audit-logs              — Query audit log (filterable by user, action, resource, date)
```

### Permissions

```
GET    /api/v1/permissions             — List all valid permission strings (permission taxonomy)
```

---

## Security Best Practices

1. **Principle of Least Privilege** — grant only the permissions users need
2. **Regular Audits** — review `audit_logs` weekly for suspicious patterns
3. **Token Expiration** — rotate personal access tokens regularly
4. **Monitor Budget Limits** — set realistic `ai_limits` to catch runaway usage
5. **Backup Audit Logs** — the system prunes after 90 days; export important logs to cold storage
6. **API Token Rotation** — cycle tokens when users leave the team
7. **Read-Only Audit Access** — give audit.view sparingly; use for compliance reviews

---

## Troubleshooting

### "Forbidden" on an API call

Check that:
1. Your user has the required role assigned
2. The role includes the required permission
3. If using a token, the token's abilities include the permission
4. The role is assigned in the correct space (if space-scoped)

### "User cannot escalate" error

You're trying to assign a role with more permissions than you have. Have an Admin do it instead.

### Budget limit exceeded

A user has hit their `daily_generations` or `daily_cost_limit_usd`. Wait until tomorrow or ask an Admin to increase the limit or grant `ai.budget.unlimited`.

### Audit logs not appearing

Check that the action is in the list of logged actions (see [What Gets Logged](#example-what-gets-logged) above). Not all actions are audited — only sensitive ones.

---

## Examples

### Invite a team member as an Author

```bash
# 1. Create the user (via admin panel or API)
# 2. Get the Author role ID
AUTHOR_ROLE_ID="018e1234-5678-7abc-def0-aaaaaaaaaaaa"

# 3. Assign the role
curl -X POST https://yoursite.com/api/v1/users/{userId}/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"role_id": "'$AUTHOR_ROLE_ID'"}'
```

### Create a CI/CD bot with limited permissions

```bash
# 1. Create a bot user (via admin panel)
# 2. Create a token for it
curl -X POST https://yoursite.com/api/v1/api-tokens \
  -H "Authorization: Bearer BOT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GitHub Actions Bot",
    "abilities": ["content.read", "content.create", "pipeline.run"]
  }'

# 3. Use the token in your CI/CD workflow
export NUMEN_TOKEN="token_..."
curl -X POST https://yoursite.com/api/v1/briefs \
  -H "Authorization: Bearer $NUMEN_TOKEN" \
  -d '{ ... }'
```

### Audit a user's recent actions

```bash
curl "https://yoursite.com/api/v1/audit-logs?user_id={userId}&from=2026-03-01T00:00:00Z&to=2026-03-07T23:59:59Z" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Further Reading

- **Architecture & Design** — [docs/architecture/permissions-architecture.md](permissions-architecture.md)
- **Security Review** — [docs/security-review.md](../security-review.md)
- **API Reference** — [OpenAPI Spec](../openapi.yaml)
