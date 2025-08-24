<?php 
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
 
use Grebion\Tables\Repository\TableRepository;
use Grebion\Tables\Repository\ColumnRepository;
use Grebion\Tables\Repository\RowRepository;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity\DataManager;

if (!Loader::includeModule('grebion.tables')) {
    die('Модуль grebion.tables не установлен');
}

if (!Loader::includeModule('highloadblock')) {
    die('Модуль highloadblock не установлен');
}

echo '<h1>CRUD Тесты репозиториев</h1>';

// Инициализация репозиториев
$tableRepo = new TableRepository();
$columnRepo = new ColumnRepository();
$rowRepo = new RowRepository();

$testResults = [];
$startTime = microtime(true);

// Функция для вывода результатов
function printResult($testName, $success, $message = '') {
    $icon = $success ? '✓' : '✗';
    $color = $success ? 'green' : 'red';
    echo "<p style='color: $color;'>$icon $testName";
    if ($message) {
        echo ": $message";
    }
    echo '</p>';
    return $success;
}

// Функция для безопасного выполнения тестов
function safeExecute($callback, $errorMessage = 'Ошибка выполнения') {
    try {
        return $callback();
    } catch (Exception $e) {
        return ['success' => false, 'error' => $errorMessage . ': ' . $e->getMessage()];
    }
}

echo '<h2>1. Тест создания таблицы (CREATE)</h2>';

$testTableName = 'testcrudtable' . time();
$testTableId = null;

$createResult = safeExecute(function() use ($tableRepo, $testTableName, &$testTableId) {
    // Создаем HighloadBlock
    $hlblockData = [
        'NAME' => 'TestCrudTable' . time(),
        'TABLE_NAME' => $testTableName,
    ];
    
    $result = HighloadBlockTable::add($hlblockData);
    if (!$result->isSuccess()) {
        throw new Exception('Не удалось создать HighloadBlock: ' . implode(', ', $result->getErrorMessages()));
    }
    
    $testTableId = $result->getData()['id'];
    return ['success' => true, 'id' => $testTableId];
});

if ($createResult['success']) {
    printResult('Создание таблицы', true, "ID: {$createResult['id']}");
    $testResults['create_table'] = true;
} else {
    printResult('Создание таблицы', false, $createResult['error']);
    $testResults['create_table'] = false;
}

echo '<h2>2. Тест чтения таблицы (READ)</h2>';

if ($testTableId) {
    $readResult = safeExecute(function() use ($tableRepo, $testTableId) {
        $table = $tableRepo->getById($testTableId);
        return ['success' => $table !== null, 'table' => $table];
    });
    
    if ($readResult['success']) {
        printResult('Чтение таблицы', true, "Найдена таблица: {$readResult['table']['NAME']}");
        $testResults['read_table'] = true;
    } else {
        printResult('Чтение таблицы', false, 'Таблица не найдена');
        $testResults['read_table'] = false;
    }
} else {
    printResult('Чтение таблицы', false, 'Нет созданной таблицы для тестирования');
    $testResults['read_table'] = false;
}

echo '<h2>3. Тест создания колонки (CREATE)</h2>';

if ($testTableId) {
    printResult('Создание колонки', false, 'Тест пропущен - требует интеграции с пользовательскими полями');
    $testResults['create_column'] = false;
} else {
    printResult('Создание колонки', false, 'Нет таблицы для создания колонки');
    $testResults['create_column'] = false;
}

echo '<h2>4. Тест чтения колонок (READ)</h2>';

if ($testTableId) {
    $readColumnsResult = safeExecute(function() use ($columnRepo, $testTableId) {
        $columnsResult = $columnRepo->getByTableId($testTableId);
        return ['success' => true, 'count' => $columnsResult->getSelectedRowsCount()];
    });
    
    if ($readColumnsResult['success']) {
        printResult('Чтение колонок', true, "Найдено колонок: {$readColumnsResult['count']}");
        $testResults['read_columns'] = true;
    } else {
        printResult('Чтение колонок', false, $readColumnsResult['error']);
        $testResults['read_columns'] = false;
    }
} else {
    printResult('Чтение колонок', false, 'Нет таблицы для чтения колонок');
    $testResults['read_columns'] = false;
}

echo '<h2>5. Тест создания записи (CREATE)</h2>';

if ($testTableId) {
    printResult('Создание записи', false, 'Тест пропущен - требует работы с конкретной структурой таблицы');
    $testResults['create_row'] = false;
} else {
    printResult('Создание записи', false, 'Нет таблицы для создания записи');
    $testResults['create_row'] = false;
}

echo '<h2>6. Тест чтения записей (READ)</h2>';

if ($testTableId) {
    $readRowsResult = safeExecute(function() use ($rowRepo, $testTableId) {
        $rowsResult = $rowRepo->getByTableId($testTableId);
        return ['success' => true, 'count' => $rowsResult->getSelectedRowsCount()];
    });
    
    if ($readRowsResult['success']) {
        printResult('Чтение записей', true, "Найдено записей: {$readRowsResult['count']}");
        $testResults['read_rows'] = true;
    } else {
        printResult('Чтение записей', false, $readRowsResult['error']);
        $testResults['read_rows'] = false;
    }
} else {
    printResult('Чтение записей', false, 'Нет таблицы для чтения записей');
    $testResults['read_rows'] = false;
}

echo '<h2>7. Тест обновления записи (UPDATE)</h2>';

printResult('Обновление записи', false, 'Тест пропущен - нет записи для обновления');
$testResults['update_row'] = false;

echo '<h2>8. Тест удаления записи (DELETE)</h2>';

printResult('Удаление записи', false, 'Тест пропущен - нет записи для удаления');
$testResults['delete_row'] = false;

echo '<h2>9. Тест удаления колонки (DELETE)</h2>';

printResult('Удаление колонки', false, 'Тест пропущен - нет колонки для удаления');
$testResults['delete_column'] = false;

echo '<h2>10. Тест удаления таблицы (DELETE)</h2>';

if ($testTableId) {
    $deleteResult = safeExecute(function() use ($testTableId) {
        $result = HighloadBlockTable::delete($testTableId);
        if (!$result->isSuccess()) {
            throw new Exception('Не удалось удалить HighloadBlock: ' . implode(', ', $result->getErrorMessages()));
        }
        return ['success' => true];
    });
    
    if ($deleteResult['success']) {
        printResult('Удаление таблицы', true, "Таблица ID: $testTableId удалена");
        $testResults['delete_table'] = true;
    } else {
        printResult('Удаление таблицы', false, $deleteResult['error']);
        $testResults['delete_table'] = false;
    }
} else {
    printResult('Удаление таблицы', false, 'Нет таблицы для удаления');
    $testResults['delete_table'] = false;
}

echo '<h2>Итоги CRUD тестирования</h2>';

$totalTests = count($testResults);
$passedTests = array_sum($testResults);
$failedTests = $totalTests - $passedTests;
$executionTime = round((microtime(true) - $startTime) * 1000, 3);

echo "<p>Всего тестов: $totalTests</p>";
echo "<p>Пройдено: $passedTests</p>";
echo "<p>Провалено: $failedTests</p>";
echo "<p>Время выполнения: $executionTime мс</p>";

if ($failedTests > 0) {
    echo '<p style="color: orange;">⚠️ Некоторые CRUD тесты провалены</p>';
} else {
    echo '<p style="color: green;">✅ Все CRUD тесты пройдены успешно!</p>';
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
?>