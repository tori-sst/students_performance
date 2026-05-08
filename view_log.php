<?php
// view_log.php
$logFile = __DIR__ . '/logs/debug.log';

echo "<h1>Debug Log - Админ режим</h1>";
echo "<p>Файл лога: " . $logFile . "</p>";

// Проверяем, существует ли папка logs
if (!file_exists(__DIR__ . '/logs')) {
    echo "<p style='color: orange;'>Папка logs не существует! Создаю...</p>";
    mkdir(__DIR__ . '/logs', 0777, true);
    echo "<p style='color: green;'>Папка logs создана!</p>";
}

// Проверяем, существует ли файл лога
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if (empty($content)) {
        echo "<p style='color: blue;'>Лог пуст. Попробуйте выполнить действие (изменить данные в админ-режиме).</p>";
    } else {
        echo "<pre style='background: #1e1e1e; color: #d4d4d4; padding: 15px; overflow: auto; max-height: 600px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;'>";
        echo htmlspecialchars($content);
        echo "</pre>";
    }
    
    echo "<hr>";
    echo "<form method='post' style='margin-top: 10px;'>
            <button type='submit' name='clear' style='background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>Очистить лог</button>
            <button type='submit' name='refresh' style='background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;'>Обновить</button>
          </form>";
    
    if (isset($_POST['clear'])) {
        file_put_contents($logFile, "=== Лог очищен " . date('Y-m-d H:i:s') . " ===\n");
        echo "<p style='color: green;'>Лог очищен! Обновите страницу.</p>";
        header("Refresh:1");
    }
    
    if (isset($_POST['refresh'])) {
        header("Location: view_log.php");
    }
    
    // Информация о размере файла
    $size = filesize($logFile);
    echo "<p style='margin-top: 10px; color: #666;'>Размер лога: " . round($size / 1024, 2) . " KB</p>";
    
} else {
    echo "<p style='color: red;'>Лог-файл не найден.</p>";
    echo "<p>Создаю файл...</p>";
    file_put_contents($logFile, "=== Лог создан " . date('Y-m-d H:i:s') . " ===\n");
    echo "<p style='color: green;'>Файл создан! Обновите страницу.</p>";
}
?>