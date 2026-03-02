<?php
// Presenter/shared/delete_student.php
// Deletes a student and all associated data from the database.

require_once __DIR__ . '/../../Model/UserModel.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));

if (!$isCli) {
    header('Content-Type: application/json');
}

// Obtain id from CLI args or HTTP params
$id = null;
if ($isCli) {
    // Parse CLI args: allow `php delete_student.php 123`, `php delete_student.php id=123`, or `php delete_student.php --id=123`
    global $argv;
    $opts = [];
    foreach ($argv as $idx => $arg) {
        if ($idx === 0)
            continue;
        if (strpos($arg, '--') === 0) {
            $arg = substr($arg, 2);
        }
        if (strpos($arg, '=') !== false) {
            [$k, $v] = explode('=', $arg, 2);
            $opts[$k] = $v;
        } elseif (is_numeric($arg) && !isset($opts['id'])) {
            $opts['id'] = $arg;
        }
    }
    $id = $opts['id'] ?? null;
}

// Validate id
if (empty($id) || !is_numeric($id)) {
    if ($isCli) {
        fwrite(STDERR, "Error: Missing or invalid id parameter\n");
        fwrite(STDERR, "Usage: php Presenter/shared/delete_student.php 123\n");
        fwrite(STDERR, "Or: php Presenter/shared/delete_student.php --id=123\n");
        exit(1);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid id parameter']);
        exit;
    }
}


$model = new UserModel();
$ok = $model->deleteStudentCascadeById((int) $id);

if ($ok) {
    if ($isCli) {
        echo "Student (id={$id}) and related data deleted successfully\n";
        exit(0);
    } else {
        echo json_encode(['success' => true, 'message' => 'Student and related data deleted successfully']);
    }
} else {
    if ($isCli) {
        fwrite(STDERR, "Failed to delete student (id={$id}); check logs for details\n");
        exit(2);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete student; check logs for details']);
    }
}

?>