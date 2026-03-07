<?php

namespace App\Services\Authorization;

/**
 * Result of a BudgetGuard::canGenerate() check.
 */
enum BudgetCheckResult
{
    /** Generation is allowed, proceed immediately. */
    case Allowed;

    /** Generation is denied — limit exceeded and no approval route. */
    case Denied;

    /** Generation cost exceeds the threshold; requires pipeline.approve to continue. */
    case NeedsApproval;
}
