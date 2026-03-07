# Role-Based Access Control (RBAC)

> **v0.5.0** · Granular permissions, space-scoped roles, AI budget governance, and audit logging

---

## Overview

Numen's RBAC system enables fine-grained team access control without external dependencies. Manage who can create, edit, and publish content; set AI generation budgets; and maintain a complete audit trail of sensitive actions.

### Key Features

- **No vendor lock-in** — Numen's own lightweight RBAC implementation
- **Flat permission strings** — easy to grep, compose, and document (`content.publish`, `ai.generate`, etc.)
- **Space-scoped roles** — users can be Editor in one space and Viewer in another
- **AI governance** — per-role budget limits, model access restrictions, and token scoping
- **Audit logs** — immutable records of all sensitive actions
- **Built-in roles** — Admin, Editor, Author, Viewer with sensible defaults
- **Token scoping** — API tokens inherit a subset of user permissions

---

## Quick Start

### 1. Assign a Role to a User

```bash
# Get the role ID
curl https://yoursite.com/api/v1/roles \
  -H "Authorization: Bearer YOUR_TOKEN"

# Assign the role
curl -X POST https://yoursite.com/api/v1/users/{userId}/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"role_id": "role-id-here"}'
```

### 2. Check Your Permissions

```bash
curl https://yoursite.com/api/v1/permissions \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Create an API Token with Limited Permissions

```bash
curl -X POST https://yoursite.com/api/v1/api-tokens \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GitHub Actions Bot",
    "abilities": ["content.read", "content.create", "pipeline.run"]
  }'
```

---

## Built-In Roles

Every space includes four system roles (seeded on first migration). They're editable but not deletable.

| Role | Description | Key Permissions | AI Limits |
|---|---|---|---|
| **Admin** | Full system access | `*` (wildcard) | Unlimited |
| **Editor** | Manage content & pipeline | `content.*`, `pipeline.*`, `media.*`, `ai.generate`, `settings.personas` | 100 gen/day, all models except Opus |
| **Author** | Create & submit content | `content.create/read/update`, `pipeline.run`, `media.upload`, `ai.generate` | 20 gen/day, Haiku only |
| **Viewer** | Read-only access | `content.read`, `media.read` | No AI access |

**New users start with no role.** You must explicitly assign one.

---

## Understanding Permissions

### Permission Format

Permissions follow a domain-based naming convention:

```
domain.action           Examples: content.create, users.manage
domain.sub.action      Examples: ai.model.opus, users.roles.assign
```

### All Available Permissions

Fetch the complete list via the API:

```bash
GET /api/v1/permissions
```

Or see the [Permission Taxonomy](#permission-taxonomy) section below.

### Wildcard Expansion

- `*` grants **all permissions** (system admin)
- `content.*` grants all content permissions (`content.create`, `content.read`, `content.delete`, etc.)
- `pipeline.*` grants all pipeline permissions
- Wildcard expansion happens at check-time — new permissions added in future releases automatically apply

### Permission Resolution

When a user has multiple roles, their **effective permissions are the union** of all assigned roles:

```
User's assigned roles:
  - Editor (in Space A)
  - Author (globally)

Effective permissions = Editor ∪ Author
```

If the Editor and Author roles conflict, the **most permissive** setting wins.

---

## Permission Taxonomy

### Content Management

| Permission | Description |
|---|---|
| `content.create` | Create new content entries |
| `content.read` | View draft and published content |
| `content.update` | Edit existing content |
| `content.delete` | Delete content |
| `content.publish` | Publish or unpublish content |
| `content.restore` | Restore deleted content |

### Pipeline & Publishing

| Permission | Description |
|---|---|
| `pipeline.run` | Trigger pipeline execution |
| `pipeline.approve` | Approve pending runs for publication |
| `pipeline.reject` | Reject pipeline output |

### Media Management

| Permission | Description |
|---|---|
| `media.upload` | Upload media assets |
| `media.delete` | Delete media assets |
| `media.organize` | Manage media folders and tags |

### User & Team Management

| Permission | Description |
|---|---|
| `users.manage` | Invite, edit, deactivate users |
| `users.roles.assign` | Assign and revoke roles |
| `users.invite` | Invite new users |
| `users.delete` | Delete user accounts |

### Role Management

| Permission | Description |
|---|---|
| `roles.manage` | Create, edit, and delete custom roles |

### Space Management

| Permission | Description |
|---|---|
| `spaces.manage` | Create and configure spaces |
| `spaces.delete` | Delete spaces |

### Settings & Configuration

| Permission | Description |
|---|---|
| `settings.general` | Modify system configuration |
| `settings.api_tokens` | Manage API tokens |
| `settings.personas` | Create and edit personas |

### Audit & Security

| Permission | Description |
|---|---|
| `audit.view` | Access audit logs |

### AI & Content Blocks

| Permission | Description |
|---|---|
| `ai.generate` | Trigger AI text generation |
| `ai.image.generate` | Trigger AI image generation |
| `ai.budget.unlimited` | Bypass daily/monthly generation limits |
| `ai.model.haiku` | Use Haiku-tier models (cheap) |
| `ai.model.sonnet` | Use Sonnet-tier models (standard) |
| `ai.model.opus` | Use Opus-tier models (expensive) |
| `component.manage` | Register and update custom component types |
| `persona.view` | View persona configurations |

---

## Space-Scoped vs. Global Roles

### Global Roles

A user assigned a role **without a space** has that role everywhere:

```json
{
  "user_id": "user-123",
  "role_id": "role-author",
  "space_id": null   ← null = global assignment
}
```

The Viewer role is typically assigned globally to give read-only access across all spaces.

### Space-Scoped Roles

A user can have different roles in different spaces:

```json
// User is Editor in Space A
{
  "user_id": "user-456",
  "role_id": "role-editor",
  "space_id": "space-a-id"
}

// But only Viewer in Space B
{
  "user_id": "user-456",
  "role_id": "role-viewer",
  "space_id": "space-b-id"
}
```

When the user works in Space A, they have Editor permissions. In Space B, they only have Viewer permissions.

---

## API Endpoints — Roles & Permissions

### List Roles

```bash
GET /api/v1/roles?space_id=space-123
Authorization: Bearer YOUR_TOKEN
```

Returns all roles (global + space-scoped) in that space.

**Requires:** `roles.manage` permission

### Create a Custom Role

```bash
POST /api/v1/roles
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Content Reviewer",
  "slug": "content-reviewer",
  "description": "Reviews and approves AI-generated content",
  "space_id": "space-123",  # Optional: leave null for global role
  "permissions": [
    "content.read",
    "content.update",
    "pipeline.approve",
    "pipeline.reject"
  ],
  "ai_limits": {
    "daily_generations": 0,
    "allowed_models": []
  }
}
```

Returns the created role object.

**Requires:** `roles.manage` permission

### Update a Role

```bash
PUT /api/v1/roles/{roleId}
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "permissions": ["content.read", "content.update"],
  "ai_limits": { ... }
}
```

**Requires:** `roles.manage` permission

### Delete a Role

```bash
DELETE /api/v1/roles/{roleId}
Authorization: Bearer YOUR_TOKEN
```

System roles (`is_system: true`) cannot be deleted, only edited.

**Requires:** `roles.manage` permission

### List Users with a Role

```bash
GET /api/v1/roles/{roleId}/users
Authorization: Bearer YOUR_TOKEN
```

**Requires:** `roles.manage` permission

---

## API Endpoints — User-Role Assignment

### Assign a Role to a User

```bash
POST /api/v1/users/{userId}/roles
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "role_id": "role-123",
  "space_id": null  # Optional: null = global assignment
}
```

**Requires:** `users.roles.assign` permission

**Security Note:** You cannot assign a role with more permissions than you have. The system enforces principle of least privilege.

### List User's Roles

```bash
GET /api/v1/users/{userId}/roles
Authorization: Bearer YOUR_TOKEN
```

Returns all roles assigned to the user (space-scoped + global).

### Revoke a Role

```bash
DELETE /api/v1/users/{userId}/roles/{roleId}
Authorization: Bearer YOUR_TOKEN
```

If the user has multiple roles in a space, this removes one of them.

**Requires:** `users.roles.assign` permission

---

## API Endpoints — Audit Logs

### Query Audit Logs

```bash
GET /api/v1/audit-logs?user_id=user-123&action=content.publish&from=2026-03-01T00:00:00Z&to=2026-03-07T23:59:59Z&per_page=50&page=1
Authorization: Bearer YOUR_TOKEN
```

| Parameter | Type | Description |
|---|---|---|
| `user_id` | string | Filter by user |
| `action` | string | Filter by action (e.g., `content.publish`) |
| `resource_type` | string | Filter by resource model |
| `from` | ISO-8601 | Earliest timestamp |
| `to` | ISO-8601 | Latest timestamp |
| `per_page` | integer | Results per page (default: 50) |
| `page` | integer | Page number |

Response:
```json
{
  "data": [
    {
      "id": "018e1234...",
      "user_id": "user-123",
      "space_id": "space-456",
      "action": "content.publish",
      "resource_type": "App\\Models\\Content",
      "resource_id": "content-789",
      "metadata": {
        "version": 3,
        "scheduled_for": "2026-03-10T09:00:00Z"
      },
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2026-03-07T12:34:56Z"
    }
  ]
}
```

**Requires:** `audit.view` permission

### What Gets Logged

| Action | When Logged |
|---|---|
| `content.publish` | Content published |
| `content.delete` | Content deleted |
| `content.restore` | Content restored |
| `pipeline.run` | Pipeline execution starts |
| `pipeline.approve` | Human approves a run |
| `pipeline.reject` | Human rejects a run |
| `role.assign` | Role assigned to user |
| `role.revoke` | Role revoked from user |
| `ai.generation` | AI text generation completed |
| `ai.generation.failed` | AI generation failed |
| `ai.budget.exceeded` | User exceeds budget limit |
| `users.create` | New user invited |
| `users.delete` | User account deleted |
| `permission.denied` | Permission check failed |

---

## API Endpoints — Permissions Catalog

### Get All Available Permissions

```bash
GET /api/v1/permissions
Authorization: Bearer YOUR_TOKEN
```

Response:
```json
{
  "data": {
    "content": {
      "content.create": "Create new content entries",
      "content.read": "View draft and published content",
      ...
    },
    "users": {
      "users.manage": "Manage user accounts",
      ...
    },
    ...
  }
}
```

Use this endpoint to:
- Populate permission checkboxes in a role editor UI
- Validate permission strings before creating/updating roles
- Display permission descriptions to users

**Requires:** `roles.manage` permission (admin UI only) or `auth:sanctum` (to prevent discovery by anonymous users)

---

## Token Scoping with Laravel Sanctum

### Create a Personal Access Token

```bash
POST /api/v1/api-tokens
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "CI/CD Bot",
  "abilities": ["content.read", "content.create", "pipeline.run"]
}
```

**Key Point:** The token can only have abilities that are a **subset** of your user's permissions. You cannot escalate privileges via token creation.

### How Token Permissions Work

Token permissions use an **intersection check**:

```
User roles:          [Editor, Author]
User permissions:    [content.*, pipeline.*, media.*, ai.generate]
Token abilities:     [content.read, content.create]  ← subset
───────────────────────────────────────────────────────
Effective access:    [content.read, content.create]
```

Both the user **and** the token must grant permission for an action to succeed.

### Wildcard Support in Token Abilities

Tokens support wildcard abilities:

```json
{
  "abilities": ["content.*", "pipeline.run"]
}
```

Same rules apply:
- `*` grants all abilities
- `content.*` grants all content abilities
- `pipeline.*` grants all pipeline abilities

### Use the Token in Requests

```bash
curl -X POST https://yoursite.com/api/v1/content \
  -H "Authorization: Bearer token_abc123xyz" \
  -H "Content-Type: application/json" \
  -d '{ ... }'
```

### Revoking Tokens

Tokens don't auto-revoke, but you can revoke them via:

```bash
DELETE /api/v1/api-tokens/{tokenId}
Authorization: Bearer YOUR_TOKEN
```

---

## AI Budget & Generation Limits

### How AI Limits Work

Each role can have generation limits configured:

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

When a user attempts generation:

1. Effective limits are computed from all assigned roles (most permissive wins)
2. System checks before the API call:
   - Is the model in `allowed_models`?
   - Have we hit `daily_generations` today?
   - Would this exceed `monthly_cost_limit_usd`?
3. If within limits → proceeds
4. If above cost threshold → pauses and requires approval

### The `ai.budget.unlimited` Permission

Grant `ai.budget.unlimited` to bypass all numeric limits:

```json
{
  "permissions": ["ai.generate", "ai.budget.unlimited"]
}
```

---

## Developer Guide: Adding New Permissions

### Step 1: Define the Permission

Edit `app/Services/Authorization/PermissionRegistrar.php`:

```php
public function all(): array
{
    return [
        'content' => [
            'content.create' => 'Create new content entries',
            'content.read' => 'View draft and published content',
            // Add your new permission here:
            'content.bulk_edit' => 'Edit multiple content items at once',
        ],
        // ... other domains
    ];
}
```

### Step 2: Document the Permission

Add an entry to the [Permission Taxonomy](#permission-taxonomy) section above.

### Step 3: Use it in Code

#### In Controllers

```php
use App\Services\AuthorizationService;

class ContentController extends Controller
{
    public function bulkEdit(Request $request, AuthorizationService $authz)
    {
        $authz->authorize(auth()->user(), 'content.bulk_edit', $request->space_id);
        
        // Proceed with bulk edit logic
    }
}
```

#### In Routes

```php
Route::put('/content/bulk', [ContentController::class, 'bulkEdit'])
    ->middleware('permission:content.bulk_edit');
```

#### In Blade Templates

```php
@can('content.bulk_edit')
    <button>Bulk Edit</button>
@endcan
```

### Step 4: Grant Permission via Roles

Update built-in role seeders or let admins grant it via the role editor:

```json
POST /api/v1/roles/{roleId}

{
  "permissions": ["content.create", "content.read", "content.bulk_edit"]
}
```

### Step 5: Log the Action

```php
AuditLog::create([
    'user_id' => auth()->user()->id,
    'action' => 'content.bulk_edit',
    'resource_type' => 'App\Models\Content',
    'metadata' => ['count' => $count],
]);
```

---

## Security Best Practices

1. **Principle of Least Privilege** — grant only required permissions
2. **Regular Audits** — review `audit_logs` weekly for suspicious patterns
3. **Token Rotation** — cycle personal access tokens quarterly
4. **Budget Monitoring** — set realistic `ai_limits`; alert on excess usage
5. **Backup Logs** — export important audit logs before 90-day prune
6. **Rate Limiting** — combine RBAC with endpoint rate limits
7. **API Key Governance** — revoke tokens when users leave the team
8. **Audit Log Access** — grant `audit.view` sparingly; use for compliance only

---

## Troubleshooting

### "Forbidden" Error

1. Check user has the required role assigned
2. Verify role includes the required permission
3. If using a token, verify token abilities include the permission
4. Confirm role is assigned in the correct space (if space-scoped)

### "Cannot Escalate" Error

You're trying to assign a role with more permissions than you have. Have an Admin do it instead.

### Budget Limit Exceeded

User hit `daily_generations` or `monthly_cost_limit_usd`. Wait until tomorrow or ask Admin to increase limit or grant `ai.budget.unlimited`.

### Audit Logs Not Appearing

Check action is in the [logged actions list](#what-gets-logged). Not all actions are audited — only sensitive ones.

---

## Examples

### Invite a Team Member as Author

```bash
# 1. Create the user (via admin panel)
# 2. Get the Author role ID
AUTHOR_ROLE_ID="018e1234-5678-7abc-def0-aaaaaaaaaaaa"

# 3. Assign the role
curl -X POST https://yoursite.com/api/v1/users/{userId}/roles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"role_id": "'$AUTHOR_ROLE_ID'"}'
```

### Create a CI/CD Bot with Scoped Permissions

```bash
# 1. Create a bot user account
# 2. Assign it a limited Author role (no publishing)
# 3. Create a token with minimal abilities
curl -X POST https://yoursite.com/api/v1/api-tokens \
  -H "Authorization: Bearer BOT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GitHub Actions",
    "abilities": ["content.read", "content.create"]
  }'
```

### Audit Recent User Activity

```bash
curl "https://yoursite.com/api/v1/audit-logs?user_id={userId}&from=2026-03-01T00:00:00Z" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Related Documentation

- **[RBAC Architecture & Design](../architecture/permissions-architecture.md)** — deep dive into system design
- **[Security Review](../security-review.md)** — threat model and mitigations
- **[OpenAPI Specification](../../openapi.yaml)** — complete API reference
