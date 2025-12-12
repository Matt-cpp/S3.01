<?php

namespace Tests\Unit\Model;

use Tests\TestCase;
use Tests\Fixtures\UsersFixture;
use Tests\Fixtures\AbsencesFixture;
use Tests\Fixtures\ProofsFixture;

require_once __DIR__ . '/../../../Model/ProofModel.php';

/**
 * Unit tests for ProofModel
 * Tests the most critical and complex proof management features
 */
class ProofModelTest extends TestCase
{
    private \ProofModel $model;
    private array $testStudent;
    private array $testManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new \ProofModel();

        // Create test users with unique emails to avoid conflicts between tests
        $timestamp = microtime(true);
        $this->testStudent = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'TEST001-' . $timestamp,
            'email' => 'test.student.' . $timestamp . '@test.com'
        ]);

        $this->testManager = UsersFixture::createAcademicManager($this->getConnection(), [
            'identifier' => 'MGR001-' . $timestamp,
            'email' => 'test.manager.' . $timestamp . '@test.com'
        ]);
    }

    // =========================================================================
    // Test: getProofDetails()
    // =========================================================================

    public function testGetProofDetailsReturnsCorrectData(): void
    {
        // Arrange: Create proof with absences
        $courseSlot1 = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => '2024-12-10',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00'
        ]);

        $absence1 = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot1['id']
        );

        $proof = ProofsFixture::createProof($this->getConnection(), $this->testStudent['identifier'], [
            'absence_start_date' => '2024-12-10',
            'absence_end_date' => '2024-12-10',
            'main_reason' => 'illness',
            'status' => 'pending'
        ]);

        ProofsFixture::linkProofToAbsences($this->getConnection(), $proof['id'], [$absence1['id']]);

        // Act
        $result = $this->model->getProofDetails($proof['id']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($proof['id'], $result['proof_id']);
        $this->assertEquals($this->testStudent['identifier'], $result['student_identifier']);
        $this->assertEquals('illness', $result['main_reason']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals($this->testStudent['first_name'], $result['first_name']);
        $this->assertEquals($this->testStudent['last_name'], $result['last_name']);
    }

    public function testGetProofDetailsReturnsNullForNonexistentProof(): void
    {
        $result = $this->model->getProofDetails(99999);
        $this->assertNull($result);
    }

    // =========================================================================
    // Test: extractProofFiles()
    // =========================================================================

    public function testExtractProofFilesFromJsonb(): void
    {
        // Create proof with JSONB files
        $files = [
            ['path' => '/uploads/medical.pdf', 'name' => 'medical.pdf'],
            ['path' => '/uploads/prescription.pdf', 'name' => 'prescription.pdf']
        ];

        $proof = ProofsFixture::createProofWithFiles(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $files
        );

        // Act
        $result = $this->model->getProofDetails($proof['id']);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsArray($result['files']);
        $this->assertCount(2, $result['files']);
        $this->assertEquals('/uploads/medical.pdf', $result['files'][0]['path']);
        $this->assertEquals('medical.pdf', $result['files'][0]['name']);
    }

    public function testExtractProofFilesFallbackToFilePath(): void
    {
        // Create proof with old-style file_path (no JSONB)
        $proof = ProofsFixture::createProof($this->getConnection(), $this->testStudent['identifier'], [
            'file_path' => '/uploads/old_proof.pdf',
            'proof_files' => '[]'
        ]);

        // Act
        $result = $this->model->getProofDetails($proof['id']);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsArray($result['files']);
        $this->assertCount(1, $result['files']);
        $this->assertEquals('/uploads/old_proof.pdf', $result['files'][0]['path']);
        $this->assertEquals('old_proof.pdf', $result['files'][0]['name']);
    }

    // =========================================================================
    // Test: updateProofStatus()
    // =========================================================================

    public function testUpdateProofStatusChangesStatus(): void
    {
        // Arrange
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        // Act
        $result = $this->model->updateProofStatus($proof['id'], 'accepted');

        // Assert
        $this->assertTrue($result);
        $this->assertProofStatus($proof['id'], 'accepted');
    }

    public function testUpdateProofStatusWithInvalidStatusReturnsFalse(): void
    {
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        $result = $this->model->updateProofStatus($proof['id'], 'invalid_status');

        // Should fail gracefully
        $this->assertFalse($result);
    }

    // =========================================================================
    // Test: setRejectionReason() - Complex transaction logic
    // =========================================================================

    public function testSetRejectionReasonUpdatesProofAndCreatesHistory(): void
    {
        // Arrange: Create proof with linked absence
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
        $absence = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id']
        );

        $proof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            [$absence['id']]
        );

        // Act
        $result = $this->model->setRejectionReason(
            $proof['id'],
            'Insufficient documentation',
            'Missing medical certificate',
            $this->testManager['id']
        );

        // Assert
        $this->assertTrue($result);
        $this->assertProofStatus($proof['id'], 'rejected');
        $this->assertDecisionHistoryExists($proof['id'], 'reject');

        // Verify absence remains unjustified
        $this->assertAbsenceJustified($absence['id'], false);
    }

    public function testSetRejectionReasonWithoutUserIdUsesSystemDefault(): void
    {
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        // Act without user_id
        $result = $this->model->setRejectionReason(
            $proof['id'],
            'Test rejection',
            'Test comment',
            null // No user ID
        );

        // Assert - should still work (uses SYSTEM_USER_ID or first user)
        $this->assertTrue($result);
        $this->assertProofStatus($proof['id'], 'rejected');
    }

    // =========================================================================
    // Test: setValidationReason() - Accept proof logic
    // =========================================================================

    public function testSetValidationReasonAcceptsProofAndJustifiesAbsences(): void
    {
        // Arrange: Create proof with multiple linked absences
        $courseSlot1 = AbsencesFixture::createCourseSlot($this->getConnection());
        $courseSlot2 = AbsencesFixture::createCourseSlot($this->getConnection());

        $absence1 = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot1['id']
        );
        $absence2 = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot2['id']
        );

        $proof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            [$absence1['id'], $absence2['id']]
        );

        // Act
        $result = $this->model->setValidationReason(
            $proof['id'],
            'Medical certificate provided',
            'Valid documentation',
            $this->testManager['id']
        );

        // Assert
        $this->assertTrue($result);
        $this->assertProofStatus($proof['id'], 'accepted');
        $this->assertDecisionHistoryExists($proof['id'], 'accept');

        // Verify both absences are now justified
        $this->assertAbsenceJustified($absence1['id'], true);
        $this->assertAbsenceJustified($absence2['id'], true);
    }

    // =========================================================================
    // Test: setRequestInfo() - Request additional information
    // =========================================================================

    public function testSetRequestInfoSetsUnderReviewStatus(): void
    {
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        // Act
        $result = $this->model->setRequestInfo(
            $proof['id'],
            'Need more information',
            (int) $this->testManager['id']
        );

        // Assert
        $this->assertTrue($result);
        $this->assertProofStatus($proof['id'], 'under_review');
        $this->assertDecisionHistoryExists($proof['id'], 'request_info');
    }

    // =========================================================================
    // Test: verrouiller() / deverouiller() - Lock/Unlock mechanisms
    // =========================================================================

    public function testVerrouillerLocksProof(): void
    {
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        // Act
        $result = $this->model->verrouiller($proof['id']);

        // Assert
        $this->assertTrue($result);
        $this->assertProofLocked($proof['id'], true);
    }

    public function testDeverrouillerUnlocksProof(): void
    {
        $proof = ProofsFixture::createLockedProof($this->getConnection(), $this->testStudent['identifier']);

        // Act
        $result = $this->model->deverouiller($proof['id']);

        // Assert
        $this->assertTrue($result);
        $this->assertProofLocked($proof['id'], false);
    }

    public function testIsLockedReturnsCorrectStatus(): void
    {
        $lockedProof = ProofsFixture::createLockedProof($this->getConnection(), $this->testStudent['identifier']);
        $unlockedProof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        $this->assertTrue($this->model->isLocked($lockedProof['id']));
        $this->assertFalse($this->model->isLocked($unlockedProof['id']));
    }

    // =========================================================================
    // Test: updateAbsencesForProof() - CASCADE logic
    // =========================================================================

    public function testUpdateAbsencesForProofSetsJustifiedTrue(): void
    {
        // Arrange
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
        $absence = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id'],
            ['justified' => false]
        );

        $proof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            [$absence['id']]
        );

        // Act
        $result = $this->model->updateAbsencesForProof(
            $this->testStudent['identifier'],
            $proof['absence_start_date'],
            $proof['absence_end_date'],
            'accepted'
        );

        // Assert
        $this->assertTrue($result !== false);
        $this->assertAbsenceJustified($absence['id'], true);
    }

    public function testUpdateAbsencesForProofSetsJustifiedFalse(): void
    {
        // Arrange
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
        $absence = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id'],
            ['justified' => true]
        );

        $proof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            [$absence['id']]
        );

        // Act
        $result = $this->model->updateAbsencesForProof(
            $this->testStudent['identifier'],
            $proof['absence_start_date'],
            $proof['absence_end_date'],
            'rejected'
        );

        // Assert
        $this->assertTrue($result !== false);
        $this->assertAbsenceJustified($absence['id'], false);
    }

    // =========================================================================
    // Test: splitProofMultiple() - Most complex feature
    // =========================================================================

    public function testSplitProofMultipleCreatesSeparateProofs(): void
    {
        // Arrange: Create original proof with 6 absences (2 days, 3 slots per day)
        $absences = [];
        for ($i = 0; $i < 6; $i++) {
            $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
                'course_date' => date('Y-m-d', strtotime('+' . $i . ' days'))
            ]);
            $absences[] = AbsencesFixture::createAbsence(
                $this->getConnection(),
                $this->testStudent['identifier'],
                $courseSlot['id']
            );
        }

        $originalProof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            array_column($absences, 'id')
        );

        // Act: Split into 2 periods
        $periods = [
            [
                'startDate' => date('Y-m-d'),
                'startTime' => '08:00:00',
                'endDate' => date('Y-m-d', strtotime('+2 days')),
                'endTime' => '18:00:00',
                'reason' => 'illness',
                'validate' => false
            ],
            [
                'startDate' => date('Y-m-d', strtotime('+3 days')),
                'startTime' => '08:00:00',
                'endDate' => date('Y-m-d', strtotime('+5 days')),
                'endTime' => '18:00:00',
                'reason' => 'family_obligations',
                'validate' => true // Auto-validate this one
            ]
        ];

        $result = $this->model->splitProofMultiple(
            $originalProof['id'],
            $periods,
            'Split for different reasons',
            $this->testManager['id']
        );

        // Assert
        $this->assertTrue($result);

        // Original proof should be deleted
        $this->assertRecordNotExists('proof', ['id' => $originalProof['id']]);

        // Should have 2 new proofs
        $newProofs = $this->query(
            "SELECT * FROM proof WHERE student_identifier = ? AND id > ? ORDER BY id",
            [$this->testStudent['identifier'], $originalProof['id']]
        );
        $this->assertCount(2, $newProofs);

        // First proof should be pending
        $this->assertEquals('pending', $newProofs[0]['status']);

        // Second proof should be accepted (auto-validated)
        $this->assertEquals('accepted', $newProofs[1]['status']);
    }

    public function testSplitProofMultipleReassignsAbsencesCorrectly(): void
    {
        // Arrange: Create 4 absences across 4 days
        $courseSlots = [];
        $absences = [];
        for ($day = 0; $day < 4; $day++) {
            $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
                'course_date' => date('Y-m-d', strtotime('2024-12-10 +' . $day . ' days')),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00'
            ]);
            $courseSlots[] = $courseSlot;
            $absences[] = AbsencesFixture::createAbsence(
                $this->getConnection(),
                $this->testStudent['identifier'],
                $courseSlot['id']
            );
        }

        $originalProof = ProofsFixture::createProofWithAbsences(
            $this->getConnection(),
            $this->testStudent['identifier'],
            array_column($absences, 'id'),
            [
                'absence_start_date' => '2024-12-10',
                'absence_end_date' => '2024-12-13'
            ]
        );

        // Act: Split into 2 periods (day 1-2 vs day 3-4)
        $periods = [
            [
                'startDate' => '2024-12-10',
                'startTime' => '00:00:00',
                'endDate' => '2024-12-11',
                'endTime' => '23:59:59',
                'reason' => 'illness',
                'validate' => false
            ],
            [
                'startDate' => '2024-12-12',
                'startTime' => '00:00:00',
                'endDate' => '2024-12-13',
                'endTime' => '23:59:59',
                'reason' => 'other',
                'validate' => false
            ]
        ];

        $result = $this->model->splitProofMultiple(
            $originalProof['id'],
            $periods,
            'Different periods',
            $this->testManager['id']
        );

        // Assert
        $this->assertTrue($result);

        // Get the 2 new proofs
        $newProofs = $this->query(
            "SELECT * FROM proof WHERE student_identifier = ? AND id > ? ORDER BY id",
            [$this->testStudent['identifier'], $originalProof['id']]
        );

        // First proof should have absences from days 1-2
        $proof1Absences = ProofsFixture::getLinkedAbsences($this->getConnection(), $newProofs[0]['id']);
        $this->assertCount(2, $proof1Absences);

        // Second proof should have absences from days 3-4
        $proof2Absences = ProofsFixture::getLinkedAbsences($this->getConnection(), $newProofs[1]['id']);
        $this->assertCount(2, $proof2Absences);
    }

    public function testSplitProofMultipleRollsBackOnFailure(): void
    {
        // Arrange
        $proof = ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);

        // Act: Try to split with invalid data (no periods)
        $result = $this->model->splitProofMultiple(
            $proof['id'],
            [], // Empty periods array
            'Invalid split',
            $this->testManager['id']
        );

        // Assert: Should fail and original proof should still exist
        $this->assertFalse($result);
        $this->assertRecordExists('proof', ['id' => $proof['id']]);
    }

    // =========================================================================
    // Test: Translation and formatting methods
    // =========================================================================

    public function testTranslateReasonReturnsCorrectFrenchTranslation(): void
    {
        $this->assertEquals('Maladie', $this->model->translate('reason', 'illness'));
        $this->assertEquals('Décès', $this->model->translate('reason', 'death'));
        $this->assertEquals('Obligations familiales', $this->model->translate('reason', 'family_obligations'));
    }

    public function testTranslateStatusReturnsCorrectFrenchTranslation(): void
    {
        $this->assertEquals('En attente', $this->model->translate('status', 'pending'));
        $this->assertEquals('Accepté', $this->model->translate('status', 'accepted'));
        $this->assertEquals('Rejeté', $this->model->translate('status', 'rejected'));
        $this->assertEquals('En révision', $this->model->translate('status', 'under_review'));
    }

    // =========================================================================
    // Test: getRecentProofs() and getAllProofs() with filters
    // =========================================================================

    public function testGetRecentProofsReturnsLimitedResults(): void
    {
        // Arrange: Create 10 proofs
        for ($i = 0; $i < 10; $i++) {
            ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);
        }

        // Act: Get recent 5
        $result = $this->model->getRecentProofs(5);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(5, $result);
    }

    public function testGetAllProofsFiltersByStatus(): void
    {
        // Arrange: Create proofs with different statuses
        ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);
        ProofsFixture::createPendingProof($this->getConnection(), $this->testStudent['identifier']);
        ProofsFixture::createAcceptedProof($this->getConnection(), $this->testStudent['identifier'], $this->testManager['id']);

        // Act: Filter by pending status
        $result = $this->model->getAllProofs(['status' => 'pending']);

        // Assert
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));

        foreach ($result as $proof) {
            $this->assertEquals('pending', $proof['status']);
        }
    }

    public function testGetAllProofsFiltersByDateRange(): void
    {
        // Arrange
        $oldProof = ProofsFixture::createProof($this->getConnection(), $this->testStudent['identifier'], [
            'absence_start_date' => '2024-01-10',
            'absence_end_date' => '2024-01-12'
        ]);

        $recentProof = ProofsFixture::createProof($this->getConnection(), $this->testStudent['identifier'], [
            'absence_start_date' => '2024-12-10',
            'absence_end_date' => '2024-12-12'
        ]);

        // Act: Filter by recent dates
        $result = $this->model->getAllProofs([
            'start_date' => '2024-12-01',
            'end_date' => '2024-12-31'
        ]);

        // Assert
        $this->assertIsArray($result);
        $foundRecent = false;
        $foundOld = false;

        foreach ($result as $proof) {
            if ($proof['id'] == $recentProof['id']) {
                $foundRecent = true;
            }
            if ($proof['id'] == $oldProof['id']) {
                $foundOld = true; // Should NOT find old proof
            }
        }

        $this->assertTrue($foundRecent, 'Should find recent proof in date range');
        $this->assertFalse($foundOld, 'Should NOT find old proof outside date range');
    }
}
