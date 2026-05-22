<?php
require_once 'login.php';
require_once 'admin_mode.php';

session_start();

// Обработка админ-действий
if (isset($_GET['admin_action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['admin_action']) {
        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(['success' => adminLogin($data['password'])]);
            exit;
            
        case 'logout':
            adminLogout();
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
            
        case 'update_cell':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = updateAnyTableCell($pdo, $data['table'], $data['id'], $data['column'], $data['value']);
            echo json_encode($result);
            exit;
            
        case 'delete_record':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = deleteRecord($pdo, $data['table'], $data['id'], $data['id_column']);
            echo json_encode($result);
            exit;
            
        case 'add_record':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = addRecord($pdo, $data['table'], $data['data']);
            echo json_encode($result);
            exit;
            
        case 'get_student':
            $stud_id = $_GET['id'] ?? 0;
            $result = getStudentData($pdo, $stud_id);
            echo json_encode($result ?: ['error' => 'Студент не найден']);
            exit;
            
        case 'update_student':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = updateStudent($pdo, $data['stud_id'], $data);
            echo json_encode($result);
            exit;
            
        case 'add_student':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = addStudent($pdo, $data);
            echo json_encode($result);
            exit;
            
        case 'delete_student':
            $stud_id = $_GET['id'] ?? 0;
            $result = deleteStudent($pdo, $stud_id);
            echo json_encode($result);
            exit;
            
        case 'get_student_stats':
            $stud_id = $_GET['id'] ?? 0;
            $result = getStudentStats($pdo, $stud_id);
            echo json_encode($result);
            exit;
            
        case 'get_departments':
            $stmt = $pdo->query("SELECT dep_id as id, dep_name as name FROM departments ORDER BY dep_name");
            echo json_encode($stmt->fetchAll());
            exit;
            
        case 'get_faculties':
            $stmt = $pdo->query("SELECT fac_id as id, fac_name as name FROM faculties ORDER BY fac_name");
            echo json_encode($stmt->fetchAll());
            exit;
    }
}

$page = $_GET['page'] ?? 'dashboard';
$title = "Главная";
$query = "";
$student_info = null;
$search = $_GET['search'] ?? '';
$teacher_id = $_SESSION['teacher_id'] ?? null;
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Функции для админ-панели
function getTableData($pdo, $table, $orderBy = '') {
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher'];
    if (!in_array($table, $allowedTables)) return [];
    
    $orderClause = $orderBy ? "ORDER BY $orderBy" : "";
    try {
        $stmt = $pdo->query("SELECT * FROM `$table` $orderClause");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getTableColumns($pdo, $table) {
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher'];
    if (!in_array($table, $allowedTables)) return [];
    
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllStudentsWithDetails($pdo, $search = '') {
    $where = $search ? " WHERE s.full_name LIKE '%$search%' OR s.stud_id LIKE '%$search%' OR g.group_name LIKE '%$search%'" : "";
    $sql = "SELECT 
                s.stud_id AS 'ID',
                s.full_name AS 'ФИО',
                s.stud_phone AS 'Телефон',
                s.birth_date AS 'Год рождения',
                s.admission_year AS 'Год поступления',
                g.group_name AS 'Группа',
                g.course AS 'Курс',
                d.dep_name AS 'Кафедра',
                s.groups_group_id as group_id
            FROM students s 
            JOIN `groups` g ON s.groups_group_id = g.group_id 
            JOIN departments d ON g.departments_dep_id = d.dep_id 
            $where 
            ORDER BY s.full_name";
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

$adminSection = $_GET['admin_section'] ?? 'faculties';

switch ($page) {
    case 'students':
        $type = $_GET['search_type'] ?? null; 
        $keyword = $_GET['keyword'] ?? null;
        $fac_id = $_GET['fac_id'] ?? null;
        $dep_id = $_GET['dep_id'] ?? null;
        $group_id = $_GET['group_id'] ?? null;
        $global_search = $_GET['global_search'] ?? '';

        if ($global_search && !$fac_id && !$dep_id && !$group_id && !$type) {
            $title = "Результаты глобального поиска: \"$global_search\"";
            $query = "
                SELECT 'faculty' as type, fac_id as id, fac_name as name, '' as extra FROM faculties WHERE fac_name LIKE '%$global_search%'
                UNION ALL
                SELECT 'department' as type, dep_id as id, dep_name as name, '' as extra FROM departments WHERE dep_name LIKE '%$global_search%'
                UNION ALL
                SELECT 'group' as type, group_id as id, group_name as name, CONCAT(course, ' курс') as extra FROM `groups` WHERE group_name LIKE '%$global_search%'
                UNION ALL
                SELECT 'student' as type, stud_id as id, full_name as name, stud_phone as extra FROM students WHERE full_name LIKE '%$global_search%' OR stud_phone LIKE '%$global_search%'
                UNION ALL
                SELECT 'teacher' as type, teach_id as id, teach_name as name, '' as extra FROM teacher WHERE teach_name LIKE '%$global_search%'
                ORDER BY name
            ";
        } elseif ($group_id) {
            $title = "Состав группы";
            $where = $global_search ? " WHERE full_name LIKE '%$global_search%'" : "";
            $query = "SELECT stud_id AS 'ID', full_name AS 'ФИО', gender AS 'Пол', admission_year AS 'Год поступления' FROM students WHERE groups_group_id = " . intval($group_id) . $where;
        } elseif ($dep_id) {
            $title = "Группы кафедры";
            $where = $global_search ? " WHERE group_name LIKE '%$global_search%'" : "";
            $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа', course AS 'Курс' FROM `groups` WHERE departments_dep_id = " . intval($dep_id) . $where;
        } elseif ($fac_id) {
            $title = "Кафедры факультета";
            $where = $global_search ? " WHERE dep_name LIKE '%$global_search%'" : "";
            $query = "SELECT dep_id AS 'ID_D', dep_name AS 'Кафедра' FROM departments WHERE faculties_fac_id = " . intval($fac_id) . $where;
        } else {
            if ($type === 'fac') {
                $title = "Поиск факультета";
                $where = $keyword ? " WHERE fac_name LIKE '%$keyword%'" : "";
                $query = "SELECT fac_id AS 'ID_F', fac_name AS 'Название факультета' FROM faculties $where";
            } elseif ($type === 'dep') {
                $title = "Поиск кафедры";
                $where = $keyword ? " WHERE dep_name LIKE '%$keyword%'" : "";
                $query = "SELECT dep_id AS 'ID_D', dep_name AS 'Название кафедры' FROM departments $where";
            } elseif ($type === 'group') {
                $title = "Поиск группы";
                $where = $keyword ? " WHERE group_name LIKE '%$keyword%'" : "";
                $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа', course AS 'Курс' FROM `groups` $where";
            } else {
                $title = "Реестр: Выберите категорию";
                $query = ""; 
            }
        }
        break;
        
    case 'all_students':
        $title = "Полный список студентов";
        $studentsData = getAllStudentsWithDetails($pdo, $search);
        break;

    case 'manage':
        $title = "Управление студентами";
        $studentsList = $pdo->query("
            SELECT s.stud_id, s.full_name, g.group_name, g.group_id 
            FROM students s 
            JOIN `groups` g ON s.groups_group_id = g.group_id 
            ORDER BY s.full_name
        ")->fetchAll();
        break;

    case 'archive':
        $title = "Архив выпускников";
        try {
            if ($pdo->query("SHOW TABLES LIKE 'graduated_students'")->rowCount() > 0 && $pdo->query("SHOW TABLES LIKE 'graduated_groups'")->rowCount() > 0) {
                $where = $search ? " WHERE s.full_name LIKE '%$search%' OR g.group_name LIKE '%$search%'" : "";
                $query = "SELECT s.stud_id AS 'ID студента', s.full_name AS 'ФИО', s.stud_phone AS 'Телефон', s.birth_date AS 'Год рождения', s.admission_year AS 'Год поступления', g.group_name AS 'Группа', s.graduation_year AS 'Год выпуска' FROM graduated_students s JOIN graduated_groups g ON s.groups_group_id = g.group_id $where ORDER BY s.graduation_year DESC, s.full_name";
            } else { $query = null; }
        } catch (PDOException $e) { $query = null; }
        break;

    case 'student_info':
        $title = "Поиск зачетной книжки";
        $student_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        if ($student_id) {
            $info_stmt = $pdo->prepare("SELECT s.full_name, g.group_name FROM students s JOIN `groups` g ON s.groups_group_id = g.group_id WHERE s.stud_id = ?");
            $info_stmt->execute([$student_id]);
            $student_info = $info_stmt->fetch();
            if ($student_info) {
                $title = "Зачетка: " . $student_info['full_name'];
                $query = "SELECT sub.sub_name AS 'Дисциплина', g.grade AS 'Оценка', t.teach_name AS 'Преподаватель' FROM grades g JOIN subjects sub ON g.subjects_sub_id = sub.sub_id JOIN teacher t ON sub.teacher_teach_id = t.teach_id WHERE g.students_stud_id = $student_id ORDER BY sub.sub_name";
            }
        }
        break;

    case 'debtors':
        $title = "Студенты-должники (имеют оценку 2)";
        $where = $search ? " AND s.full_name LIKE '%$search%'" : "";
        $query = "SELECT DISTINCT s.stud_id AS 'ID', s.full_name AS 'ФИО', g.group_name AS 'Группа' FROM students s JOIN grades gr ON s.stud_id = gr.students_stud_id JOIN `groups` g ON s.groups_group_id = g.group_id WHERE gr.grade = 2 $where ORDER BY s.full_name";
        break;

    case 'retake':
        $title = "Пересдачи (студенты с оценкой 2)";
        $where = $search ? " AND (s.full_name LIKE '%$search%' OR sub.sub_name LIKE '%$search%' OR t.teach_name LIKE '%$search%')" : "";
        $query = "SELECT s.full_name AS 'Студент', sub.sub_name AS 'Дисциплина', t.teach_name AS 'Преподаватель', gr.grade AS 'Оценка', COALESCE(r.reason, 'Низкий балл') AS 'Причина пересдачи' FROM grades gr JOIN students s ON gr.students_stud_id = s.stud_id JOIN subjects sub ON gr.subjects_sub_id = sub.sub_id JOIN teacher t ON sub.teacher_teach_id = t.teach_id LEFT JOIN reexams r ON r.stud_id = s.stud_id AND r.sub_id = sub.sub_id WHERE gr.grade = 2 $where ORDER BY s.full_name";
        break;

    case 'top':
        $title = "Рейтинг лучших студентов (средний балл >= 4.5)";
        $where = $search ? " WHERE `ФИО студента` LIKE '%$search%'" : "";
        $query = "SELECT `Инд. номер студента` AS 'ID', `ФИО студента` AS 'Студент', `Средний балл студента` AS 'Средний балл' FROM top_students $where ORDER BY `Средний балл студента` DESC";
        break;

    case 'scholarship':
        $title = "Расчет стипендии";
        $where = $search ? " HAVING `Студент` LIKE '%$search%'" : "";
        $query = "SELECT s.full_name AS 'Студент', CASE WHEN MIN(g.grade) = 5 THEN 1500 WHEN MIN(g.grade) <= 3 THEN 0 ELSE 1000 END AS 'Стипендия (руб.)', ROUND(AVG(g.grade), 2) AS 'Средний балл' FROM students s JOIN grades g ON s.stud_id = g.students_stud_id GROUP BY s.stud_id $where ORDER BY `Стипендия (руб.)` DESC, `Средний балл` DESC";
        break;

    case 'stats_group':
        $title = "Средний балл по группам";
        $where = $search ? " HAVING `Группа` LIKE '%$search%'" : "";
        $query = "SELECT gr.group_id AS 'ID группы', gr.group_name AS 'Группа', ROUND(AVG(g.grade), 2) AS 'Средний балл', COUNT(DISTINCT s.stud_id) AS 'Кол-во студентов' FROM grades g JOIN students s ON g.students_stud_id = s.stud_id JOIN `groups` gr ON s.groups_group_id = gr.group_id GROUP BY gr.group_id $where ORDER BY `Средний балл` DESC";
        break;

    case 'stats_sub':
        $title = "Средний балл по дисциплинам";
        $where = $search ? " HAVING `Дисциплина` LIKE '%$search%'" : "";
        $query = "SELECT su.sub_id AS 'ID дисциплины', su.sub_name AS 'Дисциплина', ROUND(AVG(g.grade), 2) AS 'Средний балл', COUNT(DISTINCT g.students_stud_id) AS 'Кол-во студентов' FROM grades g JOIN subjects su ON g.subjects_sub_id = su.sub_id GROUP BY su.sub_id $where ORDER BY `Средний балл` DESC";
        break;

    case 'dep_data':
        $title = "Данные по кафедрам";
        $where = $search ? " WHERE `Кафедра` LIKE '%$search%'" : "";
        $query = "SELECT `Кафедра`, `ФИО Заведеющего`, `Телефон`, `Кол-во групп`, `Кол-во преподавателей`, `Факультет` FROM dep_data $where";
        break;

    case 'groups_data':
        $title = "Состав групп";
        $where = $search ? " WHERE `Название группы` LIKE '%$search%'" : "";
        $query = "SELECT `Название группы`, `Курс`, `Кафедра`, `Кол-во студентов` FROM groups_data $where";
        break;

    case 'teacher_load':
        $title = "Нагрузка преподавателей";
        $where = $search ? " WHERE `Преподаватель` LIKE '%$search%' OR `Кафедра` LIKE '%$search%'" : "";
        $query = "SELECT t.teach_id AS 'ID преподавателя', t.teach_name AS 'Преподаватель', d.dep_name AS 'Кафедра', COUNT(s.sub_id) AS 'Кол-во дисциплин', GROUP_CONCAT(DISTINCT s.sub_name SEPARATOR ', ') AS 'Дисциплины' FROM teacher t LEFT JOIN subjects s ON t.teach_id = s.teacher_teach_id LEFT JOIN departments d ON t.departments_dep_id = d.dep_id GROUP BY t.teach_id $where ORDER BY `Преподаватель`";
        break;

    case 'admin':
        $title = "Администрирование: " . (
            $adminSection == 'faculties' ? 'Факультеты' :
            ($adminSection == 'departments' ? 'Кафедры' :
            ($adminSection == 'groups' ? 'Группы' :
            ($adminSection == 'teachers' ? 'Преподаватели' : 'Факультеты'))))
        ;
        break;
        
    case 'add_group': 
        $title = "Добавление новой группы"; 
        break;
        
    case 'annual_promotion': 
        $title = "Перевод на следующий курс"; 
        break;
        
    case 'teacher_panel': 
        $title = $teacher_id ? "Панель преподавателя" : "Вход в панель преподавателя"; 
        break;
        
    case 'add_grade': 
        $title = "Выставление оценки"; 
        break;
    
    default:
        break;
}

function renderTable($pdo, $query, $page = '', $showActions = false) {
    if (!$query) return;
    try {
        $res = $pdo->query($query);
        $data = $res->fetchAll();
        if (!$data) { echo '<div class="p-10 text-center"><i class="fas fa-search text-4xl text-gray-200 mb-3"></i><p class="text-gray-400">Ничего не найдено</p></div>'; return; }
        $cols = array_keys($data[0]);
        echo '<div class="overflow-x-auto"><table class="data-table w-full"><thead class="bg-gray-50"><tr>';
        foreach ($cols as $c) echo '<th class="px-6 py-3">' . htmlspecialchars($c) . '</th>';
        if ($showActions) echo '<th class="px-6 py-3 text-center">Действия</th>';
        echo '</thead><tbody>';
        foreach ($data as $row) {
            echo '<tr class="hover:bg-gray-50">';
            foreach ($row as $key => $val) {
                echo '<td class="px-6 py-3">';
                if ($key === 'ID' && isset($row['ID'])) echo '<a href="?page=student_info&id=' . $val . '" class="text-blue-600 hover:underline font-mono">' . htmlspecialchars($val) . '</a>';
                elseif ($key === 'ID_F') echo '<a href="?page=students&fac_id=' . $val . '" class="text-blue-600">Смотреть факультет</a>';
                elseif ($key === 'ID_D') echo '<a href="?page=students&dep_id=' . $val . '" class="text-emerald-600">Смотреть кафедру</a>';
                elseif ($key === 'ID_G') echo '<a href="?page=students&group_id=' . $val . '" class="text-purple-600">Смотреть группу</a>';
                else echo htmlspecialchars($val);
                echo '</td>';
            }
            if ($showActions && isset($row['ID'])) echo '<td class="px-6 py-3 text-center"><a href="?page=student_info&id=' . $row['ID'] . '" class="text-blue-500"><i class="fas fa-address-card"></i></a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } catch (PDOException $e) {
        echo '<div class="p-10 text-center bg-red-50"><p class="text-red-600">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    }
}

function renderAdminTable($pdo, $table, $displayName, $idColumn, $orderBy = '') {
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher'];
    if (!in_array($table, $allowedTables)) {
        echo '<div class="p-10 text-center"><p class="text-red-600">Таблица не доступна для редактирования</p></div>';
        return;
    }
    
    $data = getTableData($pdo, $table, $orderBy);
    if (!$data) {
        echo '<div class="p-10 text-center"><p class="text-gray-400">Нет данных</p></div>';
        return;
    }
    
    $cols = array_keys($data[0]);
    echo '<div class="overflow-x-auto"><table class="data-table w-full" id="adminTable"><thead class="bg-gray-50"><tr>';
    foreach ($cols as $c) echo '<th class="px-6 py-3">' . htmlspecialchars($c) . '</th>';
    echo '<th class="px-6 py-3 text-center">Удалить</th>';
    echo '</thead><tbody>';
    foreach ($data as $row) {
        echo '<tr data-id="' . $row[$idColumn] . '" class="hover:bg-gray-50">';
        foreach ($row as $key => $val) {
            echo '<td class="px-6 py-3" data-column="' . htmlspecialchars($key) . '">';
            if ($key != $idColumn) {
                echo '<span class="admin-editable-cell" onclick="editCell(this)" data-table="' . $table . '" data-id="' . $row[$idColumn] . '" data-column="' . htmlspecialchars($key) . '">' . nl2br(htmlspecialchars($val)) . '</span>';
            } else {
                echo htmlspecialchars($val);
            }
            echo '</td>';
        }
        echo '<td class="px-6 py-3 text-center"><button onclick="deleteRecord(\'' . $table . '\', \'' . $row[$idColumn] . '\', \'' . $idColumn . '\')" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    
    // Форма добавления
    echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg"><h4 class="font-bold mb-2">➕ Добавить новую запись в ' . $displayName . '</h4>';
    echo '<form id="addRecordForm" onsubmit="addRecord(event)" class="space-y-3">';
    echo '<input type="hidden" name="table" value="' . $table . '">';
    $columns = getTableColumns($pdo, $table);
    foreach ($columns as $col) {
        if ($col == $idColumn) continue;
        if ($col == 'departments_dep_id') {
            echo '<div><label class="block text-sm font-medium">Кафедра</label>';
            echo '<select name="' . $col . '" class="w-full p-2 border rounded">';
            $deps = $pdo->query("SELECT dep_id, dep_name FROM departments ORDER BY dep_name");
            while ($d = $deps->fetch()) {
                echo '<option value="' . $d['dep_id'] . '">' . htmlspecialchars($d['dep_name']) . '</option>';
            }
            echo '</select></div>';
        } elseif ($col == 'faculties_fac_id') {
            echo '<div><label class="block text-sm font-medium">Факультет</label>';
            echo '<select name="' . $col . '" class="w-full p-2 border rounded">';
            $facs = $pdo->query("SELECT fac_id, fac_name FROM faculties ORDER BY fac_name");
            while ($f = $facs->fetch()) {
                echo '<option value="' . $f['fac_id'] . '">' . htmlspecialchars($f['fac_name']) . '</option>';
            }
            echo '</select></div>';
        } else {
            echo '<div><label class="block text-sm font-medium">' . htmlspecialchars($col) . '</label>';
            echo '<input type="text" name="' . $col . '" class="w-full p-2 border rounded" placeholder="Введите значение"></div>';
        }
    }
    echo '<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">➕ Добавить</button>';
    echo '</form></div>';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Nunito", sans-serif; }
        .stat-card { transition: transform 0.2s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        
        /* Единые стили для всех таблиц */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table thead th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        /* Стили для админ-таблиц */
        .admin-editable-cell {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.2s;
            display: inline-block;
            min-width: 50px;
        }
        .admin-editable-cell:hover {
            background-color: #e0f2fe !important;
        }
        .admin-editable-cell.editing {
            background-color: #fef08a;
            padding: 0;
        }
        .admin-editable-cell.editing input, 
        .admin-editable-cell.editing select {
            border: 2px solid #3b82f6;
            padding: 4px 8px;
            border-radius: 4px;
            outline: none;
            width: 100%;
            background: white;
        }
        
        /* Стили для редактируемых ячеек студентов */
        .student-editable-cell {
            cursor: pointer;
            transition: background-color 0.2s;
            border-radius: 6px;
            padding: 4px 8px;
            display: inline-block;
        }
        .student-editable-cell:hover {
            background-color: #fff3cd !important;
        }
        .student-editable-cell.editing {
            background-color: #fef08a;
            padding: 0;
        }
        .student-editable-cell.editing input,
        .student-editable-cell.editing select {
            border: 2px solid #3b82f6;
            padding: 6px 10px;
            border-radius: 6px;
            outline: none;
            width: 100%;
            background: white;
            font-size: 14px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .admin-badge { background: linear-gradient(135deg, #667eea, #764ba2); }
        .delete-btn { color: #ef4444; cursor: pointer; }
        .delete-btn:hover { color: #dc2626; }
        
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        .notification.success { background: #10b981; }
        .notification.error { background: #ef4444; }
        .notification.warning { background: #f59e0b; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .manage-card {
            transition: all 0.3s ease;
        }
        .manage-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen">
    <aside class="bg-slate-900 text-white w-64 hidden md:flex flex-col min-h-screen">
        <div class="p-6 border-b border-slate-800">
            <i class="fas fa-university text-2xl text-blue-400"></i>
            <h1 class="text-xl font-bold mt-2">УНИВЕР</h1>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Учебный процесс</p>
                <a href="?page=students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='students'?'bg-blue-600':'' ?>"><i class="fas fa-user-graduate w-6"></i> Реестр</a>
                <a href="?page=all_students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='all_students'?'bg-blue-600':'' ?>"><i class="fas fa-list w-6"></i> Все студенты</a>
                <a href="?page=student_info" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='student_info'?'bg-blue-600':'' ?>"><i class="fas fa-address-card w-6"></i> Зачетные книжки</a>
                <a href="?page=debtors" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='debtors'?'bg-blue-600':'' ?>"><i class="fas fa-user-slash w-6"></i> Должники</a>
                <a href="?page=retake" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='retake'?'bg-blue-600':'' ?>"><i class="fas fa-clock w-6"></i> Пересдачи</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Аналитика</p>
                <a href="?page=top" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='top'?'bg-blue-600':'' ?>"><i class="fas fa-medal w-6"></i> Отличники</a>
                <a href="?page=scholarship" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='scholarship'?'bg-blue-600':'' ?>"><i class="fas fa-wallet w-6"></i> Стипендия</a>
                <a href="?page=stats_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_group'?'bg-blue-600':'' ?>"><i class="fas fa-chart-bar w-6"></i> Рейтинг групп</a>
                <a href="?page=stats_sub" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_sub'?'bg-blue-600':'' ?>"><i class="fas fa-chart-line w-6"></i> Рейтинг дисциплин</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Преподавательская</p>
                <a href="?page=teacher_panel" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='teacher_panel'?'bg-blue-600':'' ?>"><i class="fas fa-chalkboard-teacher w-6"></i> Вход для преподавателя</a>
                <a href="?page=add_grade" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='add_grade'?'bg-blue-600':'' ?>"><i class="fas fa-pen w-6"></i> Выставить оценку</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Управление</p>
                <a href="?page=manage" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='manage'?'bg-blue-600':'' ?>"><i class="fas fa-users-cog w-6"></i> Управление студентами</a>
                <a href="?page=add_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='add_group'?'bg-blue-600':'' ?>"><i class="fas fa-plus-circle w-6"></i> Добавить группу</a>
                <a href="?page=annual_promotion" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='annual_promotion'?'bg-blue-600':'' ?>"><i class="fas fa-calendar-alt w-6"></i> Годовой перевод</a>
                <a href="?page=archive" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='archive'?'bg-blue-600':'' ?>"><i class="fas fa-archive w-6"></i> Архив</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Администрирование</p>
                <?php if ($isAdmin): ?>
                    <a href="?page=admin&admin_section=faculties" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='faculties')?'bg-blue-600':'' ?>"><i class="fas fa-building w-6"></i> Факультеты</a>
                    <a href="?page=admin&admin_section=departments" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='departments')?'bg-blue-600':'' ?>"><i class="fas fa-university w-6"></i> Кафедры</a>
                    <a href="?page=admin&admin_section=groups" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='groups')?'bg-blue-600':'' ?>"><i class="fas fa-users w-6"></i> Группы</a>
                    <a href="?page=admin&admin_section=teachers" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='teachers')?'bg-blue-600':'' ?>"><i class="fas fa-chalkboard-teacher w-6"></i> Преподаватели</a>
                    <hr class="my-2 border-slate-700">
                    <a href="#" onclick="logoutAdmin()" class="flex items-center p-2 rounded-lg bg-red-700/20 mt-2 border border-red-500/30"><i class="fas fa-sign-out-alt w-6 text-red-300"></i><span class="text-red-300">Выйти из админ-режима</span></a>
                <?php else: ?>
                    <a href="#" onclick="showAdminLogin()" class="flex items-center p-2 rounded-lg bg-purple-700/20 mt-2 border border-purple-500/30"><i class="fas fa-shield-alt w-6 text-purple-300"></i><span class="text-purple-300">Вход для администратора</span></a>
                <?php endif; ?>
            </section>
        </nav>
    </aside>
    
    <main class="flex-1 flex flex-col min-w-0">
        <header class="bg-white p-6 shadow-sm flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800"><?= $title ?></h2>
            <?php if ($isAdmin): ?>
            <div class="admin-badge bg-purple-600 text-white px-4 py-2 rounded-full flex items-center gap-2">
                <i class="fas fa-shield-alt"></i>
                <span>Admin Mode</span>
            </div>
            <?php endif; ?>
        </header>
        
        <div class="p-6">
            <!-- Управление студентами -->
            <?php if ($page === 'manage'): ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">✅ Студент успешно добавлен!</div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">❌ Ошибка: <?= htmlspecialchars($_GET['error']) ?></div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="manage-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-green-700 p-4">
                            <h3 class="text-white font-bold text-lg"><i class="fas fa-user-plus mr-2"></i> Добавление студента</h3>
                            <p class="text-green-100 text-sm">Заполните форму для добавления нового студента</p>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_student">
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">ID студента *</label>
                                        <input type="number" name="stud_id" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Год рождения</label>
                                        <input type="number" name="birth_date" class="w-full p-2 border rounded-lg">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ФИО *</label>
                                    <input type="text" name="full_name" required class="w-full p-2 border rounded-lg">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Год поступления</label>
                                        <input type="number" name="admission_year" class="w-full p-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Пол</label>
                                        <select name="gender" class="w-full p-2 border rounded-lg">
                                            <option>Мужской</option>
                                            <option>Женский</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Телефон</label>
                                        <input type="text" name="phone" class="w-full p-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Город</label>
                                        <input type="text" name="city" value="Уфа" class="w-full p-2 border rounded-lg">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Адрес</label>
                                    <input type="text" name="address" class="w-full p-2 border rounded-lg">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Группа *</label>
                                    <select name="group_id" required class="w-full p-2 border rounded-lg">
                                        <option value="">Выберите группу</option>
                                        <?php foreach($pdo->query("SELECT group_id, group_name, course FROM `groups` ORDER BY course, group_name") as $group): ?>
                                        <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?> (<?= $group['course'] ?> курс)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition font-semibold">
                                    <i class="fas fa-save mr-2"></i> Добавить студента
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="manage-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4">
                            <h3 class="text-white font-bold text-lg"><i class="fas fa-exchange-alt mr-2"></i> Перевод студента</h3>
                            <p class="text-blue-100 text-sm">Переместите студента в другую группу</p>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="transfer">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Студент</label>
                                    <select name="stud_id" required class="w-full p-2 border rounded-lg">
                                        <option value="">Выберите студента</option>
                                        <?php 
                                        $students = $pdo->query("
                                            SELECT s.stud_id, s.full_name, g.group_name 
                                            FROM students s 
                                            JOIN `groups` g ON s.groups_group_id = g.group_id 
                                            ORDER BY s.full_name
                                        ");
                                        foreach($students as $student): 
                                        ?>
                                        <option value="<?= $student['stud_id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['group_name']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Новая группа</label>
                                    <select name="new_group_id" required class="w-full p-2 border rounded-lg">
                                        <option value="">Выберите группу</option>
                                        <?php foreach($pdo->query("SELECT group_id, group_name, course FROM `groups` ORDER BY course, group_name") as $group): ?>
                                        <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?> (<?= $group['course'] ?> курс)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                                    <i class="fas fa-arrow-right mr-2"></i> Перевести студента
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="manage-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-red-600 to-red-700 p-4">
                            <h3 class="text-white font-bold text-lg"><i class="fas fa-trash-alt mr-2"></i> Удаление студента</h3>
                            <p class="text-red-100 text-sm">⚠️ Безвозвратное удаление с каскадным эффектом</p>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4" onsubmit="return confirm('Вы уверены, что хотите удалить этого студента? Все его оценки и пересдачи также будут удалены!')">
                                <input type="hidden" name="action" value="delete_student_proc">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID студента</label>
                                    <input type="number" name="stud_id" required class="w-full p-2 border rounded-lg" placeholder="Введите ID студента">
                                </div>
                                
                                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition font-semibold">
                                    <i class="fas fa-trash-alt mr-2"></i> Удалить студента
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="manage-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-600 to-gray-700 p-4">
                            <h3 class="text-white font-bold text-lg"><i class="fas fa-list mr-2"></i> Список студентов</h3>
                            <p class="text-gray-100 text-sm">Всего студентов: <?= count($studentsList) ?></p>
                        </div>
                        <div class="p-0 max-h-96 overflow-y-auto">
                            <table class="data-table">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-sm">ID</th>
                                        <th class="px-4 py-2 text-sm">ФИО</th>
                                        <th class="px-4 py-2 text-sm">Группа</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($studentsList as $student): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm"><a href="?page=student_info&id=<?= $student['stud_id'] ?>" class="text-blue-600 hover:underline font-mono"><?= $student['stud_id'] ?></a></td>
                                        <td class="px-4 py-2 text-sm"><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td class="px-4 py-2 text-sm"><?= htmlspecialchars($student['group_name']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            <!-- Страница со всеми студентами с редактированием и удалением -->
            <?php elseif ($page === 'all_students'): ?>
                <div class="mb-6 flex gap-4 flex-wrap">
                    <form method="GET" class="flex-1 min-w-[200px]">
                        <input type="hidden" name="page" value="all_students">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по ФИО, ID или группе..." class="w-full pl-10 pr-4 py-2 border rounded-lg">
                        </div>
                    </form>
                    <?php if ($isAdmin): ?>
                    <button onclick="enableEditMode()" id="editModeBtn" class="bg-indigo-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-indigo-700 transition shrink-0">
                        <i class="fas fa-edit"></i> Включить режим редактирования
                    </button>
                    <button onclick="showAddStudentModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 hover:bg-green-700 transition shrink-0">
                        <i class="fas fa-user-plus"></i> Добавить студента
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table" id="studentsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3">ID</th>
                                    <th class="px-4 py-3">ФИО</th>
                                    <th class="px-4 py-3">Телефон</th>
                                    <th class="px-4 py-3">Год рождения</th>
                                    <th class="px-4 py-3">Год поступления</th>
                                    <th class="px-4 py-3">Группа</th>
                                    <th class="px-4 py-3">Курс</th>
                                    <th class="px-4 py-3">Кафедра</th>
                                    <?php if ($isAdmin): ?>
                                    <th class="px-4 py-3 text-center">Действия</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsData as $student): ?>
                                <tr data-student-id="<?= $student['ID'] ?>" data-group-id="<?= $student['group_id'] ?>" class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><a href="?page=student_info&id=<?= $student['ID'] ?>" class="text-blue-600 hover:underline font-mono"><?= htmlspecialchars($student['ID']) ?></a></td>
                                    <td class="px-4 py-3"><span class="student-editable-cell" data-field="full_name" data-original="<?= htmlspecialchars($student['ФИО']) ?>"><?= htmlspecialchars($student['ФИО']) ?></span></td>
                                    <td class="px-4 py-3"><span class="student-editable-cell" data-field="stud_phone" data-original="<?= htmlspecialchars($student['Телефон']) ?>"><?= htmlspecialchars($student['Телефон']) ?></span></td>
                                    <td class="px-4 py-3"><span class="student-editable-cell" data-field="birth_date" data-original="<?= htmlspecialchars($student['Год рождения']) ?>"><?= htmlspecialchars($student['Год рождения']) ?></span></td>
                                    <td class="px-4 py-3"><span class="student-editable-cell" data-field="admission_year" data-original="<?= htmlspecialchars($student['Год поступления']) ?>"><?= htmlspecialchars($student['Год поступления']) ?></span></td>
                                    <td class="px-4 py-3">
                                        <span class="student-editable-cell" data-field="groups_group_id" data-original="<?= $student['group_id'] ?>" data-display="<?= htmlspecialchars($student['Группа']) ?>">
                                            <span class="display-value"><?= htmlspecialchars($student['Группа']) ?></span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($student['Курс']) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($student['Кафедра']) ?></td>
                                    <?php if ($isAdmin): ?>
                                    <td class="px-4 py-3 text-center">
                                        <button onclick="deleteStudent(<?= $student['ID'] ?>)" class="text-red-500 hover:text-red-700 transition" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($studentsData)): ?>
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">Нет студентов для отображения</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Модальное окно добавления студента -->
                <div id="addStudentModal" class="modal">
                    <div class="modal-content p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-bold">Добавление студента</h3>
                            <button onclick="closeAddStudentModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
                        </div>
                        <form id="addStudentForm" onsubmit="addNewStudent(event)">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">ID студента *</label>
                                    <input type="number" name="stud_id" required class="w-full p-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Год рождения</label>
                                    <input type="number" name="birth_date" class="w-full p-2 border rounded-lg">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">ФИО *</label>
                                    <input type="text" name="full_name" required class="w-full p-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Год поступления</label>
                                    <input type="number" name="admission_year" class="w-full p-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Пол</label>
                                    <select name="gender" class="w-full p-2 border rounded-lg">
                                        <option>Мужской</option>
                                        <option>Женский</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Телефон</label>
                                    <input type="text" name="stud_phone" class="w-full p-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Город</label>
                                    <input type="text" name="city" value="Уфа" class="w-full p-2 border rounded-lg">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Адрес</label>
                                    <input type="text" name="adress" class="w-full p-2 border rounded-lg">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-1">Группа *</label>
                                    <select name="group_id" required class="w-full p-2 border rounded-lg">
                                        <option value="">Выберите группу</option>
                                        <?php foreach($pdo->query("SELECT group_id, group_name, course FROM `groups` ORDER BY course, group_name") as $group): ?>
                                        <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?> (<?= $group['course'] ?> курс)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg mt-4 hover:bg-green-700 transition">Добавить студента</button>
                        </form>
                    </div>
                </div>
                
            <!-- Админ-панель -->
            <?php elseif ($page === 'admin' && $isAdmin): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-semibold">
                            <?php if ($adminSection == 'faculties'): ?><i class="fas fa-building mr-2"></i> Управление факультетами
                            <?php elseif ($adminSection == 'departments'): ?><i class="fas fa-university mr-2"></i> Управление кафедрами
                            <?php elseif ($adminSection == 'groups'): ?><i class="fas fa-users mr-2"></i> Управление группами
                            <?php else: ?><i class="fas fa-chalkboard-teacher mr-2"></i> Управление преподавателями
                            <?php endif; ?>
                        </h3>
                        <p class="text-sm text-gray-500">Кликните на ячейку для редактирования</p>
                    </div>
                    
                    <?php
                    if ($adminSection == 'faculties') {
                        renderAdminTable($pdo, 'faculties', 'факультеты', 'fac_id', 'fac_name');
                    } elseif ($adminSection == 'departments') {
                        renderAdminTable($pdo, 'departments', 'кафедры', 'dep_id', 'dep_name');
                    } elseif ($adminSection == 'groups') {
                        renderAdminTable($pdo, 'groups', 'группы', 'group_id', 'group_name');
                    } elseif ($adminSection == 'teachers') {
                        renderAdminTable($pdo, 'teacher', 'преподаватели', 'teach_id', 'teach_name');
                    }
                    ?>
                </div>
                
            <!-- Страница реестра (students) -->
            <?php elseif ($page === 'students'): ?>
                <?php if (!isset($_GET['global_search']) && !$type && !$fac_id && !$dep_id && !$group_id): ?>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <a href="?page=all_students" class="stat-card block">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-xl text-white shadow-lg">
                                <i class="fas fa-users text-4xl opacity-80 mb-4"></i>
                                <h4 class="text-lg uppercase">Студентов</h4>
                                <p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?></p>
                            </div>
                        </a>
                        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-xl text-white shadow-lg">
                            <i class="fas fa-chart-line text-4xl opacity-80 mb-4"></i>
                            <h4 class="text-lg uppercase">Средний балл</h4>
                            <p class="text-5xl font-black"><?= round($pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(), 2) ?></p>
                        </div>
                        <a href="?page=scholarship" class="stat-card block">
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-xl text-white shadow-lg">
                                <i class="fas fa-wallet text-4xl opacity-80 mb-4"></i>
                                <h4 class="text-lg uppercase">Стипендиатов</h4>
                                <p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM (SELECT s.stud_id FROM students s JOIN grades g ON s.stud_id = g.students_stud_id GROUP BY s.stud_id HAVING MIN(g.grade) >= 4) as t")->fetchColumn() ?></p>
                            </div>
                        </a>
                        <a href="?page=teacher_load" class="stat-card block">
                            <div class="bg-gradient-to-br from-orange-500 to-orange-600 p-6 rounded-xl text-white shadow-lg">
                                <i class="fas fa-chalkboard-teacher text-4xl opacity-80 mb-4"></i>
                                <h4 class="text-lg uppercase">Преподавателей</h4>
                                <p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn() ?></p>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <form method="GET" class="max-w-2xl">
                        <input type="hidden" name="page" value="students">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="global_search" value="<?= htmlspecialchars($_GET['global_search'] ?? '') ?>" placeholder="Глобальный поиск по факультетам, кафедрам, группам, студентам, преподавателям..." class="w-full pl-10 pr-4 py-3 border rounded-lg">
                        </div>
                    </form>
                </div>
                
                <?php if (!isset($_GET['global_search']) && !$type && !$fac_id && !$dep_id && !$group_id): ?>
                    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <a href="?page=students&search_type=fac" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-blue-500 text-center">
                            <i class="fas fa-university text-4xl text-blue-500 mb-4"></i>
                            <h4 class="font-bold">По факультетам</h4>
                        </a>
                        <a href="?page=students&search_type=dep" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-emerald-500 text-center">
                            <i class="fas fa-building text-4xl text-emerald-500 mb-4"></i>
                            <h4 class="font-bold">По кафедрам</h4>
                        </a>
                        <a href="?page=students&search_type=group" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-purple-500 text-center">
                            <i class="fas fa-users text-4xl text-purple-500 mb-4"></i>
                            <h4 class="font-bold">По группам</h4>
                        </a>
                    </section>
                <?php endif; ?>
                
                <?php if ($query): ?>
                    <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <?php renderTable($pdo, $query, $page, ($page === 'students' && isset($group_id))); ?>
                    </section>
                <?php elseif (!isset($_GET['global_search']) && !$type && !$fac_id && !$dep_id && !$group_id): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Выберите категорию поиска или используйте глобальный поиск</p>
                    </div>
                <?php endif; ?>
                
            <!-- Остальные страницы -->
            <?php elseif ($page === 'archive'): ?>
                <?php if ($query): ?>
                    <section class="bg-white rounded-xl shadow-sm"><div class="p-4 bg-gray-50 border-b"><h3 class="text-lg font-semibold"><i class="fas fa-archive mr-2"></i> Архив выпускников</h3></div><?php renderTable($pdo, $query, $page, false); ?></section>
                <?php else: ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4"><p class="text-yellow-700">Архив пуст</p></div>
                <?php endif; ?>
                
            <?php elseif ($page === 'add_group'): ?>
                <div class="max-w-2xl mx-auto"><div class="bg-white rounded-xl p-6"><h3 class="text-xl font-bold mb-6">Добавление группы</h3><form action="actions.php" method="POST" class="space-y-4"><input type="hidden" name="action" value="add_group"><div><label class="block text-sm font-medium mb-1">Название группы</label><input type="text" name="group_name" required class="w-full p-2 border rounded-lg"></div><div><label class="block text-sm font-medium mb-1">Курс</label><select name="course" class="w-full p-2 border rounded-lg"><option value="1">1 курс</option><option value="2">2 курс</option><option value="3">3 курс</option><option value="4">4 курс</option></select></div><div><label class="block text-sm font-medium mb-1">Кафедра</label><select name="dep_id" required class="w-full p-2 border rounded-lg"><option value="">Выберите</option><?php foreach($pdo->query("SELECT dep_id, dep_name FROM departments ORDER BY dep_name") as $dep): ?><option value="<?= $dep['dep_id'] ?>"><?= htmlspecialchars($dep['dep_name']) ?></option><?php endforeach; ?></select></div><button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg">Добавить</button></form></div></div>
                
            <?php elseif ($page === 'annual_promotion'): ?>
                <div class="max-w-2xl mx-auto"><div class="bg-white rounded-xl p-6"><div class="text-center mb-6"><i class="fas fa-calendar-alt text-5xl text-yellow-500 mb-3"></i><h3 class="text-xl font-bold">Годовой перевод</h3><div class="mt-4 p-3 bg-yellow-50 rounded text-sm">Внимание! Действие необратимо</div></div><form action="actions.php" method="POST" onsubmit="return confirm('Вы уверены?')"><input type="hidden" name="action" value="annual_promotion"><div class="bg-gray-50 rounded p-4 mb-4"><?php foreach($pdo->query("SELECT course, COUNT(*) as count FROM `groups` GROUP BY course") as $stat): $students = $pdo->query("SELECT COUNT(*) FROM students s JOIN `groups` g ON s.groups_group_id = g.group_id WHERE g.course = ".$stat['course'])->fetchColumn(); ?><div class="flex justify-between text-sm"><span><?= $stat['course'] ?> курс:</span><span><?= $stat['count'] ?> групп, <?= $students ?> студентов</span></div><?php endforeach; ?></div><button type="submit" class="w-full bg-yellow-600 text-white py-3 rounded-lg font-bold">Выполнить перевод</button></form></div></div>
                
            <?php elseif ($page === 'teacher_panel'): ?>
                <?php if (!$teacher_id): ?>
                    <div class="max-w-md mx-auto"><form method="POST" action="actions.php" class="bg-white p-8 rounded-xl"><input type="hidden" name="action" value="teacher_login"><h3 class="text-xl font-bold mb-6">Вход для преподавателя</h3><input type="number" name="teacher_id" placeholder="ID преподавателя" required class="w-full p-2 border rounded-lg mb-4"><button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg">Войти</button></form></div>
                <?php else: ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded mb-6"><div class="flex justify-between"><span><i class="fas fa-check-circle text-green-400"></i> Вы вошли как: <?php $stmt = $pdo->prepare("SELECT teach_name FROM teacher WHERE teach_id = ?"); $stmt->execute([$teacher_id]); echo $stmt->fetchColumn(); ?></span><a href="actions.php?action=teacher_logout" class="text-red-500">Выйти</a></div></div>
                    <div class="bg-white rounded-xl p-6"><h3 class="font-bold text-lg mb-4">Ваши дисциплины</h3><?php $subjects = $pdo->prepare("SELECT sub_id, sub_name FROM subjects WHERE teacher_teach_id = ?"); $subjects->execute([$teacher_id]); if($subjects->rowCount()): ?><ul><?php while($sub = $subjects->fetch()): ?><li class="flex justify-between p-2 bg-gray-50 rounded mb-2"><span><?= htmlspecialchars($sub['sub_name']) ?></span><a href="?page=add_grade&subject_id=<?= $sub['sub_id'] ?>" class="text-blue-500">Выставить оценки</a></li><?php endwhile; ?></ul><?php else: ?><p class="text-gray-500">Нет дисциплин</p><?php endif; ?></div>
                <?php endif; ?>
                
            <?php elseif ($page === 'add_grade'): ?>
                <?php if (!$teacher_id): ?>
                    <div class="bg-yellow-50 p-4 rounded"><p>Сначала войдите в панель преподавателя</p><a href="?page=teacher_panel" class="text-blue-500">Перейти ко входу</a></div>
                <?php else: ?>
                    <div class="max-w-2xl mx-auto"><div class="bg-white rounded-xl p-6"><h3 class="text-xl font-bold mb-6">Выставить оценку</h3><form method="POST" action="actions.php" class="space-y-4"><input type="hidden" name="action" value="add_grade"><input type="hidden" name="teacher_id" value="<?= $teacher_id ?>"><div><label>Дисциплина</label><select name="subject_id" required class="w-full p-2 border rounded-lg"><?php $subjects = $pdo->prepare("SELECT sub_id, sub_name FROM subjects WHERE teacher_teach_id = ?"); $subjects->execute([$teacher_id]); while($sub = $subjects->fetch()): ?><option value="<?= $sub['sub_id'] ?>"><?= htmlspecialchars($sub['sub_name']) ?></option><?php endwhile; ?></select></div><div><label>ID студента</label><input type="number" name="student_id" required class="w-full p-2 border rounded-lg"></div><div><label>Оценка</label><select name="grade" required class="w-full p-2 border rounded-lg"><option value="5">5 - Отлично</option><option value="4">4 - Хорошо</option><option value="3">3 - Удовлетворительно</option><option value="2">2 - Неудовлетворительно</option></select></div><button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg">Выставить</button></form></div></div>
                <?php endif; ?>
                
            <?php elseif ($page === 'student_info'): ?>
                <div class="max-w-md mx-auto mb-8"><form method="GET" class="bg-white p-6 rounded-xl"><input type="hidden" name="page" value="student_info"><label class="block text-sm font-medium mb-2">Введите ID студента</label><div class="flex gap-2"><input type="number" name="id" value="<?= $_GET['id'] ?? '' ?>" placeholder="10001" class="flex-1 p-2 border rounded-lg"><button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg">Найти</button></div></form></div>
                <?php if ($student_info && $query): ?><div class="bg-white rounded-xl shadow-sm overflow-hidden"><div class="p-6 bg-gradient-to-r from-blue-50"><h3 class="text-xl font-bold"><?= htmlspecialchars($student_info['full_name']) ?></h3><p>Группа: <?= htmlspecialchars($student_info['group_name']) ?></p></div><?php renderTable($pdo, $query, $page); ?></div><?php elseif (isset($_GET['id']) && $_GET['id'] !== ''): ?><div class="bg-yellow-50 p-4 rounded"><p>Студент не найден</p></div><?php endif; ?>
                
            <?php elseif ($query): ?>
                <section class="bg-white rounded-xl shadow-sm overflow-hidden"><?php renderTable($pdo, $query, $page, false); ?></section>
                
            <!-- Дашборд -->
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <a href="?page=all_students" class="stat-card block"><div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-xl text-white shadow-lg"><i class="fas fa-users text-4xl opacity-80 mb-4"></i><h4 class="text-lg uppercase">Студентов</h4><p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?></p></div></a>
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-xl text-white shadow-lg"><i class="fas fa-chart-line text-4xl opacity-80 mb-4"></i><h4 class="text-lg uppercase">Средний балл</h4><p class="text-5xl font-black"><?= round($pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(), 2) ?></p></div>
                    <a href="?page=scholarship" class="stat-card block"><div class="bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-xl text-white shadow-lg"><i class="fas fa-wallet text-4xl opacity-80 mb-4"></i><h4 class="text-lg uppercase">Стипендиатов</h4><p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM (SELECT s.stud_id FROM students s JOIN grades g ON s.stud_id = g.students_stud_id GROUP BY s.stud_id HAVING MIN(g.grade) >= 4) as t")->fetchColumn() ?></p></div></a>
                    <a href="?page=teacher_load" class="stat-card block"><div class="bg-gradient-to-br from-orange-500 to-orange-600 p-6 rounded-xl text-white shadow-lg"><i class="fas fa-chalkboard-teacher text-4xl opacity-80 mb-4"></i><h4 class="text-lg uppercase">Преподавателей</h4><p class="text-5xl font-black"><?= $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn() ?></p></div></a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Модальные окна -->
    <div id="adminLoginModal" class="modal">
        <div class="modal-content p-6">
            <div class="flex justify-between mb-4">
                <h3 class="text-xl font-bold">Вход в админ-режим</h3>
                <button onclick="closeAdminLogin()" class="text-gray-500">&times;</button>
            </div>
            <form onsubmit="adminLogin(event)">
                <input type="password" id="adminPassword" placeholder="Пароль" class="w-full p-2 border rounded-lg mb-4">
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg">Войти</button>
            </form>
        </div>
    </div>
    
    <script>
        function showAdminLogin(){ document.getElementById('adminLoginModal').classList.add('active'); }
        function closeAdminLogin(){ document.getElementById('adminLoginModal').classList.remove('active'); }
        
        function adminLogin(e){
            e.preventDefault();
            const pwd = document.getElementById('adminPassword').value;
            fetch("?admin_action=login", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({password: pwd})
            }).then(r => r.json()).then(d => {
                if(d.success) location.reload();
                else alert("Неверный пароль!");
            });
        }
        
        function logoutAdmin(){ window.location.href = "?admin_action=logout"; }
        
        function showNotification(message, type = 'success'){
            const notif = document.createElement('div');
            notif.className = `notification ${type}`;
            notif.innerText = message;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }
        
        // Админ-таблицы
        function editCell(element) {
            if (element.querySelector('input') || element.querySelector('select')) return;
            const currentValue = element.innerText;
            const table = element.dataset.table;
            const id = element.dataset.id;
            const column = element.dataset.column;
            
            const isSelect = column === 'course' || column === 'gender' || column === 'departments_dep_id' || column === 'faculties_fac_id';
            
            if (isSelect) {
                const select = document.createElement('select');
                select.className = 'w-full p-1 border-2 border-blue-500 rounded';
                
                if (column === 'course') {
                    for(let i = 1; i <= 4; i++) {
                        const opt = document.createElement('option');
                        opt.value = i;
                        opt.text = i;
                        if (currentValue == i) opt.selected = true;
                        select.appendChild(opt);
                    }
                } else if (column === 'gender') {
                    ['Мужской', 'Женский'].forEach(g => {
                        const opt = document.createElement('option');
                        opt.value = g;
                        opt.text = g;
                        if (currentValue == g) opt.selected = true;
                        select.appendChild(opt);
                    });
                } else {
                    select.innerHTML = '<option>Загрузка...</option>';
                    element.innerHTML = '';
                    element.appendChild(select);
                    
                    let url = column === 'departments_dep_id' ? '?admin_action=get_departments' : '?admin_action=get_faculties';
                    fetch(url).then(r => r.json()).then(data => {
                        select.innerHTML = '';
                        data.forEach(opt => {
                            const option = document.createElement('option');
                            option.value = opt.id;
                            option.text = opt.name;
                            if (currentValue == opt.id) option.selected = true;
                            select.appendChild(option);
                        });
                    });
                }
                
                element.innerHTML = '';
                element.appendChild(select);
                element.classList.add('editing');
                select.focus();
                select.onblur = () => saveCellEdit(element, select.value, table, id, column, currentValue);
                select.onkeypress = (e) => { if(e.key === 'Enter') select.blur(); };
            } else {
                const input = document.createElement('input');
                input.type = (column.includes('id') || column === 'course') ? 'number' : 'text';
                input.value = currentValue;
                input.className = 'w-full p-1 border-2 border-blue-500 rounded';
                element.innerHTML = '';
                element.appendChild(input);
                element.classList.add('editing');
                input.focus();
                input.onblur = () => saveCellEdit(element, input.value, table, id, column, currentValue);
                input.onkeypress = (e) => { if(e.key === 'Enter') input.blur(); };
            }
        }
        
        function saveCellEdit(element, newValue, table, id, column, oldValue) {
            element.classList.remove('editing');
            if (newValue == oldValue) {
                element.innerHTML = oldValue;
                return;
            }
            element.innerHTML = '<span class="text-gray-400">⏳ сохранение...</span>';
            fetch("?admin_action=update_cell", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ table: table, id: id, column: column, value: newValue })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    element.innerHTML = newValue;
                    showNotification('✅ Сохранено!');
                } else {
                    element.innerHTML = oldValue;
                    showNotification('❌ Ошибка: ' + data.error, 'error');
                }
            })
            .catch(() => {
                element.innerHTML = oldValue;
                showNotification('❌ Ошибка сети', 'error');
            });
        }
        
        function deleteRecord(table, id, idColumn) {
            if (!confirm(`Удалить запись #${id}?\n\n⚠️ Если есть связанные данные, удаление будет невозможно!`)) return;
            fetch("?admin_action=delete_record", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ table: table, id: id, id_column: idColumn })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ Запись удалена!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('❌ ' + (data.error || 'Не удалось удалить'), 'error');
                }
            })
            .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
        
        function addRecord(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => { if(key !== 'table') data[key] = value; });
            const table = formData.get('table');
            fetch("?admin_action=add_record", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ table: table, data: data })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showNotification('✅ Запись добавлена!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                }
            })
            .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const addRecordForm = document.getElementById('addRecordForm');
            if (addRecordForm) {
                addRecordForm.onsubmit = addRecord;
            }
        });

        // ========== ФУНКЦИИ ДЛЯ РЕДАКТИРОВАНИЯ СТУДЕНТОВ ==========
    
        let editMode = false;
        
        function enableEditMode() {
            editMode = !editMode;
            const cells = document.querySelectorAll('#studentsTable .student-editable-cell');
            const btn = document.getElementById('editModeBtn');
            
            cells.forEach(cell => {
                if (editMode) {
                    cell.style.backgroundColor = '#fff3cd';
                    cell.style.cursor = 'pointer';
                    cell.style.position = 'relative';
                    cell.onclick = () => makeEditable(cell);
                } else {
                    cell.style.backgroundColor = '';
                    cell.style.cursor = '';
                    cell.onclick = null;
                    // Восстанавливаем оригинальное значение, если было в режиме редактирования
                    const originalValue = cell.dataset.original;
                    if (originalValue !== undefined && cell.querySelector('input, select') === null) {
                        if (cell.dataset.field === 'groups_group_id') {
                            const displaySpan = cell.querySelector('.display-value');
                            if (displaySpan) displaySpan.innerHTML = cell.dataset.display || originalValue;
                        } else {
                            cell.innerHTML = originalValue;
                        }
                    }
                }
            });
            
            if (btn) {
                btn.innerHTML = editMode ? '<i class="fas fa-check"></i> Режим редактирования включен' : '<i class="fas fa-edit"></i> Включить режим редактирования';
                btn.classList.toggle('bg-indigo-600');
                btn.classList.toggle('bg-green-600');
            }
            
            showNotification(editMode ? 'Режим редактирования включен. Кликайте по ячейкам для изменения.' : 'Режим редактирования выключен', 'warning');
        }
        
        function makeEditable(cell) {
            if (!editMode) return;
            if (cell.querySelector('input') || cell.querySelector('select')) return;
            
            const row = cell.closest('tr');
            const studentId = row.dataset.studentId;
            const field = cell.dataset.field;
            const currentValue = cell.dataset.original;
            const currentDisplay = cell.dataset.display;
            
            if (field === 'groups_group_id') {
                // Создаем select для выбора группы
                const select = document.createElement('select');
                select.className = 'w-full p-1 border-2 border-blue-500 rounded bg-white';
                <?php 
                $groups = $pdo->query("SELECT group_id, group_name, course FROM `groups` ORDER BY course, group_name");
                foreach($groups as $g): ?>
                select.innerHTML += '<option value="<?= $g['group_id'] ?>" data-name="<?= htmlspecialchars($g['group_name']) ?>"><?= htmlspecialchars($g['group_name']) ?> (<?= $g['course'] ?> курс)</option>';
                <?php endforeach; ?>
                select.value = currentValue;
                
                const originalHtml = cell.innerHTML;
                
                cell.innerHTML = '';
                cell.appendChild(select);
                cell.classList.add('editing');
                select.focus();
                
                const save = () => {
                    const newGroupId = select.value;
                    const selectedOption = select.options[select.selectedIndex];
                    const newGroupName = selectedOption.getAttribute('data-name') || selectedOption.text.split(' (')[0];
                    
                    if (newGroupId == currentValue) {
                        cell.innerHTML = originalHtml;
                        cell.classList.remove('editing');
                        return;
                    }
                    
                    cell.innerHTML = '<span class="text-gray-400">⏳ сохранение...</span>';
                    
                    fetch("?admin_action=update_student", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify({ stud_id: studentId, groups_group_id: newGroupId })
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            cell.innerHTML = `<span class="display-value">${newGroupName}</span>`;
                            cell.dataset.original = newGroupId;
                            cell.dataset.display = newGroupName;
                            row.dataset.groupId = newGroupId;
                            updateStudentRowInfo(row, studentId);
                            showNotification('✅ Группа обновлена!');
                        } else {
                            cell.innerHTML = originalHtml;
                            showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                        }
                        cell.classList.remove('editing');
                    })
                    .catch((err) => {
                        console.error('Error:', err);
                        cell.innerHTML = originalHtml;
                        cell.classList.remove('editing');
                        showNotification('❌ Ошибка сети: ' + err.message, 'error');
                    });
                };
                
                select.onblur = save;
                select.onkeypress = (e) => { if(e.key === 'Enter') save(); };
            } else {
                // Для обычных текстовых полей
                const input = document.createElement('input');
                let inputType = 'text';
                if (field === 'birth_date' || field === 'admission_year') {
                    inputType = 'number';
                }
                input.type = inputType;
                input.value = currentValue;
                input.className = 'w-full p-1 border-2 border-blue-500 rounded';
                
                const originalHtml = cell.innerHTML;
                cell.innerHTML = '';
                cell.appendChild(input);
                cell.classList.add('editing');
                input.focus();
                
                const save = () => {
                    const newValue = input.value;
                    
                    if (newValue == currentValue) {
                        cell.innerHTML = originalHtml;
                        cell.classList.remove('editing');
                        return;
                    }
                    
                    cell.innerHTML = '<span class="text-gray-400">⏳ сохранение...</span>';
                    
                    const data = { stud_id: studentId, field: field };
                    data[field] = newValue;
                    
                    fetch("?admin_action=update_student", {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify(data)
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            cell.innerHTML = newValue;
                            cell.dataset.original = newValue;
                            showNotification('✅ Данные обновлены!');
                        } else {
                            cell.innerHTML = originalHtml;
                            showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                        }
                        cell.classList.remove('editing');
                    })
                    .catch((err) => {
                        console.error('Error:', err);
                        cell.innerHTML = originalHtml;
                        cell.classList.remove('editing');
                        showNotification('❌ Ошибка сети: ' + err.message, 'error');
                    });
                };
                
                input.onblur = save;
                input.onkeypress = (e) => { if(e.key === 'Enter') save(); };
            }
        }
        
        function updateStudentRowInfo(row, studentId) {
            fetch(`?admin_action=get_student&id=${studentId}`)
                .then(r => r.json())
                .then(data => {
                    if (data && !data.error) {
                        const courseCell = row.cells[6];
                        if (courseCell && data.course) {
                            courseCell.innerHTML = data.course;
                        }
                        const depCell = row.cells[7];
                        if (depCell && data.dep_name) {
                            depCell.innerHTML = data.dep_name;
                        }
                    }
                })
                .catch(err => console.error('Error updating row info:', err));
        }
        
        function deleteStudent(id) {
            if (!confirm(`Удалить студента #${id}?\n\n⚠️ Все его оценки и пересдачи также будут удалены! Это действие необратимо!`)) return;
            
            fetch(`?admin_action=delete_student&id=${id}`)
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        showNotification('✅ Студент удален');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('❌ ' + (result.error || 'Ошибка при удалении'), 'error');
                    }
                })
                .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
        
        function showAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
        }
        
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            document.getElementById('addStudentForm').reset();
        }
        
        function addNewStudent(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });
            
            fetch("?admin_action=add_student", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showNotification('✅ Студент добавлен!');
                    closeAddStudentModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('❌ ' + (result.error || 'Ошибка при добавлении'), 'error');
                }
            })
            .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
    </script>
</body>
</html>