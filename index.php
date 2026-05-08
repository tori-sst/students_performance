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
            $success = false;
            
            switch ($data['table']) {
                case 'students': $success = updateStudentCell($pdo, $data['id'], $data['column'], $data['value']); break;
                case 'teacher': $success = updateTeacherCell($pdo, $data['id'], $data['column'], $data['value']); break;
                case 'groups': $success = updateGroupCell($pdo, $data['id'], $data['column'], $data['value']); break;
                case 'departments': $success = updateDepartmentCell($pdo, $data['id'], $data['column'], $data['value']); break;
                case 'subjects': $success = updateSubjectCell($pdo, $data['id'], $data['column'], $data['value']); break;
            }
            echo json_encode(['success' => $success]);
            exit;
    }
}

$page = $_GET['page'] ?? 'dashboard';
$title = "Главная";
$query = "";
$student_info = null;
$search = $_GET['search'] ?? '';
$teacher_id = $_SESSION['teacher_id'] ?? null;

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
        $where = $search ? " WHERE s.full_name LIKE '%$search%' OR s.stud_id LIKE '%$search%' OR g.group_name LIKE '%$search%'" : "";
        $query = "SELECT s.stud_id AS 'ID студента', s.full_name AS 'ФИО', s.stud_phone AS 'Телефон', s.birth_date AS 'Год рождения', s.admission_year AS 'Год поступления', g.group_name AS 'Группа', g.course AS 'Курс', d.dep_name AS 'Кафедра' FROM students s JOIN `groups` g ON s.groups_group_id = g.group_id JOIN departments d ON g.departments_dep_id = d.dep_id $where ORDER BY s.full_name";
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

    case 'add_group': $title = "Добавление новой группы"; break;
    case 'annual_promotion': $title = "Перевод на следующий курс"; break;
    case 'teacher_panel': $title = $teacher_id ? "Панель преподавателя" : "Вход в панель преподавателя"; break;
    case 'add_grade': $title = "Выставление оценки"; break;
    case 'manage': $title = "Управление студентами"; break;
}

function renderTable($pdo, $query, $page = '', $showActions = false) {
    if (!$query) return;
    try {
        $res = $pdo->query($query);
        $data = $res->fetchAll();
        if (!$data) { echo '<div class="p-10 text-center"><i class="fas fa-search text-4xl text-gray-200 mb-3"></i><p class="text-gray-400">Ничего не найдено</p></div>'; return; }
        $cols = array_keys($data[0]);
        echo '<div class="overflow-x-auto"><table class="w-full text-left border-collapse"><thead class="bg-gray-50 border-b"><tr>';
        foreach ($cols as $c) echo '<th class="px-6 py-4">' . htmlspecialchars($c) . '</th>';
        if ($showActions) echo '<th class="px-6 py-4 text-center">Действия</th>';
        echo '</thead><tbody>';
        foreach ($data as $row) {
            echo '<tr class="hover:bg-blue-50">';
            foreach ($row as $key => $val) {
                echo '<td class="px-6 py-4 text-sm">';
                if ($key === 'ID' && isset($row['ID'])) echo '<a href="?page=student_info&id=' . $val . '" class="text-blue-600 hover:underline">' . htmlspecialchars($val) . '</a>';
                elseif ($key === 'ID_F') echo '<a href="?page=students&fac_id=' . $val . '" class="text-blue-600">Смотреть факультет</a>';
                elseif ($key === 'ID_D') echo '<a href="?page=students&dep_id=' . $val . '" class="text-emerald-600">Смотреть кафедру</a>';
                elseif ($key === 'ID_G') echo '<a href="?page=students&group_id=' . $val . '" class="text-purple-600">Смотреть группу</a>';
                else echo htmlspecialchars($val);
                echo '</td>';
            }
            if ($showActions && isset($row['ID'])) echo '<td class="text-center"><a href="?page=student_info&id=' . $row['ID'] . '" class="text-blue-500"><i class="fas fa-address-card"></i></a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } catch (PDOException $e) {
        echo '<div class="p-10 text-center bg-red-50"><p class="text-red-600">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
    }
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
        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
        <?= getAdminCSS() ?>
        <?php else: ?>
        .admin-enter-btn { position: fixed; bottom: 20px; right: 20px; z-index: 999; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 20px; border-radius: 50px; cursor: pointer; font-weight: bold; border: none; }
        .admin-enter-btn:hover { transform: scale(1.05); }
        <?php endif; ?>
    </style>
    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) echo getAdminJS(); ?>
</head>
<body class="bg-gray-100 flex min-h-screen">
    <aside class="bg-slate-900 text-white w-64 hidden md:flex flex-col min-h-screen">
        <div class="p-6 border-b border-slate-800"><i class="fas fa-university text-2xl text-blue-400"></i><h1 class="text-xl font-bold mt-2">УНИВЕР</h1></div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <section><p class="text-xs text-slate-500 uppercase mb-2">Учебный процесс</p>
                <a href="?page=students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='students'?'bg-blue-600':'' ?>"><i class="fas fa-user-graduate w-6"></i> Реестр</a>
                <a href="?page=all_students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='all_students'?'bg-blue-600':'' ?>"><i class="fas fa-list w-6"></i> Все студенты</a>
                <a href="?page=student_info" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='student_info'?'bg-blue-600':'' ?>"><i class="fas fa-address-card w-6"></i> Зачетные книжки</a>
                <a href="?page=debtors" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='debtors'?'bg-blue-600':'' ?>"><i class="fas fa-user-slash w-6"></i> Должники</a>
                <a href="?page=retake" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='retake'?'bg-blue-600':'' ?>"><i class="fas fa-clock w-6"></i> Пересдачи</a>
            </section>
            <section><p class="text-xs text-slate-500 uppercase mb-2">Аналитика</p>
                <a href="?page=top" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='top'?'bg-blue-600':'' ?>"><i class="fas fa-medal w-6"></i> Отличники</a>
                <a href="?page=scholarship" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='scholarship'?'bg-blue-600':'' ?>"><i class="fas fa-wallet w-6"></i> Стипендия</a>
                <a href="?page=stats_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_group'?'bg-blue-600':'' ?>"><i class="fas fa-chart-bar w-6"></i> Рейтинг групп</a>
                <a href="?page=stats_sub" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_sub'?'bg-blue-600':'' ?>"><i class="fas fa-chart-line w-6"></i> Рейтинг дисциплин</a>
            </section>
            <section><p class="text-xs text-slate-500 uppercase mb-2">Преподавательская</p>
                <a href="?page=teacher_panel" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='teacher_panel'?'bg-blue-600':'' ?>"><i class="fas fa-chalkboard-teacher w-6"></i> Вход для преподавателя</a>
                <a href="?page=add_grade" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='add_grade'?'bg-blue-600':'' ?>"><i class="fas fa-pen w-6"></i> Выставить оценку</a>
            </section>
            <section><p class="text-xs text-slate-500 uppercase mb-2">Администрирование</p>
                <a href="?page=dep_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-building w-6"></i> Кафедры</a>
                <a href="?page=groups_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-users w-6"></i> Группы</a>
                <a href="?page=teacher_load" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-chalkboard-teacher w-6"></i> Преподаватели</a>
                <a href="?page=add_group" class="flex items-center p-2 rounded-lg bg-green-700/20 mt-2 border border-green-500/30"><i class="fas fa-plus-circle w-6 text-green-300"></i><span class="text-green-300"> Добавить группу</span></a>
                <a href="?page=annual_promotion" class="flex items-center p-2 rounded-lg bg-yellow-700/20 mt-2 border border-yellow-500/30"><i class="fas fa-calendar-alt w-6 text-yellow-400"></i><span class="text-yellow-300">Годовой перевод</span></a>
                <a href="?page=manage" class="flex items-center p-2 rounded-lg bg-orange-700/20 mt-2 border border-orange-500/30"><i class="fas fa-user-plus w-6 text-orange-300"></i><span class="text-orange-300">Управление студентами</span></a>
                <a href="?page=archive" class="flex items-center p-2 rounded-lg bg-gray-700/20 mt-2 border border-gray-500/30"><i class="fas fa-archive w-6 text-gray-400"></i><span class="text-gray-300">Архив</span></a>
                <a href="#" onclick="showAdminLogin()" class="flex items-center p-2 rounded-lg bg-purple-700/20 mt-2 border border-purple-500/30"><i class="fas fa-shield-alt w-6 text-purple-300"></i><span class="text-purple-300">Вход для администратора</span></a>
            </section>
        </nav>
    </aside>
    <main class="flex-1 flex flex-col min-w-0">
        <header class="bg-white p-6 shadow-sm flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800"><?= $title ?></h2>
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
            <div class="admin-badge"><i class="fas fa-shield-alt"></i><span>Admin Mode</span><a href="?admin_action=logout" style="color:white;margin-left:8px;"><i class="fas fa-sign-out-alt"></i></a></div>
            <?php endif; ?>
        </header>
        <div class="p-6">
            <?php if ($page === 'students'): ?>
                <div class="mb-6"><form method="GET" class="max-w-2xl"><input type="hidden" name="page" value="students"><div class="relative"><i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i><input type="text" name="global_search" value="<?= htmlspecialchars($_GET['global_search'] ?? '') ?>" placeholder="Поиск" class="w-full pl-10 pr-4 py-3 border rounded-lg"></div></form></div>
            <?php endif; ?>
            <?php if ($page !== 'students' && $page !== 'student_info' && $page !== 'manage' && $page !== 'teacher_panel' && $page !== 'add_grade' && $page !== 'add_group' && $page !== 'annual_promotion' && $page !== 'dashboard' && $query): ?>
                <div class="mb-6"><form method="GET" class="max-w-md"><input type="hidden" name="page" value="<?= $page ?>"><div class="relative"><i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск" class="w-full pl-10 pr-4 py-2 border rounded-lg"></div></form></div>
            <?php endif; ?>
            <?php if ($page === 'students' && !isset($_GET['global_search']) && !$type && !$fac_id && !$dep_id && !$group_id): ?>
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <a href="?page=students&search_type=fac" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-blue-500 text-center"><i class="fas fa-university text-4xl text-blue-500 mb-4"></i><h4 class="font-bold">По факультетам</h4></a>
                    <a href="?page=students&search_type=dep" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-emerald-500 text-center"><i class="fas fa-building text-4xl text-emerald-500 mb-4"></i><h4 class="font-bold">По кафедрам</h4></a>
                    <a href="?page=students&search_type=group" class="p-8 bg-white rounded-xl shadow-sm border-t-4 border-purple-500 text-center"><i class="fas fa-users text-4xl text-purple-500 mb-4"></i><h4 class="font-bold">По группам</h4></a>
                </section>
            <?php endif; ?>
            <?php if ($page === 'archive'): ?>
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
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded mb-6"><div class="flex justify-between"><span><i class="fas fa-check-circle text-green-400"></i> Вы вошли как: <?= $pdo->prepare("SELECT teach_name FROM teacher WHERE teach_id = ?")->execute([$teacher_id]) ? $pdo->prepare("SELECT teach_name FROM teacher WHERE teach_id = ?")->fetchColumn() : '' ?></span><a href="actions.php?action=teacher_logout" class="text-red-500">Выйти</a></div></div>
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
            <?php elseif ($page === 'manage'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white rounded-xl"><div class="bg-green-600 p-4"><h3 class="text-white font-bold"><i class="fas fa-user-plus"></i> Добавление студента</h3></div><div class="p-6"><form action="actions.php" method="POST" class="space-y-4"><input type="hidden" name="action" value="add_student"><input type="number" name="stud_id" placeholder="ID" required class="w-full p-2 border rounded"><input type="text" name="full_name" placeholder="ФИО" required class="w-full p-2 border rounded"><input type="number" name="birth_date" placeholder="Год рождения" class="w-full p-2 border rounded"><select name="gender" class="w-full p-2 border rounded"><option>Мужской</option><option>Женский</option></select><input type="text" name="phone" placeholder="Телефон" class="w-full p-2 border rounded"><select name="group_id" required class="w-full p-2 border rounded"><option value="">Выберите группу</option><?php foreach($pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name") as $group): ?><option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option><?php endforeach; ?></select><button type="submit" class="w-full bg-green-600 text-white py-2 rounded">Добавить</button></form></div></div>
                    <div class="bg-white rounded-xl"><div class="bg-red-600 p-4"><h3 class="text-white font-bold"><i class="fas fa-trash-alt"></i> Удаление студента</h3><p class="text-red-100 text-sm">⚠️ Необратимо</p></div><div class="p-6"><form action="actions.php" method="POST" onsubmit="return confirm('Удалить студента?')"><input type="hidden" name="action" value="delete_student"><input type="number" name="stud_id" placeholder="ID студента" required class="w-full p-2 border rounded"><button type="submit" class="w-full bg-red-600 text-white py-2 rounded mt-4">Удалить</button></form></div></div>
                </div>
            <?php elseif ($query): ?>
                <section class="bg-white rounded-xl shadow-sm overflow-hidden"><?php renderTable($pdo, $query, $page, ($page === 'students' && isset($group_id)) || ($page === 'all_students')); ?></section>
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
    <div id="adminLoginModal" style="display:none;" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"><div class="bg-white rounded-xl w-96 p-6"><div class="flex justify-between mb-4"><h3 class="text-xl font-bold">Вход в админ-режим</h3><button onclick="closeAdminLogin()" class="text-gray-500">&times;</button></div><form onsubmit="adminLogin(event)"><input type="password" id="adminPassword" placeholder="Пароль" class="w-full p-2 border rounded-lg mb-4"><button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg">Войти</button></form></div></div>
    
    <script>
    function showAdminLogin(){document.getElementById('adminLoginModal').style.display='flex';}
    function closeAdminLogin(){document.getElementById('adminLoginModal').style.display='none';}
    function adminLogin(e){e.preventDefault();const pwd=document.getElementById('adminPassword').value;fetch("?admin_action=login",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({password:pwd})}).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert("Неверный пароль!");});}
    </script>
</body>
</html>