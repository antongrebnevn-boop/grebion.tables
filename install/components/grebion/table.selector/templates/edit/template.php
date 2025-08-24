<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Repository\TableRepository;

Loc::loadMessages(__FILE__);

$userField = $arParams['userField'] ?? [];
$additionalParameters = $arParams['additionalParameters'] ?? [];
$value = $additionalParameters['VALUE'] ?? '';
$fieldName = $additionalParameters['NAME'] ?? $userField['FIELD_NAME'];
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
    
    echo '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $table['ID'] . '">' . htmlspecialchars($table['NAME']) . '</a>';
} else {
    // Режим редактирования
    $tableRepository = new TableRepository();
    $tables = $tableRepository->getList();
    ?>
    <select name="<?= htmlspecialchars($fieldName) ?>" class="grebion-table-selector">
        <option value=""><?= Loc::getMessage('GREBION_TABLES_UF_SELECT_TABLE') ?></option>
        <?php foreach ($tables as $table): ?>
            <option value="<?= $table['ID'] ?>"<?= ($value == $table['ID']) ? ' selected' : '' ?>>
                <?= htmlspecialchars($table['NAME']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}