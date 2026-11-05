<?php
declare(strict_types=1);

/**
 * e-Lesson Access Service
 *
 * Single source of truth for "does this student currently have paid access to this
 * course". Under the target model (CLAUDE.md rule 5, README section 12), access is
 * controlled by course_subs.status = 'active' — never by comparing access_expires_at
 * to NOW(). access_expires_at is informational only (the planned end of the current
 * paid period); an overdue active subscription still grants access.
 */

class AccessService
{
    /**
     * Whether $userId has active paid access to $courseId within $tenantId.
     * Does NOT apply the free-course bypass (price = 0 && classLevel match) — callers
     * that need that check it separately, since it depends on the specific course/user
     * row, not just the subscription.
     */
    public static function hasActiveAccess(PDO $pdo, int $tenantId, int $userId, int $courseId): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM course_subs WHERE user_id = ? AND course_id = ? AND tenant_id = ? AND status = 'active'"
        );
        $stmt->execute([$userId, $courseId, $tenantId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
