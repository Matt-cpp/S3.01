<?php

namespace Tests\Fixtures;

/**
 * Test fixtures for creating proofs/justifications
 */
class ProofsFixture
{
    /**
     * Create a test proof
     */
    public static function createProof(\PDO $pdo, string $studentIdentifier, array $overrides = []): array
    {
        $data = array_merge([
            'student_identifier' => $studentIdentifier,
            'absence_start_date' => date('Y-m-d'),
            'absence_end_date' => date('Y-m-d', strtotime('+2 days')),
            'concerned_courses' => 'CM Programmation, TD Bases de données',
            'main_reason' => 'illness',
            'custom_reason' => null,
            'file_path' => '/uploads/proof_' . rand(1000, 9999) . '.pdf',
            'student_comment' => 'Test comment',
            'status' => 'pending',
            'manager_comment' => null,
            'processed_by_user_id' => null,
            'locked' => false,
            'proof_files' => '[]'
        ], $overrides);

        // Ensure boolean columns are properly typed
        $data['locked'] = $data['locked'] === '' ? false : (bool) $data['locked'];

        $sql = "INSERT INTO proof (student_identifier, absence_start_date, absence_end_date, concerned_courses, 
                    main_reason, custom_reason, file_path, student_comment, status, manager_comment, 
                    processed_by_user_id, submission_date, processing_date, locked, proof_files, created_at, updated_at) 
                VALUES (:student_identifier, :absence_start_date, :absence_end_date, :concerned_courses, 
                    :main_reason, :custom_reason, :file_path, :student_comment, :status, :manager_comment, 
                    :processed_by_user_id, CURRENT_TIMESTAMP, NULL, :locked, :proof_files::jsonb, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, student_identifier, absence_start_date, absence_end_date, main_reason, status, locked";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':student_identifier', $data['student_identifier']);
        $stmt->bindValue(':absence_start_date', $data['absence_start_date']);
        $stmt->bindValue(':absence_end_date', $data['absence_end_date']);
        $stmt->bindValue(':concerned_courses', $data['concerned_courses']);
        $stmt->bindValue(':main_reason', $data['main_reason']);
        $stmt->bindValue(':custom_reason', $data['custom_reason']);
        $stmt->bindValue(':file_path', $data['file_path']);
        $stmt->bindValue(':student_comment', $data['student_comment']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':manager_comment', $data['manager_comment']);
        $stmt->bindValue(':processed_by_user_id', $data['processed_by_user_id']);
        $stmt->bindValue(':locked', $data['locked'], \PDO::PARAM_BOOL);
        $stmt->bindValue(':proof_files', $data['proof_files']);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a proof with multiple files (JSONB)
     */
    public static function createProofWithFiles(\PDO $pdo, string $studentIdentifier, array $files = []): array
    {
        if (empty($files)) {
            $files = [
                ['path' => '/uploads/medical_certificate.pdf', 'name' => 'medical_certificate.pdf'],
                ['path' => '/uploads/prescription.pdf', 'name' => 'prescription.pdf']
            ];
        }

        return self::createProof($pdo, $studentIdentifier, [
            'proof_files' => json_encode($files),
            'file_path' => null // New proofs use proof_files, not file_path
        ]);
    }

    /**
     * Link a proof to absences via proof_absences table
     */
    public static function linkProofToAbsences(\PDO $pdo, int $proofId, array $absenceIds): void
    {
        foreach ($absenceIds as $absenceId) {
            $sql = "INSERT INTO proof_absences (proof_id, absence_id) 
                    VALUES (:proof_id, :absence_id)
                    ON CONFLICT (proof_id, absence_id) DO NOTHING";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['proof_id' => $proofId, 'absence_id' => $absenceId]);
        }
    }

    /**
     * Create a complete proof scenario with linked absences
     */
    public static function createProofWithAbsences(\PDO $pdo, string $studentIdentifier, array $absenceIds, array $proofData = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, $proofData);
        self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);

        return $proof;
    }

    /**
     * Create a pending proof
     */
    public static function createPendingProof(\PDO $pdo, string $studentIdentifier, array $absenceIds = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, ['status' => 'pending']);

        if (!empty($absenceIds)) {
            self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);
        }

        return $proof;
    }

    /**
     * Create an accepted proof
     */
    public static function createAcceptedProof(\PDO $pdo, string $studentIdentifier, int $processedByUserId, array $absenceIds = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, [
            'status' => 'accepted',
            'processed_by_user_id' => $processedByUserId
        ]);

        if (!empty($absenceIds)) {
            self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);

            // Mark absences as justified
            foreach ($absenceIds as $absenceId) {
                $stmt = $pdo->prepare("UPDATE absences SET justified = true WHERE id = ?");
                $stmt->execute([$absenceId]);
            }
        }

        return $proof;
    }

    /**
     * Create a rejected proof
     */
    public static function createRejectedProof(\PDO $pdo, string $studentIdentifier, int $processedByUserId, array $absenceIds = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, [
            'status' => 'rejected',
            'processed_by_user_id' => $processedByUserId,
            'manager_comment' => 'Proof rejected - insufficient documentation'
        ]);

        if (!empty($absenceIds)) {
            self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);
        }

        return $proof;
    }

    /**
     * Create a proof under review
     */
    public static function createUnderReviewProof(\PDO $pdo, string $studentIdentifier, int $processedByUserId, array $absenceIds = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, [
            'status' => 'under_review',
            'processed_by_user_id' => $processedByUserId,
            'manager_comment' => 'Need additional information'
        ]);

        if (!empty($absenceIds)) {
            self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);
        }

        return $proof;
    }

    /**
     * Create a locked proof
     */
    public static function createLockedProof(\PDO $pdo, string $studentIdentifier, array $absenceIds = []): array
    {
        $proof = self::createProof($pdo, $studentIdentifier, [
            'status' => 'pending',
            'locked' => true
        ]);

        if (!empty($absenceIds)) {
            self::linkProofToAbsences($pdo, $proof['id'], $absenceIds);
        }

        return $proof;
    }

    /**
     * Create proofs in all statuses
     */
    public static function createProofsWithAllStatuses(\PDO $pdo, string $studentIdentifier, int $managerId): array
    {
        return [
            'pending' => self::createPendingProof($pdo, $studentIdentifier),
            'accepted' => self::createAcceptedProof($pdo, $studentIdentifier, $managerId),
            'rejected' => self::createRejectedProof($pdo, $studentIdentifier, $managerId),
            'under_review' => self::createUnderReviewProof($pdo, $studentIdentifier, $managerId)
        ];
    }

    /**
     * Create a decision history entry
     */
    public static function createDecisionHistory(\PDO $pdo, int $proofId, int $userId, string $action, array $overrides = []): array
    {
        $data = array_merge([
            'justification_id' => $proofId,
            'user_id' => $userId,
            'action' => $action,
            'old_status' => 'pending',
            'new_status' => 'accepted',
            'comment' => 'Test decision',
            'rejection_reason' => null
        ], $overrides);

        $sql = "INSERT INTO decision_history (justification_id, user_id, action, old_status, new_status, comment, rejection_reason, created_at) 
                VALUES (:justification_id, :user_id, :action, :old_status, :new_status, :comment, :rejection_reason, CURRENT_TIMESTAMP)
                RETURNING id, justification_id, user_id, action, old_status, new_status, comment";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a rejection reason
     */
    public static function createRejectionReason(\PDO $pdo, string $label = null): array
    {
        $label = $label ?? 'Test Rejection Reason ' . rand(1000, 9999);

        $sql = "INSERT INTO rejection_validation_reasons (label, type_of_reason) 
                VALUES (:label, 'rejection')
                ON CONFLICT (label) DO NOTHING
                RETURNING id, label, type_of_reason";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['label' => $label]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        // If conflict occurred, fetch the existing one
        if (!$result) {
            $stmt = $pdo->prepare("SELECT id, label, type_of_reason FROM rejection_validation_reasons WHERE label = ?");
            $stmt->execute([$label]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * Create a validation reason
     */
    public static function createValidationReason(\PDO $pdo, string $label = null): array
    {
        $label = $label ?? 'Test Validation Reason ' . rand(1000, 9999);

        $sql = "INSERT INTO rejection_validation_reasons (label, type_of_reason) 
                VALUES (:label, 'validation')
                ON CONFLICT (label) DO NOTHING
                RETURNING id, label, type_of_reason";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['label' => $label]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        // If conflict occurred, fetch the existing one
        if (!$result) {
            $stmt = $pdo->prepare("SELECT id, label, type_of_reason FROM rejection_validation_reasons WHERE label = ?");
            $stmt->execute([$label]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return $result;
    }

    /**
     * Get linked absence IDs for a proof
     */
    public static function getLinkedAbsences(\PDO $pdo, int $proofId): array
    {
        $sql = "SELECT absence_id FROM proof_absences WHERE proof_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$proofId]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'absence_id');
    }
}
