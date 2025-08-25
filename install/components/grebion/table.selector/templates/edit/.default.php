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

// Режим редактирования
$tableRepository = new TableRepository();
$tables = $tableRepository->getList();
?>
<span class="field-wrap">
    <?php foreach ($values as $index => $value): ?>
        <span class="field-item">
            <select name="<?= htmlspecialchars($fieldName) ?>" class="grebion-table-selector">
                <option value=""><?= Loc::getMessage('GREBION_TABLES_UF_SELECT_TABLE') ?></option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?= $table->getId() ?>"<?= ($value == $table->getId()) ? ' selected' : '' ?>>
                        <?= htmlspecialchars($table->getName()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </span>
    <?php endforeach; ?>

    <?php if (($userField['MULTIPLE'] ?? 'N') === 'Y' && ($additionalParameters['SHOW_BUTTON'] ?? 'Y') !== 'N'): ?>
        <?= $component->getHtmlBuilder()->getCloneButton($fieldName) ?>
    <?php endif; ?>
</span>