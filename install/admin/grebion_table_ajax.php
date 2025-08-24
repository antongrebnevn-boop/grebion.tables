<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Grebion\Tables\Service\TableService;

// Проверяем права доступа
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ запрещен');
}

// Проверяем сессию
if (!check_bitrix_sessid()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Неверная сессия'
    ]);
    die();
}

// Подключаем модуль
if (!Loader::includeModule('grebion.tables')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Модуль не установлен'
    ]);
    die();
}

$request = Application::getInstance()->getContext()->getRequest();
$action = $request->getPost('action');

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'get_tables_list':
            $tableService = new TableService();
            $result = $tableService->getTablesList();
            
            if ($result->isSuccess()) {
                $tables = $result->getData();
                
                // Форматируем данные для фронтенда
                $formattedTables = [];
                foreach ($tables as $table) {
                    $formattedTables[] = [
                        'ID' => $table['ID'],
                        'TITLE' => $table['TITLE'],
                        'OWNER_TYPE' => $table['OWNER_TYPE'],
                        'OWNER_ID' => $table['OWNER_ID'],
                        'DATE_CREATE' => $table['DATE_CREATE'],
                        'DATE_MODIFY' => $table['DATE_MODIFY']
                    ];
                }
                
                echo json_encode([
                    'status' => 'success',
                    'tables' => $formattedTables
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => implode(', ', $result->getErrorMessages())
                ]);
            }
            break;
            
        case 'get_table_info':
            $tableId = (int)$request->getPost('table_id');
            
            if ($tableId <= 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Неверный ID таблицы'
                ]);
                break;
            }
            
            $tableService = new TableService();
            $result = $tableService->getTable($tableId);
            
            if ($result->isSuccess()) {
                $table = $result->getData();
                
                echo json_encode([
                    'status' => 'success',
                    'table' => [
                        'ID' => $table['ID'],
                        'TITLE' => $table['TITLE'],
                        'OWNER_TYPE' => $table['OWNER_TYPE'],
                        'OWNER_ID' => $table['OWNER_ID']
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => implode(', ', $result->getErrorMessages())
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Неизвестное действие'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php';