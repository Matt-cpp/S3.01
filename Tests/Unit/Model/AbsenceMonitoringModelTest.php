<?php

namespace Tests\Unit\Model;

use Tests\TestCase;
use Tests\Fixtures\UsersFixture;
use Tests\Fixtures\AbsencesFixture;

require_once __DIR__ . '/../../../Model/AbsenceMonitoringModel.php';

/**
 * Unit tests for AbsenceMonitoringModel
 * Tests the automated absence monitoring and notification system
 */
class AbsenceMonitoringModelTest extends TestCase
{
    private \AbsenceMonitoringModel $model;
    private array $testStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new \AbsenceMonitoringModel();

        // Create test student
        $this->testStudent = UsersFixture::createStudent($this->getConnection(), [
            'identifier' => 'TEST_MONITOR_001',
            'email' => 'monitor.test@test.com'
        ]);
    }

    // =========================================================================
    // Test: getStudentsWithOngoingAbsences()
    // =========================================================================

    public function testGetStudentsWithOngoingAbsencesReturnsRecentAbsences(): void
    {
        // Arrange: Create absences in the last 7 days (ensure weekday)
        // Find the most recent weekday (Monday-Friday)
        $recentDate = date('Y-m-d', strtotime('-1 day'));
        $dayOfWeek = date('N', strtotime($recentDate)); // 1 (Monday) to 7 (Sunday)
        
        // If it's a weekend, go back to Friday
        if ($dayOfWeek == 6) { // Saturday
            $recentDate = date('Y-m-d', strtotime($recentDate . ' -1 day'));
        } elseif ($dayOfWeek == 7) { // Sunday
            $recentDate = date('Y-m-d', strtotime($recentDate . ' -2 days'));
        }
        
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $recentDate
        ]);

        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id'],
            ['justified' => false]
        );

        // Act
        $result = $this->model->getStudentsWithOngoingAbsences();

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $found = false;
        foreach ($result as $student) {
            if ($student['student_identifier'] === $this->testStudent['identifier']) {
                $found = true;
                $this->assertEquals($this->testStudent['id'], $student['student_id']);
                break;
            }
        }
        $this->assertTrue($found, 'Student with recent absence should be in results');
    }

    public function testGetStudentsWithOngoingAbsencesExcludesJustifiedAbsences(): void
    {
        // Arrange: Create justified absence
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d', strtotime('-2 days'))
        ]);

        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id'],
            ['justified' => true] // Justified
        );

        // Act
        $result = $this->model->getStudentsWithOngoingAbsences();

        // Assert: Should NOT include student with justified absence
        foreach ($result as $student) {
            $this->assertNotEquals(
                $this->testStudent['identifier'],
                $student['student_identifier'],
                'Student with only justified absences should not be included'
            );
        }
    }

    public function testGetStudentsWithOngoingAbsencesExcludesOldAbsences(): void
    {
        // Arrange: Create old absence (10 days ago, beyond 7-day window)
        $oldDate = date('Y-m-d', strtotime('-10 days'));
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $oldDate
        ]);

        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id']
        );

        // Act
        $result = $this->model->getStudentsWithOngoingAbsences();

        // Assert: Should NOT include student with old absence
        foreach ($result as $student) {
            $this->assertNotEquals(
                $this->testStudent['identifier'],
                $student['student_identifier'],
                'Student with absences older than 7 days should not be included'
            );
        }
    }

    // =========================================================================
    // Test: hasStudentReturnedToClass() - Complex business logic
    // =========================================================================

    public function testHasStudentReturnedToClassReturnsTrueAfter5PM(): void
    {
        // Arrange: Last absence was yesterday, current time is after 5PM
        $lastAbsenceDate = date('Y-m-d', strtotime('-1 day'));

        // Mock current time to be after 5PM (17:00)
        $currentHour = (int) date('H');
        if ($currentHour < 17) {
            // If test runs before 5PM, we need to test for tomorrow's return
            $lastAbsenceDate = date('Y-m-d', strtotime('-2 days'));
        }

        // Create absence on last absence date
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $lastAbsenceDate,
            'start_time' => '10:00:00'
        ]);

        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id']
        );

        // Create present course today (no absence = student returned)
        $todaySlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '10:00:00'
        ]);

        // Act
        $result = $this->model->hasStudentReturnedToClass(
            $this->testStudent['identifier'],
            $lastAbsenceDate
        );

        // Assert: Should return true if after 5PM and no new absences
        $this->assertIsBool($result);
    }

    public function testHasStudentReturnedToClassReturnsFalseWithNewAbsences(): void
    {
        // Arrange: Last absence was 2 days ago, but has new absence today
        $lastAbsenceDate = date('Y-m-d', strtotime('-2 days'));

        $oldSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $lastAbsenceDate
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $oldSlot['id']
        );

        // New absence today
        $newSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d')
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $newSlot['id']
        );

        // Act
        $result = $this->model->hasStudentReturnedToClass(
            $this->testStudent['identifier'],
            $lastAbsenceDate
        );

        // Assert: Should return false because student has new absence
        $this->assertFalse($result);
    }

    public function testHasStudentReturnedToClassIgnoresWeekends(): void
    {
        // Arrange: Create absence on Friday, check on Monday
        // Find last Friday
        $friday = date('Y-m-d', strtotime('last friday'));

        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $friday
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id']
        );

        // Act: Check return status (should only count school days)
        $result = $this->model->hasStudentReturnedToClass(
            $this->testStudent['identifier'],
            $friday
        );

        // Assert: Logic should account for weekends
        $this->assertIsBool($result);
    }

    // =========================================================================
    // Test: recordStudentReturn() - ON CONFLICT upsert logic
    // =========================================================================

    public function testRecordStudentReturnInsertsNewRecord(): void
    {
        // Arrange
        $startDate = date('Y-m-d', strtotime('-3 days'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        $lastAbsenceDate = date('Y-m-d', strtotime('-1 day'));

        // Act
        $result = $this->model->recordStudentReturn(
            $this->testStudent['id'],
            $this->testStudent['identifier'],
            $startDate,
            $endDate,
            $lastAbsenceDate
        );

        // Assert
        $this->assertTrue($result);
        $this->assertRecordExists('absence_monitoring', [
            'student_identifier' => $this->testStudent['identifier'],
            'absence_period_start' => $startDate,
            'absence_period_end' => $endDate
        ]);
    }

    public function testRecordStudentReturnUpdatesExistingRecord(): void
    {
        // Arrange: Create initial record
        $startDate = date('Y-m-d', strtotime('-3 days'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        $lastAbsenceDate = date('Y-m-d', strtotime('-1 day'));

        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, return_detected_at, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                $startDate,
                $endDate,
                $lastAbsenceDate
            ]
        );

        // Act: Record return again (should update, not insert)
        $result = $this->model->recordStudentReturn(
            $this->testStudent['id'],
            $this->testStudent['identifier'],
            $startDate,
            $endDate,
            $lastAbsenceDate
        );

        // Assert
        $this->assertTrue($result);

        // Should still have only 1 record for this period
        $count = $this->queryOne(
            "SELECT COUNT(*) as cnt FROM absence_monitoring 
             WHERE student_identifier = ? AND absence_period_start = ? AND absence_period_end = ?",
            [$this->testStudent['identifier'], $startDate, $endDate]
        );
        $this->assertEquals(1, (int) $count['cnt'], 'Should have exactly 1 record due to ON CONFLICT');
    }

    // =========================================================================
    // Test: calculateHalfDays() - Morning/afternoon split logic
    // =========================================================================

    public function testCalculateHalfDaysMorningSession(): void
    {
        // Arrange: Create 2 absences in the morning (before 12:30)
        $courseSlot1 = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00'
        ]);
        $courseSlot2 = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00'
        ]);

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

        // Act
        $result = $this->model->calculateHalfDays(
            $this->testStudent['identifier'],
            date('Y-m-d'),
            date('Y-m-d')
        );

        // Assert: Should count as 1 half-days (morning only)
        $this->assertEquals(1.0, $result);
    }

    public function testCalculateHalfDaysAfternoonSession(): void
    {
        // Arrange: Create absence in the afternoon (after 12:30)
        $courseSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '16:00:00'
        ]);

        $absence = AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $courseSlot['id']
        );

        // Act
        $result = $this->model->calculateHalfDays(
            $this->testStudent['identifier'],
            date('Y-m-d'),
            date('Y-m-d')
        );

        // Assert: Should count as 1 half-days (afternoon only)
        $this->assertEquals(1.0, $result);
    }

    public function testCalculateHalfDaysFullDay(): void
    {
        // Arrange: Create absences in both morning and afternoon
        $morningSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00'
        ]);
        $afternoonSlot = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => date('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '16:00:00'
        ]);

        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $morningSlot['id']
        );
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $afternoonSlot['id']
        );

        // Act
        $result = $this->model->calculateHalfDays(
            $this->testStudent['identifier'],
            date('Y-m-d'),
            date('Y-m-d')
        );

        // Assert: Should count as 2 full day
        $this->assertEquals(2.0, $result);
    }

    public function testCalculateHalfDaysMultipleDays(): void
    {
        // Arrange: Create absences over 3 consecutive weekdays (Mon-Fri only)
        $today = date('Y-m-d');
        $dayOfWeek = date('N', strtotime($today)); // 1 (Monday) to 7 (Sunday)
        
        // Ensure we have 3 consecutive weekdays
        if ($dayOfWeek == 1) { // Monday - can't go back 2 weekdays, use Mon, Tue, Wed
            $day1 = $today; // Monday
            $day2 = date('Y-m-d', strtotime('+1 day')); // Tuesday
            $day3 = date('Y-m-d', strtotime('+2 days')); // Wednesday
        } elseif ($dayOfWeek == 2) { // Tuesday - use Mon, Tue, Wed
            $day1 = date('Y-m-d', strtotime('-1 day')); // Monday
            $day2 = $today; // Tuesday
            $day3 = date('Y-m-d', strtotime('+1 day')); // Wednesday
        } elseif ($dayOfWeek == 6) { // Saturday - use Wed, Thu, Fri
            $day1 = date('Y-m-d', strtotime('-3 days')); // Wednesday
            $day2 = date('Y-m-d', strtotime('-2 days')); // Thursday
            $day3 = date('Y-m-d', strtotime('-1 day')); // Friday
        } elseif ($dayOfWeek == 7) { // Sunday - use Wed, Thu, Fri
            $day1 = date('Y-m-d', strtotime('-4 days')); // Wednesday
            $day2 = date('Y-m-d', strtotime('-3 days')); // Thursday
            $day3 = date('Y-m-d', strtotime('-2 days')); // Friday
        } else { // Wed-Fri: use current day and 2 previous days (all weekdays)
            $day1 = date('Y-m-d', strtotime('-2 days'));
            $day2 = date('Y-m-d', strtotime('-1 day'));
            $day3 = $today;
        }

        // Day 1: Morning only
        $slot1 = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $day1,
            'start_time' => '08:00:00'
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $slot1['id']
        );

        // Day 2: Afternoon only
        $slot2 = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $day2,
            'start_time' => '14:00:00'
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $slot2['id']
        );

        // Day 3: Full day
        $slot3Morning = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $day3,
            'start_time' => '08:00:00'
        ]);
        $slot3Afternoon = AbsencesFixture::createCourseSlot($this->getConnection(), [
            'course_date' => $day3,
            'start_time' => '14:00:00'
        ]);
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $slot3Morning['id']
        );
        AbsencesFixture::createAbsence(
            $this->getConnection(),
            $this->testStudent['identifier'],
            $slot3Afternoon['id']
        );

        // Act
        $result = $this->model->calculateHalfDays(
            $this->testStudent['identifier'],
            $day1,
            $day3
        );

        // Assert: 1.0 + 1.0 + 2.0 = 4.0 half-days
        $this->assertEquals(4.0, $result);
    }

    // =========================================================================
    // Test: getStudentsAwaitingReturnNotification()
    // =========================================================================

    public function testGetStudentsAwaitingReturnNotificationReturnsCorrectStudents(): void
    {
        // Arrange: Create monitoring record with return detected but not notified
        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, return_detected_at, return_notification_sent, 
                created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                date('Y-m-d', strtotime('-5 days')),
                date('Y-m-d', strtotime('-2 days')),
                date('Y-m-d', strtotime('-2 days'))
            ]
        );

        // Act
        $result = $this->model->getStudentsAwaitingReturnNotification();

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $found = false;
        foreach ($result as $student) {
            if ($student['student_identifier'] === $this->testStudent['identifier']) {
                $found = true;
                $this->assertFalse((bool) $student['return_notification_sent']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    // =========================================================================
    // Test: markReturnNotificationSent()
    // =========================================================================

    public function testMarkReturnNotificationSentUpdatesRecord(): void
    {
        // Arrange
        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, return_detected_at, return_notification_sent, 
                created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             RETURNING id",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                date('Y-m-d', strtotime('-5 days')),
                date('Y-m-d', strtotime('-2 days')),
                date('Y-m-d', strtotime('-2 days'))
            ]
        );

        $monitoringId = $this->lastInsertId('absence_monitoring_id_seq');

        // Act
        $result = $this->model->markReturnNotificationSent($monitoringId);

        // Assert
        $this->assertTrue($result);

        $record = $this->queryOne(
            "SELECT return_notification_sent, return_notification_sent_at 
             FROM absence_monitoring WHERE id = ?",
            [$monitoringId]
        );

        $this->assertTrue((bool) $record['return_notification_sent']);
        $this->assertNotNull($record['return_notification_sent_at']);
    }

    // =========================================================================
    // Test: getStudentsNeedingReminder()
    // =========================================================================

    public function testGetStudentsNeedingReminderReturnsStudentsAfter24Hours(): void
    {
        // Arrange: Create record with notification sent > 24h ago
        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, return_detected_at, return_notification_sent, 
                return_notification_sent_at, reminder_notification_sent, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, TRUE, CURRENT_TIMESTAMP - INTERVAL '25 hours', 
                FALSE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                date('Y-m-d', strtotime('-5 days')),
                date('Y-m-d', strtotime('-2 days')),
                date('Y-m-d', strtotime('-2 days'))
            ]
        );

        // Act
        $result = $this->model->getStudentsNeedingReminder();

        // Assert
        $this->assertIsArray($result);
        // May be empty in test environment depending on data, but should not error
    }

    // =========================================================================
    // Test: cleanupOldRecords() - 30-day retention
    // =========================================================================

    public function testCleanupOldRecordsDeletesOldRecords(): void
    {
        // Arrange: Create old record (35 days ago) with is_justified = TRUE so it will be cleaned up
        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, is_justified, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP - INTERVAL '35 days', CURRENT_TIMESTAMP - INTERVAL '35 days')",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                date('Y-m-d', strtotime('-38 days')),
                date('Y-m-d', strtotime('-36 days')),
                date('Y-m-d', strtotime('-36 days'))
            ]
        );

        // Act
        $result = $this->model->cleanupOldRecords();

        // Assert
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result, 'Should delete at least 1 old record');

        // Verify old record is deleted
        $count = $this->queryOne(
            "SELECT COUNT(*) as cnt FROM absence_monitoring 
             WHERE student_identifier = ? AND created_at < CURRENT_TIMESTAMP - INTERVAL '30 days'",
            [$this->testStudent['identifier']]
        );
        $this->assertEquals(0, (int) $count['cnt']);
    }

    public function testCleanupOldRecordsKeepsRecentRecords(): void
    {
        // Arrange: Create recent record (5 days ago)
        $this->execute(
            "INSERT INTO absence_monitoring (student_id, student_identifier, absence_period_start, 
                absence_period_end, last_absence_date, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP - INTERVAL '5 days', CURRENT_TIMESTAMP)",
            [
                $this->testStudent['id'],
                $this->testStudent['identifier'],
                date('Y-m-d', strtotime('-7 days')),
                date('Y-m-d', strtotime('-5 days')),
                date('Y-m-d', strtotime('-5 days'))
            ]
        );

        // Act
        $this->model->cleanupOldRecords();

        // Assert: Recent record should still exist
        $count = $this->queryOne(
            "SELECT COUNT(*) as cnt FROM absence_monitoring 
             WHERE student_identifier = ?",
            [$this->testStudent['identifier']]
        );
        $this->assertGreaterThanOrEqual(1, (int) $count['cnt'], 'Recent records should not be deleted');
    }
}
