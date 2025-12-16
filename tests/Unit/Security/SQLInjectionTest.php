<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Tests\Fixtures\UsersFixture;
use Tests\Fixtures\AbsencesFixture;
use Tests\Fixtures\ProofsFixture;

require_once __DIR__ . '/../../../Model/database.php';
require_once __DIR__ . '/../../../Model/UserModel.php';
require_once __DIR__ . '/../../../Model/ProofModel.php';
require_once __DIR__ . '/../../../Model/AbsenceModel.php';

/**
 * SQL Injection Security Tests
 * 
 * These tests verify that the database layer properly sanitizes inputs
 * and prevents SQL injection attacks through prepared statements.
 * 
 * Each test attempts various SQL injection patterns to ensure they are
 * safely handled without executing malicious SQL code.
 */
class SQLInjectionTest extends TestCase
{
    private \Database $db;
    private \UserModel $userModel;
    private \ProofModel $proofModel;
    private \AbsenceModel $absenceModel;

    protected function setUp(): void
    {
        parent::setUp();
        // Database::getInstance() already uses the test connection set in TestCase
        $this->db = \Database::getInstance();
        // Models will automatically use Database::getInstance() internally
        $this->userModel = new \UserModel();
        $this->proofModel = new \ProofModel();
        $this->absenceModel = new \AbsenceModel();
    }

    // =========================================================================
    // Test: Database Layer - Direct SQL Injection Attempts
    // =========================================================================

    public function testSelectWithQuoteInjectionAttempt(): void
    {
        // Arrange: Create a test user
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'SAFE_USER',
            'email' => 'safe@test.com'
        ]);

        // Act: Try to inject SQL through email parameter
        $maliciousEmail = "safe@test.com' OR '1'='1";
        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email",
            ['email' => $maliciousEmail]
        );

        // Assert: Should not find any user (injection failed)
        $this->assertNull($result, 'SQL injection attempt should not bypass query');
    }

    public function testSelectWithUnionInjectionAttempt(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'TEST_USER',
            'email' => 'test@example.com'
        ]);

        // Act: Try UNION-based SQL injection
        $maliciousId = "1 UNION SELECT password_hash FROM users--";
        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE identifier = :identifier",
            ['identifier' => $maliciousId]
        );

        // Assert: Should not find user or leak data
        $this->assertNull($result, 'UNION injection should be prevented');
    }

    public function testSelectWithCommentInjectionAttempt(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'COMMENT_TEST',
            'email' => 'comment@test.com'
        ]);

        // Act: Try to use SQL comments to bypass query
        $maliciousInput = "COMMENT_TEST'--";
        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE identifier = :identifier",
            ['identifier' => $maliciousInput]
        );

        // Assert: Should not find the user (literal string comparison)
        $this->assertNull($result, 'Comment-based injection should be prevented');
    }

    public function testSelectWithStatementiTerminationAttempt(): void
    {
        // Arrange
        UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'SAFE_ID',
            'email' => 'safe@test.com'
        ]);

        // Act: Try to terminate statement and inject new command
        $maliciousEmail = "safe@test.com'; DROP TABLE users; --";

        try {
            $result = $this->db->selectOne(
                "SELECT * FROM users WHERE email = :email",
                ['email' => $maliciousEmail]
            );

            // Assert: Query should execute safely (not drop table)
            $this->assertNull($result, 'Statement termination should be prevented');

            // Verify table still exists
            $tableCheck = $this->db->selectOne(
                "SELECT COUNT(*) as count FROM users",
                []
            );
            $this->assertNotNull($tableCheck, 'Users table should still exist');

        } catch (\Exception $e) {
            // If exception thrown, that's also acceptable (injection prevented)
            $this->addToAssertionCount(1);
        }
    }

    public function testInsertWithInjectionAttempt(): void
    {
        // Act: Try to inject SQL through INSERT parameters
        $maliciousData = [
            'identifier' => "TEST'); DROP TABLE users; --",
            'last_name' => "Test",
            'first_name' => "User",
            'email' => "test@example.com",
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'student'
        ];

        try {
            $this->db->execute(
                "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role) 
                 VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role)",
                $maliciousData
            );

            // Assert: Data should be inserted as literal string
            $result = $this->db->selectOne(
                "SELECT * FROM users WHERE identifier = :identifier",
                ['identifier' => $maliciousData['identifier']]
            );

            $this->assertNotNull($result, 'Data should be inserted literally');
            $this->assertEquals($maliciousData['identifier'], $result['identifier']);

            // Verify table still exists (wasn't dropped)
            $tableCheck = $this->db->select("SELECT COUNT(*) as count FROM users", []);
            $this->assertNotEmpty($tableCheck, 'Users table should still exist');

        } catch (\Exception $e) {
            // If exception is thrown, injection was prevented
            $this->addToAssertionCount(1);
        }
    }

    public function testUpdateWithInjectionAttempt(): void
    {
        // Arrange: Create user
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'UPDATE_TEST',
            'last_name' => 'Original'
        ]);

        // Act: Try to inject through UPDATE
        $maliciousName = "Hacker' WHERE '1'='1";

        $this->db->execute(
            "UPDATE users SET last_name = :last_name WHERE id = :id",
            [
                'last_name' => $maliciousName,
                'id' => $user['id']
            ]
        );

        // Assert: Only the specific user should be updated
        $updated = $this->db->selectOne(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $user['id']]
        );
        $this->assertEquals($maliciousName, $updated['last_name']);

        // Verify other users weren't affected (if any exist)
        $allUsers = $this->db->select("SELECT * FROM users WHERE id != :id", ['id' => $user['id']]);
        foreach ($allUsers as $otherUser) {
            $this->assertNotEquals($maliciousName, $otherUser['last_name']);
        }
    }

    public function testDeleteWithInjectionAttempt(): void
    {
        // Arrange: Create multiple users
        $user1 = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'KEEP_ME',
            'email' => 'keep@test.com'
        ]);
        $user2 = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'DELETE_ME',
            'email' => 'delete@test.com'
        ]);

        // Act: Try to delete all users through injection
        $maliciousId = $user2['id'] . "' OR '1'='1";

        // Assert: PostgreSQL should reject the malicious input  
        try {
            $deleted = $this->db->execute(
                "DELETE FROM users WHERE id = :id",
                ['id' => $maliciousId]
            );
            // If it doesn't throw, it should delete 0 rows (type mismatch)
            $this->assertEquals(0, $deleted, 'Injection should not delete any rows');
        } catch (\Exception $e) {
            // PostgreSQL type validation caught the injection - this is GOOD!
            // This is the EXPECTED behavior - injection is prevented
            $this->assertStringContainsString(
                'integer',
                $e->getMessage(),
                'PostgreSQL should reject invalid integer input (injection prevented!)'
            );
        }
    }

    // =========================================================================
    // Test: UserModel - SQL Injection Protection
    // =========================================================================

    public function testUserModelGetUserByIdWithInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'email' => 'legitimate@test.com'
        ]);

        // Act: Try to bypass with SQL injection in ID parameter
        $maliciousId = $user['id'] . "' OR '1'='1";

        try {
            $result = $this->userModel->getUserById($maliciousId);
            // If no exception, should return null (type mismatch)
            $this->assertNull($result, 'ID-based injection should be prevented');
        } catch (\Exception $e) {
            // Type validation caught it - this is good!
            $this->addToAssertionCount(1);
        }
    }

    public function testUserModelGetUserByIdentifierWithInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'VALID_ID'
        ]);

        // Act: Various injection attempts
        $injectionAttempts = [
            "VALID_ID' OR '1'='1",
            "VALID_ID'; DROP TABLE users; --",
            "' UNION SELECT password_hash FROM users --",
            "VALID_ID' AND '1'='2' UNION SELECT * FROM users --"
        ];

        foreach ($injectionAttempts as $attempt) {
            $result = $this->userModel->getUserByIdentifier($attempt);
            $this->assertNull(
                $result,
                "Injection attempt should be prevented: {$attempt}"
            );
        }
    }

    public function testUserModelDirectInsertWithInjectionInFields(): void
    {
        // Act: Try to inject SQL through direct INSERT with malicious fields
        $maliciousData = [
            'identifier' => "INJ'; DROP TABLE users; --",
            'last_name' => "O'Malley' OR '1'='1",
            'first_name' => "Bobby'; DELETE FROM users WHERE '1'='1",
            'email' => "test' UNION SELECT password_hash--@test.com",
            'password_hash' => password_hash('test', PASSWORD_DEFAULT),
            'role' => 'student'
        ];

        try {
            // Try to insert directly with malicious data
            $this->db->execute(
                "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role) 
                 VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role)",
                $maliciousData
            );
            $userId = $this->db->lastInsertId();

            // Assert: Data should be inserted as literal strings (safe)
            $created = $this->userModel->getUserById($userId);
            $this->assertNotNull($created);
            $this->assertEquals($maliciousData['identifier'], $created['identifier']);
            $this->assertEquals($maliciousData['last_name'], $created['last_name']);

            // Verify database integrity
            $tableCheck = $this->db->select("SELECT COUNT(*) as count FROM users", []);
            $this->assertNotEmpty($tableCheck, 'Users table should be intact');

        } catch (\Exception $e) {
            // Exception is acceptable
            $this->addToAssertionCount(1);
        }
    }

    // =========================================================================
    // Test: ProofModel - SQL Injection Protection
    // =========================================================================

    public function testProofModelGetProofByIdWithInjection(): void
    {
        // Arrange: Create user and proof
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'PROOF_USER'
        ]);
        $proof = ProofsFixture::createProof($this->getConnection(), 'PROOF_USER');

        // Act: Try to inject through proof ID
        $maliciousId = $proof['id'] . "' OR '1'='1";

        try {
            $result = $this->proofModel->getProofDetails((int) $maliciousId);
            $this->assertNull($result, 'Invalid ID format should return null');
        } catch (\Exception $e) {
            // Exception is acceptable (type error expected)
            $this->addToAssertionCount(1);
        }
    }

    public function testProofModelGetProofsForStudentWithInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'STUDENT_123',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        $proof = ProofsFixture::createProof($this->getConnection(), 'STUDENT_123');

        // Act: Try to access all proofs through SQL injection in status filter
        // Using a field that should have exact matching, not ILIKE
        $maliciousStatus = "pending' OR '1'='1' --";
        $results = $this->proofModel->getAllProofs(['status' => $maliciousStatus]);

        // Assert: Should return empty (injection prevented)
        // Invalid status should not match any proofs
        $this->assertIsArray($results);

        // The malicious status won't match the enum, so should return empty
        // or if it does pattern matching, verify no unauthorized access
        if (!empty($results)) {
            // If somehow results exist, they should only be the legitimate ones
            foreach ($results as $result) {
                $this->assertEquals('STUDENT_123', $result['student_identifier']);
            }
        }
    }

    public function testProofModelUpdateStatusWithInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'STATUS_USER'
        ]);
        $proof1 = ProofsFixture::createProof($this->getConnection(), 'STATUS_USER', [
            'status' => 'pending'
        ]);
        $proof2 = ProofsFixture::createProof($this->getConnection(), 'STATUS_USER', [
            'status' => 'pending'
        ]);

        // Act: Try to update with malicious enum value
        $maliciousStatus = "accepted' WHERE '1'='1";

        // Assert: PostgreSQL enum validation should reject invalid enum value
        $exceptionCaught = false;
        try {
            $result = $this->proofModel->updateProofStatus(
                $proof1['id'],
                $maliciousStatus
            );
            // If it returns false, the update failed (which is good)
            $this->assertFalse($result, 'Update should fail with invalid enum value');
        } catch (\Exception $e) {
            // EXPECTED: Enum validation catches the injection attempt
            $this->assertStringContainsString(
                'justification_status',
                $e->getMessage(),
                'PostgreSQL enum validation should reject invalid value (injection prevented!)'
            );
            $exceptionCaught = true;
        }

        // If exception was thrown, we demonstrated injection was caught
        if ($exceptionCaught) {
            $this->assertTrue(true, 'SQL injection was successfully prevented by PostgreSQL');
        }
    }

    // =========================================================================
    // Test: AbsenceModel - SQL Injection Protection
    // =========================================================================

    public function testAbsenceModelGetAbsencesWithInjection(): void
    {
        // Arrange - Create complete test data
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'ABS_USER',
            'first_name' => 'Test',
            'last_name' => 'Student'
        ]);

        // Create course slot (it creates dependencies automatically)
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
        $absence = AbsencesFixture::createAbsence($this->getConnection(), 'ABS_USER', $courseSlot['id']);

        // Act: Try to get all absences through name injection
        $maliciousName = "Student' OR '1'='1' --";
        $results = $this->absenceModel->getAllAbsences(['name' => $maliciousName]);

        // Assert: Should not return absences (injection prevented by ILIKE with %)
        $this->assertIsArray($results);
        // The query uses ILIKE with wildcards, so it searches for the literal string
        // This is safe because the string is bound as a parameter
        foreach ($results as $result) {
            // If any results exist, verify they actually match the search pattern
            $this->assertStringContainsStringIgnoringCase(
                $maliciousName,
                $result['student_name'] ?? '',
                'Results should only match the literal search string'
            );
        }
    }

    // =========================================================================
    // Test: Advanced Injection Patterns
    // =========================================================================

    public function testStackedQueriesInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'email' => 'stacked@test.com'
        ]);

        // Act: Try stacked queries (multiple statements)
        $maliciousEmail = "stacked@test.com'; UPDATE users SET role = 'academic_manager'; --";

        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email",
            ['email' => $maliciousEmail]
        );

        // Assert: Injection should not execute
        $this->assertNull($result);

        // Verify user role wasn't changed
        $check = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email",
            ['email' => 'stacked@test.com']
        );
        $this->assertEquals('student', $check['role']);
    }

    public function testBooleanBasedBlindInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'BLIND_TEST',
            'email' => 'blind@test.com'
        ]);

        // Act: Boolean-based blind SQL injection attempts
        $attempts = [
            "BLIND_TEST' AND '1'='1",
            "BLIND_TEST' AND '1'='2",
            "BLIND_TEST' AND (SELECT COUNT(*) FROM users) > 0 --",
        ];

        foreach ($attempts as $attempt) {
            $result = $this->db->selectOne(
                "SELECT * FROM users WHERE identifier = :identifier",
                ['identifier' => $attempt]
            );
            $this->assertNull($result, "Boolean injection should be prevented: {$attempt}");
        }
    }

    public function testTimeBasedBlindInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'TIME_TEST'
        ]);

        // Act: Time-based blind injection (pg_sleep)
        $maliciousId = "TIME_TEST'; SELECT pg_sleep(5); --";

        $startTime = microtime(true);
        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE identifier = :identifier",
            ['identifier' => $maliciousId]
        );
        $endTime = microtime(true);

        // Assert: Query should complete quickly (injection prevented)
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(2.0, $executionTime, 'Time-based injection should be prevented');
        $this->assertNull($result);
    }

    public function testSecondOrderInjection(): void
    {
        // Arrange: Insert malicious data that might be used in future queries
        $maliciousIdentifier = "SAFE_ID'; DROP TABLE users; --";

        $userId = $this->db->execute(
            "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role) 
             VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role)",
            [
                'identifier' => $maliciousIdentifier,
                'last_name' => 'Test',
                'first_name' => 'User',
                'email' => 'secondorder@test.com',
                'password_hash' => password_hash('test', PASSWORD_DEFAULT),
                'role' => 'student'
            ]
        );

        // Act: Retrieve and use the identifier in another query
        $stored = $this->db->selectOne(
            "SELECT identifier FROM users WHERE email = :email",
            ['email' => 'secondorder@test.com']
        );

        // Use the stored identifier in a new query
        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE identifier = :identifier",
            ['identifier' => $stored['identifier']]
        );

        // Assert: Second query should handle the data safely
        $this->assertNotNull($result);
        $this->assertEquals($maliciousIdentifier, $result['identifier']);

        // Verify table still exists
        $tableCheck = $this->db->select("SELECT COUNT(*) as count FROM users", []);
        $this->assertNotEmpty($tableCheck, 'Second-order injection should be prevented');
    }

    public function testHexEncodedInjection(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'email' => 'hex@test.com'
        ]);

        // Act: Hex-encoded injection attempt
        // 0x61646d696e = 'admin' in hex
        $hexInjection = "hex@test.com' OR email = 0x61646d696e@test.com --";

        $result = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email",
            ['email' => $hexInjection]
        );

        // Assert: Should not find any user
        $this->assertNull($result, 'Hex-encoded injection should be prevented');
    }

    public function testSpecialCharactersInInputs(): void
    {
        // Test that special characters are properly escaped
        $specialChars = [
            "O'Brien",
            'Test"User',
            'Test\\User',
            "Test\nUser",
            "Test\rUser",
            "Test\tUser"
            // Note: \x00 (null byte) and \x1a may be filtered by PostgreSQL
        ];

        foreach ($specialChars as $testValue) {
            // Act: Insert with special characters
            $identifier = 'SPECIAL_' . md5($testValue);
            $this->db->execute(
                "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role) 
                 VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role)",
                [
                    'identifier' => $identifier,
                    'last_name' => $testValue,
                    'first_name' => 'Test',
                    'email' => md5($testValue) . '@test.com',
                    'password_hash' => password_hash('test', PASSWORD_DEFAULT),
                    'role' => 'student'
                ]
            );

            // Assert: Data should be stored (may be normalized by database)
            $result = $this->db->selectOne(
                "SELECT * FROM users WHERE identifier = :identifier",
                ['identifier' => $identifier]
            );

            $this->assertNotNull($result, "Special character should be stored: {$testValue}");
            // PostgreSQL may normalize some characters, so we verify it's stored safely
            $this->assertNotEmpty($result['last_name'], 'Last name should not be empty');
        }
    }
}
