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

$component = $this->getComponent();
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

$mode = $additionalParameters['mode'] ?? 'edit';

// Отображение значений (режим просмотра)
$isFirst = true;
foreach ($values as $value) {
    if (!$isFirst) {
        echo '<br>';
    }
    $isFirst = false;

    if (empty($value)) {
        continue;
    }

    $tableRepository = new TableRepository();
    $table = $tableRepository->getById((int)$value);

    if (!$table) {
        echo Loc::getMessage('GREBION_TABLES_UF_TABLE_NOT_FOUND');
        continue;
    }

    echo '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $table->getId() . '">' . htmlspecialchars($table->getName()) . '</a>';
}