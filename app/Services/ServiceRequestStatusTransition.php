<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;

final class ServiceRequestStatusTransition
{
    /**
     * Define all statuses as constants (single source of truth)
     */
    public const SUBMITTED   = 'submitted';
    public const IN_PROGRESS = 'in_progress';
    public const APPROVED    = 'approved';
    public const REJECTED    = 'rejected';
    public const CLOSED      = 'closed';

    /**
     * Valid transitions map
     */
    private const TRANSITIONS = [
        self::SUBMITTED   => [self::IN_PROGRESS, self::REJECTED],
        self::IN_PROGRESS => [self::APPROVED, self::REJECTED],
        self::APPROVED    => [self::CLOSED],
        self::REJECTED    => [self::SUBMITTED, self::IN_PROGRESS],
        self::CLOSED      => [],
    ];

    /**
     * Get all valid statuses
     */
    public static function getAllStatuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    /**
     * Validate status exists
     */
    public static function isValid(string $status): bool
    {
        return array_key_exists($status, self::TRANSITIONS);
    }

    /**
     * Check if transition is allowed
     */
    public static function canTransition(string $from, string $to): bool
    {
        if (!self::isValid($from) || !self::isValid($to)) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /**
     * Get allowed transitions
     */
    public static function getAllowedTransitions(string $status): array
    {
        if (!self::isValid($status)) {
            return [];
        }

        return self::TRANSITIONS[$status];
    }

    /**
     * Assert valid transition
     */
    public static function assertTransition(string $from, string $to): void
    {
        if (!self::isValid($from)) {
            throw new ApiException(
                ErrorCode::INVALID_STATUS_TRANSITION,
                null,
                ['invalid_status' => $from],
                "Invalid current status: '{$from}'"
            );
        }

        if (!self::isValid($to)) {
            throw new ApiException(
                ErrorCode::INVALID_STATUS_TRANSITION,
                null,
                ['invalid_status' => $to],
                "Invalid target status: '{$to}'"
            );
        }

        if (!self::canTransition($from, $to)) {
            throw new ApiException(
                ErrorCode::INVALID_STATUS_TRANSITION,
                null,
                [
                    'from'    => $from,
                    'to'      => $to,
                    'allowed' => self::getAllowedTransitions($from),
                ],
                "Cannot transition from '{$from}' to '{$to}'"
            );
        }
    }

    /**
     * Human readable label
     */
    public static function label(string $status): string
    {
        return match ($status) {
            self::SUBMITTED   => 'Submitted',
            self::IN_PROGRESS => 'In Progress',
            self::APPROVED    => 'Approved',
            self::REJECTED    => 'Rejected',
            self::CLOSED      => 'Closed',
            default           => $status,
        };
    }

    /**
     * UI color
     */
    public static function color(string $status): string
    {
        return match ($status) {
            self::SUBMITTED   => 'info',
            self::IN_PROGRESS => 'warning',
            self::APPROVED    => 'success',
            self::REJECTED    => 'danger',
            self::CLOSED      => 'secondary',
            default           => 'light',
        };
    }

    /**
     * Icon name
     */
    public static function icon(string $status): string
    {
        return match ($status) {
            self::SUBMITTED   => 'check-circle',
            self::IN_PROGRESS => 'spinner',
            self::APPROVED    => 'thumbs-up',
            self::REJECTED    => 'times-circle',
            self::CLOSED      => 'lock',
            default           => 'circle',
        };
    }

    /**
     * Description
     */
    public static function description(string $status): string
    {
        return match ($status) {
            self::SUBMITTED   => 'Request has been submitted and is awaiting processing.',
            self::IN_PROGRESS => 'Request is currently being processed.',
            self::APPROVED    => 'Request has been approved.',
            self::REJECTED    => 'Request has been rejected.',
            self::CLOSED      => 'Request has been closed.',
            default           => '',
        };
    }

    /**
     * Is final status?
     */
    public static function isFinal(string $status): bool
    {
        return self::isValid($status)
            && empty(self::TRANSITIONS[$status]);
    }
}