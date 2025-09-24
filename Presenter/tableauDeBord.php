
<?php
require_once __DIR__ . '/../Model/database.php';
$db = getDatabase();
$query = "SELECT student_identifier,course_slot_id,status,justified FROM absences";
$t=$db->select($query);
echo json_encode($t);