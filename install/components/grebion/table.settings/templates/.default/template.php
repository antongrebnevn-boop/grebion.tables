<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use Bitrix\Main\UI\Extension;

Loc::loadMessages(__FILE__);

// Подключаем UI расширения Битрикса
Extension::load(['ui.design-tokens', 'ui.fonts.opensans', 'ui.buttons', 'ui.forms', 'ui.alerts', 'ui.icons']);

$columnTypes = $arResult['COLUMN_TYPES'];
$currentSchema = $arResult['CURRENT_SCHEMA'];
$availableSchemas = $arResult['AVAILABLE_SCHEMAS'];
$htmlControlName = $arResult['HTML_CONTROL_NAME'];
$componentId = $arResult['COMPONENT_ID'];
$currentSchemaId = $arResult['CURRENT_SCHEMA_ID']; 
?>

<div class="grebion-table-settings" id="<?= htmlspecialchars($componentId) ?>">
    <?php if (!empty($arResult['SAVE_SUCCESS'])): ?>
        <div class="ui-alert ui-alert-success">
            <span class="ui-alert-message">Схема успешно сохранена!</span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($arResult['SAVE_ERROR'])): ?>
        <div class="ui-alert ui-alert-danger">
            <span class="ui-alert-message"><?= htmlspecialchars($arResult['SAVE_ERROR']) ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Выбор существующей схемы -->
    <div class="grebion-schema-selector">
        <div class="ui-form-row">
            <div class="ui-form-label">
                <div class="ui-form-label-text">Выберите схему:</div>
            </div>
            <div class="ui-form-content">
                <select name="<?= htmlspecialchars($htmlControlName['VALUE']) ?>" 
                        id="schema-select-<?= htmlspecialchars($componentId) ?>"
                        class="ui-ctl-element"
                        data-component-id="<?= htmlspecialchars($componentId) ?>"
                        data-action="schema-select">
                    <option value="">-- Создать новую схему --</option>
                    <?php foreach ($availableSchemas as $schema): ?>
                        <option value="<?= $schema['ID'] ?>"
                                <?= $currentSchemaId == $schema['ID'] ? 'selected' : '' ?>
                                data-schema-name="<?= htmlspecialchars($schema['NAME']) ?>"
                                data-schema-description="<?= htmlspecialchars($schema['DESCRIPTION']) ?>">
                            <?= htmlspecialchars($schema['NAME']) ?>
                            <?php if ($schema['DESCRIPTION']): ?>
                                - <?= htmlspecialchars($schema['DESCRIPTION']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Настройки схемы -->
    <div class="grebion-schema-settings" id="schema-settings-<?= htmlspecialchars($componentId) ?>">
        <div class="ui-form-row">
            <div class="ui-form-label">
                <div class="ui-form-label-text">Название схемы:</div>
            </div>
            <div class="ui-form-content">
                <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                    <input type="text" 
                           class="ui-ctl-element" 
                           id="schema-name-<?= htmlspecialchars($componentId) ?>"
                           placeholder="Введите название схемы"
                           value="">
                </div>
            </div>
        </div>
        
        <div class="ui-form-row">
            <div class="ui-form-label">
                <div class="ui-form-label-text">Описание:</div>
            </div>
            <div class="ui-form-content">
                <div class="ui-ctl ui-ctl-textarea ui-ctl-w100">
                    <textarea class="ui-ctl-element" 
                              id="schema-description-<?= htmlspecialchars($componentId) ?>"
                              placeholder="Описание схемы (необязательно)"></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Заголовок с кнопками -->
    <div class="grebion-table-header">
        <h3 class="grebion-table-title">Колонки таблицы</h3>
        <div class="grebion-table-actions">
            <button type="button" 
                    class="ui-btn ui-btn-primary ui-btn-icon-add"
                    data-component-id="<?= htmlspecialchars($componentId) ?>"
                    data-action="add-column">
                Добавить колонку
            </button>
            <button type="button" 
                    class="ui-btn ui-btn-success ui-btn-icon-disk"
                    data-component-id="<?= htmlspecialchars($componentId) ?>"
                    data-action="save-schema">
                Сохранить схему
            </button>
        </div>
    </div>
    
    <!-- Контейнер для колонок -->
    <div class="grebion-columns-container" id="columns-container-<?= htmlspecialchars($componentId) ?>">
        <?php if (!empty($currentSchema)): ?>
            <?php foreach ($currentSchema as $index => $column): ?>
                <?= $component->renderColumnItem($componentId, $index, $column, $columnTypes) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Скрытое поле для хранения JSON схемы -->
    <input type="hidden" 
           id="schema-json-<?= htmlspecialchars($componentId) ?>" 
           value="<?= htmlspecialchars(Json::encode($currentSchema)) ?>">
</div>



<script>
// Данные для инициализации компонента
window.grebionTableSettingsData = window.grebionTableSettingsData || {};
window.grebionTableSettingsData['<?= htmlspecialchars($componentId) ?>'] = {
    componentId: '<?= htmlspecialchars($componentId) ?>',
    initialColumnCount: <?= count($currentSchema) ?>,
    columnTypes: <?= json_encode($columnTypes) ?>
};
// Инициализация через BX.ready
BX.ready(function() {
    if (typeof window.GrebionTableSettings !== 'undefined') {
        var data = window.grebionTableSettingsData['<?= htmlspecialchars($componentId) ?>'];
        window.GrebionTableSettings.init(data.componentId, data.initialColumnCount);
        window.grebionColumnTypes = data.columnTypes;
    }
});

</script>