<?php
require_once 'login.php';

session_start();
$page = $_GET['page'] ?? 'dashboard';
$title = "Главная";
$query = "";
$student_info = null;
$search = $_GET['search'] ?? '';
$teacher_id = $_SESSION['teacher_id'] ?? null;

switch ($page) {
    // ==================== УЧЕБНЫЙ ПРОЦЕСС ====================
    case 'students':
        $type = $_GET['search_type'] ?? null; 
        $keyword = $_GET['keyword'] ?? null;
        $fac_id = $_GET['fac_id'] ?? null;
        $dep_id = $_GET['dep_id'] ?? null;
        $group_id = $_GET['group_id'] ?? null;
        $global_search = $_GET['global_search'] ?? '';

        // Глобальный поиск по категориям
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
            $query = "SELECT stud_id AS 'ID', full_name AS 'ФИО', gender AS 'Пол', admission_year AS 'Год поступления' 
                      FROM students 
                      WHERE groups_group_id = " . intval($group_id) . $where;
        } elseif ($dep_id) {
            $title = "Группы кафедры";
            $where = $global_search ? " WHERE group_name LIKE '%$global_search%'" : "";
            $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа', course AS 'Курс' 
                      FROM `groups` 
                      WHERE departments_dep_id = " . intval($dep_id) . $where;
        } elseif ($fac_id) {
            $title = "Кафедры факультета";
            $where = $global_search ? " WHERE dep_name LIKE '%$global_search%'" : "";
            $query = "SELECT dep_id AS 'ID_D', dep_name AS 'Кафедра' 
                      FROM departments 
                      WHERE faculties_fac_id = " . intval($fac_id) . $where;
        } else {
            if ($type === 'fac') {
                $title = "Поиск факультета";
                $where = $keyword ? " WHERE fac_name LIKE '%$keyword%'" : "";
                $query = "SELECT fac_id AS 'ID_F', fac_name AS 'Название факультета' 
                          FROM faculties $where";
            } elseif ($type === 'dep') {
                $title = "Поиск кафедры";
                $where = $keyword ? " WHERE dep_name LIKE '%$keyword%'" : "";
                $query = "SELECT dep_id AS 'ID_D', dep_name AS 'Название кафедры' 
                          FROM departments $where";
            } elseif ($type === 'group') {
                $title = "Поиск группы";
                $where = $keyword ? " WHERE group_name LIKE '%$keyword%'" : "";
                $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа', course AS 'Курс' 
                          FROM `groups` $where";
            } else {
                $title = "Реестр: Выберите категорию";
                $query = ""; 
            }
        }
        break;

    // Список всех студентов (полная информация)
    case 'all_students':
        $title = "Полный список студентов";
        $where = $search ? " WHERE s.full_name LIKE '%$search%' OR s.stud_id LIKE '%$search%' OR g.group_name LIKE '%$search%'" : "";
        $query = "SELECT 
                    s.stud_id AS 'ID студента',
                    s.full_name AS 'ФИО',
                    s.stud_phone AS 'Телефон',
                    s.birth_date AS 'Год рождения',
                    s.admission_year AS 'Год поступления',
                    g.group_name AS 'Группа',
                    g.course AS 'Курс',
                    d.dep_name AS 'Кафедра'
                  FROM students s
                  JOIN `groups` g ON s.groups_group_id = g.group_id
                  JOIN departments d ON g.departments_dep_id = d.dep_id
                  $where
                  ORDER BY s.full_name";
        break;

    // Архив выпускников
    case 'archive':
        $title = "Архив выпускников";
        try {
            $check_graduated_students = $pdo->query("SHOW TABLES LIKE 'graduated_students'")->rowCount();
            $check_graduated_groups = $pdo->query("SHOW TABLES LIKE 'graduated_groups'")->rowCount();
            
            if ($check_graduated_students > 0 && $check_graduated_groups > 0) {
                $where = $search ? " WHERE s.full_name LIKE '%$search%' OR g.group_name LIKE '%$search%'" : "";
                $query = "SELECT 
                            s.stud_id AS 'ID студента',
                            s.full_name AS 'ФИО',
                            s.stud_phone AS 'Телефон',
                            s.birth_date AS 'Год рождения',
                            s.admission_year AS 'Год поступления',
                            g.group_name AS 'Группа',
                            s.graduation_year AS 'Год выпуска'
                          FROM graduated_students s
                          JOIN graduated_groups g ON s.groups_group_id = g.group_id
                          $where
                          ORDER BY s.graduation_year DESC, s.full_name";
            } else {
                $query = null;
            }
        } catch (PDOException $e) {
            $query = null;
        }
        break;

    case 'student_info':
        $title = "Поиск зачетной книжки";
        $student_id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if ($student_id) {
            $student_id = intval($student_id);
            $info_stmt = $pdo->prepare("SELECT s.full_name, g.group_name 
                                        FROM students s 
                                        JOIN `groups` g ON s.groups_group_id = g.group_id 
                                        WHERE s.stud_id = ?");
            $info_stmt->execute([$student_id]);
            $student_info = $info_stmt->fetch();

            if ($student_info) {
                $title = "Зачетка: " . $student_info['full_name'];
                $query = "SELECT 
                            sub.sub_name AS 'Дисциплина',
                            g.grade AS 'Оценка',
                            t.teach_name AS 'Преподаватель'
                          FROM grades g
                          JOIN subjects sub ON g.subjects_sub_id = sub.sub_id
                          JOIN teacher t ON sub.teacher_teach_id = t.teach_id
                          WHERE g.students_stud_id = $student_id
                          ORDER BY sub.sub_name";
            }
        }
        break;

    case 'debtors':
        $title = "Студенты-должники (имеют оценку 2)";
        $where = $search ? " AND s.full_name LIKE '%$search%'" : "";
        $query = "SELECT DISTINCT 
                    s.stud_id AS 'ID', 
                    s.full_name AS 'ФИО', 
                    g.group_name AS 'Группа'
                  FROM students s
                  JOIN grades gr ON s.stud_id = gr.students_stud_id
                  JOIN `groups` g ON s.groups_group_id = g.group_id
                  WHERE gr.grade = 2 $where
                  ORDER BY s.full_name";
        break;

    case 'retake':
        $title = "Пересдачи (студенты с оценкой 2)";
        $where = $search ? " AND (s.full_name LIKE '%$search%' OR sub.sub_name LIKE '%$search%' OR t.teach_name LIKE '%$search%')" : "";
        $query = "SELECT 
                    s.full_name AS 'Студент', 
                    sub.sub_name AS 'Дисциплина', 
                    t.teach_name AS 'Преподаватель',
                    gr.grade AS 'Оценка',
                    COALESCE(r.reason, 'Низкий балл') AS 'Причина пересдачи'
                  FROM grades gr
                  JOIN students s ON gr.students_stud_id = s.stud_id
                  JOIN subjects sub ON gr.subjects_sub_id = sub.sub_id
                  JOIN teacher t ON sub.teacher_teach_id = t.teach_id
                  LEFT JOIN reexams r ON r.stud_id = s.stud_id AND r.sub_id = sub.sub_id
                  WHERE gr.grade = 2 $where
                  ORDER BY s.full_name";
        break;

    // ==================== АНАЛИТИКА ====================
    case 'top':
        $title = "Рейтинг лучших студентов (средний балл >= 4.5)";
        $where = $search ? " WHERE `ФИО студента` LIKE '%$search%'" : "";
        $query = "SELECT 
                    `Инд. номер студента` AS 'ID',
                    `ФИО студента` AS 'Студент',
                    `Средний балл студента` AS 'Средний балл'
                  FROM top_students $where 
                  ORDER BY `Средний балл студента` DESC";
        break;

    case 'scholarship':
        $title = "Расчет стипендии";
        $where = $search ? " HAVING `Студент` LIKE '%$search%'" : "";
        $query = "SELECT 
                    s.full_name AS 'Студент',
                    CASE 
                        WHEN MIN(g.grade) = 5 THEN 1500  
                        WHEN MIN(g.grade) <= 3 THEN 0    
                        ELSE 1000                         
                    END AS 'Стипендия (руб.)',
                    ROUND(AVG(g.grade), 2) AS 'Средний балл'
                  FROM students AS s
                  JOIN grades AS g ON s.stud_id = g.students_stud_id
                  GROUP BY s.stud_id
                  $where
                  ORDER BY `Стипендия (руб.)` DESC, `Средний балл` DESC";
        break;

    case 'stats_group':
        $title = "Средний балл по группам";
        $where = $search ? " HAVING `Группа` LIKE '%$search%'" : "";
        $query = "SELECT 
                    gr.group_id AS 'ID группы',
                    gr.group_name AS 'Группа', 
                    ROUND(AVG(g.grade), 2) AS 'Средний балл',
                    COUNT(DISTINCT s.stud_id) AS 'Кол-во студентов'
                  FROM grades AS g
                  JOIN students AS s ON g.students_stud_id = s.stud_id
                  JOIN `groups` AS gr ON s.groups_group_id = gr.group_id
                  GROUP BY gr.group_id
                  $where
                  ORDER BY `Средний балл` DESC";
        break;

    case 'stats_sub':
        $title = "Средний балл по дисциплинам";
        $where = $search ? " HAVING `Дисциплина` LIKE '%$search%'" : "";
        $query = "SELECT 
                    su.sub_id AS 'ID дисциплины',
                    su.sub_name AS 'Дисциплина', 
                    ROUND(AVG(g.grade), 2) AS 'Средний балл',
                    COUNT(DISTINCT g.students_stud_id) AS 'Кол-во студентов'
                  FROM grades AS g
                  JOIN subjects AS su ON g.subjects_sub_id = su.sub_id
                  GROUP BY su.sub_id
                  $where
                  ORDER BY `Средний балл` DESC";
        break;

    // ==================== АДМИНИСТРИРОВАНИЕ ====================
    case 'dep_data':
        $title = "Данные по кафедрам";
        $where = $search ? " WHERE `Кафедра` LIKE '%$search%'" : "";
        $query = "SELECT 
                    `Кафедра`,
                    `ФИО Заведеющего`,
                    `Телефон`,
                    `Кол-во групп`,
                    `Кол-во преподавателей`,
                    `Факультет`
                  FROM dep_data $where";
        break;

    case 'groups_data':
        $title = "Состав групп";
        $where = $search ? " WHERE `Название группы` LIKE '%$search%'" : "";
        $query = "SELECT 
                    `Название группы`,
                    `Курс`,
                    `Кафедра`,
                    `Кол-во студентов`
                  FROM groups_data $where";
        break;

    case 'teacher_load':
        $title = "Нагрузка преподавателей";
        $where = $search ? " WHERE `Преподаватель` LIKE '%$search%' OR `Кафедра` LIKE '%$search%'" : "";
        $query = "SELECT 
                    t.teach_id AS 'ID преподавателя',
                    t.teach_name AS 'Преподаватель',
                    d.dep_name AS 'Кафедра',
                    COUNT(s.sub_id) AS 'Кол-во дисциплин',
                    GROUP_CONCAT(DISTINCT s.sub_name SEPARATOR ', ') AS 'Дисциплины'
                  FROM teacher AS t 
                  LEFT JOIN subjects AS s ON t.teach_id = s.teacher_teach_id
                  LEFT JOIN departments d ON t.departments_dep_id = d.dep_id
                  GROUP BY t.teach_id
                  $where
                  ORDER BY `Преподаватель`";
        break;

    case 'add_group':
        $title = "Добавление новой группы";
        break;

    case 'annual_promotion':
        $title = "Перевод на следующий курс";
        break;

    case 'teacher_panel':
        $title = "Панель преподавателя";
        if (!$teacher_id) {
            $title = "Вход в панель преподавателя";
        }
        break;

    case 'add_grade':
        $title = "Выставление оценки";
        break;

    case 'manage':
        $title = "Управление студентами";
        break;
}

// ==================== ФУНКЦИЯ ДЛЯ ВЫВОДА ТАБЛИЦЫ ====================
function renderTable($pdo, $query, $page = '', $showActions = false) {
    if (!$query) return;
    
    try {
        $res = $pdo->query($query);
    } catch (PDOException $e) {
        echo '<div class="p-10 text-center bg-red-50 rounded-lg">
                <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-3"></i>
                <p class="text-red-600">Ошибка запроса: ' . htmlspecialchars($e->getMessage()) . '</p>
              </div>';
        return;
    }
    
    $data = $res->fetchAll();

    if (!$data) {
        echo '<div class="p-10 text-center">
                <i class="fas fa-search text-4xl text-gray-200 mb-3"></i>
                <p class="text-gray-400">По вашему запросу ничего не найдено</p>
              </div>';
        return;
    }

    $cols = array_keys($data[0]);
    ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 text-xs uppercase font-bold sticky top-0">
                <tr>
                    <?php foreach ($cols as $c): ?>
                        <th class="px-6 py-4"><?= htmlspecialchars($c) ?></th>
                    <?php endforeach; ?>
                    <?php if ($showActions): ?>
                        <th class="px-6 py-4 text-center">Действия</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $row): ?>
                    <tr class="hover:bg-blue-50/50 transition-colors">
                        <?php foreach ($row as $key => $val): ?>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php if ($key === 'type'): ?>
                                    <?php if ($val === 'faculty'): ?>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">🏛️ Факультет</span>
                                    <?php elseif ($val === 'department'): ?>
                                        <span class="px-2 py-1 bg-emerald-100 text-emerald-800 text-xs rounded-full">📚 Кафедра</span>
                                    <?php elseif ($val === 'group'): ?>
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">👥 Группа</span>
                                    <?php elseif ($val === 'student'): ?>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">🎓 Студент</span>
                                    <?php elseif ($val === 'teacher'): ?>
                                        <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">👨‍🏫 Преподаватель</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($val) ?>
                                    <?php endif; ?>
                                <?php elseif ($key === 'id'): ?>
                                    <?php if ($row['type'] === 'faculty'): ?>
                                        <a href="?page=students&fac_id=<?= $val ?>" class="text-blue-600 hover:underline">
                                            🔍 <?= htmlspecialchars($val) ?>
                                        </a>
                                    <?php elseif ($row['type'] === 'department'): ?>
                                        <a href="?page=students&dep_id=<?= $val ?>" class="text-emerald-600 hover:underline">
                                            🔍 <?= htmlspecialchars($val) ?>
                                        </a>
                                    <?php elseif ($row['type'] === 'group'): ?>
                                        <a href="?page=students&group_id=<?= $val ?>" class="text-purple-600 hover:underline">
                                            🔍 <?= htmlspecialchars($val) ?>
                                        </a>
                                    <?php elseif ($row['type'] === 'student'): ?>
                                        <a href="?page=student_info&id=<?= $val ?>" class="text-blue-600 hover:underline">
                                            🔍 <?= htmlspecialchars($val) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($val) ?>
                                    <?php endif; ?>
                                <?php elseif ($key === 'name'): ?>
                                    <?= htmlspecialchars($val) ?>
                                <?php elseif ($key === 'extra' && $row['type'] === 'group'): ?>
                                    <?= $val ?>
                                <?php elseif ($key === 'extra' && $row['type'] === 'student'): ?>
                                    Тел: <?= $val ?>
                                <?php elseif ($key === 'ID_F'): ?>
                                    <a href="?page=students&fac_id=<?= $val ?>" class="text-blue-600 font-bold hover:underline">
                                        🏛️ Смотреть факультет
                                    </a>
                                <?php elseif ($key === 'ID_D'): ?>
                                    <a href="?page=students&dep_id=<?= $val ?>" class="text-emerald-600 font-bold hover:underline">
                                        📚 Смотреть кафедру
                                    </a>
                                <?php elseif ($key === 'ID_G'): ?>
                                    <a href="?page=students&group_id=<?= $val ?>" class="text-purple-600 font-bold hover:underline">
                                        👥 Смотреть группу
                                    </a>
                                <?php elseif (isset($row['ID студента']) && $key === 'ID студента'): ?>
                                    <a href="?page=student_info&id=<?= $val ?>" class="text-blue-600 hover:underline">
                                        🔍 <?= htmlspecialchars($val) ?>
                                    </a>
                                <?php elseif (isset($row['ID']) && $key === 'ID'): ?>
                                    <a href="?page=student_info&id=<?= $val ?>" class="text-blue-600 hover:underline">
                                        🔍 <?= htmlspecialchars($val) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($val) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <?php if ($showActions && isset($row['ID'])): ?>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-3">
                                    <a href="?page=student_info&id=<?= $row['ID'] ?>" 
                                       class="text-blue-500 hover:text-blue-700" 
                                       title="Посмотреть зачетку">
                                        <i class="fas fa-address-card"></i>
                                    </a>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?> | Deanery System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans flex min-h-screen">

    <!-- ==================== БОКОВОЕ МЕНЮ ==================== -->
    <aside class="bg-slate-900 text-white w-64 flex-shrink-0 hidden md:flex flex-col min-h-screen">
        <header class="p-6 flex items-center gap-3 border-b border-slate-800">
            <i class="fas fa-university text-2xl text-blue-400"></i>
            <h1 class="text-xl font-bold tracking-tight">УНИВЕР</h1>
        </header>
        
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <!-- Учебный процесс -->
            <section>
                <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">📖 Учебный процесс</p>
                <div class="space-y-1">
                    <a href="?page=students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='students'?'bg-blue-600':'' ?>">
                        <i class="fas fa-user-graduate w-6"></i> Реестр
                    </a>
                    <a href="?page=all_students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='all_students'?'bg-blue-600':'' ?>">
                        <i class="fas fa-list w-6"></i> Все студенты
                    </a>
                    <a href="?page=student_info" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='student_info'?'bg-blue-600':'' ?>">
                        <i class="fas fa-address-card w-6"></i> Зачетные книжки
                    </a>
                    <a href="?page=debtors" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='debtors'?'bg-blue-600':'' ?>">
                        <i class="fas fa-user-slash w-6"></i> Должники
                    </a>
                    <a href="?page=retake" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='retake'?'bg-blue-600':'' ?>">
                        <i class="fas fa-clock w-6"></i> Пересдачи
                    </a>
                </div>
            </section>

            <!-- Аналитика -->
            <section>
                <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">📊 Аналитика</p>
                <div class="space-y-1">
                    <a href="?page=top" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='top'?'bg-blue-600':'' ?>">
                        <i class="fas fa-medal w-6"></i> Отличники
                    </a>
                    <a href="?page=scholarship" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='scholarship'?'bg-blue-600':'' ?>">
                        <i class="fas fa-wallet w-6"></i> Стипендия
                    </a>
                    <a href="?page=stats_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_group'?'bg-blue-600':'' ?>">
                        <i class="fas fa-chart-bar w-6"></i> Рейтинг групп
                    </a>
                    <a href="?page=stats_sub" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats_sub'?'bg-blue-600':'' ?>">
                        <i class="fas fa-chart-line w-6"></i> Рейтинг дисциплин
                    </a>
                </div>
            </section>

            <!-- Преподавательская -->
            <section>
                <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">👨‍🏫 Преподавательская</p>
                <div class="space-y-1">
                    <a href="?page=teacher_panel" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='teacher_panel'?'bg-blue-600':'' ?>">
                        <i class="fas fa-chalkboard-teacher w-6"></i> Вход для преподавателя
                    </a>
                    <a href="?page=add_grade" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='add_grade'?'bg-blue-600':'' ?>">
                        <i class="fas fa-pen w-6"></i> Выставить оценку
                    </a>
                </div>
            </section>

            <!-- Администрирование -->
            <section>
                <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">⚙️ Администрирование</p>
                <div class="space-y-1">
                    <a href="?page=dep_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='dep_data'?'bg-blue-600':'' ?>">
                        <i class="fas fa-building w-6"></i> Кафедры
                    </a>
                    <a href="?page=groups_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='groups_data'?'bg-blue-600':'' ?>">
                        <i class="fas fa-users w-6"></i> Группы
                    </a>
                    <a href="?page=teacher_load" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='teacher_load'?'bg-blue-600':'' ?>">
                        <i class="fas fa-chalkboard-teacher w-6"></i> Нагрузка преподавателей
                    </a>
                    <a href="?page=add_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='add_group'?'bg-blue-600':'' ?>">
                        <i class="fas fa-plus-circle w-6"></i> Добавить группу
                    </a>
                    <a href="?page=annual_promotion" class="flex items-center p-2 rounded-lg hover:bg-yellow-800 bg-yellow-700/20 mt-2 border border-yellow-500/30">
                        <i class="fas fa-calendar-alt w-6 text-yellow-400"></i>
                        <span class="text-yellow-300">Годовой перевод</span>
                    </a>
                    <a href="?page=manage" class="flex items-center p-2 rounded-lg hover:bg-green-800 bg-green-700/20 mt-2 border border-green-500/30">
                        <i class="fas fa-user-plus w-6 text-green-400"></i> 
                        <span class="text-green-300">Управление студентами</span>
                    </a>
                    <a href="?page=archive" class="flex items-center p-2 rounded-lg hover:bg-gray-800 bg-gray-700/20 mt-2 border border-gray-500/30">
                        <i class="fas fa-archive w-6 text-gray-400"></i>
                        <span class="text-gray-300">Архив выпускников</span>
                    </a>
                </div>
            </section>
        </nav>
    </aside>

    <!-- ==================== ОСНОВНОЙ КОНТЕНТ ==================== -->
    <main class="flex-1 flex flex-col min-w-0">
        <header class="bg-white p-6 shadow-sm flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800"><?= $title ?></h2>
            <div class="text-sm text-gray-500">
                Пользователь: <strong>Admin</strong>
            </div>
        </header>

        <div class="p-6">
            <!-- ПОИСК ТОЛЬКО ДЛЯ РЕЕСТРА -->
            <?php if ($page === 'students'): ?>
                <div class="mb-6">
                    <form method="GET" class="max-w-2xl">
                        <input type="hidden" name="page" value="students">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="global_search" value="<?= htmlspecialchars($_GET['global_search'] ?? '') ?>" 
                                   placeholder="🔍 Глобальный поиск: факультеты, кафедры, группы, студенты, преподаватели..." 
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Поиск по всем категориям: факультетам, кафедрам, группам, студентам и преподавателям</p>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Обычный поиск для других страниц -->
            <?php if ($page !== 'students' && $page !== 'student_info' && $page !== 'manage' && $page !== 'teacher_panel' && $page !== 'add_grade' && $page !== 'add_group' && $page !== 'annual_promotion' && $page !== 'dashboard' && $query): ?>
                <div class="mb-6">
                    <form method="GET" class="max-w-md">
                        <input type="hidden" name="page" value="<?= $page ?>">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="🔍 Поиск по текущей странице..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Блок выбора категории для реестра (только если нет глобального поиска) -->
            <?php if ($page === 'students' && !isset($_GET['global_search']) && !$type && !$fac_id && !$dep_id && !$group_id): ?>
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <a href="?page=students&search_type=fac" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-blue-500 text-center group">
                        <i class="fas fa-university text-4xl text-blue-500 mb-4 group-hover:scale-110 transition"></i>
                        <h4 class="font-bold text-lg">По факультетам</h4>
                        <p class="text-sm text-gray-500 mt-2">Просмотр по факультетам</p>
                    </a>
                    <a href="?page=students&search_type=dep" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-emerald-500 text-center group">
                        <i class="fas fa-building text-4xl text-emerald-500 mb-4 group-hover:scale-110 transition"></i>
                        <h4 class="font-bold text-lg">По кафедрам</h4>
                        <p class="text-sm text-gray-500 mt-2">Просмотр по кафедрам</p>
                    </a>
                    <a href="?page=students&search_type=group" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-purple-500 text-center group">
                        <i class="fas fa-users text-4xl text-purple-500 mb-4 group-hover:scale-110 transition"></i>
                        <h4 class="font-bold text-lg">По группам</h4>
                        <p class="text-sm text-gray-500 mt-2">Просмотр по группам</p>
                    </a>
                </section>
            <?php endif; ?>

            <!-- Поиск в реестре (когда уже выбран факультет/кафедра/группа) -->
            <?php if ($page === 'students' && ($fac_id || $dep_id || $group_id) && !isset($_GET['global_search'])): ?>
                <div class="mb-6">
                    <form method="GET" class="max-w-md">
                        <input type="hidden" name="page" value="students">
                        <?php if ($fac_id): ?>
                            <input type="hidden" name="fac_id" value="<?= $fac_id ?>">
                        <?php elseif ($dep_id): ?>
                            <input type="hidden" name="dep_id" value="<?= $dep_id ?>">
                        <?php elseif ($group_id): ?>
                            <input type="hidden" name="group_id" value="<?= $group_id ?>">
                        <?php endif; ?>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="global_search" value="<?= htmlspecialchars($_GET['global_search'] ?? '') ?>" 
                                   placeholder="🔍 Поиск по текущему списку..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Архив выпускников -->
            <?php if ($page === 'archive'): ?>
                <?php if ($query): ?>
                    <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                        <div class="p-4 bg-gray-50 border-b">
                            <h3 class="text-lg font-semibold text-gray-700">
                                <i class="fas fa-archive text-gray-500 mr-2"></i> Архив выпускников
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">Студенты, успешно завершившие обучение</p>
                        </div>
                        <?php renderTable($pdo, $query, $page, false); ?>
                    </section>
                <?php else: ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <div class="flex">
                            <i class="fas fa-info-circle text-yellow-400 mr-3"></i>
                            <div>
                                <p class="text-yellow-700">Архив пока пуст или таблицы архива не созданы.</p>
                                <p class="text-sm text-yellow-600 mt-1">Выпускники появятся здесь после выполнения годового перевода.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <!-- Добавление группы -->
            <?php elseif ($page === 'add_group'): ?>
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-xl font-bold mb-6">Добавление новой группы</h3>
                        <form action="actions.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_group">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Название группы</label>
                                <input type="text" name="group_name" required 
                                       class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Курс</label>
                                <select name="course" required class="w-full p-2 border rounded-lg">
                                    <option value="1">1 курс</option>
                                    <option value="2">2 курс</option>
                                    <option value="3">3 курс</option>
                                    <option value="4">4 курс</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Кафедра</label>
                                <select name="dep_id" required class="w-full p-2 border rounded-lg">
                                    <option value="">Выберите кафедру</option>
                                    <?php
                                    $deps = $pdo->query("SELECT dep_id, dep_name FROM departments ORDER BY dep_name");
                                    while($dep = $deps->fetch()):
                                    ?>
                                    <option value="<?= $dep['dep_id'] ?>"><?= htmlspecialchars($dep['dep_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-save"></i> Добавить группу
                            </button>
                        </form>
                    </div>
                </div>

            <!-- Годовой перевод -->
            <?php elseif ($page === 'annual_promotion'): ?>
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-center mb-6">
                            <i class="fas fa-calendar-alt text-5xl text-yellow-500 mb-3"></i>
                            <h3 class="text-xl font-bold">Годовой перевод студентов на следующий курс</h3>
                            <p class="text-gray-500 mt-2">Эта операция переведет всех студентов на следующий курс.<br>
                            Студенты 4 курса будут выпущены (перенесены в архив).</p>
                            <div class="mt-4 p-3 bg-yellow-50 rounded-lg text-sm text-yellow-700">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Внимание! Это действие необратимо. Рекомендуется сделать резервную копию.
                            </div>
                        </div>
                        
                        <form action="actions.php" method="POST" class="space-y-4" 
                              onsubmit="return confirm('ВНИМАНИЕ! Вы уверены, что хотите выполнить годовой перевод? Все студенты 4 курса будут выпущены, остальные переведены на следующий курс. Это действие необратимо!')">
                            <input type="hidden" name="action" value="annual_promotion">
                            
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-bold mb-2">Текущая статистика:</h4>
                                <?php
                                $stats = $pdo->query("SELECT course, COUNT(*) as count FROM `groups` GROUP BY course ORDER BY course");
                                while($stat = $stats->fetch()):
                                    $course_num = $stat['course'];
                                    $groups_count = $stat['count'];
                                    $students_count = $pdo->query("SELECT COUNT(*) FROM students s JOIN `groups` g ON s.groups_group_id = g.group_id WHERE g.course = $course_num")->fetchColumn();
                                ?>
                                <div class="flex justify-between text-sm mb-1">
                                    <span><?= $course_num ?> курс:</span>
                                    <span><?= $groups_count ?> групп, <?= $students_count ?> студентов</span>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="p-3 bg-green-50 rounded-lg text-sm text-green-700">
                                <i class="fas fa-info-circle"></i>
                                После выполнения операции:
                                <ul class="list-disc list-inside mt-2 ml-2">
                                    <li>Студенты 1-3 курсов перейдут на следующий курс</li>
                                    <li>Студенты 4 курса будут перенесены в архив</li>
                                    <li>Группы 4 курса будут перенесены в архив</li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="w-full bg-yellow-600 text-white py-3 rounded-lg hover:bg-yellow-700 font-bold">
                                <i class="fas fa-arrow-right"></i> Выполнить годовой перевод
                            </button>
                        </form>
                    </div>
                </div>

            <!-- Панель преподавателя (вход) -->
            <?php elseif ($page === 'teacher_panel'): ?>
                <?php if (!$teacher_id): ?>
                    <div class="max-w-md mx-auto">
                        <form method="POST" action="actions.php" class="bg-white p-8 rounded-xl shadow-sm">
                            <input type="hidden" name="action" value="teacher_login">
                            <h3 class="text-xl font-bold mb-6 text-gray-800">Вход в панель преподавателя</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID преподавателя</label>
                                    <input type="number" name="teacher_id" required 
                                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Введите свой ID из таблицы teacher</p>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-sign-in-alt"></i> Войти
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded mb-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <i class="fas fa-check-circle text-green-400"></i>
                                <?php
                                $teacher = $pdo->prepare("SELECT teach_name FROM teacher WHERE teach_id = ?");
                                $teacher->execute([$teacher_id]);
                                $teacher_name = $teacher->fetchColumn();
                                ?>
                                <span class="font-bold">Вы вошли как: <?= htmlspecialchars($teacher_name) ?></span>
                            </div>
                            <a href="actions.php?action=teacher_logout" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-sign-out-alt"></i> Выйти
                            </a>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="font-bold text-lg mb-4">Ваши дисциплины</h3>
                            <?php
                            $subjects = $pdo->prepare("SELECT sub_id, sub_name FROM subjects WHERE teacher_teach_id = ?");
                            $subjects->execute([$teacher_id]);
                            if ($subjects->rowCount() > 0):
                            ?>
                            <ul class="space-y-2">
                                <?php while($sub = $subjects->fetch()): ?>
                                <li class="flex justify-between items-center p-2 bg-gray-50 rounded">
                                    <span><?= htmlspecialchars($sub['sub_name']) ?></span>
                                    <a href="?page=add_grade&subject_id=<?= $sub['sub_id'] ?>" 
                                       class="text-blue-500 hover:text-blue-700 text-sm">
                                        Выставить оценки <i class="fas fa-arrow-right"></i>
                                    </a>
                                </li>
                                <?php endwhile; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-gray-500">У вас пока нет закрепленных дисциплин</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <!-- Выставление оценки -->
            <?php elseif ($page === 'add_grade'): ?>
                <?php if (!$teacher_id): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <p class="text-yellow-700">Сначала войдите в панель преподавателя</p>
                        <a href="?page=teacher_panel" class="text-blue-500 hover:underline mt-2 inline-block">Перейти ко входу</a>
                    </div>
                <?php else: ?>
                    <?php
                    $teacher = $pdo->prepare("SELECT teach_name FROM teacher WHERE teach_id = ?");
                    $teacher->execute([$teacher_id]);
                    $teacher_name = $teacher->fetchColumn();
                    ?>
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded mb-6">
                        <i class="fas fa-user-check text-blue-400"></i>
                        <span class="font-bold">Вы под именем: <?= htmlspecialchars($teacher_name) ?> (ID: <?= $teacher_id ?>)</span>
                    </div>
                    
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h3 class="text-xl font-bold mb-6">Выставить оценку</h3>
                            <form method="POST" action="actions.php" class="space-y-4" id="gradeForm">
                                <input type="hidden" name="action" value="add_grade">
                                <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Дисциплина</label>
                                    <select name="subject_id" required class="w-full p-2 border rounded-lg" id="subject_id">
                                        <option value="">Выберите дисциплину</option>
                                        <?php
                                        $subjects = $pdo->prepare("SELECT sub_id, sub_name FROM subjects WHERE teacher_teach_id = ?");
                                        $subjects->execute([$teacher_id]);
                                        while($sub = $subjects->fetch()):
                                            $selected = ($_GET['subject_id'] ?? '') == $sub['sub_id'] ? 'selected' : '';
                                        ?>
                                        <option value="<?= $sub['sub_id'] ?>" <?= $selected ?>><?= htmlspecialchars($sub['sub_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID студента</label>
                                    <input type="number" name="student_id" id="student_id" required 
                                           class="w-full p-2 border border-gray-300 rounded-lg">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Оценка (2-5)</label>
                                    <select name="grade" required class="w-full p-2 border rounded-lg" id="grade">
                                        <option value="">Выберите оценку</option>
                                        <option value="5">5 - Отлично</option>
                                        <option value="4">4 - Хорошо</option>
                                        <option value="3">3 - Удовлетворительно</option>
                                        <option value="2">2 - Неудовлетворительно</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                                    <i class="fas fa-save"></i> Выставить оценку
                                </button>
                            </form>
                            
                            <div class="mt-6 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                                <i class="fas fa-info-circle"></i> Примечание: При выставлении оценки 2, студент автоматически добавится в список на пересдачу
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    document.getElementById('gradeForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        var subject_id = document.getElementById('subject_id').value;
                        var student_id = document.getElementById('student_id').value;
                        var grade = document.getElementById('grade').value;
                        
                        if (!subject_id || !student_id || !grade) {
                            alert('Пожалуйста, заполните все поля');
                            return;
                        }
                        
                        fetch('check_grade.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                subject_id: subject_id,
                                student_id: student_id
                            })
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.exists) {
                                if (confirm('У студента уже есть оценка ' + data.grade + ' по этой дисциплине. Хотите изменить её на ' + grade + '?')) {
                                    document.getElementById('gradeForm').submit();
                                }
                            } else {
                                document.getElementById('gradeForm').submit();
                            }
                        });
                    });
                    </script>
                <?php endif; ?>

            <!-- Зачетная книжка -->
            <?php elseif ($page === 'student_info'): ?>
                <div class="max-w-md mx-auto mb-8">
                    <form method="GET" class="bg-white p-6 rounded-xl shadow-sm">
                        <input type="hidden" name="page" value="student_info">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Введите номер зачетной книжки (ID студента)
                        </label>
                        <div class="flex gap-2">
                            <input type="number" name="id" value="<?= isset($_GET['id']) ? $_GET['id'] : '' ?>" 
                                   placeholder="Например: 10001" 
                                   class="flex-1 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-search"></i> Найти
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($student_info && $query): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="p-6 border-b bg-gradient-to-r from-blue-50 to-white">
                            <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($student_info['full_name']) ?></h3>
                            <p class="text-gray-600 mt-1">Группа: <?= htmlspecialchars($student_info['group_name']) ?></p>
                        </div>
                        <?php renderTable($pdo, $query, $page); ?>
                    </div>
                <?php elseif (isset($_GET['id']) && $_GET['id'] !== '' && !$student_info): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mr-3"></i>
                            <p class="text-yellow-700">Студент с ID <?= htmlspecialchars($_GET['id']) ?> не найден</p>
                        </div>
                    </div>
                <?php endif; ?>

            <!-- Управление студентами -->
            <?php elseif ($page === 'manage'): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Форма добавления студента -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-green-600 p-4">
                            <h3 class="text-white text-xl font-bold flex items-center gap-2">
                                <i class="fas fa-user-plus"></i> Добавление нового студента
                            </h3>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_student">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID студента</label>
                                    <input type="number" name="stud_id" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ФИО студента</label>
                                    <input type="text" name="full_name" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Год поступления</label>
                                    <input type="number" name="admission_year" 
                                           class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата рождения (год)</label>
                                    <input type="number" name="birth_date" placeholder="ГГГГ"
                                           class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Пол</label>
                                    <select name="gender" required class="w-full p-2 border rounded-md">
                                        <option value="Мужской">Мужской</option>
                                        <option value="Женский">Женский</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Адрес</label>
                                    <input type="text" name="address" 
                                           class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Город</label>
                                    <input type="text" name="city" value="Уфа"
                                           class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Телефон</label>
                                    <input type="text" name="phone" placeholder="+7XXXXXXXXXX"
                                           class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID группы</label>
                                    <select name="group_id" required class="w-full p-2 border rounded-md">
                                        <option value="">Выберите группу</option>
                                        <?php
                                        $groups = $pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name");
                                        while($group = $groups->fetch()):
                                        ?>
                                        <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-md hover:bg-green-700">
                                    <i class="fas fa-save"></i> Добавить студента
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Форма перевода студента -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-blue-600 p-4">
                            <h3 class="text-white text-xl font-bold flex items-center gap-2">
                                <i class="fas fa-exchange-alt"></i> Перевод студента
                            </h3>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="transfer">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID студента</label>
                                    <input type="number" name="stud_id" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID новой группы</label>
                                    <select name="new_group_id" required class="w-full p-2 border rounded-md">
                                        <option value="">Выберите группу</option>
                                        <?php
                                        $groups = $pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name");
                                        while($group = $groups->fetch()):
                                        ?>
                                        <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-arrow-right"></i> Выполнить перевод
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Форма удаления студента -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-red-600 p-4">
                            <h3 class="text-white text-xl font-bold flex items-center gap-2">
                                <i class="fas fa-trash-alt"></i> Удаление студента
                            </h3>
                            <p class="text-red-100 text-sm mt-1">⚠️ Внимание! Это действие необратимо</p>
                        </div>
                        <div class="p-6">
                            <form action="actions.php" method="POST" class="space-y-4" 
                                  onsubmit="return confirm('ВНИМАНИЕ! Вы уверены, что хотите полностью удалить студента? Все его оценки будут удалены безвозвратно!')">
                                <input type="hidden" name="action" value="delete_student">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ID студента для удаления</label>
                                    <input type="number" name="stud_id" required 
                                           class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500">
                                    <p class="text-xs text-gray-500 mt-1">Будут удалены: записи об успеваемости и данные студента</p>
                                </div>
                                
                                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-md hover:bg-red-700">
                                    <i class="fas fa-trash-alt"></i> Полностью удалить студента
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Обычная таблица для остальных страниц -->
            <?php elseif ($query): ?>
                <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <?php 
                    $showActions = ($page === 'students' && isset($group_id)) || ($page === 'all_students');
                    renderTable($pdo, $query, $page, $showActions); 
                    ?>
                </section>

            <!-- Главная страница -->
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="?page=all_students" class="stat-card block">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6 rounded-xl text-white shadow-lg">
                            <i class="fas fa-users text-4xl opacity-80 mb-4"></i>
                            <h4 class="text-lg opacity-90 uppercase tracking-wide">Студентов</h4>
                            <p class="text-5xl font-black mt-2">
                                <?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?>
                            </p>
                            <p class="text-sm opacity-75 mt-2">Нажмите для просмотра →</p>
                        </div>
                    </a>
                    
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 rounded-xl text-white shadow-lg">
                        <i class="fas fa-chart-line text-4xl opacity-80 mb-4"></i>
                        <h4 class="text-lg opacity-90 uppercase tracking-wide">Средний балл</h4>
                        <p class="text-5xl font-black mt-2">
                            <?= round($pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(), 2) ?>
                        </p>
                    </div>
                    
                    <a href="?page=scholarship" class="stat-card block">
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-6 rounded-xl text-white shadow-lg">
                            <i class="fas fa-wallet text-4xl opacity-80 mb-4"></i>
                            <h4 class="text-lg opacity-90 uppercase tracking-wide">Стипендиатов</h4>
                            <p class="text-5xl font-black mt-2">
                                <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM (
                                        SELECT s.stud_id
                                        FROM students s
                                        JOIN grades g ON s.stud_id = g.students_stud_id
                                        GROUP BY s.stud_id
                                        HAVING MIN(g.grade) >= 4
                                    ) as scholars");
                                    echo $stmt->fetchColumn();
                                ?>
                            </p>
                            <p class="text-sm opacity-75 mt-2">Нажмите для просмотра →</p>
                        </div>
                    </a>

                    <a href="?page=teacher_load" class="stat-card block">
                        <div class="bg-gradient-to-br from-orange-500 to-orange-600 p-6 rounded-xl text-white shadow-lg">
                            <i class="fas fa-chalkboard-teacher text-4xl opacity-80 mb-4"></i>
                            <h4 class="text-lg opacity-90 uppercase tracking-wide">Преподавателей</h4>
                            <p class="text-5xl font-black mt-2">
                                <?= $pdo->query("SELECT COUNT(*) FROM teacher")->fetchColumn() ?>
                            </p>
                            <p class="text-sm opacity-75 mt-2">Нажмите для просмотра →</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>