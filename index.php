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
        // ... (оставляем без изменений) ...
        break;
        
    case 'all_students':
        $title = "Полный список студентов (Режим редактирования)";
        $studentsData = getAllStudentsWithDetails($pdo, $search);
        break;

    case 'admin':
        $title = "Администрирование: " . (
            $adminSection == 'faculties' ? 'Факультеты' :
            ($adminSection == 'departments' ? 'Кафедры' :
            ($adminSection == 'groups' ? 'Группы' :
            ($adminSection == 'teachers' ? 'Преподаватели' : 'Факультеты'))))
        ;
        break;
        
    // ... (остальные case оставляем как в оригинале) ...
    
    default:
        // Дашборд
        break;
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
        
        /* Стили для редактируемых ячеек */
        .editable-cell {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background 0.2s;
            display: inline-block;
            min-width: 50px;
        }
        .editable-cell:hover {
            background-color: #e0f2fe !important;
        }
        .editable-cell.editing {
            background-color: #fef08a;
            padding: 0;
        }
        .editable-cell.editing input, 
        .editable-cell.editing select {
            border: 2px solid #3b82f6;
            padding: 4px 8px;
            border-radius: 4px;
            outline: none;
            width: 100%;
            background: white;
        }
        
        /* Стили для модальных окон */
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
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .admin-badge { background: linear-gradient(135deg, #667eea, #764ba2); }
        .edit-mode-btn { background: linear-gradient(135deg, #10b981, #059669); }
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
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen">
    <!-- Sidebar (оставляем как в оригинале, но с добавлением пункта для всех студентов) -->
    <aside class="bg-slate-900 text-white w-64 hidden md:flex flex-col min-h-screen">
        <div class="p-6 border-b border-slate-800">
            <i class="fas fa-university text-2xl text-blue-400"></i>
            <h1 class="text-xl font-bold mt-2">УНИВЕР</h1>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-6">
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Учебный процесс</p>
                <a href="?page=students" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-user-graduate w-6"></i> Реестр</a>
                <a href="?page=all_students" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= $page=='all_students'?'bg-blue-600':'' ?>"><i class="fas fa-list w-6"></i> Все студенты</a>
                <a href="?page=student_info" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-address-card w-6"></i> Зачетные книжки</a>
                <a href="?page=debtors" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-user-slash w-6"></i> Должники</a>
                <a href="?page=retake" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-clock w-6"></i> Пересдачи</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Аналитика</p>
                <a href="?page=top" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-medal w-6"></i> Отличники</a>
                <a href="?page=scholarship" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-wallet w-6"></i> Стипендия</a>
                <a href="?page=stats_group" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-chart-bar w-6"></i> Рейтинг групп</a>
                <a href="?page=stats_sub" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-chart-line w-6"></i> Рейтинг дисциплин</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Преподавательская</p>
                <a href="?page=teacher_panel" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-chalkboard-teacher w-6"></i> Вход для преподавателя</a>
                <a href="?page=add_grade" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-pen w-6"></i> Выставить оценку</a>
            </section>
            <section>
                <p class="text-xs text-slate-500 uppercase mb-2">Администрирование</p>
                <?php if ($isAdmin): ?>
                    <a href="?page=admin&admin_section=faculties" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='faculties')?'bg-blue-600':'' ?>"><i class="fas fa-building w-6"></i> Факультеты</a>
                    <a href="?page=admin&admin_section=departments" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='departments')?'bg-blue-600':'' ?>"><i class="fas fa-university w-6"></i> Кафедры</a>
                    <a href="?page=admin&admin_section=groups" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='groups')?'bg-blue-600':'' ?>"><i class="fas fa-users w-6"></i> Группы</a>
                    <a href="?page=admin&admin_section=teachers" class="flex items-center p-2 rounded-lg hover:bg-slate-800 <?= ($page=='admin' && $adminSection=='teachers')?'bg-blue-600':'' ?>"><i class="fas fa-chalkboard-teacher w-6"></i> Преподаватели</a>
                    <hr class="my-2 border-slate-700">
                    <a href="?page=archive" class="flex items-center p-2 rounded-lg bg-gray-700/20 mt-2 border border-gray-500/30"><i class="fas fa-archive w-6 text-gray-400"></i><span class="text-gray-300">Архив</span></a>
                    <a href="#" onclick="showAddStudentModal()" class="flex items-center p-2 rounded-lg bg-green-700/20 mt-2 border border-green-500/30"><i class="fas fa-user-plus w-6 text-green-300"></i><span class="text-green-300">Добавить студента</span></a>
                    <a href="#" onclick="logoutAdmin()" class="flex items-center p-2 rounded-lg bg-red-700/20 mt-2 border border-red-500/30"><i class="fas fa-sign-out-alt w-6 text-red-300"></i><span class="text-red-300">Выйти из админ-режима</span></a>
                <?php else: ?>
                    <a href="?page=dep_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-building w-6"></i> Кафедры</a>
                    <a href="?page=groups_data" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-users w-6"></i> Группы</a>
                    <a href="?page=teacher_load" class="flex items-center p-2 rounded-lg hover:bg-slate-800"><i class="fas fa-chalkboard-teacher w-6"></i> Преподаватели</a>
                    <a href="?page=archive" class="flex items-center p-2 rounded-lg bg-gray-700/20 mt-2 border border-gray-500/30"><i class="fas fa-archive w-6 text-gray-400"></i><span class="text-gray-300">Архив</span></a>
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
            <!-- Поиск для разных страниц -->
            <?php if ($page === 'all_students'): ?>
                <div class="mb-6 flex gap-4">
                    <form method="GET" class="flex-1">
                        <input type="hidden" name="page" value="all_students">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Поиск по ФИО, ID или группе..." class="w-full pl-10 pr-4 py-2 border rounded-lg">
                        </div>
                    </form>
                    <?php if ($isAdmin): ?>
                    <button onclick="enableStudentEditMode()" class="edit-mode-btn text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-edit"></i> Включить режим редактирования
                    </button>
                    <button onclick="showAddStudentModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-user-plus"></i> Добавить студента
                    </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Страница со всеми студентами (с поддержкой редактирования) -->
            <?php if ($page === 'all_students'): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse" id="studentsTable">
                            <thead class="bg-gray-50 border-b">
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
                                <tr data-student-id="<?= $student['ID'] ?>" data-group-id="<?= $student['group_id'] ?>">
                                    <td class="px-4 py-3 font-mono text-sm"><?= htmlspecialchars($student['ID']) ?></td>
                                    <td class="px-4 py-3 editable-student-cell" data-field="full_name"><?= htmlspecialchars($student['ФИО']) ?></td>
                                    <td class="px-4 py-3 editable-student-cell" data-field="stud_phone"><?= htmlspecialchars($student['Телефон']) ?></td>
                                    <td class="px-4 py-3 editable-student-cell" data-field="birth_date"><?= htmlspecialchars($student['Год рождения']) ?></td>
                                    <td class="px-4 py-3 editable-student-cell" data-field="admission_year"><?= htmlspecialchars($student['Год поступления']) ?></td>
                                    <td class="px-4 py-3 editable-student-cell" data-field="group_name" data-group-id-field="groups_group_id"><?= htmlspecialchars($student['Группа']) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($student['Курс']) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($student['Кафедра']) ?></td>
                                    <?php if ($isAdmin): ?>
                                    <td class="px-4 py-3 text-center">
                                        <button onclick="editStudent(<?= $student['ID'] ?>)" class="text-blue-500 hover:text-blue-700 mr-2" title="Редактировать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteStudentRecord(<?= $student['ID'] ?>)" class="delete-btn" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($studentsData)): ?>
                                <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Нет студентов для отображения</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                        <p class="text-sm text-gray-500">Кликните на ячейку для редактирования. Используйте форму ниже для добавления.</p>
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
                
            <!-- Остальные страницы -->
            <?php else: ?>
                <!-- Здесь остальной код как в оригинале -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <p class="p-8 text-center text-gray-400">Выберите раздел в меню</p>
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
    
    <div id="addStudentModal" class="modal">
        <div class="modal-content p-6">
            <div class="flex justify-between mb-4">
                <h3 class="text-xl font-bold">Добавление студента</h3>
                <button onclick="closeAddStudentModal()" class="text-gray-500">&times;</button>
            </div>
            <form id="addStudentForm" onsubmit="addNewStudent(event)">
                <div class="space-y-3">
                    <div><label class="block text-sm font-medium">ID студента</label><input type="number" name="stud_id" required class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">ФИО</label><input type="text" name="full_name" required class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Телефон</label><input type="text" name="stud_phone" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Год рождения</label><input type="number" name="birth_date" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Год поступления</label><input type="number" name="admission_year" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Пол</label><select name="gender" class="w-full p-2 border rounded"><option>Мужской</option><option>Женский</option></select></div>
                    <div><label class="block text-sm font-medium">Адрес</label><input type="text" name="adress" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Город</label><input type="text" name="city" class="w-full p-2 border rounded" value="Уфа"></div>
                    <div><label class="block text-sm font-medium">Группа</label>
                        <select name="group_id" required class="w-full p-2 border rounded">
                            <option value="">Выберите группу</option>
                            <?php foreach($pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name") as $group): ?>
                            <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg mt-4">Добавить студента</button>
            </form>
        </div>
    </div>
    
    <div id="editStudentModal" class="modal">
        <div class="modal-content p-6">
            <div class="flex justify-between mb-4">
                <h3 class="text-xl font-bold">Редактирование студента</h3>
                <button onclick="closeEditStudentModal()" class="text-gray-500">&times;</button>
            </div>
            <form id="editStudentForm" onsubmit="updateStudentData(event)">
                <input type="hidden" name="stud_id" id="edit_stud_id">
                <div class="space-y-3">
                    <div><label class="block text-sm font-medium">ФИО</label><input type="text" name="full_name" id="edit_full_name" required class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Телефон</label><input type="text" name="stud_phone" id="edit_stud_phone" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Год рождения</label><input type="number" name="birth_date" id="edit_birth_date" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Год поступления</label><input type="number" name="admission_year" id="edit_admission_year" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Пол</label><select name="gender" id="edit_gender" class="w-full p-2 border rounded"><option>Мужской</option><option>Женский</option></select></div>
                    <div><label class="block text-sm font-medium">Адрес</label><input type="text" name="adress" id="edit_adress" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Город</label><input type="text" name="city" id="edit_city" class="w-full p-2 border rounded"></div>
                    <div><label class="block text-sm font-medium">Группа</label>
                        <select name="group_id" id="edit_group_id" required class="w-full p-2 border rounded">
                            <?php foreach($pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name") as $group): ?>
                            <option value="<?= $group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg mt-4">Сохранить изменения</button>
            </form>
        </div>
    </div>
    
    <script>
        // Админ-функции
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
        
        // ========== ФУНКЦИИ ДЛЯ АДМИН-ТАБЛИЦ ==========
        
        function editCell(element) {
            if (element.querySelector('input') || element.querySelector('select')) return;
            const currentValue = element.innerText;
            const table = element.dataset.table;
            const id = element.dataset.id;
            const column = element.dataset.column;
            
            // Определяем тип поля
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
                    // Для внешних ключей - загружаем список
                    select.innerHTML = '<option>Загрузка...</option>';
                    element.innerHTML = '';
                    element.appendChild(select);
                    
                    let url = '';
                    if (column === 'departments_dep_id') url = '?admin_action=get_departments';
                    else if (column === 'faculties_fac_id') url = '?admin_action=get_faculties';
                    
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
        
        // ========== ФУНКЦИИ ДЛЯ РАБОТЫ СО СТУДЕНТАМИ ==========
        
        let studentEditMode = false;
        
        function enableStudentEditMode() {
            studentEditMode = !studentEditMode;
            const cells = document.querySelectorAll('#studentsTable .editable-student-cell');
            cells.forEach(cell => {
                if (studentEditMode) {
                    cell.style.backgroundColor = '#fff3cd';
                    cell.style.cursor = 'pointer';
                    cell.onclick = () => editStudentCell(cell);
                } else {
                    cell.style.backgroundColor = '';
                    cell.style.cursor = '';
                    cell.onclick = null;
                }
            });
            const btn = document.querySelector('.edit-mode-btn');
            if (btn) {
                btn.style.opacity = studentEditMode ? '0.7' : '1';
                btn.innerHTML = studentEditMode ? '<i class="fas fa-check"></i> Режим редактирования включен' : '<i class="fas fa-edit"></i> Включить режим редактирования';
            }
            showNotification(studentEditMode ? 'Режим редактирования включен. Кликайте по ячейкам для изменения.' : 'Режим редактирования выключен', 'warning');
        }
        
        function editStudentCell(cell) {
            if (!studentEditMode) return;
            if (cell.querySelector('input') || cell.querySelector('select')) return;
            
            const row = cell.closest('tr');
            const studentId = row.dataset.studentId;
            const field = cell.dataset.field;
            const currentValue = cell.innerText;
            
            let input;
            if (field === 'group_name') {
                input = document.createElement('select');
                input.innerHTML = '<?php foreach($pdo->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name") as $g): ?><option value="<?= $g["group_id"] ?>"><?= addslashes($g["group_name"]) ?></option><?php endforeach; ?>';
                const currentGroupId = row.dataset.groupId;
                if (currentGroupId) input.value = currentGroupId;
            } else {
                input = document.createElement('input');
                input.type = (field === 'birth_date' || field === 'admission_year' || field === 'stud_id') ? 'number' : 'text';
                input.value = currentValue;
            }
            
            input.className = 'w-full p-1 border-2 border-blue-500 rounded';
            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();
            
            const save = () => {
                let newValue = input.value;
                let groupId = null;
                if (field === 'group_name') {
                    groupId = input.value;
                    newValue = input.options[input.selectedIndex]?.text || newValue;
                }
                
                if (newValue === currentValue && !groupId) {
                    cell.innerHTML = currentValue;
                    return;
                }
                
                cell.innerHTML = '<span class="text-gray-400">⏳ сохранение...</span>';
                
                const data = { stud_id: studentId };
                if (field === 'group_name') {
                    data.groups_group_id = groupId;
                    data.field = 'group_id';
                } else {
                    data[field] = newValue;
                    data.field = field;
                }
                
                fetch("?admin_action=update_student", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        cell.innerHTML = newValue;
                        if (field === 'group_name') row.dataset.groupId = groupId;
                        // Обновляем курс и кафедру (можно запросить с сервера)
                        showNotification('✅ Данные обновлены');
                    } else {
                        cell.innerHTML = currentValue;
                        showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                    }
                })
                .catch(() => {
                    cell.innerHTML = currentValue;
                    showNotification('❌ Ошибка сети', 'error');
                });
            };
            
            input.addEventListener('blur', save);
            input.addEventListener('keypress', (e) => { if(e.key === 'Enter') save(); });
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
                    showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                }
            })
            .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
        
        function editStudent(id) {
            fetch(`?admin_action=get_student&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    showNotification(data.error, 'error');
                    return;
                }
                document.getElementById('edit_stud_id').value = data.stud_id;
                document.getElementById('edit_full_name').value = data.full_name || '';
                document.getElementById('edit_stud_phone').value = data.stud_phone || '';
                document.getElementById('edit_birth_date').value = data.birth_date || '';
                document.getElementById('edit_admission_year').value = data.admission_year || '';
                document.getElementById('edit_gender').value = data.gender || 'Мужской';
                document.getElementById('edit_adress').value = data.adress || '';
                document.getElementById('edit_city').value = data.city || '';
                document.getElementById('edit_group_id').value = data.groups_group_id || '';
                document.getElementById('editStudentModal').classList.add('active');
            });
        }
        
        function closeEditStudentModal() {
            document.getElementById('editStudentModal').classList.remove('active');
        }
        
        function updateStudentData(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });
            
            fetch("?admin_action=update_student", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showNotification('✅ Данные обновлены');
                    closeEditStudentModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                }
            })
            .catch(() => showNotification('❌ Ошибка сети', 'error'));
        }
        
        function deleteStudentRecord(id) {
            if (!confirm(`Удалить студента #${id}?\n\n)) return;
            
            fetch(`?admin_action=delete_student&id=${id}`)
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showNotification('✅ Студент удален');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('❌ ' + (result.error || 'Ошибка'), 'error');
                }
            });
        }
    </script>
</body>
</html>

<?php
// Функция для отображения админской таблицы
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
    echo '<div class="overflow-x-auto"><table class="w-full text-left border-collapse" id="adminTable"><thead class="bg-gray-50 border-b"><tr>';
    foreach ($cols as $c) echo '<th class="px-6 py-4">' . htmlspecialchars($c) . '</th>';
    echo '<th class="px-6 py-4 text-center">Удалить</th>';
    echo '</thead><tbody>';
    foreach ($data as $row) {
        echo '<tr data-id="' . $row[$idColumn] . '">';
        foreach ($row as $key => $val) {
            echo '<td class="px-6 py-4 text-sm" data-column="' . htmlspecialchars($key) . '">';
            if ($key != $idColumn) {
                echo '<span class="editable-cell" onclick="editCell(this)" data-table="' . $table . '" data-id="' . $row[$idColumn] . '" data-column="' . htmlspecialchars($key) . '">' . nl2br(htmlspecialchars($val)) . '</span>';
            } else {
                echo htmlspecialchars($val);
            }
            echo '</td>';
        }
        echo '<td class="text-center"><button onclick="deleteRecord(\'' . $table . '\', \'' . $row[$idColumn] . '\', \'' . $idColumn . '\')" class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button></td>';
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
            echo '<div><label class="block text-sm font-medium">ID Кафедры</label>';
            echo '<select name="' . $col . '" class="w-full p-2 border rounded">';
            $deps = $pdo->query("SELECT dep_id, dep_name FROM departments ORDER BY dep_name");
            while ($d = $deps->fetch()) {
                echo '<option value="' . $d['dep_id'] . '">' . htmlspecialchars($d['dep_name']) . ' (ID: ' . $d['dep_id'] . ')</option>';
            }
            echo '</select></div>';
        } elseif ($col == 'faculties_fac_id') {
            echo '<div><label class="block text-sm font-medium">ID Факультета</label>';
            echo '<select name="' . $col . '" class="w-full p-2 border rounded">';
            $facs = $pdo->query("SELECT fac_id, fac_name FROM faculties ORDER BY fac_name");
            while ($f = $facs->fetch()) {
                echo '<option value="' . $f['fac_id'] . '">' . htmlspecialchars($f['fac_name']) . ' (ID: ' . $f['fac_id'] . ')</option>';
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