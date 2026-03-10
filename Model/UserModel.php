<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class UserModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    // Get user information by ID
    public function getUserById(int $userId): ?array
    {
        $query = "
            SELECT 
                id,
                identifier,
                last_name,
                first_name,
                middle_name,
                birth_date,
                degrees,
                department,
                email,
                role,
                created_at,
                updated_at
            FROM users
            WHERE id = :user_id
        ";

        try {
            return $this->db->selectOne($query, [':user_id' => $userId]);
        } catch (Exception $e) {
            error_log("Error fetching user by ID: " . $e->getMessage());
            return null;
        }
    }

    // Get user information by identifier
    public function getUserByIdentifier(string $identifier): ?array
    {
        $query = "
            SELECT 
                id,
                identifier,
                last_name,
                first_name,
                middle_name,
                birth_date,
                degrees,
                department,
                email,
                role,
                created_at,
                updated_at
            FROM users
            WHERE UPPER(identifier) = UPPER(:identifier)
        ";

        try {
            return $this->db->selectOne($query, [':identifier' => $identifier]);
        } catch (Exception $e) {
            error_log("Error fetching user by identifier: " . $e->getMessage());
            return null;
        }
    }

    // Update user password
    public function updatePassword(int $userId, string $newPasswordHash): bool
    {
        $query = "
            UPDATE users 
            SET password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ";

        try {
            $rowCount = $this->db->execute($query, [
                ':password_hash' => $newPasswordHash,
                ':user_id' => $userId
            ]);
            return $rowCount > 0;
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            return false;
        }
    }

    // Verify current password
    public function verifyPassword(int $userId, string $password): bool
    {
        $query = "SELECT password_hash FROM users WHERE id = :user_id";

        try {
            $result = $this->db->selectOne($query, [':user_id' => $userId]);
            if ($result && isset($result['password_hash'])) {
                return password_verify($password, $result['password_hash']);
            }
            return false;
        } catch (Exception $e) {
            error_log("Error verifying password: " . $e->getMessage());
            return false;
        }
    }

    // Update user email
    public function updateEmail(int $userId, string $newEmail): bool
    {
        $query = "
            UPDATE users 
            SET email = :email,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :user_id
        ";

        try {
            $rowCount = $this->db->execute($query, [
                ':email' => $newEmail,
                ':user_id' => $userId
            ]);
            return $rowCount > 0;
        } catch (Exception $e) {
            error_log("Error updating email: " . $e->getMessage());
            return false;
        }
    }

    // Check if email is already taken by another user
    public function isEmailTaken(string $email, ?int $excludeUserId = null): bool
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE UPPER(email) = UPPER(:email)";
        $params = [':email' => $email];

        if ($excludeUserId) {
            $query .= " AND id != :user_id";
            $params[':user_id'] = $excludeUserId;
        }

        try {
            $result = $this->db->selectOne($query, $params);
            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking email availability: " . $e->getMessage());
            return true; // Assume taken on error for safety
        }
    }

    // Get user's role label
    public function getRoleLabel(string $role): string
    {
        $roleLabels = [
            'student' => 'Étudiant',
            'teacher' => 'Enseignant',
            'academic_manager' => 'Responsable pédagogique',
            'secretary' => 'Secrétaire'
        ];

        return $roleLabels[$role] ?? 'Non défini';
    }

    // Get user statistics (for students)
    public function getUserStatistics(string $userIdentifier): ?array
    {
        $query = "
            SELECT 
                COUNT(*) as total_absences,
                COUNT(CASE WHEN justified = true THEN 1 END) as justified_absences,
                COUNT(CASE WHEN justified = false THEN 1 END) as unjustified_absences
            FROM absences
            WHERE student_identifier = :identifier
        ";

        try {
            return $this->db->selectOne($query, [':identifier' => $userIdentifier]);
        } catch (Exception $e) {
            error_log("Error fetching user statistics: " . $e->getMessage());
            return [
                'total_absences' => 0,
                'justified_absences' => 0,
                'unjustified_absences' => 0
            ];
        }
    }

    /**
     * Delete a student and all related data to leave no trace in the database.
     * Operates inside a transaction and attempts to remove rows from all tables
     * that reference the student's `identifier` or `id`.
     *
     * @param string $identifier Student identifier (may be NULL for users without identifier)
     * @return bool True on success, false on failure
     */
    public function deleteStudentCascade(string $identifier): bool
    {
        try {
            // Find user by identifier (case-insensitive)
            $user = $this->getUserByIdentifier($identifier);
            if (!$user) {
                return false; // nothing to delete
            }

            $userId = $user['id'];
            $email = $user['email'] ?? null;

            // Begin transaction
            $this->db->beginTransaction();

            // Remove decision history related to proofs of this student
            $sql = "DELETE FROM decision_history WHERE justification_id IN (SELECT id FROM proof WHERE student_identifier = :identifier)";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Remove decision history entries created by or processed by this user
            $sql = "DELETE FROM decision_history WHERE user_id = :user_id";
            $this->db->execute($sql, [':user_id' => $userId]);

            // Delete proofs (will cascade to proof_absences via ON DELETE CASCADE)
            $sql = "DELETE FROM proof WHERE student_identifier = :identifier";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Remove any proof_absences that reference absences of this student (defensive)
            $sql = "DELETE FROM proof_absences WHERE absence_id IN (SELECT id FROM absences WHERE student_identifier = :identifier)";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Delete notifications
            $sql = "DELETE FROM notifications WHERE student_identifier = :identifier";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Delete makeups
            $sql = "DELETE FROM makeups WHERE student_identifier = :identifier";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Delete absence monitoring records referencing either student id or identifier
            $sql = "DELETE FROM absence_monitoring WHERE student_identifier = :identifier OR student_id = :user_id";
            $this->db->execute($sql, [':identifier' => $identifier, ':user_id' => $userId]);

            // Delete absences (will cascade to proof_absences via ON DELETE CASCADE)
            $sql = "DELETE FROM absences WHERE student_identifier = :identifier";
            $this->db->execute($sql, [':identifier' => $identifier]);

            // Remove user-group links
            $sql = "DELETE FROM user_groups WHERE user_id = :user_id";
            $this->db->execute($sql, [':user_id' => $userId]);

            // Remove email_verifications for this email (if present)
            if (!empty($email)) {
                $sql = "DELETE FROM email_verifications WHERE UPPER(email) = UPPER(:email)";
                $this->db->execute($sql, [':email' => $email]);
            }

            // Finally delete the user record
            $sql = "DELETE FROM users WHERE id = :user_id";
            $this->db->execute($sql, [':user_id' => $userId]);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            error_log("Error deleting student cascade: " . $e->getMessage());
            try {
                $this->db->rollBack();
            } catch (Exception $e2) {
                // ignore rollback errors
            }
            return false;
        }
    }

    /**
     * Delete a student and all related data using the user's numeric id.
     * This complements deleteStudentCascade(string $identifier) and is the
     * preferred method when the caller has the user's `id`.
     *
     * @param int $userId
     * @return bool
     */
    public function deleteStudentCascadeById(int $userId): bool
    {
        try {
            // Find user by id
            $user = $this->getUserById($userId);
            if (!$user) {
                return false; // nothing to delete
            }

            $identifier = $user['identifier'] ?? null;
            $email = $user['email'] ?? null;

            $this->db->beginTransaction();

            // Decision history referencing proofs for this student (if identifier available)
            if (!empty($identifier)) {
                $sql = "DELETE FROM decision_history WHERE justification_id IN (SELECT id FROM proof WHERE student_identifier = :identifier)";
                $this->db->execute($sql, [':identifier' => $identifier]);
            }

            // Decision history entries created by / processed by this user
            $sql = "DELETE FROM decision_history WHERE user_id = :user_id";
            $this->db->execute($sql, [':user_id' => $userId]);

            // Delete proofs (by identifier)
            if (!empty($identifier)) {
                $sql = "DELETE FROM proof WHERE student_identifier = :identifier";
                $this->db->execute($sql, [':identifier' => $identifier]);

                // Defensive cleanup for proof_absences that reference absences of this student
                $sql = "DELETE FROM proof_absences WHERE absence_id IN (SELECT id FROM absences WHERE student_identifier = :identifier)";
                $this->db->execute($sql, [':identifier' => $identifier]);

                // Delete notifications, makeups and absences by identifier
                $this->db->execute("DELETE FROM notifications WHERE student_identifier = :identifier", [':identifier' => $identifier]);
                $this->db->execute("DELETE FROM makeups WHERE student_identifier = :identifier", [':identifier' => $identifier]);
                $this->db->execute("DELETE FROM absences WHERE student_identifier = :identifier", [':identifier' => $identifier]);

                // absence_monitoring rows by identifier
                $this->db->execute("DELETE FROM absence_monitoring WHERE student_identifier = :identifier", [':identifier' => $identifier]);
            }

            // Also remove monitoring rows referencing the numeric student id
            $this->db->execute("DELETE FROM absence_monitoring WHERE student_id = :user_id", [':user_id' => $userId]);

            // Remove user-group links
            $this->db->execute("DELETE FROM user_groups WHERE user_id = :user_id", [':user_id' => $userId]);

            // Remove email_verifications for this email (if present)
            if (!empty($email)) {
                $this->db->execute("DELETE FROM email_verifications WHERE UPPER(email) = UPPER(:email)", [':email' => $email]);
            }

            // Finally delete the user record
            $this->db->execute("DELETE FROM users WHERE id = :user_id", [':user_id' => $userId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            error_log("Error deleting student cascade by id: " . $e->getMessage());
            try {
                $this->db->rollBack();
            } catch (Exception $e2) {
                // ignore
            }
            return false;
        }
    }
}
