<?php

declare(strict_types=1);

// Manages the absence details page for teachers
// Shows absence details (justified or not) for a specific evaluation
// Used after clicking on an evaluation on the evaluations page
class AbsenceDetailsPresenter
{
    private TeacherDataModel $teacherModel;
    private int $courseSlotId;

    public function __construct(int $courseId)
    {
        require_once __DIR__ . '/../../Model/TeacherDataModel.php';
        $this->teacherModel = new TeacherDataModel();
        $this->courseSlotId = $courseId;
    }

    public function getCourseId(): int
    {
        return $this->courseSlotId;
    }

    // Retrieve details of the specific evaluation (subject, date, time)
    public function getAbsenceDetails(): array
    {
        return $this->teacherModel->getCourseSlotSummary($this->getCourseId()) ?? [];
    }
    // Retrieve the list of absences (justified or not) for the specific evaluation
    public function getAbsences(): array
    {
        return $this->teacherModel->getCourseSlotAbsences($this->getCourseId());
    }
}
