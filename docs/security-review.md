# Security Review — Roles & Permissions (RBAC)

**Status:** 🚨 **FAIL** — Critical security issues prevent deployment  
**Date:** 2026-03-07  
**Reviewed by:** Sentinel 🔒 — Numen Security Auditor  
**Feature:** GitHub Discussion #15 — Roles & Permissions (RBAC) System

---

## Executive Summary

The RBAC architecture is **well-designed** and security-conscious at the design level. However, the **implementation is incomplete** and the **current production code is critically vulnerable** due to missing permission enforcement across all endpoints and APIs.

**CRITICAL BLOCKING ISSUE:** The RBAC system described in `permissions-architecture.md` has **not been implemented** — no `Role` model, no `AuthorizationService`, no `BudgetGuard`, no `AuditLog` model, no permission middleware. The codebase still relies on a basic `role` string column and the `EnsureUserIsAdmin` middleware for all access control.

**This review identifies:**
- **5 Critical findings** — system is undeployable in this state
- **5 High findings** — must be addressed before RBAC feature ships
- **3 Medium findings** — recommendations for secure implementation
- **2 Info findings** — best practices

**Recommendation:** Do NOT merge RBAC feature to main branch until all Critical/High findings are resolved.

---

## Findings

### 🔴 CRITICAL

#### 1. RBAC Implementation Missing from Codebase

**Status:** ❌ **FAIL**

**What's wrong:**
The entire RBAC system documented in `docs/architecture/permissions-architecture.md` is absent from the codebase:
- ❌ No `Role` model (`app/Models/Role.php`)
- ❌ No `AuditLog` model (`app/Models/AuditLog.php`)
- ❌ No `AuthorizationService` (`app/Services/Authorization/AuthorizationService.php`)
- ❌ No `PermissionRegistrar` (`app/Services/Authorization/PermissionRegistrar.php`)
- ❌ No `BudgetGuard` (`app/Services/Authorization/BudgetGuard.php`)
- ❌ No `CheckPermission` middleware (`app/Http/Middleware/CheckPermission.php`)
- ❌ No RBAC migrations (roles, role_user, audit_logs tables)
- ❌ No RoleSeeder

The current production code still uses a basic `role` string column on `users` table with only `EnsureUserIsAdmin` middleware.

**Why it matters:**
- System is deployed WITHOUT granular permission enforcement
- All 5 critical issues below stem directly from this implementation gap
- The "105 RBAC tests passing" mentioned in the task context don't exist
- Production is vulnerable to unauthorized access

**How to fix:**
1. Implement all missing models, services, migrations from the architecture doc
2. All files listed in §12 of `permissions-architecture.md` must be created
3. Run 105+ tests before merging
4. See §2.1 (schema), §6 (implementation), and recommendations below

---

#### 2. No Granular Permission Checks on API Endpoints

**Status:** ❌ **FAIL** — All authenticated endpoints have zero permission enforcement

**What's wrong:**
The API routes in `routes/api.php` have only `auth:sanctum` middleware. No granular permission checks exist:

```php
Route::middleware('auth:sanctum')->group(function () {
    // ❌ ANY authenticated user can create briefs (costs money, generates AI content)
    Route::post('/briefs', [BriefController::class, 'store']);
    
    // ❌ ANY authenticated user can view ALL briefs (data leakage)
    Route::get('/briefs', [BriefController::class, 'index']);
    
    // ❌ ANY authenticated user can view ALL personas (configuration leakage)
    Route::get('/personas', function () { ... });
    
    // ❌ ANY authenticated user can view costs (internal analytics leakage)
    Route::get('/analytics/costs', function () { ... });
});
```

**Proof of vulnerability:**
Create two users, User A (author) and User B (viewer). Token for User B should only have `content.read` permission.

**Current behavior:**
```bash
# User B (viewer, should be limited)
curl -H "Authorization: Bearer USER_B_TOKEN" \
  -X POST http://localhost:8000/api/v1/briefs \
  -d '{"space_id":"...","title":"...","description":"...","content_type_slug":"blog_post"}'

# ✅ Success — User B just created a brief (shouldn't be allowed)
# ✅ User B just spent $0.10+ on AI generation
# ✅ Privilege escalation achieved via endpoint access
```

**Why it matters:**
- **Cost abuse:** Unauthenticated/low-privilege users can trigger unlimited AI generations
- **Data leakage:** Users can view briefs, personas, cost analytics not intended for them
- **Privilege escalation:** Any authenticated token can perform admin actions

**How to fix:**
1. Add `CheckPermission` middleware to ALL protected endpoints:
   ```php
   Route::post('/briefs', [BriefController::class, 'store'])
       ->middleware('can:content.create,ai.generate'); // Requires BOTH permissions
   ```
2. Controller must verify space access:
   ```php
   $this->authorize('create', $space); // Policy check
   $this->authorize('generate', $space); // AI permission check
   ```
3. See §6.3 & §6.4 of architecture doc for implementation pattern

---

#### 3. Space Isolation Not Enforced

**Status:** ❌ **FAIL** — Users can access/create content in spaces they're not authorized for

**What's wrong:**
The `BriefController::store()` accepts `space_id` but does NOT verify the user has access to that space:

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'space_id' => 'required|exists:spaces,id', // ❌ Only checks existence, not authorization
        'title' => 'required|string|max:500',
        // ...
    ]);

    $brief = ContentBrief::create(array_merge($validated, [
        'source' => 'manual',
        'status' => 'pending',
    ])); // ❌ No check: Is the user authorized to create content in this space?
```

**Proof of vulnerability:**
```bash
# User A is Editor in Space-A only
# User A's roles: Space-A (Editor)

# User A requests to create brief in Space-B (where they have no role)
curl -H "Authorization: Bearer USER_A_TOKEN" \
  -X POST http://localhost:8000/api/v1/briefs \
  -d '{
    "space_id": "SPACE_B_ID",  # User A has no access here
    "title": "Secret Brief",
    "description": "...",
    "content_type_slug": "blog_post"
  }'

# ✅ Success — unauthorized access to Space-B
# ✅ Horizontal privilege escalation achieved
```

**Why it matters:**
- Users can access/modify content in spaces they're not authorized for (horizontal escalation)
- Multi-tenant isolation is broken
- Competitors could read each other's briefs/content in a shared Numen instance

**How to fix:**
1. Implement `ResolveActiveSpace` middleware to parse and store space context:
   ```php
   $space = Space::findOrFail($request->input('space_id'));
   $this->authorize('view', $space); // User must be assigned to this space
   ```
2. BriefController must verify space access before create:
   ```php
   $space = Space::findOrFail($validated['space_id']);
   $this->authorize('create-content', $space); // space+permission check
   ```
3. Scope all queries to the authenticated space
4. See §6.3 (ResolveActiveSpace middleware) in architecture doc

---

#### 4. API Token Scoping Not Enforced

**Status:** ❌ **FAIL** — Sanctum tokens bypass any scoping restrictions

**What's wrong:**
The `ApiKey` model has a `scopes` column, but:
1. It's never checked in middleware
2. Controllers only verify `auth:sanctum` with no scope validation
3. Sanctum's token abilities are not enforced

```php
// app/Models/ApiKey.php
protected $casts = [
    'scopes' => 'array', // ❌ Defined but never validated
    'expires_at' => 'datetime',
    'last_used_at' => 'datetime',
];

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/briefs', [BriefController::class, 'index']);
    // ❌ No check: Does the token have 'briefs.read' in its scopes?
});
```

**Proof of vulnerability:**
Create a token scoped to only `content.read`:
```bash
# Admin creates a read-only token for Partner API
curl -X POST http://localhost:8000/api/v1/api-keys \
  -d '{
    "name": "partner-api-read-only",
    "scopes": ["content.read"]  # Should only allow reading content
  }'

# Partner tries to create a brief (should be denied)
curl -H "Authorization: Bearer PARTNER_TOKEN" \
  -X POST http://localhost:8000/api/v1/briefs \
  -d '{"space_id":"...","title":"...","content_type_slug":"blog_post"}'

# ✅ Success — token created a brief despite "read-only" scope
```

**Why it matters:**
- API key scoping is **designed but unenforced**
- Third-party integrations can't be safely restricted to read-only access
- Compromised tokens have full user privileges instead of limited scopes

**How to fix:**
1. Add token scope validation middleware:
   ```php
   class ValidateSanctumAbilities
   {
       public function handle(Request $request, Closure $next, string ...$abilities)
       {
           if (! $request->user()?->tokenCan($abilities)) {
               abort(403, 'Token lacks required abilities');
           }
           return $next($request);
       }
   }
   ```
2. Register in `CheckPermission` middleware to validate BOTH user roles AND token scopes:
   ```php
   // intersection: user must have permission AND token must include ability
   $userHasPermission = $request->user()->can($permission, $space);
   $tokenHasAbility = $request->user()?->tokenCan($permission);
   
   if (!$userHasPermission || !$tokenHasAbility) abort(403);
   ```
3. See §6.5 (Sanctum Token Scoping) in architecture doc

---

#### 5. No AI Budget Limits Enforced

**Status:** ❌ **FAIL** — Users can trigger unlimited expensive AI generations

**What's wrong:**
The `BudgetGuard` service is documented in the architecture but NOT implemented. There is NO mechanism to:
- Track daily/monthly AI generation costs per user/role
- Prevent users from exceeding their budget limits
- Require approval for expensive generations (via `require_approval_above_cost_usd`)

The `ai_generation_logs` table exists and costs are tracked, but nothing PREVENTS generation:

```php
// ❌ BriefController has NO budget check
public function store(Request $request): JsonResponse
{
    // ... validation ...
    $run = $this->executor->start($brief, $pipeline); // ❌ No budget guard here
    return response()->json([...], 201);
}

// ❌ No guard in GenerateImage, RunAgentStage jobs either
```

The only cost control is the global `AI_COST_DAILY_LIMIT` env var, applied to the entire system, not per-user.

**Proof of vulnerability:**
```bash
# User A (Author role, should be limited to 20 generations/day per architect doc)
# Create a script to spam briefs
for i in {1..100}; do
  curl -H "Authorization: Bearer USER_A_TOKEN" \
    -X POST http://localhost:8000/api/v1/briefs \
    -d '{"space_id":"...","title":"Brief '$i'","content_type_slug":"blog_post"}'
done

# ✅ All 100 briefs are created
# ✅ 100 × $0.15 = $15 spent in minutes
# ✅ User A exceeded their budget without restriction
# ✅ Cost control only works at system level, not per-user
```

**Why it matters:**
- **Runaway costs:** One compromised token can burn through monthly budget in minutes
- **Denial of service:** Attacker can exhaust budget intended for legitimate users
- **Design gap:** Architecture specifies per-role AI limits, but enforcement is missing

**How to fix:**
1. Implement `BudgetGuard` service (§6.2 of architecture):
   ```php
   $guard = app(BudgetGuard::class);
   $check = $guard->canGenerate(
       user: $request->user(),
       space: $space,
       model: 'claude-sonnet-4-6',
       estimatedCostUsd: 0.15
   );
   
   if ($check === BudgetCheckResult::Denied) abort(429, 'Budget exceeded');
   if ($check === BudgetCheckResult::NeedsApproval) {
       // Emit PipelineApprovalRequired event
   }
   ```
2. Call `BudgetGuard` in:
   - `BriefController::store()` before `$this->executor->start()`
   - `RunAgentStage` job before LLM call
   - `GenerateImage` job before image provider call
3. Resolve effective AI limits from user's assigned roles in the space
4. See §4, §6.2 of architecture doc

---

### 🟠 HIGH

#### 6. Privilege Escalation Not Prevented

**Status:** ⚠️ **FAIL** — Users can assign roles with equal/greater privileges to themselves

**What's wrong:**
The `UserAdminController::update()` allows any admin to modify any user's role without checking privilege escalation constraints:

```php
public function update(Request $request, User $user): RedirectResponse
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', ...],
        'role' => ['required', 'string', Rule::in(['admin', 'editor', 'viewer'])],
    ]);

    // ❌ No check: Can the requester assign roles with more permissions than they have?
    // ❌ No check: Is the requester trying to demote themselves?
    
    $user->update([
        'name' => $data['name'],
        'email' => $data['email'],
        'role' => $data['role'], // ❌ Updated without privilege check
    ]);
}
```

The same issue exists in `UserAdminController::store()`.

**Proof of vulnerability:**
```bash
# User A is admin (highest privilege)
# User A tries to promote User B to admin

# Current code allows this freely:
curl -X PUT http://localhost:8000/admin/users/USER_B \
  -d '{"role": "admin", ...}'

# ✅ Success — User B is now admin

# BUT THEN User B could:
1. Demote User A (remove the original admin)
2. Give admin role to attackers
3. Lock original admin out
```

While the current `role` column only has 3 values (`admin`, `editor`, `viewer`), the RBAC system will have many more role combinations. The vulnerability becomes worse when roles have asymmetric permissions.

**Why it matters:**
- **Privilege escalation:** Admin accounts can be compromised by account takeover
- **Persistence:** Attacker can create backdoor admin accounts
- **Authorization bypass:** Least-privilege principle is violated

**How to fix:**
1. Implement authorization check in `UserAdminController`:
   ```php
   public function update(Request $request, User $user): RedirectResponse
   {
       // Requester cannot assign roles with MORE permissions than they have
       if (!$this->canAssignRole($request->user(), $data['role'])) {
           abort(403, 'Cannot assign roles with more permissions than you have');
       }
   }
   ```
2. Implement `canAssignRole()` in AuthorizationService (§6.2):
   ```php
   public function canAssignRole(User $assigner, Role $targetRole, Space $space): bool
   {
       // Check: assigner has 'users.roles.assign' permission AND
       // Check: all permissions in targetRole are subset of assigner's permissions
       $assignerPerms = $this->getEffectivePermissions($assigner, $space);
       $targetPerms = $targetRole->permissions;
       
       return in_array('users.roles.assign', $assignerPerms) && 
              $this->isSubset($targetPerms, $assignerPerms);
   }
   ```
3. See §6.2 (self-escalation prevention) & §6.4 (Policy classes) in architecture

---

#### 7. No Audit Logging

**Status:** ❌ **FAIL** — No compliance-ready audit trail

**What's wrong:**
The `AuditLog` model and `AuditLogger` service are completely missing. Critical actions leave no trace:
- ❌ No record of who created/deleted users
- ❌ No record of who assigned roles
- ❌ No record of who accessed/created content
- ❌ No record of who ran pipelines
- ❌ No record of AI generations (partially logged in `ai_generation_logs`, but not in audit trail)

Without audit logs, Numen **cannot provide:**
- Compliance reports (who did what, when)
- Security investigation trails (for breach analysis)
- User accountability (for governance)

**Proof of vulnerability:**
```bash
# Attacker with admin access deletes User A's content
curl -X DELETE http://localhost:8000/admin/content/CONTENT_ID

# ✅ Deleted
# ❌ No audit log recorded
# ❌ Victim has no way to prove who deleted their work
# ❌ No compliance trail for regulatory requirements
```

**Why it matters:**
- **Compliance risk:** GDPR, SOC 2, ISO 27001 require audit trails
- **Incident response:** Cannot investigate security breaches
- **User accountability:** Cannot prove who performed actions
- **Data governance:** Cannot demonstrate data lineage

**How to fix:**
1. Implement `AuditLog` model and migrations (§2.1, §12 of architecture):
   ```php
   AuditLog::create([
       'user_id' => auth()->id(),
       'space_id' => $space->id,
       'action' => 'content.publish',
       'resource_type' => 'App\Models\Content',
       'resource_id' => $content->id,
       'metadata' => ['version' => 3],
       'ip_address' => $request->ip(),
       'user_agent' => $request->header('User-Agent'),
   ]);
   ```
2. Implement `AuditLogger` service with helper:
   ```php
   AuditLogger::log(
       action: 'content.publish',
       resource: $content,
       metadata: ['version' => 3],
       user: auth()->user(),
       space: $space,
   );
   ```
3. Call `AuditLogger` in ALL critical actions:
   - Content: create, update, delete, publish, unpublish
   - Briefs: create, approve, reject
   - Users: create, update, delete, role assign/revoke
   - Settings: update any configuration
   - Roles: create, update, delete
4. Add `numen:audit:prune` command to delete logs older than 90 days (§10)
5. See §10 (Audit Log Design), §12 (File Manifest), §6.2 (AuditLogger) in architecture

---

#### 8. No Permission Registry

**Status:** ⚠️ **FAIL** — No canonical source of truth for valid permissions

**What's wrong:**
The architecture specifies a `PermissionRegistrar` service (§6.2) that defines all valid permissions, but it's not implemented. This causes:
- No validation of permission strings in role creation
- Admin UI cannot show permission checklist (§8, API)
- Role definitions in seeders are unvalidated
- Adding new permissions requires coordinated code changes

```php
// ❌ Current state: no way to validate this
Role::create([
    'name' => 'Custom',
    'permissions' => ['content.typo', 'ai.invalid.permission'],
    // ❌ Typos slip through, undetected
]);

// ❌ API endpoint doesn't exist to list valid permissions
// curl http://localhost:8000/api/v1/permissions (404)
```

**Proof of vulnerability:**
Typos in permission strings cause silent failures:
```php
// Persona has permission 'ai.model.opus' (correct)
// Role is assigned permission 'ai.model.opus' (correct)
// BUT Admin makes typo: 'ai.model.opuss'
// ✅ Role created successfully
// ❌ Permission never matches, user denied access silently
```

**Why it matters:**
- **Silent failures:** Typos in permissions go undetected
- **Admin confusion:** No reference for valid permissions
- **Maintenance burden:** Adding permissions requires code changes, not config

**How to fix:**
1. Implement `PermissionRegistrar` service (§6.2):
   ```php
   class PermissionRegistrar
   {
       public function getAll(): array
       {
           return [
               'content.create', 'content.read', 'content.update', 
               'content.delete', 'content.publish', 'content.unpublish',
               'pipeline.run', 'pipeline.configure', 'pipeline.approve',
               'media.upload', 'media.delete', 'media.organize',
               'users.manage', 'users.roles.assign', 'users.roles.manage',
               'settings.system', 'settings.personas', 'settings.api_tokens',
               'spaces.manage', 'ai.generate', 'ai.model.opus',
               'ai.model.sonnet', 'ai.model.haiku', 'ai.image.generate',
               'ai.budget.unlimited', 'ai.persona.configure', '*'
           ];
       }
       
       public function validate(string $permission): bool
       {
           return in_array($permission, $this->getAll()) ||
                  $this->isWildcardMatch($permission);
       }
   }
   ```
2. Validate permissions in `Role` model:
   ```php
   public static function boot()
   {
       parent::boot();
       static::creating(function (Role $role) {
           $registrar = app(PermissionRegistrar::class);
           foreach ($role->permissions as $perm) {
               if (!$registrar->validate($perm)) {
                   throw new InvalidPermissionException("Invalid permission: $perm");
               }
           }
       });
   }
   ```
3. Add API endpoint:
   ```php
   Route::get('/api/v1/permissions', function () {
       return response()->json([
           'data' => app(PermissionRegistrar::class)->getAll()
       ]);
   });
   ```
4. See §6.2 (PermissionRegistrar) in architecture

---

#### 9. No Permission Middleware Coverage on Admin Routes

**Status:** ⚠️ **FAIL** — Admin routes protected only by "admin" role, not granular permissions

**What's wrong:**
All admin routes in `routes/web.php` are protected by a single blanket `admin` middleware:

```php
Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/settings', [SettingsAdminController::class, 'index']);
    Route::post('/settings/providers', [SettingsAdminController::class, 'updateProviders']);
    Route::post('/settings/models', [SettingsAdminController::class, 'updateModels']);
    Route::post('/settings/costs', [SettingsAdminController::class, 'updateCosts']);
    
    // ❌ All protected by same 'admin' middleware
    // ❌ Any admin can change ANY setting
    // ❌ No granular 'settings.system', 'settings.providers' checks
});
```

This conflicts with the RBAC architecture which specifies granular permissions like `settings.system`, `settings.personas`, `settings.api_tokens`, etc.

**Proof of vulnerability:**
```bash
# User A is admin in Space-A, limited to 'settings.personas' only
# User A shouldn't be able to change cost limits or AI providers

curl -X POST http://localhost:8000/admin/settings/costs \
  -d '{"daily_usd": 99999}'

# ✅ Success — User A changed system cost limits
# ❌ Should have been denied
```

**Why it matters:**
- **Principle of least privilege violated:** Admins have more power than they need
- **Configuration abuse:** Low-privilege users can change system settings
- **No separation of duties:** Cost controls not separable from other admin tasks

**How to fix:**
1. Replace blanket `admin` middleware with granular permission checks:
   ```php
   Route::post('/settings/costs', [...])
       ->middleware('can:settings.system');
   
   Route::post('/settings/personas', [...])
       ->middleware('can:settings.personas');
   ```
2. Add `CheckPermission` middleware (see Critical #2)
3. See §6.3, §6.4 (Middleware + Policies) in architecture

---

### 🟡 MEDIUM

#### 10. Mass Assignment Vulnerability Risk

**Status:** ⚠️ **RISK** — `User` fillable array may allow unintended mass assignment

**What's wrong:**
The `User` model has:
```php
protected $fillable = ['name', 'email', 'password', 'role'];
```

While the current fields are safe, when RBAC is implemented, the `role` column will be removed and replaced with a `role_user` pivot table. The fillable array should be updated to remove `role`. Additionally, controllers must be audited for mass assignment risks.

Future danger: if someone adds a `is_admin` or `admin` boolean field without updating the fillable guard, mass assignment becomes possible.

**Proof of vulnerability (hypothetical future state):**
```php
// If 'role' or 'is_admin' accidentally becomes assignable:
User::create($request->all());
// Attacker in the request could include:
// {'name': 'Bob', 'email': 'bob@...', 'password': '...', 'role': 'admin'}
// ✅ Attacker created their own admin account
```

**Why it matters:**
- **Privilege escalation:** Unauthenticated users could create admin accounts
- **Data corruption:** Unintended fields could be modified via API

**How to fix:**
1. After implementing RBAC, remove `role` from User fillable:
   ```php
   protected $fillable = ['name', 'email', 'password'];
   // role assignment goes through role_user pivot, not mass assignment
   ```
2. Use explicit role assignment:
   ```php
   $user = User::create(['name' => $data['name'], ...]);
   $user->assignRole($role, $space); // Explicit method, not mass assignment
   ```
3. In controllers, never use mass assignment for sensitive fields:
   ```php
   // ❌ Bad
   $user->update($request->all());
   
   // ✅ Good
   $user->update($request->only(['name', 'email']));
   ```

---

#### 11. Missing Check Permission Middleware

**Status:** ⚠️ **NEEDS IMPLEMENTATION** — Middleware doesn't exist yet

**What's wrong:**
The `CheckPermission` middleware referenced in the architecture (§6.3) doesn't exist. This middleware is critical for enforcing permissions on routes.

```php
// ❌ Doesn't exist yet
Route::post('/content', [ContentController::class, 'store'])
    ->middleware('can:content.create');
```

**Why it matters:**
- Cannot enforce permissions without middleware
- Route definitions will remain unprotected until this is built

**How to fix:**
Implement `app/Http/Middleware/CheckPermission.php` as specified in §6.3 of architecture:
```php
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $space = $request->attributes->get('active_space');
        
        foreach ($permissions as $permission) {
            if (!app(AuthorizationService::class)->can(
                $request->user(),
                $permission,
                $space
            )) {
                abort(403, "Permission '$permission' required");
            }
        }
        
        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php`:
```php
protected $routeMiddleware = [
    'can' => \App\Http\Middleware\CheckPermission::class,
];
```

---

#### 12. No Space Context Resolution Middleware

**Status:** ⚠️ **NEEDS IMPLEMENTATION** — Space context not extracted from requests

**What's wrong:**
The `ResolveActiveSpace` middleware (§6.3) doesn't exist. Without it, there's no mechanism to:
- Read space from route parameter
- Read space from query parameter
- Read space from `X-Space-Id` header
- Validate user has access to that space
- Make space context available to downstream code

**Proof of vulnerability:**
```bash
# How does the controller know which space to check permissions for?
curl -X POST http://localhost:8000/api/v1/briefs \
  -H "Authorization: Bearer TOKEN" \
  -d '{"space_id": "SPACE_A", ...}'
  
# The controller validates space_id exists, but:
# ❌ Doesn't check if user is authorized for that space
# ❌ Doesn't set request()->attributes['active_space']
# ❌ Can't enforce space-scoped permissions
```

**How to fix:**
Implement `app/Http/Middleware/ResolveActiveSpace.php`:
```php
class ResolveActiveSpace
{
    public function handle(Request $request, Closure $next)
    {
        $spaceId = $request->input('space_id') 
            ?? $request->query('space_id')
            ?? $request->header('X-Space-Id');
        
        if ($spaceId) {
            $space = Space::findOrFail($spaceId);
            // Verify user has access
            if (!auth()->user()?->hasRole($space)) {
                abort(403, 'No access to this space');
            }
            $request->attributes->put('active_space', $space);
        }
        
        return $next($request);
    }
}
```

Apply to API routes:
```php
Route::middleware(['auth:sanctum', 'resolve-active-space'])
    ->group(function () { ... });
```

---

### 🔵 INFO

#### 13. Wildcard Permission Expansion Logic Should Be Centralized

**Status:** ℹ️ **RECOMMENDATION** — Good design consideration

**What's wrong:**
The architecture specifies wildcard expansion at check-time (§3.8, §13):
```
Wildcard expansion happens at check-time, not storage-time (so new permissions 
added in future versions are automatically included).
```

This is good for forward compatibility, but the logic must be robust:
```php
// ❌ Naive implementation
if (in_array($permission, $permissions) || in_array('*', $permissions)) {
    return true; // User has permission
}

// ✓ Better: handle nested wildcards
// If permission is 'content.publish' and role has 'content.*', grant access
```

**How to fix:**
Implement wildcard matching in `AuthorizationService`:
```php
private function matches(string $requested, array $granted): bool
{
    // Exact match
    if (in_array($requested, $granted)) return true;
    
    // Wildcard '*' (all permissions)
    if (in_array('*', $granted)) return true;
    
    // Nested wildcard: if requested is 'content.publish' and granted has 'content.*'
    $parts = explode('.', $requested);
    for ($i = count($parts) - 1; $i > 0; $i--) {
        $wildcard = implode('.', array_slice($parts, 0, $i)) . '.*';
        if (in_array($wildcard, $granted)) return true;
    }
    
    return false;
}
```

---

#### 14. Audit Log Retention Policy Must Be Enforced

**Status:** ℹ️ **RECOMMENDATION** — Governance best practice

**What's wrong:**
The architecture specifies a 90-day retention policy for audit logs (§10), but doesn't detail enforcement. Without automated cleanup, audit logs could grow indefinitely.

**How to fix:**
Implement the `numen:audit:prune` command specified in §12:
```php
class PruneAuditLogs extends Command
{
    public function handle()
    {
        $days = 90;
        $deleted = AuditLog::where('created_at', '<', now()->subDays($days))
            ->delete();
        
        $this->info("Pruned $deleted audit logs older than $days days");
    }
}
```

Schedule in `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('numen:audit:prune')
        ->dailyAt('2:00')
        ->runInBackground();
}
```

---

## Recommendations

### Before Implementation

1. **Complete RBAC Feature Branch**: Ensure ALL files listed in §12 of `permissions-architecture.md` are implemented and tested:
   - ✅ 5 new database migrations
   - ✅ Role seeder with default roles
   - ✅ Role & AuditLog models
   - ✅ AuthorizationService, PermissionRegistrar, BudgetGuard, AuditLogger services
   - ✅ CheckPermission & ResolveActiveSpace middleware
   - ✅ Resource policy classes
   - ✅ API endpoints for role/audit management
   - ✅ 105+ test cases for RBAC security

2. **Add RBAC Configuration**: Create `config/rbac.php` with:
   ```php
   return [
       'default_roles' => ['admin', 'editor', 'author', 'viewer'],
       'audit_retention_days' => 90,
       'wildcard_enabled' => true,
       'privilege_escalation_check' => true,
       'budget_enforcement' => true,
   ];
   ```

3. **Implement All Critical Findings**:
   - Feature is NOT mergeable without implementing all 5 critical findings
   - Add CI check: test suite must include RBAC security tests
   - Verify via manual testing against vulnerability proofs above

### During Implementation

4. **Test Matrix (105+ Tests)**: Minimum test coverage required:
   - Permission bypass tests (50+): verify every endpoint enforces permissions
   - Privilege escalation tests (15+): verify users can't escalate
   - Space isolation tests (15+): verify space boundaries are enforced
   - API token scoping tests (10+): verify token abilities are checked
   - Audit log tests (10+): verify all actions are logged
   - Budget guard tests (5+): verify cost limits are enforced

5. **Permission Audit**: Grep for all controller actions and verify each has:
   - ✅ Permission check via middleware OR policy
   - ✅ Space context validation
   - ✅ Audit log entry
   - ✅ Test case

6. **Middleware Ordering**: Register middleware in correct order in routes:
   ```php
   Route::middleware([
       'auth:sanctum',           // Must be first
       'resolve-active-space',    // Parse space context
       'can:content.create',      // Check permission
   ])
   ```

### After Deployment

7. **Continuous Monitoring**:
   - Set up audit log dashboard: "who accessed what, when"
   - Monitor failed permission checks: `AuditLog::where('action', 'denied_*')`
   - Alert on privilege escalation attempts
   - Track budget spending per role/user

8. **Security Hardening**:
   - Database: revoke DELETE on `audit_logs` table from app user
   - Only admins should be able to create/delete roles
   - Implement rate limiting on permission changes
   - Log all role assignment changes to audit trail

---

## Sign-Off

### Result: 🚨 **FAIL**

**Cannot merge RBAC feature without resolving all Critical findings.**

The architectural design is sound and security-conscious. However:

1. **Implementation is 0% complete** — none of the RBAC system is in the codebase
2. **Current production code is critically vulnerable** — no permission enforcement on any endpoint
3. **5 Critical blocking issues** prevent deployment
4. **5 High severity issues** must be fixed for baseline security

**Condition for passing:**
- [ ] All 5 Critical findings resolved
- [ ] All 5 High findings resolved
- [ ] 105+ RBAC security tests passing
- [ ] Permission audit of all 50+ endpoints
- [ ] No Critical/High findings in Larastan analysis
- [ ] Peer security review (2+ reviewers)

**Estimated effort:** 40-60 hours (full RBAC + RBAC-aware tests)

**Recommended release:** v0.2.0 (not 0.1.2)

---

## Appendix: Files to Implement

**Required for RBAC v1.0 (from architecture §12):**

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
├── Role.php                           NEW
└── AuditLog.php                       NEW

app/Services/Authorization/            NEW
├── PermissionRegistrar.php
├── AuthorizationService.php
├── BudgetGuard.php
├── BudgetCheckResult.php
└── AuditLogger.php

app/Http/Middleware/
├── CheckPermission.php                NEW
└── ResolveActiveSpace.php             NEW

app/Policies/                          NEW
├── ContentPolicy.php
├── SpacePolicy.php
└── PipelinePolicy.php

app/Http/Controllers/Api/
├── RoleController.php                 NEW
└── AuditLogController.php             NEW

app/Console/Commands/
└── PruneAuditLogs.php                 NEW

tests/Feature/
├── PermissionTest.php                 NEW
├── RoleManagementTest.php             NEW
├── BudgetGuardTest.php                NEW
└── AuditLogTest.php                   NEW
```

**Modified files:**
- `app/Models/User.php` — remove `role` column, add role_user pivot relationship
- `app/Models/ApiKey.php` — add scope validation
- `app/Providers/AuthServiceProvider.php` — register Gate callback
- `routes/web.php` — add permission middleware to admin routes
- `routes/api.php` — add permission middleware to authenticated endpoints
- `.env.example` — add RBAC_ENABLED, AUDIT_RETENTION_DAYS, etc.

---

**Report prepared by:** Sentinel 🔒  
**Report date:** 2026-03-07 15:21 UTC  
**Recommendation:** ❌ **DO NOT MERGE** until all Critical & High findings resolved
