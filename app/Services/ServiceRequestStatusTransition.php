<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;

/**
 * ServiceRequestStatusTransition manages valid status transitions.
 *
 * Status Lifecycle:
 * submitted → in_progress → approved → closed
 *                        ↓
 *                      rejected → (can return to submitted)
 */
class ServiceRequestStatusTransition
{
    /**
     * Valid status transitions map.
     * Key: current status, Value: array of allowed next statuses.
     */
    private static array $transitions = [
        'submitted' => ['in_progress', 'rejected'],
        'in_progress' => ['approved', 'rejected'],
        'approved' => ['closed'],
        'rejected' => ['submitted', 'in_progress'],
        'closed' => [],
    ];

    /**
     * Static status list.
     */
    public static array $validStatuses = ['submitted', 'in_progress', 'approved', 'rejected', 'closed'];

    /**
     * Check if transition is allowed.
     */
    public static function canTransition(string $from, string $to): bool
    {
        return \in_array($to, self::$transitions[$from] ?? []);
    }

    /**
     * Get allowed transitions from current status.
     * Returns array of allowed target statuses.
     */
    public static function getAllowedTransitions(string $status): array
    {
        return self::$transitions[$status] ?? [];
    }

    /**
     * Assert transition is valid, throw exception if not.
     */
    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new ApiException(
                ErrorCode::INVALID_STATUS_TRANSITION,
                null,
                [
                    'from' => $from,
                    'to' => $to,
                    'allowed' => self::getAllowedTransitions($from),
                ],
                "Cannot transition from '{$from}' to '{$to}'"
            );
        }
    }

    /**
     * Get human-readable status name.
     */
    public static function statusLabel(string $status): string
    {
        return \match($status) {
            'submitted' => 'Submitted',
            'in_progress' => 'In Progress',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'closed' => 'Closed',
            default => $status,
        };
    }

    /**
     * Get status color for UI display.
     */
    public static function statusColor(string $status): string
    {
        return \match($status) {
            'submitted' => 'info',
            'in_progress' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'closed' => 'secondary',
            default => 'light',
        };
    }

    /**
     * Get status icon for UI display.
     */
    public static function statusIcon(string $status): string
    {
        return \match($status) {
            'submitted' => 'check-circle',
            'in_progress' => 'spinner',
            'approved' => 'thumbs-up',
            'rejected' => 'times-circle',
            'closed' => 'lock',
            default => 'circle',
        };
    }

    /**
     * Get status description.
     */
    public static function statusDescription(string $status): string
    {
        return \match($status) {
            'submitted' => 'Request has been submitted and is awaiting processing',
            'in_progress' => 'Request is being processed',
            'approved' => 'Request has been approved',
            'rejected' => 'Request has been rejected',
            'closed' => 'Request is closed',
            default => '',
        };
    }

    /**
     * Is status final (no more transitions possible)?
     */
    public static function isFinal(string $status): bool
    {
        return empty(self::$transitions[$status]);
    }

    /**
     * Get all possible statuses.
     */
    public static function getAllStatuses(): array
    {
        return self::$validStatuses;
    }

    /**
     * Validate status is in allowed list.
     */
    public static function isValid(string $status): bool
    {
        return \in_array($status, self::$validStatuses);
    }
}
