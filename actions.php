<?php
session_start();
require_once 'login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Добавление нового студента
    if ($action === 'add_student') {
        $stud_id = intval($_POST['stud_id']);
        $full_name = $_POST['full_name'];
        $admission_year = !empty($_POST['admission_year']) ? intval($_POST['admission_year']) : null;
        $birth_date = !empty($_POST['birth_date']) ? intval($_POST['birth_date']) : null;
        $gender = $_POST['gender'];
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $group_id = intval($_POST['group_id']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO students (stud_id, full_name, admission_year, birth_date, gender, adress, city, stud_phone, groups_group_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$stud_id, $full_name, $admission_year, $birth_date, $gender, $address, $city, $phone, $group_id]);
            
            header("Location: index.php?page=manage&success=added");
        } catch (PDOException $e) {
            header("Location: index.php?page=manage&error=" . urlencode($e->getMessage()));
        }
    }
    
    // Выставление оценки
    elseif ($action === 'add_grade') {
        $subject_id = intval($_POST['subject_id']);
        $student_id = intval($_POST['student_id']);
        $grade = intval($_POST['grade']);
        
        try {
            // Проверяем, существует ли уже оценка
            $check = $pdo->prepare("SELECT grade FROM grades WHERE subjects_sub_id = ? AND students_stud_id = ?");
            $check->execute([$subject_id, $student_id]);
            $existing = $check->fetch();
            
            if ($existing) {
                // Оценка существует - обновляем
                $stmt = $pdo->prepare("UPDATE grades SET grade = ? WHERE subjects_sub_id = ? AND students_stud_id = ?");
                $stmt->execute([$grade, $subject_id, $student_id]);
                $message = "Оценка обновлена с {$existing['grade']} на $grade";
            } else {
                // Оценки нет - вставляем новую
                $stmt = $pdo->prepare("INSERT INTO grades (subjects_sub_id, students_stud_id, grade) VALUES (?, ?, ?)");
                $stmt->execute([$subject_id, $student_id, $grade]);
                $message = "Оценка $grade успешно выставлена";
            }
            
            header("Location: index.php?page=add_grade&success=" . urlencode($message));
        } catch (PDOException $e) {
            header("Location: index.php?page=add_grade&error=" . urlencode($e->getMessage()));
        }
    }
    
    // Перевод студента
    elseif ($action === 'transfer') {
        $stud_id = intval($_POST['stud_id']);
        $new_group_id = intval($_POST['new_group_id']);
        
        try {
            $pdo->beginTransaction();
            
            $checkGroup = $pdo->prepare("SELECT group_id FROM `groups` WHERE group_id = ?");
            $checkGroup->execute([$new_group_id]);
            if (!$checkGroup->fetch()) {
                throw new Exception("Группа с ID $new_group_id не существует");
            }
            
            $checkStudent = $pdo->prepare("SELECT stud_id FROM students WHERE stud_id = ?");
            $checkStudent->execute([$stud_id]);
            if (!$checkStudent->fetch()) {
                throw new Exception("Студент с ID $stud_id не существует");
            }
            
            $stmt = $pdo->prepare("UPDATE students SET groups_group_id = ? WHERE stud_id = ?");
            $stmt->execute([$new_group_id, $stud_id]);
            
            $pdo->commit();
            header("Location: index.php?page=manage&success=transfer");
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: index.php?page=manage&error=" . urlencode($e->getMessage()));
        }
    }
    
    // Удаление через хранимую процедуру
    elseif ($action === 'delete_student_proc') {
        $stud_id = intval($_POST['stud_id']);
        
        try {
            $stmt = $pdo->prepare("CALL DeleteStudentCompletely(?)");
            $stmt->execute([$stud_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            header("Location: index.php?page=manage&success=deleted&message=" . urlencode($result['Результат'] ?? 'Студент удален'));
        } catch (PDOException $e) {
            header("Location: index.php?page=manage&error=" . urlencode($e->getMessage()));
        }
    }
    
    // Вход преподавателя
    elseif ($action === 'teacher_login') {
        $teacher_id = intval($_POST['teacher_id']);
        
        $check = $pdo->prepare("SELECT teach_id FROM teacher WHERE teach_id = ?");
        $check->execute([$teacher_id]);
        
        if ($check->fetch()) {
            $_SESSION['teacher_id'] = $teacher_id;
            header("Location: index.php?page=teacher_panel&success=logged_in");
        } else {
            header("Location: index.php?page=teacher_panel&error=Преподаватель с ID $teacher_id не найден");
        }
    }
}

// GET действия
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Выход преподавателя
    if ($action === 'teacher_logout') {
        unset($_SESSION['teacher_id']);
        header("Location: index.php?page=teacher_panel&success=logged_out");
    }
}

// Показываем сообщения об ошибках/успехе
if (isset($_GET['success']) || isset($_GET['error'])) {
    $page = $_GET['page'] ?? 'manage';
    echo "<script>";
    if (isset($_GET['success'])) {
        $msg = $_GET['success'] === 'added' ? 'Студент успешно добавлен!' : 
               ($_GET['success'] === 'transfer' ? 'Студент переведен!' :
               ($_GET['success'] === 'deleted' ? 'Студент удален!' : $_GET['success']));
        echo "alert('✅ " . addslashes($msg) . "');";
    }
    if (isset($_GET['error'])) {
        echo "alert('Ошибка: " . addslashes($_GET['error']) . "');";
    }
    echo "window.location.href = 'index.php?page=$page';";
    echo "</script>";
}

exit;
?>