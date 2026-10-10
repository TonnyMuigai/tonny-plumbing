<?php
declare(strict_types=1);

/**
 * Role model:
 *   super_admin — one account. Full control. Only role that can manage users.
 *   admin       — full business access (bookings, orders, products, reports, settings).
 *   staff       — day-to-day operations only (bookings, orders, products). No settings, no reports, no user management.
 *
 * Account limit:
 *   MAX_NON_SUPER_ACCOUNTS = 5 combined admins + staff.
 *   Plus 1 super admin = 6 total.
 */

const ROLE_SUPER_ADMIN = 'super_admin';
const ROLE_ADMIN       = 'admin';
const ROLE_STAFF       = 'staff';

const MAX_NON_SUPER_ACCOUNTS = 5;

function auth_role(): string
{
    return (string)($_SESSION['admin_role'] ?? '');
}

function auth_user_id(): int
{
    return (int)($_SESSION['admin_id'] ?? 0);
}

function auth_user_name(): string
{
    return (string)($_SESSION['admin_name'] ?? '');
}

function is_logged_in(): bool
{
    return auth_user_id() > 0;
}

function is_super_admin(): bool
{
    return auth_role() === ROLE_SUPER_ADMIN;
}

function is_admin(): bool
{
    return auth_role() === ROLE_ADMIN;
}

function is_staff(): bool
{
    return auth_role() === ROLE_STAFF;
}

function is_admin_or_above(): bool
{
    return in_array(auth_role(), [ROLE_SUPER_ADMIN, ROLE_ADMIN], true);
}

function require_role(array $roles, string $redirect = 'index.php'): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
    if (!in_array(auth_role(), $roles, true)) {
        flash('error', 'You do not have permission to open that page.');
        redirect($redirect);
    }
}

function require_super_admin(): void
{
    require_role([ROLE_SUPER_ADMIN]);
}

function require_admin_or_above(): void
{
    require_role([ROLE_SUPER_ADMIN, ROLE_ADMIN]);
}

function non_super_account_count(): int
{
    try {
        return (int)db()->query(
            "SELECT COUNT(*) FROM admin_users WHERE role <> 'super_admin'"
        )->fetchColumn();
    } catch (Throwable $ex) {
        error_log('non_super_account_count failed: ' . $ex->getMessage());
        return 0;
    }
}

function can_create_more_accounts(): bool
{
    return non_super_account_count() < MAX_NON_SUPER_ACCOUNTS;
}

function role_label(string $role): string
{
    return match ($role) {
        ROLE_SUPER_ADMIN => 'Super admin',
        ROLE_ADMIN       => 'Admin',
        ROLE_STAFF       => 'Staff',
        default          => ucfirst($role),
    };
}

/**
 * Nav items for the current role. [filename => [label, visible]]
 */
function nav_items(): array
{
    $r         = auth_role();
    $isSuper   = $r === ROLE_SUPER_ADMIN;
    $isAdminUp = $isSuper || $r === ROLE_ADMIN;

    return [
        'index.php'    => ['Dashboard',     true],
        'bookings.php' => ['Bookings',      true],
        'orders.php'   => ['Orders',        true],
        'products.php' => ['Products',      true],
        'reports.php'  => ['Reports',       $isAdminUp],
        'settings.php' => ['Site settings', $isAdminUp],
        'users.php'    => ['Users',         $isSuper],
    ];
}