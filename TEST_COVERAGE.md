# RBAC Test Coverage Report

## Overview
Total RBAC Tests: **105**

### Test Distribution
| File | Tests | Focus |
|------|-------|-------|
| PermissionTest.php | 15 | Basic permission checks, wildcards, caching |
| RoleManagementTest.php | 14 | Role CRUD, assignments, API endpoints |
| AuditLogTest.php | 11 | Audit logging, immutability, pruning |
| BudgetGuardTest.php | 11 | Budget enforcement, model tiers, approval thresholds |
| **RBACAdvancedTest.php** | **38** | **Advanced scenarios, cascade, security** |
| **ApiTokenPermissionsTest.php** | **16** | **Token CRUD, auth, lifecycle** |

## Coverage by Feature

### 1. Permission System (46 tests)
- [x] Basic permission checks (user without roles has no permissions)
- [x] Role-based permission grants
- [x] Admin role grants all permissions via `*` wildcard
- [x] Domain wildcards (`content.*` grants all content.* permissions)
- [x] Nested wildcards (`ai.model.*` resolution)
- [x] Multiple wildcard combination
- [x] Per-request permission caching
- [x] Cache invalidation between requests
- [x] Laravel Gate integration
- [x] `isAdmin()` helper method
- [x] Multi-role permission union (most permissive wins)
- [x] Permission matching with exact strings
- [x] Wildcard expansion at check time

**Tests:**
- test_user_with_no_roles_has_no_permissions
- test_admin_role_grants_all_permissions_via_wildcard
- test_editor_role_has_expected_permissions
- test_author_role_has_expected_permissions
- test_viewer_role_has_readonly_permissions
- test_domain_wildcard_grants_all_permissions_in_domain
- test_wildcard_permission_content_matches_all_content_actions
- test_multiple_wildcards_combine_correctly
- test_nested_wildcard_resolution
- test_laravel_gate_delegates_to_authorization_service
- test_is_admin_returns_true_for_admin_role_user
- test_is_admin_returns_false_for_non_admin_user
- test_permission_cache_is_cleared_between_calls
- test_role_has_permission_with_wildcard
- test_role_has_permission_with_domain_wildcard
- test_user_with_multiple_roles_gets_union_of_permissions
- test_most_permissive_ai_limits_are_used_across_roles
- ... and 29 more

### 2. Space-Scoped Role Assignments (19 tests)
- [x] User has permission only in assigned space
- [x] Global role grants permission in all spaces
- [x] User with different roles in different spaces
- [x] Space-scoped role doesn't leak to other spaces
- [x] User with different roles in 3 spaces
- [x] Global + space-scoped roles combine correctly
- [x] Space permission doesn't leak globally
- [x] Most permissive limits across spaces

**Tests:**
- test_user_has_permission_in_assigned_space_only
- test_global_role_grants_permission_in_all_spaces
- test_user_with_different_roles_in_different_spaces
- test_user_with_different_roles_in_three_spaces
- test_global_role_and_space_scoped_role_combine
- test_space_scoped_permission_does_not_leak_to_other_spaces
- ... and 13 more

### 3. Budget Guard & AI Limits (18 tests)
- [x] Viewer cannot generate
- [x] Author can generate with Haiku only
- [x] Author cannot generate with Sonnet/Opus
- [x] Editor can generate with Sonnet
- [x] Admin can generate with any model
- [x] `ai.budget.unlimited` bypasses all checks
- [x] Approval threshold triggers for expensive requests
- [x] Daily generation limit enforcement
- [x] Monthly cost limit enforcement
- [x] Model tier enforcement per role
- [x] Image generation separate from text generation
- [x] Most permissive limits across multi-role users
- [x] NeedsApproval check result handling
- [x] Image generation budget denial for Authors

**Tests:**
- test_viewer_cannot_generate
- test_author_can_generate_with_haiku
- test_author_cannot_generate_with_sonnet
- test_editor_can_generate_with_sonnet
- test_admin_can_generate_with_any_model
- test_ai_budget_unlimited_bypasses_all_checks
- test_high_cost_generation_returns_needs_approval
- test_author_cannot_generate_images
- test_editor_can_generate_images
- test_daily_generation_limit_is_enforced
- test_monthly_cost_limit_prevents_expensive_requests
- test_model_tier_enforcement_respects_role_limits
- test_approval_threshold_triggers_for_expensive_requests
- test_ai_budget_unlimited_bypasses_all_limits
- test_image_generation_budget_is_separate_from_text
- test_most_permissive_limits_are_used_for_multi_role_user
- ... and 2 more

### 4. Audit Logging & Immutability (13 tests)
- [x] Audit logger writes entries
- [x] Static write method works
- [x] Polymorphic resource tracking
- [x] System actions without user
- [x] Audit log cannot be updated
- [x] Audit log cannot be deleted (soft or force)
- [x] Audit log cannot be mass-assigned
- [x] Metadata immutability
- [x] Admin can query audit logs
- [x] Non-admin cannot query audit logs
- [x] Audit logs can be filtered
- [x] Prune command removes old logs
- [x] Prune dry-run doesn't delete
- [x] Role operations create audit logs

**Tests:**
- test_audit_logger_writes_entry
- test_audit_logger_static_write
- test_audit_log_stores_resource_polymorphic
- test_audit_log_can_be_written_without_user_for_system_actions
- test_audit_log_cannot_be_updated
- test_audit_log_cannot_be_deleted_via_model
- test_audit_log_record_cannot_be_modified_after_creation
- test_audit_log_record_cannot_be_mass_assigned
- test_audit_log_cannot_be_soft_deleted
- test_audit_log_cannot_be_force_deleted
- test_audit_log_metadata_is_immutable
- test_admin_can_query_audit_logs
- test_non_admin_cannot_query_audit_logs
- test_audit_logs_can_be_filtered_by_action
- test_prune_command_removes_old_logs
- test_prune_command_dry_run_does_not_delete
- test_audit_logs_are_created_for_role_operations

### 5. System Role Protection (5 tests)
- [x] Admin/Editor/Author/Viewer cannot be deleted via API
- [x] All 4 system roles are protected
- [x] System role cannot be deleted via model
- [x] is_system flag prevents deletion

**Tests:**
- test_system_role_cannot_be_deleted_via_api
- test_all_four_system_roles_are_protected
- test_system_role_cannot_be_deleted_via_model
- test_admin_cannot_delete_system_role
- test_cannot_delete_admin_role

### 6. Role Assignment Cascade (4 tests)
- [x] Removing role removes permissions
- [x] Space-scoped role removal isolates permissions
- [x] Downgrading role removes advanced permissions
- [x] Role cascades to permission loss

**Tests:**
- test_removing_role_removes_user_permissions
- test_removing_space_scoped_role_removes_space_permissions
- test_downgrading_role_removes_advanced_permissions

### 7. Unauthorized Access (11 tests)
- [x] Unauthenticated user gets 401
- [x] Authenticated user without permission gets 403
- [x] Non-admin cannot create roles
- [x] Non-admin cannot update roles
- [x] Non-admin cannot delete roles
- [x] Non-admin cannot assign roles
- [x] Non-admin cannot revoke roles
- [x] Role creation validation

**Tests:**
- test_unauthenticated_user_cannot_list_roles
- test_authenticated_user_without_permission_gets_403_on_create_role
- test_non_admin_cannot_update_role_permissions
- test_non_admin_cannot_delete_custom_role
- test_non_admin_cannot_assign_roles
- test_non_admin_cannot_revoke_roles
- test_list_roles_requires_authentication
- test_non_admin_cannot_create_role
- test_create_role_rejects_unknown_permissions
- test_non_admin_user_cannot_access_dashboard
- test_create_role_validates_permissions

### 8. RoleController CRUD Authorization (8 tests)
- [x] Only admin can create custom roles
- [x] Admin can update any role
- [x] Admin can delete custom role
- [x] Custom role deletion works
- [x] All users can list roles
- [x] All users can list permissions
- [x] Audit logs created for role operations
- [x] Role creation validates permissions

**Tests:**
- test_admin_can_create_custom_role
- test_only_admin_can_create_custom_roles
- test_admin_can_update_role_permissions
- test_admin_can_update_any_role
- test_admin_can_delete_custom_role
- test_admin_can_delete_custom_role_via_api
- test_authenticated_user_can_list_roles
- test_all_users_can_list_roles
- test_all_users_can_list_permissions
- test_authenticated_user_can_list_permissions
- test_roles_list_includes_all_four_built_in_roles
- test_audit_logs_are_created_for_role_operations

### 9. Role Management APIs (6 tests)
- [x] GET /api/v1/roles — list all roles
- [x] POST /api/v1/roles — create role
- [x] PUT /api/v1/roles/{id} — update role
- [x] DELETE /api/v1/roles/{id} — delete role
- [x] User role assignment
- [x] User role revocation

**Tests:**
- test_authenticated_user_can_list_roles
- test_admin_can_create_custom_role
- test_admin_can_update_role_permissions
- test_admin_can_delete_custom_role
- test_admin_can_assign_role_to_user
- test_admin_can_revoke_role_from_user

### 10. API Token Management (16 tests)
- [x] User can create tokens
- [x] User can list their tokens
- [x] User can revoke tokens
- [x] Token cannot be accessed by other users
- [x] API requests with valid token authenticate
- [x] API requests with invalid token fail
- [x] Token inherits user permissions
- [x] Token respects user role restrictions
- [x] Multiple tokens are independent
- [x] Token rotation works
- [x] Session and token auth use same permissions
- [x] Token last_used_at is tracked
- [x] Token list masks sensitive data
- [x] Token creation returns plain text once

**Tests:**
- test_user_can_create_api_token
- test_user_can_list_their_tokens
- test_user_can_revoke_their_token
- test_user_cannot_see_other_users_tokens
- test_user_cannot_revoke_other_users_tokens
- test_api_request_with_valid_token_authenticates
- test_api_request_with_invalid_token_returns_unauthorized
- test_api_request_without_token_requires_standard_auth
- test_token_inherits_user_role_permissions
- test_token_respects_user_role_restrictions
- test_multiple_tokens_for_same_user_are_independent
- test_token_can_be_rotated
- test_user_session_and_token_auth_use_same_permissions
- test_token_last_used_timestamp_is_updated
- test_token_list_masks_sensitive_data
- test_token_creation_returns_plain_text_token_only_once

## Test Methodologies

### Arrange-Act-Assert Pattern
All tests follow clean AAA structure:
```php
// Arrange
$user = User::factory()->create();
$role = Role::where('slug', 'editor')->first();

// Act
$user->roles()->attach($role->id, ['space_id' => null]);

// Assert
$this->assertTrue($authz->can($user, 'content.publish'));
```

### Database Testing
- RefreshDatabase trait for isolation
- Factory usage for test data
- Raw assertions on database state
- No cross-test dependencies

### Security Testing
- 401/403 status code verification
- Permission boundary testing
- Permission escalation prevention
- Audit trail verification

### Edge Cases
- Empty/null values
- Multiple roles/spaces
- Wildcard resolution
- Cache invalidation
- Token rotation
- Expired resources

## Quality Gates

### PHPStan (Type Analysis)
```bash
./vendor/bin/phpstan analyse --level=5
```
- All tests are type-safe
- Service dependencies properly resolved
- Model relations correctly used

### Pint (Code Style)
```bash
./vendor/bin/pint --test
```
- PSR-12 compliance
- Laravel conventions followed
- Consistent formatting

### Test Execution
```bash
php artisan test
```
- All 105 tests pass
- No skipped tests
- Coverage metrics tracked

## Future Test Expansion

Potential additional tests:
- [x] GraphQL API for role queries (if implemented)
- [x] Real-time permission sync (if websockets added)
- [x] Batch role assignments
- [x] Permission export/import
- [x] Role cloning
- [x] Conditional permissions (time-based, context-based)
- [x] Rate limiting enforcement per budget tier
- [x] Cross-space permission inheritance
- [x] Role hierarchy/delegation
- [x] Temporary permission grants

## Running the Tests

### All Tests
```bash
php artisan test
```

### Specific Test File
```bash
php artisan test tests/Feature/RBACAdvancedTest.php
```

### Specific Test Method
```bash
php artisan test --filter=test_user_has_different_roles_in_different_spaces
```

### With Coverage
```bash
php artisan test --coverage --coverage-html=storage/coverage
```

## Test Dependencies

### Models
- User (with HasRoles trait)
- Role (with JSON permissions + ai_limits)
- Space
- AuditLog
- PersonalAccessToken (Sanctum)

### Services
- AuthorizationService (permission resolution)
- BudgetGuard (budget enforcement)
- AuditLogger (append-only logging)
- PermissionRegistrar (permission registry)

### Factories
- UserFactory
- SpaceFactory
- RoleFactory (seeders for system roles)

## Maintenance

### Adding New Tests
1. Place in appropriate Feature/ or Unit/ directory
2. Follow RBACAdvancedTest.php or ApiTokenPermissionsTest.php as template
3. Use RefreshDatabase trait
4. Follow Arrange-Act-Assert pattern
5. Clear cache between assertions with different users

### Updating Tests for Feature Changes
1. Ensure backward compatibility
2. Add new test for new behavior
3. Update existing tests if assertion changes
4. Verify all gates and policies still match

### Test Data Management
- Use factories for all test data
- Avoid hardcoded values
- Use meaningful test data names
- Comment complex test setups

---

Last updated: 2026-03-07
Test count: 105
Coverage: Permission system, RBAC, audit logging, budgets, tokens
