<?php

namespace App\Support;

/**
 * The granular staff scopes (blueprint Section 27). Each is a Spatie permission
 * a super admin can grant to a staff member. super_admin bypasses every gate
 * (Gate::before); admin holds all scopes; staff hold only what they're granted.
 *
 * Deleting a user is deliberately NOT a scope — account erasure is super_admin
 * only (AccountService), so no staff member can ever be granted it.
 */
class StaffScopes
{
    public const SCOPES = [
        'kyc.review' => 'Review KYC submissions',
        'tickets.manage' => 'Manage support tickets',
        'refunds.process' => 'Process refunds',
        'users.moderate' => 'Moderate user accounts',
        'orders.assist' => 'Assist with orders',
        'content.manage' => 'Manage content & pages',
        'providers.view' => 'View provider status & balances',
        'esim.manage' => 'Manage eSIM catalogue, margins & images',
        // NCI Operations Center (NAARA-BUILD-17). view = read the five pages;
        // override = the materially more sensitive manual circuit + routing-
        // preference actions, granted separately.
        'nci.view' => 'View the NCI Operations Center (registry, health, routing)',
        'nci.override' => 'Override live routing (manual circuit + provider preference)',
        // Theme System (Batch 3 §6). Grants the admin Theme Picker so delegated
        // staff can switch the platform-wide visual skin without full admin.
        'theme.manage' => 'Switch the platform-wide visual theme',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::SCOPES);
    }

    /** @return array<string,string> scope => human label */
    public static function labels(): array
    {
        return self::SCOPES;
    }

    public static function isValid(string $scope): bool
    {
        return array_key_exists($scope, self::SCOPES);
    }
}
