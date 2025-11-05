<?php

require_once __DIR__ . '/database.php';

class UserModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    //Get user information by ID
    public function getUserById($userId)
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

    //Get user information by identifier
    public function getUserByIdentifier($identifier)
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
            WHERE identifier = :identifier
        ";

        try {
            return $this->db->selectOne($query, [':identifier' => $identifier]);
        } catch (Exception $e) {
            error_log("Error fetching user by identifier: " . $e->getMessage());
            return null;
        }
    }

    //Update user password
    public function updatePassword($userId, $newPasswordHash)
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

    //Verify current password
    public function verifyPassword($userId, $password)
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

    //Update user email
    public function updateEmail($userId, $newEmail)
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

    //Check if email is already taken by another user
    public function isEmailTaken($email, $excludeUserId = null)
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
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

    //Get user's role label in French
    public function getRoleLabel($role)
    {
        $roleLabels = [
            'student' => 'Étudiant',
            'teacher' => 'Enseignant',
            'academic_manager' => 'Responsable pédagogique',
            'secretary' => 'Secrétaire'
        ];

        return $roleLabels[$role] ?? 'Non défini';
    }

    //Get user statistics (for students)
    public function getUserStatistics($userIdentifier)
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
}
