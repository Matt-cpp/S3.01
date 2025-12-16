# SQL Injection Security Tests

## Overview

This test suite (`SQLInjectionTest.php`) verifies that the application's database layer properly protects against SQL injection attacks. All database interactions use prepared statements with parameter binding, which is the primary defense against SQL injection.

## What is SQL Injection?

SQL injection is a security vulnerability where an attacker can manipulate SQL queries by injecting malicious SQL code through user inputs. For example:

```sql
-- Normal query:
SELECT * FROM users WHERE email = 'user@example.com'

-- With SQL injection attempt:
SELECT * FROM users WHERE email = 'user@example.com' OR '1'='1' --'
-- This would return all users instead of just one
```

## How We Prevent SQL Injection

### 1. Prepared Statements (Primary Defense)

All database queries use PDO prepared statements with named parameters:

```php
// ✅ SECURE - Using prepared statements
$result = $db->selectOne(
    "SELECT * FROM users WHERE email = :email",
    ['email' => $userInput]
);

// ❌ INSECURE - Direct string concatenation (NEVER DO THIS)
$result = $db->query("SELECT * FROM users WHERE email = '$userInput'");
```

### 2. Parameter Binding

The PDO driver automatically escapes and quotes all bound parameters, preventing them from being interpreted as SQL code.

## Test Categories

### 1. Database Layer Tests
Tests the `Database` class methods (`select`, `selectOne`, `execute`) to ensure they properly handle injection attempts:

- **Quote Injection**: `admin@test.com' OR '1'='1`
- **Union Injection**: `1 UNION SELECT password_hash FROM users--`
- **Comment Injection**: `TEST'--`
- **Statement Termination**: `'; DROP TABLE users; --`
- **Insert/Update/Delete Injection**: Malicious data in CUD operations

### 2. Model Layer Tests
Tests `UserModel`, `ProofModel`, and `AbsenceModel` to ensure business logic properly sanitizes inputs:

- User authentication bypass attempts
- Accessing unauthorized data
- Modifying multiple records
- Data leakage through error messages

### 3. Advanced Injection Patterns

#### Stacked Queries
Attempting to execute multiple SQL statements:
```sql
'; UPDATE users SET role = 'admin'; --
```

#### Boolean-Based Blind Injection
Using conditional logic to extract data:
```sql
' AND (SELECT COUNT(*) FROM users) > 0 --
```

#### Time-Based Blind Injection
Using database delays to infer information:
```sql
'; SELECT pg_sleep(5); --
```

#### Second-Order Injection
Storing malicious data that executes later:
1. Insert: `identifier = "SAFE'; DROP TABLE users;--"`
2. Later use of stored identifier triggers injection

#### Hex-Encoded Injection
Using hex encoding to bypass filters:
```sql
' OR email = 0x61646d696e@test.com --
```

### 4. Special Characters
Testing proper escaping of:
- Single quotes: `O'Brien`
- Double quotes: `"Test"`
- Backslashes: `Test\User`
- Control characters: `\n`, `\r`, `\t`, `\x00`

## Running the Tests

### Run All Security Tests
```bash
php vendor/bin/phpunit tests/Unit/Security/SQLInjectionTest.php
```

### Run Specific Test
```bash
php vendor/bin/phpunit --filter testSelectWithQuoteInjectionAttempt tests/Unit/Security/
```

### Run with Verbose Output
```bash
php vendor/bin/phpunit --testdox tests/Unit/Security/SQLInjectionTest.php
```

## Test Results Interpretation

### ✅ Test Passes
- **Null result**: Injection attempt returned no data (expected)
- **Literal storage**: Malicious string stored as literal data (safe)
- **Exception thrown**: Database rejected malicious query (acceptable)
- **Data integrity maintained**: Tables/data remain unchanged

### ❌ Test Fails
- **Authentication bypass**: Injection returns unauthorized data
- **Data modification**: Multiple records affected when only one expected
- **Table dropped**: Critical database structures destroyed
- **Data leakage**: Sensitive information exposed through injection

## Expected Behavior

All tests should **PASS**, demonstrating that:

1. ✅ Prepared statements prevent SQL code injection
2. ✅ Malicious strings are stored as literal data
3. ✅ Query constraints (WHERE clauses) are enforced
4. ✅ Database integrity is maintained
5. ✅ No data leakage occurs

## Common SQL Injection Patterns Tested

| Pattern | Example | Protection |
|---------|---------|------------|
| Quote escape | `' OR '1'='1` | Quoted by PDO |
| Comment injection | `admin'--` | Stored literally |
| UNION attack | `1 UNION SELECT password` | Parameter binding |
| Stacked queries | `'; DROP TABLE users` | Single statement execution |
| Boolean blind | `' AND 1=1--` | Parameter binding |
| Time-based blind | `'; SELECT pg_sleep(5)` | Parameter binding |
| Second-order | Store then execute | Consistent parameter binding |

## Security Best Practices

### ✅ DO:
- Always use prepared statements with parameter binding
- Use the `Database` class methods (`select`, `selectOne`, `execute`)
- Validate input data types (e.g., integers for IDs)
- Use whitelisting for enum values (status, role, etc.)
- Log suspicious activity

### ❌ DON'T:
- Never concatenate user input into SQL strings
- Don't trust any user input (including authenticated users)
- Don't use dynamic table/column names without validation
- Don't expose detailed error messages to users
- Don't rely solely on client-side validation

## Example: Secure Code Pattern

```php
// ✅ SECURE: Using prepared statements
public function getUserByEmail(string $email): ?array
{
    return $this->db->selectOne(
        "SELECT * FROM users WHERE email = :email",
        ['email' => $email]
    );
}

// ❌ INSECURE: String concatenation (NEVER DO THIS)
public function getUserByEmail(string $email): ?array
{
    return $this->db->query(
        "SELECT * FROM users WHERE email = '{$email}'"
    );
}
```

## Additional Security Layers

While prepared statements are the primary defense, consider:

1. **Input Validation**: Verify data types and formats
2. **Parameterized Stored Procedures**: Additional abstraction layer
3. **Least Privilege**: Database users with minimal permissions
4. **Web Application Firewall (WAF)**: Detect/block attack patterns
5. **Regular Security Audits**: Code reviews and penetration testing
6. **Security Headers**: X-Content-Type-Options, CSP, etc.

## Troubleshooting

### Tests Fail
1. Check database connection in `phpunit.xml`
2. Verify PDO is using prepared statements mode
3. Review recent database layer changes
4. Check for dynamic SQL construction

### Database Errors
1. Ensure test database is accessible
2. Verify schema is up to date
3. Check database user permissions
4. Review transaction handling

## Resources

- [OWASP SQL Injection](https://owasp.org/www-community/attacks/SQL_Injection)
- [PHP PDO Prepared Statements](https://www.php.net/manual/en/pdo.prepared-statements.php)
- [PostgreSQL Security](https://www.postgresql.org/docs/current/sql-syntax.html)

## Maintenance

- Run these tests on every commit
- Add new tests when adding database interactions
- Review failed tests immediately
- Update tests when changing database schema
- Keep test database isolated from production

## Contact

For security concerns, contact the development team immediately.

**Remember**: Security is everyone's responsibility. If you see something, say something!
