<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
 
use Grebion\Tables\Repository\TableRepository;
use Grebion\Tables\Repository\ColumnRepository;
use Grebion\Tables\Repository\RowRepository;
use Bitrix\Main\Loader;

if (!Loader::includeModule('grebion.tables')) {
    die('Модуль grebion.tables не установлен');
}

if (!Loader::includeModule('highloadblock')) {
    die('Модуль highloadblock не установлен');
}

echo '<h1>Тест репозиториев</h1>';

// Инициализация репозиториев
$tableRepo = new TableRepository();
$columnRepo = new ColumnRepository();
$rowRepo = new RowRepository();

echo '<h2>1. Тест TableRepository</h2>';

try {
    // Получение списка таблиц
    $tables = $tableRepo->getList([], ['NAME' => 'ASC'], 5);
    echo '<p>Найдено таблиц: ' . $tables->getSelectedRowsCount() . '</p>';
    
    while ($table = $tables->fetch()) {
        echo '<p>Таблица: ' . htmlspecialchars($table['NAME']) . ' (ID: ' . $table['ID'] . ')</p>';
        
        // Тест получения по ID
        $tableById = $tableRepo->getById((int)$table['ID']);
        if ($tableById) {
            echo '<p>✓ Получение по ID работает</p>';
        }
        
        // Тест получения по имени
        $tableByName = $tableRepo->getByName($table['NAME']);
        if ($tableByName) {
            echo '<p>✓ Получение по имени работает</p>';
        }
        
        // Тест ColumnRepository для этой таблицы
        echo '<h3>Колонки таблицы ' . htmlspecialchars($table['NAME']) . ':</h3>';
        $columns = $columnRepo->getByTableId((int)$table['ID']);
        echo '<p>Найдено колонок: ' . $columns->getSelectedRowsCount() . '</p>';
        
        while ($column = $columns->fetch()) {
            echo '<p>- ' . htmlspecialchars($column['FIELD_NAME']) . ' (' . htmlspecialchars($column['USER_TYPE_ID']) . ')</p>';
        }
        
        // Тест RowRepository для этой таблицы
        echo '<h3>Строки таблицы ' . htmlspecialchars($table['NAME']) . ':</h3>';
        $rows = $rowRepo->getByTableId((int)$table['ID'], ['SORT' => 'ASC'], 3);
        echo '<p>Найдено строк: ' . $rows->getSelectedRowsCount() . '</p>';
        
        while ($row = $rows->fetch()) {
            echo '<p>Строка ID: ' . $row['ID'] . ', SORT: ' . $row['SORT'] . '</p>';
        }
        
        break; // Тестируем только первую таблицу
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка TableRepository: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>2. Тест кеширования</h2>';

try {
    // Первый запрос (должен попасть в БД)
    $start = microtime(true);
    $tables1 = $tableRepo->getList([], ['NAME' => 'ASC'], 5);
    $time1 = microtime(true) - $start;
    echo '<p>Первый запрос: ' . round($time1 * 1000, 2) . ' мс</p>';
    
    // Второй запрос (должен взяться из кеша)
    $start = microtime(true);
    $tables2 = $tableRepo->getList([], ['NAME' => 'ASC'], 5);
    $time2 = microtime(true) - $start;
    echo '<p>Второй запрос (кеш): ' . round($time2 * 1000, 2) . ' мс</p>';
    
    if ($time2 < $time1) {
        echo '<p style="color: green;">✓ Кеширование работает</p>';
    } else {
        echo '<p style="color: orange;">⚠ Кеширование может не работать</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка кеширования: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>3. Тест CRUD операций</h2>';

try {
    // Найдем первую таблицу для тестов
    $tables = $tableRepo->getList([], ['ID' => 'ASC'], 1);
    $testTable = $tables->fetch();
    
    if ($testTable) {
        $tableId = (int)$testTable['ID'];
        echo '<p>Тестируем на таблице: ' . htmlspecialchars($testTable['NAME']) . '</p>';
        
        // Тест создания строки
        $newRowData = [
            'TABLE_ID' => $tableId,
            'UF_NAME' => 'Тестовая запись ' . date('Y-m-d H:i:s')
        ];
        
        $saveResult = $rowRepo->save($newRowData);
        if ($saveResult->isSuccess()) {
            $newRowId = $saveResult->getData()['id'];
            echo '<p style="color: green;">✓ Создание строки: ID ' . $newRowId . '</p>';
            
            // Тест обновления
            $updateData = [
                'UF_NAME' => 'Обновленная запись ' . date('Y-m-d H:i:s')
            ];
            
            $updateResult = $rowRepo->save($updateData, $newRowId);
            if ($updateResult->isSuccess()) {
                echo '<p style="color: green;">✓ Обновление строки</p>';
            } else {
                echo '<p style="color: red;">✗ Ошибка обновления: ' . implode(', ', $updateResult->getErrorMessages()) . '</p>';
            }
            
            // Тест получения по ID
            $row = $rowRepo->getById($newRowId);
            if ($row) {
                echo '<p style="color: green;">✓ Получение по ID: ' . htmlspecialchars($row['UF_NAME']) . '</p>';
            }
            
            // Тест удаления
            $deleteResult = $rowRepo->delete($newRowId);
            if ($deleteResult->isSuccess()) {
                echo '<p style="color: green;">✓ Удаление строки</p>';
            } else {
                echo '<p style="color: red;">✗ Ошибка удаления: ' . implode(', ', $deleteResult->getErrorMessages()) . '</p>';
            }
            
        } else {
            echo '<p style="color: red;">✗ Ошибка создания: ' . implode(', ', $saveResult->getErrorMessages()) . '</p>';
        }
        
    } else {
        echo '<p style="color: orange;">⚠ Нет таблиц для тестирования CRUD</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка CRUD: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>4. Тест массовых операций</h2>';

try {
    // Найдем таблицу для тестов
    $tables = $tableRepo->getList([], ['ID' => 'ASC'], 1);
    $testTable = $tables->fetch();
    
    if ($testTable) {
        $tableId = (int)$testTable['ID'];
        
        // Тест массового добавления
        $bulkData = [
            ['UF_NAME' => 'Массовая запись 1'],
            ['UF_NAME' => 'Массовая запись 2'],
            ['UF_NAME' => 'Массовая запись 3']
        ];
        
        $bulkResult = $rowRepo->bulkInsert($bulkData, $tableId);
        if ($bulkResult->isSuccess()) {
            $insertedIds = $bulkResult->getData()['ids'];
            echo '<p style="color: green;">✓ Массовое добавление: ' . count($insertedIds) . ' записей</p>';
            
            // Тест обновления сортировки
            $sortData = [];
            foreach ($insertedIds as $i => $id) {
                $sortData[$id] = ($i + 1) * 100;
            }
            
            $sortResult = $rowRepo->updateSort($sortData);
            if ($sortResult->isSuccess()) {
                echo '<p style="color: green;">✓ Обновление сортировки</p>';
            }
            
            // Тест массового удаления
            $deleteResult = $rowRepo->bulkDelete($insertedIds);
            if ($deleteResult->isSuccess()) {
                echo '<p style="color: green;">✓ Массовое удаление: ' . count($insertedIds) . ' записей</p>';
            } else {
                echo '<p style="color: red;">✗ Ошибка массового удаления: ' . implode(', ', $deleteResult->getErrorMessages()) . '</p>';
            }
            
        } else {
            echo '<p style="color: red;">✗ Ошибка массового добавления: ' . implode(', ', $bulkResult->getErrorMessages()) . '</p>';
        }
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка массовых операций: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>5. Тест счетчиков</h2>';

try {
    $totalTables = $tableRepo->getCount([]);
    echo '<p>Всего таблиц: ' . $totalTables . '</p>';
    
    if ($totalTables > 0) {
        $tables = $tableRepo->getList([], ['ID' => 'ASC'], 1);
        $testTable = $tables->fetch();
        
        if ($testTable) {
            $tableId = (int)$testTable['ID'];
            $totalRows = $rowRepo->getCount(['TABLE_ID' => $tableId]);
            echo '<p>Строк в таблице ' . htmlspecialchars($testTable['NAME']) . ': ' . $totalRows . '</p>';
            
            $totalColumns = $columnRepo->getCount(['TABLE_ID' => $tableId]);
            echo '<p>Колонок в таблице: ' . $totalColumns . '</p>';
        }
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка счетчиков: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>6. Тест очистки кеша</h2>';

try {
    // Очистим кеш всех репозиториев
    $tableRepo->clearCache();
    $columnRepo->clearCache();
    $rowRepo->clearCache();
    
    echo '<p style="color: green;">✓ Кеш очищен</p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка очистки кеша: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h2>Тестирование завершено</h2>';
echo '<p>Время выполнения: ' . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . ' сек</p>';

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
?>