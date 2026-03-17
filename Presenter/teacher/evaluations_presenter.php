<?php

declare(strict_types=1);

// Manages the evaluations page for teachers
// Shows only evaluations (exams) with absent students (justified or not)
class TeacherEvaluationsPresenter
{
    private TeacherDataModel $teacherModel;
    private int $userId;
    private string $filter;

    public function __construct(int $id)
    {
        require_once __DIR__ . '/../../Model/TeacherDataModel.php';
        $this->teacherModel = new TeacherDataModel();
        $this->userId = $this->linkTeacherUser($id);
        $this->filter = 'course_slots.course_date'; // Default filter
    }

    // Enable a specific filter
    public function enableFilter(string $filter): void
    {
        $allowedFilters = ['course_slots.course_date', 'nb_justifications', 'nbabs'];
        if (in_array($filter, $allowedFilters)) {
            $this->filter = $filter;
        }
    }

    // Link the teacher ID with the connected user ID via email
    private function linkTeacherUser(int $id): int
    {
        return (int) ($this->teacherModel->getTeacherIdByUserId($id) ?? 0);
    }

    // Return evaluations for taught subjects (exams with absence counts, justified or not)
    public function getEvaluations(): array
    {
        return $this->teacherModel->getTeacherEvaluations($this->userId, $this->filter);
    }
}
