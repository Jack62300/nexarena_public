<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Safely deletes a user and all their related data in the correct order,
 * respecting FK constraints (RESTRICT) that exist in the DB.
 *
 * Deletion order:
 *   1. Nullify nullable RESTRICT FKs (vote.user, comment.flagged_by, comment.deleted_by)
 *   2. Delete NOT-NULL RESTRICT rows that cannot cascade automatically:
 *      live_promotion, discord_announcement, comment, recruitment_message
 *   3. Delete servers → DB CASCADE handles everything linked to them
 *      (votes, featured_bookings, collab, premium_features, twitch_sub, boosts,
 *       stats, recruitment_listings → applications → messages, vote_rewards, comments)
 *   4. Delete remaining recruitment_listings authored by user (on other people's servers)
 *   5. Delete the user → DB CASCADE handles transactions, notifications,
 *      achievements, user_twitch_subscription, server_collaborations
 */
class UserDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerService $serverService,
    ) {}

    /**
     * Delete a user and all their associated data.
     * Returns an array of stats (what was deleted).
     *
     * @throws \Throwable on DB error (transaction is rolled back)
     */
    public function deleteUser(User $user): array
    {
        $conn = $this->em->getConnection();
        $id   = $user->getId();

        // ── Collect server file names BEFORE deletion for cleanup
        $serverFiles = $conn->fetchAllAssociative(
            'SELECT banner, presentation_image FROM server WHERE owner_id = ?',
            [$id]
        );

        $stats = [];

        $conn->beginTransaction();
        try {
            // 1. Nullify nullable RESTRICT FKs
            $conn->executeStatement('UPDATE vote           SET user_id       = NULL WHERE user_id       = ?', [$id]);
            $conn->executeStatement('UPDATE comment        SET flagged_by_id = NULL WHERE flagged_by_id = ?', [$id]);
            $conn->executeStatement('UPDATE comment        SET deleted_by_id = NULL WHERE deleted_by_id = ?', [$id]);

            // 2. Delete NOT-NULL RESTRICT entities (cannot be left orphaned)
            $stats['live_promotions']   = (int) $conn->executeStatement('DELETE FROM live_promotion         WHERE user_id       = ?', [$id]);
            $stats['announcements']     = (int) $conn->executeStatement('DELETE FROM discord_announcement   WHERE created_by_id = ?', [$id]);
            $stats['comments']          = (int) $conn->executeStatement('DELETE FROM comment                WHERE author_id     = ?', [$id]);
            $stats['messages']          = (int) $conn->executeStatement('DELETE FROM recruitment_message    WHERE sender_id     = ?', [$id]);

            // 3. Delete servers → DB cascades handle all server-related data
            $stats['servers']           = (int) $conn->executeStatement('DELETE FROM server                 WHERE owner_id      = ?', [$id]);

            // 4. Delete recruitment listings authored by user on OTHER servers (if any)
            $stats['listings']          = (int) $conn->executeStatement('DELETE FROM recruitment_listing    WHERE author_id     = ?', [$id]);

            // 5. Delete the user → DB cascades handle remaining (transactions, notifications,
            //    achievements, user_twitch_subscription, server_collaborations, featured_bookings, etc.)
            $conn->executeStatement('DELETE FROM `user` WHERE id = ?', [$id]);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        // Clear Doctrine's identity map (we bypassed it with raw SQL)
        $this->em->clear();

        // ── Clean up server upload files
        foreach ($serverFiles as $row) {
            if (!empty($row['banner'])) {
                $this->serverService->deleteFile('servers/banners', $row['banner']);
            }
            if (!empty($row['presentation_image'])) {
                $this->serverService->deleteFile('servers/presentations', $row['presentation_image']);
            }
        }

        return $stats;
    }
}
