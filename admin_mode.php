<?php
// admin_mode.php - РАСШИРЕННАЯ ВЕРСИЯ для редактирования ВСЕХ таблиц

// Функция для записи в лог
function writeLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/debug.log';
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= " - " . print_r($data, true);
    }
    $logMessage .= PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function getAdminCSS() {
    return '
    <style>
    .admin-enter-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 999;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 20px;
        border-radius: 50px;
        cursor: pointer;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border: none;
        font-family: inherit;
    }
    .admin-enter-btn:hover {
        transform: scale(1.05);
    }
    .editable-cell {
        background-color: #fff3cd !important;
        cursor: pointer !important;
        transition: all 0.3s;
        position: relative;
    }
    .editable-cell:hover {
        background-color: #ffe69e !important;
        box-shadow: inset 0 0 0 2px #10b981;
    }
    .editable-cell.editing {
        background-color: #cce5ff !important;
        box-shadow: inset 0 0 0 2px #0066cc;
    }
    .admin-badge {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: bold;
        z-index: 999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .admin-badge a {
        color: white;
        text-decoration: none;
    }
    .admin-toast {
        position: fixed;
        bottom: 80px;
        right: 20px;
        background: #333;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        z-index: 1000;
        animation: fadeOut 2s forwards;
    }
    @keyframes fadeOut {
        0% { opacity: 1; }
        70% { opacity: 1; }
        100% { opacity: 0; visibility: hidden; }
    }
    </style>
    ';
}

function getAdminJS() {
    return '
    <script>
    let adminEditMode = true;
    
    document.addEventListener("DOMContentLoaded", function() {
        console.log("🔧 Админ-режим активирован");
        enableEditMode();
    });
    
    function enableEditMode() {
        // Находим все ячейки таблиц (кроме заголовков и ячеек с действиями)
        document.querySelectorAll("table tbody tr td").forEach(function(cell) {
            // Пропускаем ячейки, которые содержат ссылки (действия)
            if (cell.querySelector("a") && cell.querySelector("a").getAttribute("href")?.includes("?page=")) {
                return;
            }
            // Пропускаем ячейки, которые уже имеют класс editable-cell
            if (cell.classList.contains("editable-cell")) return;
            
            cell.classList.add("editable-cell");
            cell.onclick = function(e) {
                e.stopPropagation();
                if (adminEditMode) {
                    editCell(this);
                }
            };
        });
        console.log("✅ Режим редактирования ВСЕХ таблиц включен");
    }
    
    function editCell(cell) {
        if (cell.classList.contains("editing")) return;
        
        var originalText = cell.innerText.trim();
        var row = cell.parentElement;
        var rowIdCell = row.cells[0]; // Первая ячейка - ID
        
        // Получаем ID строки (может быть в разных форматах)
        var rowId = rowIdCell.innerText.trim();
        
        // Если в первой ячейке ссылка - извлекаем ID из href или текста
        var link = rowIdCell.querySelector("a");
        if (link && link.getAttribute("href")) {
            var href = link.getAttribute("href");
            var idMatch = href.match(/[?&]id=(\d+)/);
            if (idMatch) rowId = idMatch[1];
            else if (link.innerText.match(/\d+/)) rowId = link.innerText.match(/\d+/)[0];
        }
        
        var columnIndex = cell.cellIndex;
        var headers = document.querySelectorAll("table thead tr th");
        var columnName = headers[columnIndex] ? headers[columnIndex].innerText.trim() : "unknown";
        
        // Определяем название таблицы по заголовку страницы или URL
        var pageTitle = document.querySelector("h2") ? document.querySelector("h2").innerText : "";
        var tableName = detectTableName(pageTitle);
        
        // Нормализация названия колонки для БД
        var dbColumn = normalizeColumnName(columnName);
        
        console.log("=== РЕДАКТИРОВАНИЕ ЯЧЕЙКИ ===");
        console.log("Таблица:", tableName);
        console.log("ID строки:", rowId);
        console.log("Колонка (видимая):", columnName);
        console.log("Колонка (БД):", dbColumn);
        console.log("Текущее значение:", originalText);
        
        // Создаем редактируемый элемент в зависимости от типа данных
        cell.classList.add("editing");
        
        // Определяем тип поля для правильного редактора
        var inputType = detectInputType(dbColumn, columnName, originalText);
        
        if (inputType === "select") {
            createSelectEditor(cell, originalText, rowId, dbColumn, tableName);
        } else if (inputType === "number") {
            createNumberEditor(cell, originalText, rowId, dbColumn, tableName);
        } else {
            createTextEditor(cell, originalText, rowId, dbColumn, tableName);
        }
    }
    
    function detectTableName(pageTitle) {
        var title = pageTitle.toLowerCase();
        if (title.includes("студент")) return "students";
        if (title.includes("преподавател")) return "teacher";
        if (title.includes("групп")) return "groups";
        if (title.includes("кафедр")) return "departments";
        if (title.includes("факультет")) return "faculties";
        if (title.includes("дисциплин")) return "subjects";
        if (title.includes("оценк")) return "grades";
        if (title.includes("пересдач")) return "reexams";
        
        // По URL параметрам
        var urlParams = new URLSearchParams(window.location.search);
        var page = urlParams.get("page");
        if (page === "all_students") return "students";
        if (page === "teacher_load") return "teacher";
        if (page === "dep_data") return "departments";
        if (page === "groups_data") return "groups";
        if (page === "top") return "students";
        if (page === "stats_group") return "groups";
        if (page === "stats_sub") return "subjects";
        
        return "students";
    }
    
    function normalizeColumnName(columnName) {
        // Убираем двоеточия и лишние пробелы
        columnName = columnName.replace(/:/g, "").trim();
        var lower = columnName.toLowerCase();
        
        // Расширенный маппинг для всех таблиц
        var mapping = {
            // Students
            "фио": "full_name",
            "фис": "full_name",
            "телефон": "stud_phone",
            "год рождения": "birth_date",
            "год поступления": "admission_year",
            "пол": "gender",
            "адрес": "adress",
            "город": "city",
            "id студента": "stud_id",
            "id": "stud_id",
            
            // Groups
            "название группы": "group_name",
            "группа": "group_name",
            "курс": "course",
            "id группы": "group_id",
            "id_g": "group_id",
            
            // Departments
            "кафедра": "dep_name",
            "фио заведеющего": "head_dep",
            "телефон кафедры": "phone",
            "id кафедры": "dep_id",
            "id_d": "dep_id",
            
            // Faculties
            "название факультета": "fac_name",
            "факультет": "fac_name",
            "декан": "dean_name",
            "номер корпуса": "building_number",
            "id факультета": "fac_id",
            "id_f": "fac_id",
            
            // Teacher
            "фио преподавателя": "teach_name",
            "преподаватель": "teach_name",
            "id преподавателя": "teach_id",
            
            // Subjects
            "дисциплина": "sub_name",
            "id дисциплины": "sub_id",
            
            // Grades
            "оценка": "grade",
            
            // Дополнительные поля из представлений (только для отображения, не редактируются)
            "средний балл": "avg_grade_readonly",
            "кол-во студентов": "student_count_readonly"
        };
        
        // Прямое соответствие
        if (mapping[columnName]) return mapping[columnName];
        if (mapping[lower]) return mapping[lower];
        
        // Преобразуем кириллицу в латиницу для полей
        var rusToLat = {
            "название": "name",
            "фио": "full_name",
            "телефон": "phone"
        };
        
        for (var key in rusToLat) {
            if (lower.includes(key)) return rusToLat[key];
        }
        
        // Если ничего не подошло, преобразуем в snake_case
        return lower.replace(/ /g, "_").replace(/[^a-zа-яё_]/g, "");
    }
    
    function detectInputType(dbColumn, displayColumn, value) {
        var col = dbColumn.toLowerCase();
        
        // Числовые поля
        if (col.includes("year") || col.includes("id") || col === "course" || col === "grade" || 
            col === "building_number") {
            return "number";
        }
        
        // Поля с выбором
        if (col === "gender") return "select";
        if (displayColumn.toLowerCase().includes("курс")) return "select";
        
        return "text";
    }
    
    function createSelectEditor(cell, originalValue, rowId, dbColumn, tableName) {
        var select = document.createElement("select");
        select.className = "w-full p-1 rounded border";
        
        var displayColumn = cell.innerText.trim();
        var options = [];
        
        // Определяем опции для select
        if (dbColumn === "gender") {
            options = ["Мужской", "Женский"];
        } else if (dbColumn === "course" || displayColumn.toLowerCase().includes("курс")) {
            options = ["1", "2", "3", "4"];
        }
        
        for (var i = 0; i < options.length; i++) {
            var option = document.createElement("option");
            option.value = options[i];
            option.text = dbColumn === "course" ? options[i] + " курс" : options[i];
            if (originalValue == options[i] || originalValue.includes(options[i])) {
                option.selected = true;
            }
            select.appendChild(option);
        }
        
        cell.innerHTML = "";
        cell.appendChild(select);
        select.focus();
        
        select.onblur = function() {
            saveEdit(cell, select.value, rowId, dbColumn, tableName, originalValue);
        };
        select.onkeypress = function(e) {
            if (e.key === "Enter") select.blur();
        };
    }
    
    function createNumberEditor(cell, originalValue, rowId, dbColumn, tableName) {
        var input = document.createElement("input");
        input.type = "number";
        input.value = originalValue;
        input.className = "w-full p-1 rounded border";
        
        cell.innerHTML = "";
        cell.appendChild(input);
        input.focus();
        
        input.onblur = function() {
            saveEdit(cell, input.value, rowId, dbColumn, tableName, originalValue);
        };
        input.onkeypress = function(e) {
            if (e.key === "Enter") input.blur();
        };
    }
    
    function createTextEditor(cell, originalValue, rowId, dbColumn, tableName) {
        var input = document.createElement("input");
        input.type = "text";
        input.value = originalValue;
        input.className = "w-full p-1 rounded border";
        
        cell.innerHTML = "";
        cell.appendChild(input);
        input.focus();
        
        input.onblur = function() {
            saveEdit(cell, input.value, rowId, dbColumn, tableName, originalValue);
        };
        input.onkeypress = function(e) {
            if (e.key === "Enter") input.blur();
        };
    }
    
    function saveEdit(cell, newValue, rowId, dbColumn, tableName, originalText) {
        cell.classList.remove("editing");
        
        // Проверка на read-only поля (из представлений)
        if (dbColumn.includes("readonly")) {
            cell.innerHTML = originalText;
            showToast("Это поле только для просмотра", "warning");
            return;
        }
        
        // Если значение не изменилось
        if (newValue == originalText) {
            cell.innerHTML = newValue;
            return;
        }
        
        console.log("=== СОХРАНЕНИЕ ===");
        console.log("Таблица:", tableName);
        console.log("ID:", rowId);
        console.log("Колонка:", dbColumn);
        console.log("Новое значение:", newValue);
        
        // Показываем индикатор загрузки
        cell.innerHTML = "<span class=\"text-gray-400\">⏳ сохранение...</span>";
        
        fetch("?admin_action=update_cell", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                table: tableName,
                id: rowId,
                column: dbColumn,
                value: newValue
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            console.log("Ответ сервера:", data);
            if (data.success) {
                cell.innerHTML = newValue;
                cell.style.backgroundColor = "#d4edda";
                setTimeout(function() { cell.style.backgroundColor = ""; }, 1000);
                showToast("✅ Сохранено!", "success");
                console.log("✓ Сохранено успешно!");
            } else {
                console.error("✗ Ошибка от сервера:", data.error);
                cell.innerHTML = originalText;
                cell.style.backgroundColor = "#f8d7da";
                setTimeout(function() { cell.style.backgroundColor = ""; }, 2000);
                showToast("❌ Ошибка: " + (data.error || "Не удалось сохранить"), "error");
            }
        })
        .catch(function(err) {
            console.error("✗ Fetch ошибка:", err);
            cell.innerHTML = originalText;
            showToast("❌ Ошибка сети: " + err.message, "error");
        });
    }
    
    function showToast(message, type) {
        var toast = document.createElement("div");
        toast.className = "admin-toast";
        toast.style.cssText = "position:fixed;bottom:20px;right:20px;background:" + 
            (type === "success" ? "#10b981" : type === "error" ? "#ef4444" : "#f59e0b") + 
            ";color:white;padding:10px 20px;border-radius:8px;z-index:1000;";
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 2000);
    }
    </script>
    ';
}

function adminLogin($password) {
    $admin_password = 'Admin123!';
    if ($password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

function adminLogout() {
    $_SESSION['admin_logged_in'] = false;
}

// ========== УНИВЕРСАЛЬНАЯ ФУНКЦИЯ ДЛЯ ОБНОВЛЕНИЯ ЛЮБОЙ ТАБЛИЦЫ ==========

function updateAnyTableCell($pdo, $table, $id, $column, $value) {
    writeLog("=== updateAnyTableCell CALLED ===");
    writeLog("Table: " . $table);
    writeLog("ID: " . $id);
    writeLog("Column: " . $column);
    writeLog("Value: " . $value);
    
    // Получаем информацию о структуре таблицы
    try {
        // Проверяем существование таблицы
        $checkTable = $pdo->prepare("SHOW TABLES LIKE ?");
        $checkTable->execute([$table]);
        if ($checkTable->rowCount() == 0) {
            writeLog("ERROR: Table '$table' does not exist");
            return ['success' => false, 'error' => "Таблица '$table' не существует"];
        }
        
        // Получаем PRIMARY KEY таблицы
        $pkQuery = $pdo->prepare("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $pkQuery->execute();
        $pkInfo = $pkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$pkInfo) {
            writeLog("ERROR: No PRIMARY KEY found for table '$table'");
            return ['success' => false, 'error' => "Не найден PRIMARY KEY для таблицы '$table'"];
        }
        
        $primaryKey = $pkInfo['Column_name'];
        writeLog("Primary key: " . $primaryKey);
        
        // Проверяем существование колонки
        $colQuery = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $colQuery->execute([$column]);
        if ($colQuery->rowCount() == 0) {
            writeLog("ERROR: Column '$column' does not exist in table '$table'");
            return ['success' => false, 'error' => "Колонка '$column' не существует в таблице '$table'"];
        }
        
        // Проверяем существование записи с таким ID
        $checkStmt = $pdo->prepare("SELECT `$primaryKey` FROM `$table` WHERE `$primaryKey` = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() == 0) {
            writeLog("ERROR: Record with ID '$id' not found in table '$table'");
            return ['success' => false, 'error' => "Запись с ID '$id' не найдена в таблице '$table'"];
        }
        
        // Выполняем обновление
        $sql = "UPDATE `$table` SET `$column` = :value WHERE `$primaryKey` = :id";
        writeLog("SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':value' => $value, ':id' => $id]);
        
        if ($result) {
            $affected = $stmt->rowCount();
            writeLog("SUCCESS! Rows affected: " . $affected);
            return ['success' => true, 'affected' => $affected];
        } else {
            $error = $stmt->errorInfo();
            writeLog("FAILED! Error info: " . print_r($error, true));
            return ['success' => false, 'error' => $error[2]];
        }
        
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Сохраняем старые функции для обратной совместимости
function updateStudentCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'students', $id, $column, $value)['success'];
}

function updateTeacherCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'teacher', $id, $column, $value)['success'];
}

function updateGroupCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'groups', $id, $column, $value)['success'];
}

function updateDepartmentCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'departments', $id, $column, $value)['success'];
}

function updateSubjectCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'subjects', $id, $column, $value)['success'];
}

function updateFacultyCell($pdo, $id, $column, $value) {
    return updateAnyTableCell($pdo, 'faculties', $id, $column, $value)['success'];
}

function updateGradeCell($pdo, $id, $column, $value) {
    // Для таблицы grades составной ключ, используем специальную обработку
    try {
        if ($column !== 'grade') return false;
        // Ожидаем id в формате "sub_id:stud_id"
        $ids = explode(':', $id);
        if (count($ids) != 2) return false;
        
        $sql = "UPDATE grades SET grade = ? WHERE subjects_sub_id = ? AND students_stud_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$value, $ids[0], $ids[1]]);
    } catch (PDOException $e) {
        writeLog("Grade update error: " . $e->getMessage());
        return false;
    }
}

function addRecordToDB($pdo, $table, $data) {
    writeLog("addRecordToDB: Table=$table");
    try {
        switch ($table) {
            case 'students':
                $sql = "INSERT INTO students (stud_id, full_name, stud_phone, birth_date, admission_year, gender, adress, city, groups_group_id) 
                        VALUES (:stud_id, :full_name, :phone, :birth_date, :admission_year, :gender, :adress, :city, :group_id)";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':stud_id' => $data['stud_id'],
                    ':full_name' => $data['full_name'],
                    ':phone' => $data['stud_phone'] ?? '',
                    ':birth_date' => $data['birth_date'] ?? null,
                    ':admission_year' => $data['admission_year'] ?? null,
                    ':gender' => $data['gender'] ?? 'Мужской',
                    ':adress' => $data['address'] ?? '',
                    ':city' => $data['city'] ?? 'Уфа',
                    ':group_id' => $data['groups_group_id']
                ]);
                
            case 'teacher':
                $sql = "INSERT INTO teacher (teach_id, teach_name, departments_dep_id) 
                        VALUES (:teach_id, :teach_name, :dep_id)";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':teach_id' => $data['teach_id'],
                    ':teach_name' => $data['teach_name'],
                    ':dep_id' => $data['departments_dep_id']
                ]);
                
            case 'groups':
                $sql = "INSERT INTO `groups` (group_id, group_name, course, departments_dep_id) 
                        VALUES (:group_id, :group_name, :course, :dep_id)";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute([
                    ':group_id' => $data['group_id'],
                    ':group_name' => $data['group_name'],
                    ':course' => $data['course'],
                    ':dep_id' => $data['departments_dep_id']
                ]);
        }
        return false;
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return false;
    }
}