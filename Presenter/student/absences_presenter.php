<?php

declare(strict_types=1);

/**
 * File: absences_presenter.php
 *
 * Student absences presenter – handles display and filtering of absences for a specific student.
 * Provides methods to:
 * - Filter absences (dates, status, course type)
 * - Retrieve absences with associated proofs
 * - Format data for display (statuses, reasons, dates)
 * - Manage proof status priority (accepted > justified > pending)
 * - Calculate total half-days of absence
 * - Translate absence reasons to French
 * Used by the student "My absences" page.
 */

require_once __DIR__ . '/../../Model/database.php';

class StudentAbsencesPresenter
{
    private array $filters;
    private string $errorMessage;
    private string $studentIdentifier;

    public function __construct(string $studentIdentifier)
    {
        $this->studentIdentifier = $studentIdentifier;
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    // Process request: extract and validate POST filters
    private function processRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

    // Validate and store filters: check date consistency
    private function validateAndSetFilters(): void
    {
        if (!empty($_POST['firstDateFilter']) && !empty($_POST['lastDateFilter'])) {
            if ($_POST['firstDateFilter'] > $_POST['lastDateFilter']) {
                $this->errorMessage = 'La première date doit être antérieure à la deuxième date.';
                return;
            }
        }

        $this->filters = [
            'start_date' => $_POST['firstDateFilter'] ?? '',
            'end_date' => $_POST['lastDateFilter'] ?? '',
            'status' => $_POST['statusFilter'] ?? '',
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }

    public function getStudentIdentifier(mixed $studentIdOrIdentifier): string
    {
        if (!is_numeric($studentIdOrIdentifier)) {
            return $studentIdOrIdentifier;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT identifier, first_name, last_name FROM users WHERE id = :id');
        $stmt->execute([':id' => $studentIdOrIdentifier]);
        $result = $stmt->fetch();

        if ($result) {
            $_SESSION['first_name'] = $result['first_name'];
            $_SESSION['last_name'] = $result['last_name'];
            return $result['identifier'];
        }

        throw new Exception('Student not found');
    }

    public function getAbsences(): array
    {
        $db = Database::getInstance()->getConnection();

        $query = "
            SELECT 
                a.id as absence_id,
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.duration_minutes,
                cs.course_type,
                cs.is_evaluation,
                a.justified,
                r.code as course_code,
                r.label as course_name,
                t.first_name as teacher_first_name,
                t.last_name as teacher_last_name,
                rm.code as room_name,
                p.id as proof_id,
                p.main_reason as motif,
                p.custom_reason as custom_motif,
                p.file_path as file_path,
                p.status as proof_status,
                p.manager_comment,
                m.id as makeup_id,
                m.scheduled as makeup_scheduled,
                m.makeup_date as makeup_date,
                m.comment as makeup_comment,
                m.duration_minutes as makeup_duration,
                makeup_rm.code as makeup_room,
                makeup_cs.start_time as makeup_start_time,
                makeup_cs.end_time as makeup_end_time,
                makeup_r.label as makeup_resource_label
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            LEFT JOIN makeups m ON a.id = m.absence_id
            LEFT JOIN rooms makeup_rm ON m.room_id = makeup_rm.id
            LEFT JOIN course_slots makeup_cs ON m.evaluation_slot_id = makeup_cs.id
            LEFT JOIN resources makeup_r ON makeup_cs.resource_id = makeup_r.id
            WHERE a.student_identifier = :student_id
        ";

        $params = [':student_id' => $this->studentIdentifier];

        if (!empty($this->filters['start_date'])) {
            $query .= " AND cs.course_date >= :start_date";
            $params[':start_date'] = $this->filters['start_date'];
        }

        if (!empty($this->filters['end_date'])) {
            $query .= " AND cs.course_date <= :end_date";
            $params[':end_date'] = $this->filters['end_date'];
        }

        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'justifiée') {
                $query .= " AND p.status = 'accepted'";
            } elseif ($this->filters['status'] === 'en_attente') {
                $query .= " AND p.status = 'pending'";
            } elseif ($this->filters['status'] === 'en_revision') {
                $query .= " AND p.status = 'under_review'";
            } elseif ($this->filters['status'] === 'refusé') {
                $query .= " AND p.status = 'rejected'";
            } elseif ($this->filters['status'] === 'non_justifiée') {
                $query .= " AND (p.id IS NULL OR p.status IS NULL)";
            }
        }

        if (!empty($this->filters['course_type'])) {
            $query .= " AND cs.course_type = :course_type";
            $params[':course_type'] = $this->filters['course_type'];
        }

        $query .= " ORDER BY cs.course_date DESC, cs.start_time DESC";

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sort results by date and time descending (most recent first)
            usort($results, function ($a, $b) {
                $dateCompare = strtotime($b['course_date']) - strtotime($a['course_date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcmp($b['start_time'], $a['start_time']);
            });

            return $results;
        } catch (Exception $e) {
            error_log('Error retrieving absences: ' . $e->getMessage());
            return [];
        }
    }

    public function getCourseTypes(): array
    {
        return [
            ['course_type' => 'CM'],
            ['course_type' => 'TD'],
            ['course_type' => 'TP']
        ];
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function translateReason(?string $reason, ?string $customReason = null): string
    {
        if (!$reason) {
            return '';
        }

        $translations = [
            'illness' => 'Maladie',
            'death' => 'Décès',
            'family_obligations' => 'Obligations familiales',
            'official_summons' => 'Convocation officielle',
            'transport_issue' => 'Problème de transport',
            'rdv_medical' => 'Rendez-vous médical',
            'other' => $customReason ? htmlspecialchars($customReason) : 'Autre'
        ];

        return $translations[$reason] ?? htmlspecialchars($reason);
    }

    public function translateStatus(bool $justified): string
    {
        return $justified ? 'Justifiée' : 'Non justifiée';
    }

    public function hasProof(array $absence): bool
    {
        return !empty($absence['proof_status']) &&
            $absence['proof_status'] === 'accepted' &&
            !empty($absence['file_path']);
    }

    public function getProofStatus(array $absence): array
    {
        $proofStatus = $absence['proof_status'] ?? null;

        if ($proofStatus === 'accepted') {
            return ['text' => 'Justifiée', 'class' => 'badge-success', 'icon' => '✅'];
        } elseif ($proofStatus === 'under_review') {
            return ['text' => 'En révision', 'class' => 'badge-warning', 'icon' => '⚠️'];
        } elseif ($proofStatus === 'pending') {
            return ['text' => 'En attente', 'class' => 'badge-info', 'icon' => '🕐'];
        } elseif ($proofStatus === 'rejected') {
            return ['text' => 'Rejeté', 'class' => 'badge-rejected', 'icon' => '🚫'];
        } else {
            return ['text' => 'Non justifiée', 'class' => 'badge-danger', 'icon' => '❌'];
        }
    }

    public function getProofPath(array $absence): string
    {
        if ($this->hasProof($absence) && isset($absence['file_path'])) {
            return '../../' . ($absence['file_path'] ?? '');
        }
        return '';
    }

    public function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatTime(string $startTime, string $endTime): string
    {
        return substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5);
    }

    public function getTotalHalfDays(array $absences): int
    {
        $halfDays = [];

        foreach ($absences as $absence) {
            $date = $absence['course_date'];
            $startTime = $absence['start_time'];

            // Determine period (morning if < 12:30, otherwise afternoon)
            $period = (strtotime($startTime) < strtotime('12:30:00')) ? 'morning' : 'afternoon';

            $key = $date . '_' . $period;
            $halfDays[$key] = true;
        }

        return count($halfDays);
    }
}
