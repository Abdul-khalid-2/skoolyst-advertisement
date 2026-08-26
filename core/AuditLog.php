<?php

namespace Core;

/**
 * AuditLog
 *
 * One write helper shared by every module that needs to record a
 * sensitive admin action (ad approve/reject — 6.v; API-key
 * regenerate — 6.w). Controllers call write() after the action
 * succeeds; this never decides whether an action is allowed, only
 * records that it happened.
 */
class AuditLog
{
    public static function write(int $adminId, string $action, string $subjectType, int $subjectId): void
    {
        Database::query(
            'INSERT INTO audit_log (admin_id, action, subject_type, subject_id) VALUES (:admin_id, :action, :subject_type, :subject_id)',
            [
                'admin_id' => $adminId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ]
        );
    }
}
