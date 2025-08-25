<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Repository\TableRepository;

/**
 * @var TableSelectorComponent $component
 * @var array $arResult
 */

Loc::loadMessages(__FILE__);

$userField = $arResult['userField'] ?? [];
$additionalParameters = $arResult['additionalParameters'] ?? [];
$fieldName = $arResult['fieldName'] ?? '';
$values = $arResult['value'] ?? [];

// Поддержка свойств инфоблоков через дополнительные параметры
$property = $additionalParameters['property'] ?? [];
$propertyValue = $additionalParameters['propertyValue'] ?? [];
$controlName = $additionalParameters['controlName'] ?? [];

// Определяем значение и имя поля
if (!empty($property)) {
    // Режим свойства инфоблока
    $values = [$propertyValue['VALUE'] ?? ''];
    $fieldName = $controlName['VALUE'] ?? '';
}

// Режим просмотра
if (empty($values) || (count($values) === 1 && empty($values[0]))) {
    echo '';
    return;
}

$tableRepository = new TableRepository();

foreach ($values as $index => $value) {
    if (empty($value)) {
        continue;
    }

    if ($index > 0) {
        echo '<br>';
    }

    $table = $tableRepository->getById((int)$value);

    if (!$table) {
        echo Loc::getMessage('GREBION_TABLES_UF_TABLE_NOT_FOUND');
        continue;
    }

    echo '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $table->getId() . '">' . htmlspecialchars($table->getName()) . '</a>';
}