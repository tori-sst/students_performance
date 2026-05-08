<?php
// admin_mode.php

// Функция для записи в лог
function writeLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/debug.log';
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
    }
    .editable-cell:hover {
        background-color: #ffe69e !important;
        box-shadow: inset 0 0 0 2px #10b981;
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
    </style>
    ';
}

function getAdminJS() {
    return '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        enableEditMode();
    });
    
    function enableEditMode() {
        document.querySelectorAll("table tbody tr td:not(:first-child)").forEach(function(cell) {
            cell.classList.add("editable-cell");
            cell.onclick = function(e) {
                e.stopPropagation();
                editCell(this);
            };
        });
        console.log("Режим редактирования БД автоматически включен");
    }
    
    function editCell(cell) {
        var originalText = cell.innerText;
        var row = cell.parentElement;
        var rowId = row.cells[0].innerText;
        var columnIndex = cell.cellIndex;
        var headers = document.querySelectorAll("table thead tr th");
        var columnName = headers[columnIndex] ? headers[columnIndex].innerText : "unknown";
        var pageTitle = document.querySelector("h2") ? document.querySelector("h2").innerText : "";
        
        // Нормализация названия колонки
        columnName = columnName.replace(/:/g, "").trim();
        var lowerColumn = columnName.toLowerCase();
        
        console.log("=== РЕДАКТИРОВАНИЕ ЯЧЕЙКИ ===");
        console.log("ID строки:", rowId);
        console.log("Оригинальное название колонки:", columnName);
        
        var tableName = "students";
        if (pageTitle.includes("Преподавател") || pageTitle.includes("teacher")) tableName = "teacher";
        else if (pageTitle.includes("Групп") || pageTitle.includes("groups")) tableName = "groups";
        else if (pageTitle.includes("Кафедр") || pageTitle.includes("departments")) tableName = "departments";
        else if (pageTitle.includes("Факультет") || pageTitle.includes("faculties")) tableName = "faculties";
        else if (pageTitle.includes("Дисциплин") || pageTitle.includes("subjects")) tableName = "subjects";
        
        console.log("Таблица:", tableName);
        
        // Расширенный маппинг со всеми вариантами
        var columnMapping = {
            "ФИО": "full_name",
            "фио": "full_name",
            "Телефон": "stud_phone",
            "телефон": "stud_phone",
            "Год рождения": "birth_date",
            "год рождения": "birth_date",
            "Год поступления": "admission_year",
            "год поступления": "admission_year",
            "Пол": "gender",
            "пол": "gender",
            "Адрес": "adress",
            "адрес": "adress",
            "Город": "city",
            "город": "city",
            "ID студента": "stud_id",
            "ID": "stud_id",
            "Группа": "group_name",
            "группа": "group_name",
            "Преподаватель": "teach_name",
            "преподаватель": "teach_name",
            "Кафедра": "dep_name",
            "кафедра": "dep_name",
            "Название группы": "group_name",
            "Курс": "course",
            "курс": "course",
            "ФИО Заведеющего": "head_dep",
            "Дисциплина": "sub_name",
            "дисциплина": "sub_name"
        };
        
        // Получаем название колонки в БД
        var dbColumn = columnMapping[columnName];
        if (!dbColumn) {
            dbColumn = columnMapping[lowerColumn];
        }
        if (!dbColumn) {
            dbColumn = lowerColumn.replace(/ /g, "_");
        }
        console.log("Колонка в БД:", dbColumn);
        
        // Для года рождения и года поступления
        if (lowerColumn === "год рождения" || lowerColumn === "год поступления") {
            var input = document.createElement("input");
            input.type = "number";
            input.value = originalText;
            input.className = "w-full p-1 rounded";
            cell.innerHTML = "";
            cell.appendChild(input);
            input.focus();
            input.onblur = function() {
                saveEdit(cell, input.value, rowId, dbColumn, tableName, originalText);
            };
            input.onkeypress = function(e) {
                if (e.key === "Enter") input.blur();
            };
            return;
        }
        
        // Для курса
        if (lowerColumn === "курс") {
            var select = document.createElement("select");
            for (var i = 1; i <= 4; i++) {
                var option = document.createElement("option");
                option.value = i;
                option.text = i + " курс";
                if (originalText == i) option.selected = true;
                select.appendChild(option);
            }
            cell.innerHTML = "";
            cell.appendChild(select);
            select.focus();
            select.onblur = function() {
                saveEdit(cell, select.value, rowId, dbColumn, tableName, originalText);
            };
            select.onkeypress = function(e) {
                if (e.key === "Enter") select.blur();
            };
            return;
        }
        
        // Для пола
        if (lowerColumn === "пол") {
            var select = document.createElement("select");
            var options = ["Мужской", "Женский"];
            for (var j = 0; j < options.length; j++) {
                var option = document.createElement("option");
                option.value = options[j];
                option.text = options[j];
                if (originalText === options[j]) option.selected = true;
                select.appendChild(option);
            }
            cell.innerHTML = "";
            cell.appendChild(select);
            select.focus();
            select.onblur = function() {
                saveEdit(cell, select.value, rowId, dbColumn, tableName, originalText);
            };
            select.onkeypress = function(e) {
                if (e.key === "Enter") select.blur();
            };
            return;
        }
        
        // Обычное текстовое поле
        var input = document.createElement("input");
        input.value = originalText;
        input.className = "w-full p-1 rounded";
        cell.innerHTML = "";
        cell.appendChild(input);
        input.focus();
        input.onblur = function() {
            saveEdit(cell, input.value, rowId, dbColumn, tableName, originalText);
        };
        input.onkeypress = function(e) {
            if (e.key === "Enter") input.blur();
        };
    }
    
    function saveEdit(cell, newValue, rowId, dbColumn, tableName, originalText) {
        console.log("=== СОХРАНЕНИЕ ===");
        console.log("Таблица:", tableName);
        console.log("ID:", rowId);
        console.log("Колонка:", dbColumn);
        console.log("Новое значение:", newValue);
        
        cell.innerHTML = newValue;
        
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
                cell.style.backgroundColor = "#d4edda";
                setTimeout(function() { cell.style.backgroundColor = ""; }, 1000);
                console.log("✓ Сохранено успешно!");
            } else {
                console.error("✗ Ошибка от сервера:", data.error);
                alert("Ошибка: " + (data.error || "Не удалось сохранить"));
                cell.innerHTML = originalText;
                cell.style.backgroundColor = "#f8d7da";
                setTimeout(function() { cell.style.backgroundColor = ""; }, 2000);
            }
        })
        .catch(function(err) {
            console.error("✗ Fetch ошибка:", err);
            alert("Ошибка сети: " + err.message);
            cell.innerHTML = originalText;
        });
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

// ========== ФУНКЦИИ ДЛЯ ОБНОВЛЕНИЯ БД ==========

function updateStudentCell($pdo, $id, $column, $value) {
    writeLog("=== updateStudentCell CALLED ===");
    writeLog("ID: " . $id);
    writeLog("Column: " . $column);
    writeLog("Value: " . $value);
    
    $allowed_columns = ['full_name', 'stud_phone', 'birth_date', 'admission_year', 'gender', 'adress', 'city', 'groups_group_id'];
    if (!in_array($column, $allowed_columns)) {
        writeLog("ERROR: Column not allowed - " . $column);
        return false;
    }
    
    try {
        $checkStmt = $pdo->prepare("SELECT stud_id FROM students WHERE stud_id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() == 0) {
            writeLog("ERROR: Student with ID " . $id . " not found");
            return false;
        }
        
        $sql = "UPDATE students SET `$column` = :value WHERE `stud_id` = :id";
        writeLog("SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':value' => $value, ':id' => $id]);
        
        if ($result) {
            writeLog("SUCCESS! Rows affected: " . $stmt->rowCount());
        } else {
            writeLog("FAILED! Error info: " . print_r($stmt->errorInfo(), true));
        }
        
        return $result;
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

function updateTeacherCell($pdo, $id, $column, $value) {
    writeLog("updateTeacherCell: ID=$id, Column=$column, Value=$value");
    $allowed_columns = ['teach_name', 'departments_dep_id'];
    if (!in_array($column, $allowed_columns)) return false;
    
    try {
        $sql = "UPDATE teacher SET `$column` = :value WHERE `teach_id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':value' => $value, ':id' => $id]);
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

function updateGroupCell($pdo, $id, $column, $value) {
    writeLog("updateGroupCell: ID=$id, Column=$column, Value=$value");
    $allowed_columns = ['group_name', 'course', 'departments_dep_id'];
    if (!in_array($column, $allowed_columns)) return false;
    
    try {
        $sql = "UPDATE `groups` SET `$column` = :value WHERE `group_id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':value' => $value, ':id' => $id]);
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

function updateDepartmentCell($pdo, $id, $column, $value) {
    writeLog("updateDepartmentCell: ID=$id, Column=$column, Value=$value");
    $allowed_columns = ['dep_name', 'head_dep', 'phone', 'faculties_fac_id'];
    if (!in_array($column, $allowed_columns)) return false;
    
    try {
        $sql = "UPDATE departments SET `$column` = :value WHERE `dep_id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':value' => $value, ':id' => $id]);
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

function updateSubjectCell($pdo, $id, $column, $value) {
    writeLog("updateSubjectCell: ID=$id, Column=$column, Value=$value");
    $allowed_columns = ['sub_name', 'teacher_teach_id'];
    if (!in_array($column, $allowed_columns)) return false;
    
    try {
        $sql = "UPDATE subjects SET `$column` = :value WHERE `sub_id` = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':value' => $value, ':id' => $id]);
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
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

?>