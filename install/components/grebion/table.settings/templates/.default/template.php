<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$componentId   = 'grebion-table-settings-' . mt_rand();
$inputName     = $arResult['INPUT_NAME'];
$schemaId      = $arResult['SCHEMA_ID'];
$schemas       = $arResult['SCHEMAS'];
$columnTypes   = $arResult['COLUMN_TYPES'];
$currentSchema = $arResult['CURRENT_SCHEMA'];

?>
<style>
    .grebion-table-settings-wrapper {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        background: #f9f9f9;
        margin: 10px 0;
    }

    .grebion-schema-selection {
        margin-bottom: 20px;
    }

    .grebion-schema-select-row {
        display: flex;
        gap: 15px;
        align-items: end;
    }

    .grebion-schema-select-field {
        flex: 1;
    }

    .grebion-schema-selection label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .grebion-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 15px;
        color: #856404;
    }

    .grebion-new-schema-form {
        border-top: 1px solid #ddd;
        padding-top: 15px;
    }

    .grebion-columns-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .grebion-column-item {
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 10px;
        background: white;
    }

    .grebion-column-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        font-weight: bold;
    }

    .grebion-column-fields {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 100px;
        gap: 10px;
    }

    .grebion-column-options {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }

    .grebion-column-options .form-group {
        margin-bottom: 10px;
    }

    .grebion-column-options .form-text {
        font-size: 11px;
        color: #666;
        margin-top: 3px;
    }

    .grebion-actions {
        margin-top: 20px;
        text-align: center;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-group label {
        display: block;
        margin-bottom: 3px;
        font-size: 12px;
        color: #666;
    }

    .form-control {
        width: 100%;
        padding: 5px 8px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }

    .btn {
        padding: 6px 12px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
    }

    .btn-primary {
        background: #0ea5e9;
        color: white;
    }

    .btn-success {
        background: #22c55e;
        color: white;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
    }
</style>
<div class="grebion-table-settings-wrapper" id="<?= htmlspecialcharsbx($componentId) ?>">
    <!-- Hidden поле для передачи выбранного SCHEMA_ID -->
    <input type="hidden" 
           name="<?= htmlspecialcharsbx($inputName) ?>" 
           id="<?= htmlspecialcharsbx($componentId) ?>-schema-id" 
           value="<?= $schemaId ?>">

    <div class="grebion-table-settings-panel">
        <h4>Настройка схемы таблицы</h4>
        
        <?php if ($schemaId > 0 && (!$currentSchema || !isset($currentSchema['EXISTS']))): ?>
            <div class="grebion-warning">
                <strong>Внимание:</strong> Схема с ID <?= $schemaId ?> не найдена. Выберите существующую схему или создайте новую.
            </div>
        <?php endif; ?>
        
        <!-- Выбор существующей схемы -->
        <div class="grebion-schema-selection">
            <div class="grebion-schema-select-row">
                <div class="grebion-schema-select-field">
                    <label>Выберите схему:</label>
                    <select id="<?= htmlspecialcharsbx($componentId) ?>-schema-select" 
                            class="form-control">
                        <option value="0">-- Выберите схему --</option>
                        <?php foreach ($schemas as $schema): ?>
                            <option value="<?= (int)$schema['ID'] ?>" 
                                    <?= $schemaId === (int)$schema['ID'] ? 'selected' : '' ?>>
                                <?= htmlspecialcharsbx($schema['NAME']) ?>
                                <?php if ($schema['DESCRIPTION']): ?>
                                    (<?= htmlspecialcharsbx($schema['DESCRIPTION']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grebion-schema-actions">
                    <button type="button" 
                            class="btn btn-primary"
                            onclick="GrebionTableSettings.createNewSchema('<?= htmlspecialcharsbx($componentId) ?>')">
                        Создать новую схему
                    </button>
                </div>
            </div>
        </div>

        <!-- Форма создания/редактирования схемы -->
        <div class="grebion-new-schema-form" 
             id="<?= htmlspecialcharsbx($componentId) ?>-new-form"
             style="<?= $schemaId > 0 ? '' : 'display: none;' ?>">
            
            <div class="form-group">
                <label>Название схемы:</label>
                <input type="text" 
                       id="<?= htmlspecialcharsbx($componentId) ?>-schema-name" 
                       class="form-control" 
                       placeholder="Введите название схемы"
                       value="">
            </div>
            
            <div class="form-group">
                <label>Описание:</label>
                <textarea id="<?= htmlspecialcharsbx($componentId) ?>-schema-desc" 
                          class="form-control" 
                          rows="2" 
                          placeholder="Описание схемы (необязательно)"></textarea>
            </div>

            <!-- Колонки схемы -->
            <div class="grebion-columns-wrapper">
                <div class="grebion-columns-header">
                    <h5>Колонки таблицы</h5>
                    <button type="button" 
                            class="btn btn-sm btn-primary"
                            onclick="GrebionTableSettings.addColumn('<?= htmlspecialcharsbx($componentId) ?>')">
                        + Добавить колонку
                    </button>
                </div>
                
                <div class="grebion-columns-list" 
                     id="<?= htmlspecialcharsbx($componentId) ?>-columns">
                    <!-- Колонки загружаются динамически через AJAX при инициализации -->
                </div>
            </div>

            <div class="grebion-actions">
                <button type="button" 
                        class="btn btn-success"
                        id="<?= htmlspecialcharsbx($componentId) ?>-save-btn"
                        onclick="GrebionTableSettings.saveSchema('<?= htmlspecialcharsbx($componentId) ?>')">
                    <?= $schemaId > 0 ? 'Обновить схему' : 'Сохранить схему' ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Инициализация компонента согласно соглашениям Битрикс
BX.ready(function() {
    // Передача данных в JavaScript объект
    if (window.GrebionTableSettings) {
        const componentId = '<?= htmlspecialcharsbx($componentId) ?>';
        const initialSchemaId = <?= $schemaId ?> || 0;
        const columnTypes = <?= json_encode($columnTypes, JSON_UNESCAPED_UNICODE) ?>;
        
        // Инициализация компонента
        GrebionTableSettings.init(componentId, initialSchemaId, columnTypes);
    }
});
</script>


