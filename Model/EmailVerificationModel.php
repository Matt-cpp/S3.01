<?php

declare(strict_types=1);

/**
 * EmailVerificationModel - Manages email verification codes.
 * Used for both password reset and new account registration flows.
 */

require_once __DIR__ . '/database.php';

class EmailVerificationModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    /**
     * Delete existing codes for the given email and insert a new one.
     */
    public function createVerification(string $email, string $code, string $expiresAt): void
    {
        $this->db->execute('DELETE FROM email_verifications WHERE email = :email', [':email' => $email]);
        $this->db->execute(
            'INSERT INTO email_verifications (email, verification_code, expires_at) VALUES (:email, :code, :expires_at)',
            [':email' => $email, ':code' => $code, ':expires_at' => $expiresAt]
        );
    }

    /**
     * Return the verification row if the code is valid, not yet verified, and not expired.
     */
    public function getValidVerification(string $email, string $code): ?array
    {
        return $this->db->selectOne(
            'SELECT id FROM email_verifications
             WHERE email = :email AND verification_code = :code AND expires_at > NOW() AND is_verified = FALSE',
            [':email' => $email, ':code' => $code]
        );
    }

    /**
     * Return the verification row if it has been verified and is still within its validity window.
     * Used by the password-reset flow before allowing a new password to be saved.
     */
    public function getVerifiedAndActive(string $email): ?array
    {
        return $this->db->selectOne(
            'SELECT id FROM email_verifications
             WHERE email = :email AND is_verified = TRUE AND expires_at > NOW()',
            [':email' => $email]
        );
    }

    /**
     * Mark the matching code as verified.
     */
    public function markVerified(string $email, string $code): void
    {
        $this->db->execute(
            'UPDATE email_verifications SET is_verified = TRUE WHERE email = :email AND verification_code = :code',
            [':email' => $email, ':code' => $code]
        );
    }

    /**
     * Delete all verification codes for the given email (used after successful account creation / password reset).
     */
    public function deleteVerifications(string $email): void
    {
        $this->db->execute('DELETE FROM email_verifications WHERE email = :email', [':email' => $email]);
    }
}
