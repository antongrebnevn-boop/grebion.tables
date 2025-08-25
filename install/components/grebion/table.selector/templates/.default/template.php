<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Repository\TableRepository;

Loc::loadMessages(__FILE__);

// Поддержка UF-типов
$userField = $arParams['userField'] ?? [];
$additionalParameters = $arParams['additionalParameters'] ?? [];

// Поддержка свойств инфоблоков
$property = $arParams['property'] ?? [];
$propertyValue = $arParams['value'] ?? [];
$controlName = $arParams['controlName'] ?? [];

// Определяем значение и имя поля
if (!empty($property)) {
    // Режим свойства инфоблока
    $value = $propertyValue['VALUE'] ?? '';
    $fieldName = $controlName['VALUE'] ?? '';
} else {
    // Режим UF-типа
    $value = $additionalParameters['VALUE'] ?? '';
    $fieldName = $additionalParameters['NAME'] ?? $userField['FIELD_NAME'];
}

$mode = $additionalParameters['mode'] ?? 'edit';

if ($mode === 'main.view') {
    // Режим просмотра
    if (empty($value)) {
        echo '';
        return;
    }
    
    $tableRepository = new TableRepository();
    $table = $tableRepository->getById((int)$value);
    
    if (!$table) {
        echo Loc::getMessage('GREBION_TABLES_UF_TABLE_NOT_FOUND');
        return;
    }
    
    echo '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $table->getId() . '">' . htmlspecialchars($table->getName()) . '</a>';
} else {
    // Режим редактирования
    $tableRepository = new TableRepository();
    $tables = $tableRepository->getList();
    ?>
    <select name="<?= htmlspecialchars($fieldName) ?>" class="grebion-table-selector">
        <option value=""><?= Loc::getMessage('GREBION_TABLES_UF_SELECT_TABLE') ?></option>
        <?php foreach ($tables as $table): ?>
            <option value="<?= $table->getId() ?>"<?= ($value == $table->getId()) ? ' selected' : '' ?>>
                <?= htmlspecialchars($table->getName()) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}