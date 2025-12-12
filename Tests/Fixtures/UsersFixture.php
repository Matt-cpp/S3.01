<?php

namespace Tests\Fixtures;

/**
 * Test fixtures for creating users in various roles
 */
class UsersFixture
{
    /**
     * Create a test student user
     */
    public static function createStudent(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'identifier' => 'STU' . rand(10000, 99999),
            'last_name' => 'TestStudent',
            'first_name' => 'John',
            'email' => 'student' . rand(1000, 9999) . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'student',
            'email_verified' => true
        ], $overrides);

        // Ensure boolean columns are properly typed
        $data['email_verified'] = $data['email_verified'] === '' ? false : (bool) $data['email_verified'];

        $sql = "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role, email_verified, created_at, updated_at) 
                VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role, :email_verified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, identifier, last_name, first_name, email, role";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test teacher user
     */
    public static function createTeacher(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'identifier' => 'TEACH' . rand(10000, 99999),
            'last_name' => 'TestTeacher',
            'first_name' => 'Jane',
            'email' => 'teacher' . rand(1000, 9999) . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'teacher',
            'email_verified' => true
        ], $overrides);

        // Ensure boolean columns are properly typed
        $data['email_verified'] = $data['email_verified'] === '' ? false : (bool) $data['email_verified'];

        $sql = "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role, email_verified, created_at, updated_at) 
                VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role, :email_verified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, identifier, last_name, first_name, email, role";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':identifier', $data['identifier']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password_hash', $data['password_hash']);
        $stmt->bindValue(':role', $data['role']);
        $stmt->bindValue(':email_verified', $data['email_verified'], \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test academic manager user
     */
    public static function createAcademicManager(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'identifier' => 'MGR' . rand(10000, 99999),
            'last_name' => 'TestManager',
            'first_name' => 'Bob',
            'email' => 'manager' . rand(1000, 9999) . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'academic_manager',
            'email_verified' => true
        ], $overrides);
        // Ensure boolean columns are properly typed
        $data['email_verified'] = $data['email_verified'] === '' ? false : (bool) $data['email_verified'];
        $sql = "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role, email_verified, created_at, updated_at) 
                VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role, :email_verified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, identifier, last_name, first_name, email, role";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':identifier', $data['identifier']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password_hash', $data['password_hash']);
        $stmt->bindValue(':role', $data['role']);
        $stmt->bindValue(':email_verified', $data['email_verified'], \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test secretary user
     */
    public static function createSecretary(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'identifier' => 'SEC' . rand(10000, 99999),
            'last_name' => 'TestSecretary',
            'first_name' => 'Alice',
            'email' => 'secretary' . rand(1000, 9999) . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'secretary',
            'email_verified' => true
        ], $overrides);

        // Ensure boolean columns are properly typed
        $data['email_verified'] = $data['email_verified'] === '' ? false : (bool) $data['email_verified'];

        $sql = "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role, email_verified, created_at, updated_at) 
                VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role, :email_verified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, identifier, last_name, first_name, email, role";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':identifier', $data['identifier']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password_hash', $data['password_hash']);
        $stmt->bindValue(':role', $data['role']);
        $stmt->bindValue(':email_verified', $data['email_verified'], \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a batch of student users
     */
    public static function createStudents(\PDO $pdo, int $count = 5): array
    {
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $students[] = self::createStudent($pdo, [
                'last_name' => 'Student' . ($i + 1),
                'first_name' => 'Test' . ($i + 1)
            ]);
        }
        return $students;
    }

    /**
     * Create a user without identifier (for registration tests)
     */
    public static function createUserWithoutIdentifier(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'identifier' => null,
            'last_name' => 'NoIdentifier',
            'first_name' => 'User',
            'email' => 'noidentifier' . rand(1000, 9999) . '@test.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'student',
            'email_verified' => false
        ], $overrides);

        $sql = "INSERT INTO users (identifier, last_name, first_name, email, password_hash, role, email_verified, created_at, updated_at) 
                VALUES (:identifier, :last_name, :first_name, :email, :password_hash, :role, :email_verified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, identifier, last_name, first_name, email, role, email_verified";

        $stmt = $pdo->prepare($sql);

        // Bind parameters with explicit types
        $stmt->bindValue(':identifier', $data['identifier']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password_hash', $data['password_hash']);
        $stmt->bindValue(':role', $data['role']);
        $stmt->bindValue(':email_verified', (bool) $data['email_verified'], \PDO::PARAM_BOOL);

        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get default password for test users
     */
    public static function getDefaultPassword(): string
    {
        return 'password123';
    }

    /**
     * Get password hash for test users
     */
    public static function getDefaultPasswordHash(): string
    {
        return password_hash(self::getDefaultPassword(), PASSWORD_DEFAULT);
    }
}
