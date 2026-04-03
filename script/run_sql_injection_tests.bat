@echo off
REM SQL Injection Test Runner
REM This script runs the SQL injection security tests

echo ================================================
echo   SQL Injection Security Tests
echo ================================================
echo.

REM Check if composer dependencies are installed
if not exist "vendor\phpunit\phpunit" (
    echo [ERROR] PHPUnit not found. Installing dependencies...
    echo.
    composer install --dev
    echo.
)

REM Run the SQL injection tests
echo Running SQL injection tests...
echo.

php vendor\bin\phpunit tests\Unit\Security\SQLInjectionTest.php --testdox --colors=always

echo.
echo ================================================
echo   Test run completed!
echo ================================================
echo.
echo To run a specific test:
echo   php vendor\bin\phpunit --filter testSelectWithQuoteInjectionAttempt tests\Unit\Security\
echo.
echo To see detailed output:
echo   php vendor\bin\phpunit tests\Unit\Security\SQLInjectionTest.php -v
echo.
pause
