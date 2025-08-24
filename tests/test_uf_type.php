<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\UserFieldTable;
use Grebion\Tables\Uftype\TableProperty;

if (!Loader::includeModule('grebion.tables')) {
    die('Модуль grebion.tables не установлен');
}

echo "<h1>Тест UF-типа TableProperty</h1>";

// 1. Проверяем регистрацию UF-типа
echo "<h2>1. Проверка регистрации UF-типа</h2>";
global $USER_FIELD_MANAGER;
$userTypes = $USER_FIELD_MANAGER->GetUserType();
if (isset($userTypes[TableProperty::USER_TYPE_ID])) {
    echo "✅ UF-тип '" . TableProperty::USER_TYPE_ID . "' зарегистрирован<br>";
    echo "Название: " . $userTypes[TableProperty::USER_TYPE_ID]['DESCRIPTION'] . "<br>";
} else {
    echo "❌ UF-тип '" . TableProperty::USER_TYPE_ID . "' НЕ зарегистрирован<br>";
}

// 2. Проверяем методы класса TableProperty
echo "<h2>2. Проверка методов класса TableProperty</h2>";
$description = TableProperty::GetUserTypeDescription();
echo "GetUserTypeDescription(): <br>";
echo "<pre>" . print_r($description, true) . "</pre>";

// 3. Тестируем валидацию
echo "<h2>3. Тест валидации</h2>";
$userField = [
    'FIELD_NAME' => 'UF_TEST_TABLE',
    'USER_TYPE_ID' => TableProperty::USER_TYPE_ID,
    'MULTIPLE' => 'N'
];

// Тест с корректным значением
echo "Тест с корректным ID таблицы (1): ";
$result = TableProperty::onBeforeSave($userField, 1);
if ($result === 1) {
    echo "✅ Валидация прошла<br>";
} else {
    echo "❌ Валидация не прошла: " . print_r($result, true) . "<br>";
}

// Тест с некорректным значением
echo "Тест с некорректным значением (строка): ";
$result = TableProperty::onBeforeSave($userField, 'invalid');
if (is_array($result) && !empty($result)) {
    echo "✅ Валидация корректно отклонила некорректное значение<br>";
} else {
    echo "❌ Валидация пропустила некорректное значение<br>";
}

// Тест с пустым значением
echo "Тест с пустым значением: ";
$result = TableProperty::onBeforeSave($userField, '');
if ($result === '') {
    echo "✅ Пустое значение обработано корректно<br>";
} else {
    echo "❌ Пустое значение обработано некорректно<br>";
}
 
// 4. Проверяем компонент
echo "<h2>4. Проверка компонента</h2>";
$componentPath = '/bitrix/modules/grebion.tables/install/components/grebion/table.selector/';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $componentPath . 'class.php')) {
    echo "✅ Файл компонента найден<br>";
    
    // Проверяем шаблоны
    $templatesPath = $_SERVER['DOCUMENT_ROOT'] . $componentPath . 'templates/';
    $templates = [];
    if (is_dir($templatesPath)) {
        $dirs = scandir($templatesPath);
        foreach ($dirs as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($templatesPath . $dir)) {
                $templates[] = $dir;
            }
        }
    }
    echo "Доступные шаблоны: " . implode(', ', $templates) . "<br>";
    
    if (in_array('edit', $templates) && in_array('main.view', $templates)) {
        echo "✅ Необходимые шаблоны (edit, main.view) созданы<br>";
    } else {
        echo "❌ Не все необходимые шаблоны созданы<br>";
    }
} else {
    echo "❌ Файл компонента НЕ найден<br>";
}

echo "<h2>Тест завершен</h2>";