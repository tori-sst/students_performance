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

// ========== УНИВЕРСАЛЬНЫЕ ФУНКЦИИ ДЛЯ РАБОТЫ С ЛЮБЫМИ ТАБЛИЦАМИ ==========

function updateAnyTableCell($pdo, $table, $id, $column, $value) {
    writeLog("=== updateAnyTableCell CALLED ===");
    writeLog("Table: " . $table);
    writeLog("ID: " . $id);
    writeLog("Column: " . $column);
    writeLog("Value: " . $value);
    
    // Список разрешённых таблиц
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher', 'students'];
    if (!in_array($table, $allowedTables)) {
        return ['success' => false, 'error' => "Таблица '$table' не доступна для редактирования"];
    }
    
    try {
        // Экранируем имя таблицы и колонки
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        
        // Получаем PRIMARY KEY таблицы
        $pkQuery = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $pkInfo = $pkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$pkInfo) {
            writeLog("ERROR: No PRIMARY KEY found for table '$table'");
            return ['success' => false, 'error' => "Не найден PRIMARY KEY для таблицы '$table'"];
        }
        
        $primaryKey = $pkInfo['Column_name'];
        writeLog("Primary key: " . $primaryKey);
        
        // Проверяем существование колонки
        $colQuery = $pdo->query("SHOW COLUMNS FROM `$table`");
        $columns = $colQuery->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array($column, $columns)) {
            writeLog("ERROR: Column '$column' does not exist in table '$table'");
            return ['success' => false, 'error' => "Колонка '$column' не существует в таблице '$table'"];
        }
        
        // Проверяем существование записи
        $checkStmt = $pdo->prepare("SELECT `$primaryKey` FROM `$table` WHERE `$primaryKey` = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->rowCount() == 0) {
            writeLog("ERROR: Record with ID '$id' not found");
            return ['success' => false, 'error' => "Запись с ID '$id' не найдена"];
        }
        
        // Выполняем обновление
        $sql = "UPDATE `$table` SET `$column` = ? WHERE `$primaryKey` = ?";
        writeLog("SQL: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$value, $id]);
        
        if ($result) {
            writeLog("SUCCESS! Rows affected: " . $stmt->rowCount());
            return ['success' => true];
        } else {
            $error = $stmt->errorInfo();
            writeLog("FAILED! Error: " . print_r($error, true));
            return ['success' => false, 'error' => $error[2]];
        }
        
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteRecord($pdo, $table, $id, $idColumn) {
    writeLog("=== deleteRecord CALLED ===");
    writeLog("Table: $table, ID: $id, ID Column: $idColumn");
    
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher', 'students'];
    if (!in_array($table, $allowedTables)) {
        return ['success' => false, 'error' => "Таблица '$table' не доступна для удаления"];
    }
    
    // Экранируем имена
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $idColumn);
    
    try {
        // Для студентов используем каскадное удаление
        if ($table == 'students') {
            return deleteStudentCascade($pdo, $id);
        }
        
        // Проверяем, есть ли связанные данные для других таблиц
        $checkSql = null;
        switch ($table) {
            case 'faculties':
                $checkSql = "SELECT COUNT(*) FROM departments WHERE faculties_fac_id = ?";
                break;
            case 'departments':
                $checkSql = "SELECT (SELECT COUNT(*) FROM `groups` WHERE departments_dep_id = ?) + (SELECT COUNT(*) FROM teacher WHERE departments_dep_id = ?) as total";
                break;
            case 'groups':
                $checkSql = "SELECT COUNT(*) FROM students WHERE groups_group_id = ?";
                break;
            case 'teacher':
                $checkSql = "SELECT COUNT(*) FROM subjects WHERE teacher_teach_id = ?";
                break;
        }
        
        if ($checkSql) {
            $stmt = $pdo->prepare($checkSql);
            if ($table == 'departments') {
                $stmt->execute([$id, $id]);
            } else {
                $stmt->execute([$id]);
            }
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                return ['success' => false, 'error' => "Невозможно удалить: существуют связанные записи ($count)"];
            }
        }
        
        // Удаляем запись
        $sql = "DELETE FROM `$table` WHERE `$idColumn` = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$id]);
        
        if ($result && $stmt->rowCount() > 0) {
            writeLog("SUCCESS: Record deleted");
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => "Запись не найдена или не удалена"];
        }
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Функция каскадного удаления студента (сначала оценки, потом пересдачи, потом сам студент)
function deleteStudentCascade($pdo, $stud_id) {
    writeLog("=== deleteStudentCascade CALLED ===");
    writeLog("Student ID: $stud_id");
    
    try {
        // Начинаем транзакцию
        $pdo->beginTransaction();
        
        $deletedCount = [];
        
        // 1. Удаляем записи о пересдачах
        $stmt = $pdo->prepare("DELETE FROM reexams WHERE stud_id = ?");
        $stmt->execute([$stud_id]);
        $deletedCount['reexams'] = $stmt->rowCount();
        writeLog("Deleted {$deletedCount['reexams']} records from reexams");
        
        // 2. Удаляем оценки
        $stmt = $pdo->prepare("DELETE FROM grades WHERE students_stud_id = ?");
        $stmt->execute([$stud_id]);
        $deletedCount['grades'] = $stmt->rowCount();
        writeLog("Deleted {$deletedCount['grades']} records from grades");
        
        // 3. Удаляем самого студента
        $stmt = $pdo->prepare("DELETE FROM students WHERE stud_id = ?");
        $stmt->execute([$stud_id]);
        $deletedCount['students'] = $stmt->rowCount();
        writeLog("Deleted {$deletedCount['students']} student record");
        
        if ($deletedCount['students'] > 0) {
            // Подтверждаем транзакцию
            $pdo->commit();
            writeLog("SUCCESS: Student $stud_id completely deleted with all related data");
            return [
                'success' => true, 
                'message' => "Студент удалён. Удалено записей: оценки - {$deletedCount['grades']}, пересдачи - {$deletedCount['reexams']}"
            ];
        } else {
            // Откатываем транзакцию
            $pdo->rollBack();
            return ['success' => false, 'error' => "Студент с ID $stud_id не найден"];
        }
        
    } catch (PDOException $e) {
        // Откатываем транзакцию при ошибке
        $pdo->rollBack();
        writeLog("EXCEPTION in deleteStudentCascade: " . $e->getMessage());
        return ['success' => false, 'error' => "Ошибка при удалении: " . $e->getMessage()];
    }
}

function addRecord($pdo, $table, $data) {
    writeLog("=== addRecord CALLED ===");
    writeLog("Table: $table");
    writeLog("Data: " . print_r($data, true));
    
    $allowedTables = ['faculties', 'departments', 'groups', 'teacher'];
    if (!in_array($table, $allowedTables)) {
        return ['success' => false, 'error' => "Таблица '$table' не доступна для добавления"];
    }
    
    // Экранируем имя таблицы
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    
    try {
        // Формируем запрос INSERT
        $columns = array_keys($data);
        $placeholders = '?' . str_repeat(', ?', count($columns) - 1);
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $values = array_values($data);
        $result = $stmt->execute($values);
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            writeLog("SUCCESS: Added record with ID: $newId");
            return ['success' => true, 'id' => $newId];
        } else {
            $error = $stmt->errorInfo();
            return ['success' => false, 'error' => $error[2]];
        }
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Функция для получения данных студента для редактирования
function getStudentData($pdo, $stud_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, g.group_name, g.course, d.dep_name 
            FROM students s 
            JOIN `groups` g ON s.groups_group_id = g.group_id 
            JOIN departments d ON g.departments_dep_id = d.dep_id 
            WHERE s.stud_id = ?
        ");
        $stmt->execute([$stud_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        writeLog("getStudentData ERROR: " . $e->getMessage());
        return null;
    }
}

// Функция для получения статистики студента (количество оценок и пересдач)
function getStudentStats($pdo, $stud_id) {
    try {
        $grades = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE students_stud_id = ?");
        $grades->execute([$stud_id]);
        $reexams = $pdo->prepare("SELECT COUNT(*) FROM reexams WHERE stud_id = ?");
        $reexams->execute([$stud_id]);
        
        return [
            'grades' => (int)$grades->fetchColumn(),
            'reexams' => (int)$reexams->fetchColumn()
        ];
    } catch (PDOException $e) {
        writeLog("getStudentStats ERROR: " . $e->getMessage());
        return ['grades' => 0, 'reexams' => 0];
    }
}

// Функция для обновления студента
function updateStudent($pdo, $stud_id, $data) {
    writeLog("=== updateStudent CALLED ===");
    writeLog("ID: $stud_id, Data: " . print_r($data, true));
    
    try {
        // Если пришло groups_group_id, обновляем только его
        if (isset($data['groups_group_id'])) {
            $sql = "UPDATE students SET groups_group_id = ? WHERE stud_id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$data['groups_group_id'], $stud_id]);
        } 
        // Если пришло отдельное поле
        elseif (isset($data['field'])) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', $data['field']);
            $value = $data[$data['field']];
            $sql = "UPDATE students SET `$field` = ? WHERE stud_id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$value, $stud_id]);
        }
        // Полное обновление
        else {
            $sql = "UPDATE students SET 
                        full_name = ?,
                        stud_phone = ?,
                        birth_date = ?,
                        admission_year = ?,
                        gender = ?,
                        adress = ?,
                        city = ?,
                        groups_group_id = ?
                    WHERE stud_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['full_name'] ?? '',
                $data['stud_phone'] ?? '',
                $data['birth_date'] ?? null,
                $data['admission_year'] ?? null,
                $data['gender'] ?? 'Мужской',
                $data['adress'] ?? '',
                $data['city'] ?? 'Уфа',
                $data['group_id'] ?? $data['groups_group_id'],
                $stud_id
            ]);
        }
        
        if ($result) {
            return ['success' => true];
        } else {
            $error = $stmt->errorInfo();
            return ['success' => false, 'error' => $error[2]];
        }
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Функция для добавления студента
function addStudent($pdo, $data) {
    writeLog("=== addStudent CALLED ===");
    writeLog("Data: " . print_r($data, true));
    
    try {
        $sql = "INSERT INTO students (stud_id, full_name, stud_phone, birth_date, admission_year, gender, adress, city, groups_group_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['stud_id'],
            $data['full_name'],
            $data['stud_phone'] ?? '',
            $data['birth_date'] ?? null,
            $data['admission_year'] ?? null,
            $data['gender'] ?? 'Мужской',
            $data['adress'] ?? '',
            $data['city'] ?? 'Уфа',
            $data['group_id']
        ]);
        
        if ($result) {
            return ['success' => true, 'id' => $data['stud_id']];
        } else {
            $error = $stmt->errorInfo();
            return ['success' => false, 'error' => $error[2]];
        }
    } catch (PDOException $e) {
        writeLog("EXCEPTION: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Функция для удаления студента (вызывает каскадное удаление)
function deleteStudent($pdo, $stud_id) {
    return deleteStudentCascade($pdo, $stud_id);
}
?>