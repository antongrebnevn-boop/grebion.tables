<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Grebion\Tables\Service\PermissionService;
use Grebion\Tables\Repository\TableRepository;
use Bitrix\Main\Loader;

if (!Loader::includeModule('grebion.tables')) {
    die('Модуль grebion.tables не установлен');
}

echo "<h1>Тест PermissionService</h1>";

try {
    $permissionService = new PermissionService();
    
    // Тест 1: Проверка создания группы
    echo "<h2>Тест 1: Создание группы для таблицы</h2>";
    $tableId = 1;
    $role = PermissionService::ROLE_EDITOR;
    
    $groupCode = "GREBION_TABLE_{$tableId}_{$role}";
    echo "Код группы: {$groupCode}<br>";
    
    // Тест 2: Назначение роли пользователю
    echo "<h2>Тест 2: Назначение роли пользователю</h2>";
    $userId = 1; // ID администратора
    
    $result = $permissionService->assignRole($tableId, $userId, $role);
    if ($result->isSuccess()) {
        echo "✅ Роль {$role} успешно назначена пользователю {$userId} для таблицы {$tableId}<br>";
    } else {
        echo "❌ Ошибка назначения роли: " . implode(', ', $result->getErrorMessages()) . "<br>";
    }
    
    // Тест 3: Проверка роли пользователя
    echo "<h2>Тест 3: Проверка роли пользователя</h2>";
    $userRole = $permissionService->getUserRoleForTable($tableId, $userId);
    echo "Роль пользователя {$userId} для таблицы {$tableId}: {$userRole}<br>";
    
    // Тест 4: Проверка прав доступа
    echo "<h2>Тест 4: Проверка прав доступа</h2>";
    $canRead = $permissionService->canRead($tableId, $userId);
    $canWrite = $permissionService->canWrite($tableId, $userId);
    $canDelete = $permissionService->canDelete($tableId, $userId);
    
    echo "Права пользователя {$userId} для таблицы {$tableId}:<br>";
    echo "- Чтение: " . ($canRead ? '✅' : '❌') . "<br>";
    echo "- Запись: " . ($canWrite ? '✅' : '❌') . "<br>";
    echo "- Удаление: " . ($canDelete ? '✅' : '❌') . "<br>";
    
    // Тест 5: Получение пользователей таблицы
    echo "<h2>Тест 5: Получение пользователей таблицы</h2>";
    $tableUsers = $permissionService->getTableUsers($tableId);
    echo "Пользователи с доступом к таблице {$tableId}:<br>";
    foreach ($tableUsers as $user) {
        echo "- ID: {$user['ID']}, Роль: {$user['ROLE']}<br>";
    }
    
    // Тест 6: Получение таблиц пользователя
    echo "<h2>Тест 6: Получение таблиц пользователя</h2>";
    $userTables = $permissionService->getUserTables($userId);
    echo "Таблицы пользователя {$userId}:<br>";
    foreach ($userTables as $table) {
        echo "- Таблица ID: {$table['TABLE_ID']}, Роль: {$table['ROLE']}<br>";
    }
    
    echo "<h2>✅ Все тесты завершены</h2>";
    
} catch (Exception $e) {
    echo "<h2>❌ Ошибка выполнения тестов</h2>";
    echo "Сообщение: " . $e->getMessage() . "<br>";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';