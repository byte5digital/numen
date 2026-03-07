<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Thrown by AuthorizationService::authorize() when a user lacks a required permission.
 *
 * Carries the $permission name so exception renderers can return a structured
 * JSON response without having to parse the exception message string.
 */
class PermissionDeniedException extends AuthorizationException
{
    public function __construct(public readonly string $permission)
    {
        parent::__construct("Forbidden. Required permission: {$permission}");
    }

    /**
     * Render as a structured JSON 403 so the exception handler returns
     * { error: 'Forbidden', required: '<permission>' } consistently,
     * matching both RequirePermission middleware responses and direct
     * AuthorizationService::authorize() calls from controllers.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'Forbidden',
            'required' => $this->permission,
        ], 403);
    }
}
