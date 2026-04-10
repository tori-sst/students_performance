<?php
require_once 'login.php';

$page = $_GET['page'] ?? 'dashboard';
$title = "Главная";
$query = "";



switch ($page) {
    // Учебный процесс
        case 'students':
    $type = $_GET['search_type'] ?? null; 
    $keyword = $_GET['keyword'] ?? null; // Текст из поиска
    $fac_id = $_GET['fac_id'] ?? null;
    $dep_id = $_GET['dep_id'] ?? null;
    $group_id = $_GET['group_id'] ?? null;

    if ($group_id) {
        $title = "Состав группы";
        $query = "SELECT stud_id AS 'ID', full_name AS 'ФИО', gender AS 'Пол' FROM students WHERE groups_group_id = " . intval($group_id);
    } elseif ($dep_id) {
        $title = "Группы кафедры";
        $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа', course AS 'Курс' FROM `groups` WHERE departments_dep_id = " . intval($dep_id);
    } elseif ($fac_id) {
        $title = "Кафедры факультета";
        $query = "SELECT dep_id AS 'ID_D', dep_name AS 'Кафедра' FROM departments WHERE faculties_fac_id = " . intval($fac_id);
    } else {
        // Уровень поиска по категориям
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
            $query = "SELECT group_id AS 'ID_G', group_name AS 'Группа' FROM `groups` $where";
        } else {
            $title = "Реестр: Выберите категорию";
            $query = ""; 
        }
    }
    break;
    case 'student_info':
    $student_id = $_GET['id'] ?? null;
    $title = "Зачетная книжка";
    $student_info = null;
    $query = "";

    if ($student_id) {
        $student_id = intval($student_id);
        // 1. Получаем инфо о студенте
        $info_stmt = $pdo->prepare("SELECT s.full_name, g.group_name 
                                    FROM students s 
                                    JOIN `groups` g ON s.groups_group_id = g.group_id 
                                    WHERE s.stud_id = ?");
        $info_stmt->execute([$student_id]);
        $student_info = $info_stmt->fetch();

        if ($student_info) {
            $title = "Зачетка: " . $student_info['full_name'];
            // 2. Готовим запрос для оценок (вызывается в шаблоне ниже)
            $query = "CALL GetStudentGrades($student_id)";
        }
    }
    break;

    // Аналитика
    case 'top':
        $title = "Рейтинг лучших студентов";
        $query = "SELECT * FROM top_students ORDER BY `Средний балл студента` DESC";
        break;
    case 'scholarship':
        $title = "Расчет стипендии";
        $query = "SELECT s.full_name AS 'Студент', ROUND(AVG(g.grade), 2) AS 'Средний балл',
                  CASE WHEN AVG(g.grade) >= 4.5 THEN 1500 WHEN AVG(g.grade) >= 3.0 THEN 1000 ELSE 0 END AS 'Стипендия (руб.)'
                  FROM students AS s JOIN grades AS g ON s.stud_id = g.students_stud_id
                  GROUP BY s.stud_id";
        break;
    case 'stats_group':
        $title = "Средний балл по группам";
        $query = "SELECT 
                  gr.group_name AS 'Группа', 
                  ROUND(AVG(g.grade), 2) AS 'Средний балл'
                FROM grades AS g
                JOIN students AS s ON g.students_stud_id = s.stud_id
                JOIN `groups` AS gr ON s.groups_group_id = gr.group_id
                GROUP BY gr.group_name
                ORDER BY `Средний балл` DESC;";
    case 'stats_sub':
        $title = "Средний балл по дисциплинам";
        $query = "SELECT su.sub_name AS 'Дисциплина', ROUND(AVG(g.grade), 2) AS 'Средний балл по дисциплине'
                  FROM grades AS g
                  JOIN subjects AS su ON g.subjects_sub_id = su.sub_id
                  GROUP BY su.sub_name
                  ORDER BY `Средний балл по дисциплине` DESC;";
                
        break;

    // Админка
    case 'dep_data':
        $title = "Данные по кафедрам";
        $query = "SELECT * FROM dep_data";
        break;
    case 'groups_data':
        $title = "Состав групп";
        $query = "SELECT * FROM groups_data";
        break;
    case 'teacher_load':
        $title = "Нагрузка преподавателей";
        $query = "SELECT t.teach_name AS 'Преподаватель', COUNT(s.sub_id) AS 'Дисциплин'
                  FROM teacher AS t LEFT JOIN subjects AS s ON t.teach_id = s.teacher_teach_id
                  GROUP BY t.teach_id";
        break;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?> | Deanery System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cloudflare.com">
</head>
<body class="bg-gray-100 font-sans flex min-h-screen">

    
<aside class="bg-slate-900 text-white w-64 flex-shrink-0 hidden md:flex flex-col min-h-screen">
    <header class="p-6 flex items-center gap-3 border-b border-slate-800">
        <i class="fas fa-university text-2xl text-blue-400"></i>
        <h1 class="text-xl font-bold tracking-tight">УНИВЕР</h1>
    </header>
    
    <nav class="flex-1 overflow-y-auto p-4 space-y-6">
        <!-- Учебный процесс -->
        <section>
            <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">Учебный процесс</p>
            <div class="space-y-1">
                <a href="?page=students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='students'?'bg-blue-600':'' ?>">
                    <i class="fas fa-user-graduate w-6 text-sm"></i> Реестр
                </a>
                <a href="?page=student_info" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='student_info'?'bg-blue-600':'' ?>">
                    <i class="fas fa-address-card w-6 text-sm"></i> Зачетные книжки
                </a>
                <a href="?page=debtors" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='debtors'?'bg-blue-600':'' ?>">
                    <i class="fas fa-user-slash w-6 text-sm"></i> Должники
                </a>
                <a href="?page=retake" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='retake'?'bg-blue-600':'' ?>">
                    <i class="fas fa-clock w-6 text-sm"></i> Пересдачи
                </a>
            </div>
        </section>

        <!-- Аналитика -->
        <section>
            <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">Аналитика</p>
            <div class="space-y-1">
                <a href="?page=top" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='top'?'bg-blue-600':'' ?>">
                    <i class="fas fa-medal w-6 text-sm"></i> Отличники
                </a>
                <a href="?page=scholarship" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='scholarship'?'bg-blue-600':'' ?>">
                    <i class="fas fa-wallet w-6 text-sm"></i> Стипендия
                </a>
                <a href="?page=stats_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats'?'bg-blue-600':'' ?>">
                    <i class="fas fa-chart-bar w-6 text-sm"></i> Рейтинг групп
                </a>
                <a href="?page=stats_sub" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='stats'?'bg-blue-600':'' ?>">
                    <i class="fas fa-chart-bar w-6 text-sm"></i> Рейтинг дисциплин
                </a>
            </div>
        </section>

        <!-- Структура и Кадры -->
        <section>
            <p class="text-xs text-slate-500 uppercase font-bold px-3 mb-2">Админка</p>
            <div class="space-y-1">
                <a href="?page=dep_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='dep_data'?'bg-blue-600':'' ?>">
                    <i class="fas fa-building w-6 text-sm"></i> Кафедры
                </a>
                <a href="?page=groups_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='groups_data'?'bg-blue-600':'' ?>">
                    <i class="fas fa-users w-6 text-sm"></i> Группы
                </a>
                <a href="?page=teacher_load" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='teacher_load'?'bg-blue-600':'' ?>">
                    <i class="fas fa-chalkboard-teacher w-6 text-sm"></i> Преподаватели
                </a>
                
            </div>
        </section>

        <!-- Системные действия -->
        <section class="pt-4 border-t border-slate-800">
            <a href="?page=transfer" class="flex items-center p-2 text-orange-400 hover:bg-slate-800 rounded-lg">
                <i class="fas fa-exchange-alt w-6 text-sm"></i> Перевод студента
            </a>
        </section>
    </nav>
</aside>

    <!-- Main -->
    <main class="flex-1 flex flex-col min-w-0">
        <header class="bg-white p-6 shadow-sm flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800"><?= $title ?></h2>
            <div class="text-sm text-gray-500">Пользователь: <strong>Admin</strong></div>
        </header>
        <div class="p-6">
    <!-- БЛОК ВЫБОРА КАТЕГОРИИ (показывается только в начале Реестра) -->
    <?php if ($page === 'students' && !$type && !$fac_id && !$dep_id && !$group_id): ?>
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="?page=students&search_type=fac" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-blue-500 text-center group">
                <i class="fas fa-university text-4xl text-blue-500 mb-4 group-hover:scale-110 transition"></i>
                <h4 class="font-bold text-lg">По факультетам</h4>
                
            </a>
            <a href="?page=students&search_type=dep" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-emerald-500 text-center group">
                <i class="fas fa-building text-4xl text-emerald-500 mb-4 group-hover:scale-110 transition"></i>
                <h4 class="font-bold text-lg">По кафедрам</h4>
                
            </a>
            <a href="?page=students&search_type=group" class="p-8 bg-white rounded-xl shadow-sm hover:shadow-md transition border-t-4 border-purple-500 text-center group">
                <i class="fas fa-users text-4xl text-purple-500 mb-4 group-hover:scale-110 transition"></i>
                <h4 class="font-bold text-lg">По группам</h4>
                
            </a>
        </section>

        
    <?php endif; ?>

    <!-- Дальше идет твой стандартный блок вывода таблицы -->
    <?php if ($query): ?>
        <section class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
    <div class="overflow-x-auto">
        <?php 
        $res = $pdo->query($query);
        $data = $res->fetchAll();

        if ($data): 
            // Получаем названия столбцов из первого ряда данных
            $cols = array_keys($data[0]);
        ?>
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 text-xs uppercase font-bold">
                <tr>
                    <?php foreach ($cols as $c): ?>
                        <th class="px-6 py-4"><?= $c ?></th>
                    <?php endforeach; ?>
                    <th class="px-6 py-4 text-right">Действие</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($data as $row): ?>
                    <tr class="hover:bg-blue-50/50 transition-colors">
                        <?php foreach ($row as $key => $val): ?>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <!-- ЛОГИКА МАТРЕШКИ: делаем ID ссылками -->
                                <?php if ($key === 'ID_F'): ?>
                                    <a href="?page=students&fac_id=<?= $val ?>" class="text-blue-600 font-bold"> Выбрать факультет</a>
                                <?php elseif ($key === 'ID_D'): ?>
                                    <a href="?page=students&dep_id=<?= $val ?>" class="text-blue-600 font-bold"> Смотреть группы</a>
                                <?php elseif ($key === 'ID_G'): ?>
                                    <a href="?page=students&group_id=<?= $val ?>" class="text-blue-600 font-bold"> Состав группы</a>
                                <?php else: ?>
                                    <?= htmlspecialchars($val) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>

                        <!-- КНОПКИ ДЕЙСТВИЙ (удаление или просмотр зачетки) -->
                        <td class="px-6 py-4 text-right">
                            <?php if (isset($row['ID'])): // Если мы на уровне списка студентов ?>
                                <div class="flex justify-end gap-3">
                                    <a href="?page=student_info&id=<?= $row['ID'] ?>" title="Посмотреть оценки" class="text-blue-500 hover:text-blue-700">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <form action="actions.php" method="POST" onsubmit="return confirm('Отчислить студента?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['ID'] ?>">
                                        <button class="text-red-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            <?php else: ?>
                               
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="p-10 text-center">
                <i class="fas fa-search text-4xl text-gray-200 mb-3"></i>
                <p class="text-gray-400">По вашему запросу ничего не найдено</p>
            </div>
        <?php endif; ?>
    </div>
</section>

    <?php endif; ?>
    
</div>
        <div class="p-6">
            <?php if ($page === 'transfer'): ?>
                <!-- Форма перевода -->
                <section class="max-w-lg bg-white p-8 rounded-xl shadow-sm">
                    <form action="actions.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="transfer">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID студента</label>
                            <input type="number" name="stud_id" required class="w-full mt-1 p-2 border rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID новой группы</label>
                            <input type="number" name="new_group_id" required class="w-full mt-1 p-2 border rounded-md">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">Выполнить перевод</button>
                    </form>
                </section>

            <?php elseif ($query): ?>
                <!-- Универсальная таблица -->
                <section class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <?php 
                        $res = $pdo->query($query);
                        $data = $res->fetchAll();
                        if ($data): 
                            $cols = array_keys($data[0]);
                        ?>
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b text-gray-400 text-xs uppercase font-bold">
                                <tr>
                                    <?php foreach ($cols as $c): ?>
                                        <th class="px-6 py-4"><?= $c ?></th>
                                    <?php endforeach; ?>
                                    <?php if($page === 'students') echo '<th class="px-6 py-4 text-right">Действия</th>'; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($data as $row): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <?php foreach ($row as $val): ?>
                                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($val) ?></td>
                                        <?php endforeach; ?>
                                        
                                        <?php if($page === 'students'): ?>
                                            <td class="px-6 py-4 text-right">
                                                <form action="actions.php" method="POST" class="inline" onsubmit="return confirm('Отчислить?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $row['Инд. номер студента'] ?>">
                                                    <button class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: echo "<p class='p-6'>Данных нет.</p>"; endif; ?>
                    </div>
                </section>

            <?php else: ?>
                <!-- Dashboard по умолчанию -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <article class="bg-blue-600 p-6 rounded-xl text-white shadow-lg">
                        <h4 class="text-lg opacity-80 uppercase">Студентов в базе</h4>
                        <p class="text-5xl font-black mt-2"><?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?></p>
                    </article>
                    <article class="bg-emerald-500 p-6 rounded-xl text-white shadow-lg">
                        <h4 class="text-lg opacity-80 uppercase">Средний балл ВУЗа</h4>
                        <p class="text-5xl font-black mt-2"><?= round($pdo->query("SELECT AVG(grade) FROM grades")->fetchColumn(), 2) ?></p>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
