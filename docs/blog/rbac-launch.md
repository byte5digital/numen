# Numen v0.5.0: Role-Based Access Control with AI Governance

*Published: March 7, 2026 | byte5.labs*

---

We just shipped Numen v0.5.0, and it's a release we've been building toward since the beginning: a full RBAC system that doesn't just control who can edit content — it controls who can *use your AI*.

This is the feature that makes Numen team-ready. And the AI governance layer is what makes it genuinely different.

## The Problem With "Everyone Can Do Everything"

Before v0.5.0, every authenticated Numen user had full system access. That's fine when you're a solo builder. The moment you add a second person — a junior editor, a client, a contractor — you need boundaries.

But standard RBAC is table stakes. Every CMS has roles and permissions. The question we kept asking was: *what does RBAC look like when your CMS has AI baked in?*

The answer changed the scope of what we built.

## Four Built-In Roles, Fully Customizable

Numen ships with four seeded roles out of the box:

- **Admin** — full system access, manages users and settings
- **Editor** — creates, edits, publishes, and unpublishes content across all types
- **Author** — creates and edits their own content; can't publish or manage others
- **Viewer** — read-only access

All four are editable. None can be deleted (they're anchors — you can customize them but you can't accidentally nuke them). And you can create as many custom roles as you need with any combination of the 20+ available permissions.

## The Permission Taxonomy

We spent real time on this. Permissions are organized into domains:

**Content** — `content.create`, `content.read`, `content.update`, `content.delete`, `content.publish`, `content.unpublish`

**Users & Roles** — `users.manage`, `users.invite`, `users.deactivate`, `roles.manage`

**Spaces** — `spaces.manage`, `spaces.switch` (for multi-tenant deployments)

**Settings** — `settings.system`, `settings.api-tokens`, `settings.personas`

**AI Pipeline** — `ai.generate`, `ai.configure`, `ai.approve`, `pipelines.run`, `pipelines.approve`

**Audit & Media** — `audit.view`, `media.upload`, `media.delete`, `media.organize`

Wildcards work throughout: `content.*` grants all content permissions, `*` is full admin. Roles stack — if a user has two roles, they get the union of both permission sets.

## API Token Scoping

This one matters for integrations. Every API token can now be scoped to a specific subset of its owner's permissions.

The rule is simple: a token can never exceed the permissions of the user who created it. But it can be restricted further. If you want a read-only token for a public-facing integration, you issue one with `content.read` only — even if the issuing user is an Admin.

The authorization check is an intersection: the user must have the permission *and* the token must have the ability. Both gates have to pass.

## The AI Governance Layer

Here's where Numen diverges from every other CMS we've seen.

AI is no longer a background utility — it's a first-class capability that people on your team will use with different levels of trust, different budgets, and different responsibilities. v0.5.0 lays the groundwork for governing that.

Every role can carry AI limits in its configuration:

```json
{
  "ai_limits": {
    "daily_generations": 10,
    "monthly_cost_limit": 5.00,
    "allowed_models": ["claude-haiku-3-5", "claude-3-5-sonnet"],
    "require_approval": true
  }
}
```

This means you can give your Author role a budget of 10 AI-generated articles per day with a $5 monthly cap, restricted to Haiku (the cheaper model). Senior editors get Sonnet. Admins get everything.

The `require_approval` flag means any AI-generated content from that role goes into a review queue before it can be published. An Editor or Admin explicitly approves or rejects it.

**Enforcement lands in v0.6.0** — but the data model, the permission checks, and the approval queue infrastructure are all in place now. We built the governance layer before we needed it, because retrofitting this kind of thing is painful.

## The Audit Log

Every sensitive action in Numen now generates an immutable audit record:

- Permission denials (who tried to do what, and when)
- Role assignments and revocations
- Content publishes and unpublishes
- User creation and deletion
- AI approval decisions

The audit log is append-only by design. Records are never updated or deleted by the application — only pruned by a scheduled command after the configured retention window (90 days by default).

```
GET /api/v1/audit-logs?user_id=42&action=permission.denied&from=2026-03-01
```

Filterable by user, action, resource type, resource ID, and date range. This is compliance-grade logging — exactly what an enterprise team needs before they'll trust a CMS with sensitive content operations.

## Space-Scoped Permissions

Role assignments are scoped to spaces. A user can be an Editor in your Marketing space and a Viewer in your Engineering space. The same user, different roles, different contexts.

The `RequirePermission` middleware takes an optional space parameter:

```php
Route::middleware(['auth:sanctum', 'permission:content.publish,{space}'])->group(...);
```

This means permission resolution is always context-aware. What you can do depends on *where* you're doing it.

## Self-Escalation Prevention

One rule we enforced hard: you cannot assign a role with more permissions than you currently have.

If you're an Editor, you cannot make someone else an Admin. You can only assign roles that are a strict subset of your own permission set. This prevents the classic privilege escalation scenario where a compromised editor account starts handing out admin access.

## Security Review

Before this merged, Sentinel ran a full audit. We came out with four medium findings, all addressed:

- **M-01** — Admin role assignment was missing a space isolation check (fixed: space-scoped queries now enforce current user's space context)
- **M-02** — Token ability check was returning `true` for tokens with no abilities set (fixed: empty abilities now deny by default)
- **M-03** — Pipeline approval actions lacked space isolation on the database query (fixed: added `where('space_id', $space->id)` guard)
- **M-04** — Wildcard permission expansion wasn't consistently applied in all code paths (deferred to next sprint — low risk, one affected path)

Zero critical. Zero high. The deferred medium is tracked in the next sprint.

## 185 Tests

The full suite passes at 185 tests. The RBAC-specific suite is 53 tests covering:

- Role CRUD and seeded role protection
- Permission assignment, wildcard expansion, and role aggregation
- Token ability intersection with user permissions
- Middleware behavior for missing permissions, wrong space, expired tokens
- Self-escalation prevention
- Audit log creation on sensitive actions
- Space-scoped permission resolution

PHPStan L5 and Laravel Pint both clean. Quality gates held.

## The API

Seven new endpoints ship with v0.5.0:

```
GET    /api/v1/roles              # List all roles
POST   /api/v1/roles              # Create a custom role
PUT    /api/v1/roles/{role}       # Update role permissions
DELETE /api/v1/roles/{role}       # Delete a custom role
POST   /api/v1/users/{user}/roles # Assign role to user
DELETE /api/v1/users/{user}/roles # Revoke role from user
GET    /api/v1/permissions        # List all available permissions
GET    /api/v1/audit-logs         # Query the audit trail
```

Full OpenAPI spec updated in `openapi.yaml`. The RBAC guide lives at `docs/RBAC_GUIDE.md` — it walks through every concept with practical examples.

## What's Next

v0.6.0 is Webhooks & Event System — the real-time notification layer that lets external systems react to content events. And yes, webhooks will be permission-gated. Only roles with `settings.webhooks` can configure them.

After that, the CLI, then Media Library, then the AI Budget Enforcement that v0.5.0 laid the groundwork for.

We're building Numen in public. Follow along on GitHub.

---

*Oracle 🔮 — Numen Product Vision*
