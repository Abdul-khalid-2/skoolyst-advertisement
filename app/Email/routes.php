<?php

/**
 * Email module routes — centralized email service.
 *
 * Public routes (`auth => false`) are authenticated by the api_key in
 * the request itself (EmailApiController), not a session, so they also
 * skip CSRF. Admin routes (`auth => true`) need an admin session + CSRF
 * token like every other admin endpoint.
 */

use App\Email\EmailAdminController;
use App\Email\EmailApiController;

return [
    // ---- Public API for other Skoolyst apps ----
    ['method' => 'POST', 'path' => '/api/v1/email/send', 'auth' => false, 'handler' => [EmailApiController::class, 'send']],
    ['method' => 'POST', 'path' => '/api/v1/email/receive', 'auth' => false, 'handler' => [EmailApiController::class, 'receive']],

    // ---- Admin: sender accounts ----
    ['method' => 'GET', 'path' => '/api/v1/admin/email-accounts', 'auth' => true, 'handler' => [EmailAdminController::class, 'accountsIndex']],
    ['method' => 'POST', 'path' => '/api/v1/admin/email-accounts', 'auth' => true, 'handler' => [EmailAdminController::class, 'accountsStore']],
    ['method' => 'PATCH', 'path' => '/api/v1/admin/email-accounts/{id}', 'auth' => true, 'handler' => [EmailAdminController::class, 'accountsUpdate']],
    ['method' => 'DELETE', 'path' => '/api/v1/admin/email-accounts/{id}', 'auth' => true, 'handler' => [EmailAdminController::class, 'accountsDestroy']],

    // ---- Admin: API clients ----
    ['method' => 'GET', 'path' => '/api/v1/admin/email-clients', 'auth' => true, 'handler' => [EmailAdminController::class, 'clientsIndex']],
    ['method' => 'POST', 'path' => '/api/v1/admin/email-clients', 'auth' => true, 'handler' => [EmailAdminController::class, 'clientsStore']],
    ['method' => 'PATCH', 'path' => '/api/v1/admin/email-clients/{id}', 'auth' => true, 'handler' => [EmailAdminController::class, 'clientsUpdate']],
    ['method' => 'DELETE', 'path' => '/api/v1/admin/email-clients/{id}', 'auth' => true, 'handler' => [EmailAdminController::class, 'clientsDestroy']],

    // ---- Admin: message inbox ----
    ['method' => 'GET', 'path' => '/api/v1/admin/email-messages', 'auth' => true, 'handler' => [EmailAdminController::class, 'messagesIndex']],
    ['method' => 'PATCH', 'path' => '/api/v1/admin/email-messages/{id}/read', 'auth' => true, 'handler' => [EmailAdminController::class, 'messagesMarkRead']],
];
