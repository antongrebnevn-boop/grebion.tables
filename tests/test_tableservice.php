<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Grebion\Tables\Service\TableService;
use Bitrix\Main\Loader;

if (!Loader::includeModule('grebion.tables')) {
    die('Модуль grebion.tables не установлен');
}

echo "<h1>Тест TableService</h1>";

try {
    $tableService = new TableService();
    
    // Тест 1: Создание таблицы с колонками
    echo "<h2>Тест 1: Создание таблицы с колонками</h2>";
    $columns = [
        [
            'code' => 'name',
            'type' => 'string',
            'title' => 'Название',
            'settings' => ['required' => true],
            'sort' => 100
        ],
        [
            'code' => 'price',
            'type' => 'number',
            'title' => 'Цена',
            'settings' => ['min' => 0],
            'sort' => 200
        ]
    ];
    
    $result = $tableService->createTable('TestTable', 'USER', 1, $columns);
    if ($result->isSuccess()) {
        $tableId = $result->getData()['table_id'];
        echo "✅ Таблица создана с ID: {$tableId}<br>";
        
        // Тест 2: Получение таблицы
        echo "<h2>Тест 2: Получение таблицы</h2>";
        $getResult = $tableService->getTable($tableId);
        if ($getResult->isSuccess()) {
            $table = $getResult->getData();
            echo "✅ Таблица получена: {$table['TITLE']}<br>";
        } else {
            echo "❌ Ошибка получения таблицы: " . implode(', ', $getResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 3: Получение колонок таблицы
        echo "<h2>Тест 3: Получение колонок таблицы</h2>";
        $columnsResult = $tableService->getTableColumns($tableId);
        if ($columnsResult->isSuccess()) {
            $data = $columnsResult->getData();
            $columns = $data['columns'] ?? [];
            $columnsCount = count($columns);
            foreach ($columns as $column) {
                echo "- Колонка: {$column['CODE']} ({$column['TITLE']})<br>";
            }
            echo "Всего колонок: {$columnsCount}<br>";
            echo "✅ Колонки получены<br>";
        } else {
            echo "❌ Ошибка получения колонок: " . implode(', ', $columnsResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 4: Добавление новой колонки
        echo "<h2>Тест 4: Добавление новой колонки</h2>";
        $columnResult = $tableService->createColumn(
            $tableId,
            'description',
            'text',
            'Описание',
            ['maxlength' => 1000],
            300
        );
        if ($columnResult->isSuccess()) {
            echo "✅ Колонка 'description' добавлена<br>";
        } else {
            echo "❌ Ошибка добавления колонки: " . implode(', ', $columnResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 5: Получение статистики таблицы
        echo "<h2>Тест 5: Получение статистики таблицы</h2>";
        $statsResult = $tableService->getTableStats($tableId);
        if ($statsResult->isSuccess()) {
            $stats = $statsResult->getData();
            echo "✅ Статистика таблицы:<br>";
            echo "- Колонок: {$stats['columns_count']}<br>";
            echo "- Строк: {$stats['rows_count']}<br>";
        } else {
            echo "❌ Ошибка получения статистики: " . implode(', ', $statsResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 6: Клонирование таблицы
        echo "<h2>Тест 6: Клонирование таблицы</h2>";
        $cloneResult = $tableService->cloneTable(
            $tableId,
            'CloneTestTable',
            'USER',
            1,
            false
        );
        if ($cloneResult->isSuccess()) {
            $cloneTableId = $cloneResult->getData()['table_id'];
            echo "✅ Таблица клонирована с ID: {$cloneTableId}<br>";
            
            // Удаляем клон
            $deleteCloneResult = $tableService->deleteTable((int)$cloneTableId);
            if ($deleteCloneResult->isSuccess()) {
                echo "✅ Клон таблицы удален<br>";
            }
        } else {
            echo "❌ Ошибка клонирования таблицы: " . implode(', ', $cloneResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 7: Получение таблиц по владельцу
        echo "<h2>Тест 7: Получение таблиц по владельцу</h2>";
        $ownerTablesResult = $tableService->getTablesByOwner('USER', 1);
        $tables = $ownerTablesResult->getData();
        $tablesCount = count($tables);
        foreach ($tables as $table) {
            echo "- Таблица: {$table['TITLE']} (ID: {$table['ID']})<br>";
        }
        echo "Всего таблиц пользователя: {$tablesCount}<br>";
        
        // Тест 8: Обновление таблицы
        echo "<h2>Тест 8: Обновление таблицы</h2>";
        $updateResult = $tableService->updateTable($tableId, [
            'OWNER_TYPE' => 'USER',
            'OWNER_ID' => 1,
            'TITLE' => 'UpdatedTestTable'
        ]);
        if ($updateResult->isSuccess()) {
            echo "✅ Таблица обновлена<br>";
        } else {
            echo "❌ Ошибка обновления таблицы: " . implode(', ', $updateResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 9: Удаление всех клонированных таблиц
        echo "<h2>Тест 9: Удаление всех клонированных таблиц</h2>";
        $ownerTablesResult = $tableService->getTablesByOwner('USER', 1);
        if ($ownerTablesResult->isSuccess()) {
            $tables = $ownerTablesResult->getData();
            $deletedCount = 0;
            foreach ($tables as $table) {
                if (strpos($table['TITLE'], 'Clone') !== false) {
                    $deleteResult = $tableService->deleteTable((int)$table['ID']);
                    if ($deleteResult->isSuccess()) {
                        $deletedCount++;
                        echo "✅ Клон таблицы '{$table['TITLE']}' (ID: {$table['ID']}) удален<br>";
                    } else {
                        echo "❌ Ошибка удаления клона '{$table['TITLE']}': " . implode(', ', $deleteResult->getErrorMessages()) . "<br>";
                    }
                }
            }
            echo "Всего удалено клонов: {$deletedCount}<br>";
        } else {
            echo "❌ Ошибка получения таблиц для удаления клонов: " . implode(', ', $ownerTablesResult->getErrorMessages()) . "<br>";
        }
        
        // Тест 10: Удаление основной таблицы
        echo "<h2>Тест 10: Удаление основной таблицы</h2>";
        $deleteResult = $tableService->deleteTable((int)$tableId);
        if ($deleteResult->isSuccess()) {
            echo "✅ Основная таблица удалена<br>";
        } else {
            echo "❌ Ошибка удаления основной таблицы: " . implode(', ', $deleteResult->getErrorMessages()) . "<br>";
        }
        
    } else {
        echo "❌ Ошибка создания таблицы: " . implode(', ', $result->getErrorMessages()) . "<br>";
    }
    
    echo "<h2>✅ Все тесты TableService завершены</h2>";
    
} catch (Exception $e) {
    echo "<h2>❌ Ошибка выполнения тестов</h2>";
    echo "Сообщение: " . $e->getMessage() . "<br>";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';