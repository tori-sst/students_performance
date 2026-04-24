<?php
require_once 'login.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$subject_id = $data['subject_id'] ?? 0;
$student_id = $data['student_id'] ?? 0;

if ($subject_id && $student_id) {
    $stmt = $pdo->prepare("SELECT grade FROM grades WHERE subjects_sub_id = ? AND students_stud_id = ?");
    $stmt->execute([$subject_id, $student_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode(['exists' => true, 'grade' => $result['grade']]);
    } else {
        echo json_encode(['exists' => false]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>