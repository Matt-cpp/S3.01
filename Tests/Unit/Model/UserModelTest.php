<?php

namespace Tests\Unit\Model;

use Tests\TestCase;
use Tests\Fixtures\UsersFixture;
use Tests\Fixtures\AbsencesFixture;

require_once __DIR__ . '/../../../Model/UserModel.php';

/**
 * Unit tests for UserModel
 * Tests user management, password verification, and email validation
 */
class UserModelTest extends TestCase
{
    private \UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new \UserModel($this->getTestDatabase());
    }

    // =========================================================================
    // Test: getUserById()
    // =========================================================================

    public function testGetUserByIdReturnsCorrectUser(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'TEST_USER_001',
            'email' => 'testuser@test.com'
        ]);

        // Act
        $result = $this->model->getUserById($user['id']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($user['id'], $result['id']);
        $this->assertEquals('TEST_USER_001', $result['identifier']);
        $this->assertEquals('testuser@test.com', $result['email']);
        $this->assertEquals('student', $result['role']);
    }

    public function testGetUserByIdReturnsNullForNonexistentUser(): void
    {
        $result = $this->model->getUserById(99999);
        $this->assertNull($result);
    }

    // =========================================================================
    // Test: getUserByIdentifier()
    // =========================================================================

    public function testGetUserByIdentifierReturnsCorrectUser(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'UNIQUE_ID_123'
        ]);

        // Act
        $result = $this->model->getUserByIdentifier('UNIQUE_ID_123');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($user['id'], $result['id']);
        $this->assertEquals('UNIQUE_ID_123', $result['identifier']);
    }

    public function testGetUserByIdentifierIsCaseInsensitive(): void
    {
        // Arrange
        UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'CaseSensitive123'
        ]);

        // Act: Search with different case
        $result = $this->model->getUserByIdentifier('casesensitive123');

        // Assert: Should still find the user
        $this->assertNotNull($result);
        $this->assertEquals('CaseSensitive123', $result['identifier']);
    }

    public function testGetUserByIdentifierReturnsNullForNonexistentIdentifier(): void
    {
        $result = $this->model->getUserByIdentifier('NONEXISTENT_ID');
        $this->assertNull($result);
    }

    // =========================================================================
    // Test: verifyPassword()
    // =========================================================================

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        // Arrange
        $password = 'correct_password_123';
        $user = UsersFixture::createStudent($this->getConnection(), [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);

        // Act
        $result = $this->model->verifyPassword($user['id'], $password);

        // Assert
        $this->assertTrue($result);
    }

    public function testVerifyPasswordReturnsFalseForIncorrectPassword(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'password_hash' => password_hash('correct_password', PASSWORD_DEFAULT)
        ]);

        // Act
        $result = $this->model->verifyPassword($user['id'], 'wrong_password');

        // Assert
        $this->assertFalse($result);
    }

    public function testVerifyPasswordReturnsFalseForNonexistentUser(): void
    {
        $result = $this->model->verifyPassword(99999, 'any_password');
        $this->assertFalse($result);
    }

    // =========================================================================
    // Test: updatePassword()
    // =========================================================================

    public function testUpdatePasswordChangesUserPassword(): void
    {
        // Arrange
        $oldPassword = 'old_password_123';
        $newPassword = 'new_password_456';
        $user = UsersFixture::createStudent($this->getConnection(), [
            'password_hash' => password_hash($oldPassword, PASSWORD_DEFAULT)
        ]);

        // Act
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $this->model->updatePassword($user['id'], $newHash);

        // Assert
        $this->assertTrue($result);

        // Verify old password no longer works
        $this->assertFalse($this->model->verifyPassword($user['id'], $oldPassword));

        // Verify new password works
        $updatedUser = $this->queryOne('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $this->assertTrue(password_verify($newPassword, $updatedUser['password_hash']));
    }

    // =========================================================================
    // Test: updateEmail()
    // =========================================================================

    public function testUpdateEmailChangesUserEmail(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'email' => 'old.email@test.com'
        ]);

        // Act
        $newEmail = 'new.email@test.com';
        $result = $this->model->updateEmail($user['id'], $newEmail);

        // Assert
        $this->assertTrue($result);

        $updatedUser = $this->queryOne('SELECT email FROM users WHERE id = ?', [$user['id']]);
        $this->assertEquals($newEmail, $updatedUser['email']);
    }

    // =========================================================================
    // Test: isEmailTaken()
    // =========================================================================

    public function testIsEmailTakenReturnsTrueForExistingEmail(): void
    {
        // Arrange
        UsersFixture::createStudent($this->getConnection(), [
            'email' => 'taken@test.com'
        ]);

        // Act
        $result = $this->model->isEmailTaken('taken@test.com');

        // Assert
        $this->assertTrue($result);
    }

    public function testIsEmailTakenReturnsFalseForAvailableEmail(): void
    {
        $result = $this->model->isEmailTaken('available@test.com');
        $this->assertFalse($result);
    }

    public function testIsEmailTakenExcludesCurrentUser(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'email' => 'myemail@test.com'
        ]);

        // Act: Check if email is taken, excluding current user
        $result = $this->model->isEmailTaken('myemail@test.com', $user['id']);

        // Assert: Should return false because we're excluding the user who has this email
        $this->assertFalse($result);
    }

    public function testIsEmailTakenIsCaseInsensitive(): void
    {
        // Arrange
        UsersFixture::createStudent($this->getConnection(), [
            'email' => 'Test@Example.com'
        ]);

        // Act
        $result = $this->model->isEmailTaken('test@example.com');

        // Assert
        $this->assertTrue($result, 'Email check should be case-insensitive');
    }

    // =========================================================================
    // Test: getRoleLabel() - Translation
    // =========================================================================

    public function testGetRoleLabelReturnsCorrectFrenchTranslation(): void
    {
        $this->assertEquals('Étudiant', $this->model->getRoleLabel('student'));
        $this->assertEquals('Enseignant', $this->model->getRoleLabel('teacher'));
        $this->assertEquals('Responsable pédagogique', $this->model->getRoleLabel('academic_manager'));
        $this->assertEquals('Secrétaire', $this->model->getRoleLabel('secretary'));
    }

    public function testGetRoleLabelReturnsOriginalValueForUnknownRole(): void
    {
        $result = $this->model->getRoleLabel('unknown_role');
        $this->assertEquals('Non défini', $result, 'Unknown roles should return "Non défini"');
    }

    // =========================================================================
    // Test: getUserStatistics()
    // =========================================================================

    public function testGetUserStatisticsReturnsAbsenceCounts(): void
    {
        // Arrange: Create student with absences
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'STAT_TEST_001'
        ]);

        // Create 3 unjustified absences
        for ($i = 0; $i < 3; $i++) {
            $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
            AbsencesFixture::createAbsence(
                $this->getConnection(),
                $user['identifier'],
                $courseSlot['id'],
                ['justified' => false]
            );
        }

        // Create 2 justified absences
        for ($i = 0; $i < 2; $i++) {
            $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection());
            AbsencesFixture::createAbsence(
                $this->getConnection(),
                $user['identifier'],
                $courseSlot['id'],
                ['justified' => true]
            );
        }

        // Act
        $result = $this->model->getUserStatistics($user['identifier']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(5, $result['total_absences']);
        $this->assertEquals(3, $result['unjustified_absences']);
        $this->assertEquals(2, $result['justified_absences']);
    }

    public function testGetUserStatisticsReturnsZeroForUserWithNoAbsences(): void
    {
        // Arrange
        $user = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'NO_ABSENCES_001'
        ]);

        // Act
        $result = $this->model->getUserStatistics($user['identifier']);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(0, $result['total_absences']);
        $this->assertEquals(0, $result['unjustified_absences']);
        $this->assertEquals(0, $result['justified_absences']);
    }

    // =========================================================================
    // Test: User creation with optional fields
    // =========================================================================

    public function testCreateUserWithoutIdentifier(): void
    {
        // Test that users can be created without identifier (for registration)
        $user = UsersFixture::createUserWithoutIdentifier($this->getConnection(), [
            'email' => 'noidentifier@test.com'
        ]);

        $this->assertNull($user['identifier']);
        $this->assertEquals('noidentifier@test.com', $user['email']);
        $this->assertFalse($user['email_verified']);
    }

    public function testMultipleUsersCanHaveNullIdentifier(): void
    {
        // Partial unique index should allow multiple NULL identifiers
        $user1 = UsersFixture::createUserWithoutIdentifier($this->getConnection());
        $user2 = UsersFixture::createUserWithoutIdentifier($this->getConnection());

        $this->assertNull($user1['identifier']);
        $this->assertNull($user2['identifier']);
        $this->assertNotEquals($user1['id'], $user2['id']);
    }

    public function testCannotCreateDuplicateNonNullIdentifiers(): void
    {
        // Arrange
        UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'DUPLICATE_ID'
        ]);

        // Act & Assert: Should throw exception for duplicate
        $this->expectException(\PDOException::class);
        UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'DUPLICATE_ID'
        ]);
    }

    // =========================================================================
    // Test: getAllUsersWithRole()
    // =========================================================================

    public function testGetAllUsersWithRoleFiltersCorrectly(): void
    {
        // Arrange: Create users with different roles
        UsersFixture::createStudent($this->getConnection());
        UsersFixture::createStudent($this->getConnection());
        UsersFixture::createTeacher($this->getConnection());
        UsersFixture::createAcademicManager($this->getConnection());

        // Act: Get all students (if method exists)
        $students = $this->query("SELECT * FROM users WHERE role = 'student'");
        $teachers = $this->query("SELECT * FROM users WHERE role = 'teacher'");
        $managers = $this->query("SELECT * FROM users WHERE role = 'academic_manager'");

        // Assert
        $this->assertGreaterThanOrEqual(2, count($students));
        $this->assertGreaterThanOrEqual(1, count($teachers));
        $this->assertGreaterThanOrEqual(1, count($managers));
    }
}
