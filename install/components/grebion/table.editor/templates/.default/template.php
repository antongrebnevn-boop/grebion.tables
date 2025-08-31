<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Данные компонента
$componentId = 'grebion-table-editor-' . mt_rand(100000, 999999);
$schemaId    = $arResult['SCHEMA_ID'];
$tableId     = $arResult['TABLE_ID'];
$inputName   = $arResult['INPUT_NAME'];
$schema      = $arResult['SCHEMA'] ?? [];
$tableData   = $arResult['TABLE_DATA'] ?? [];
$fieldTypes  = $arResult['FIELD_TYPES'];

?>
<style>
    /**
     * Стили компонента редактора таблиц
     */
    .grebion-table-editor-wrapper {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        background: #f9f9f9;
        margin: 10px 0;
    }

    .grebion-table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }

    .grebion-table-title {
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }



    .grebion-data-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .grebion-data-table th {
        background: #f5f5f5;
        padding: 12px 8px;
        text-align: left;
        font-weight: bold;
        border-bottom: 1px solid #ddd;
        border-right: 1px solid #ddd;
    }

    .grebion-data-table th:last-child {
        border-right: none;
    }

    .grebion-data-table td {
        padding: 8px;
        border-bottom: 1px solid #eee;
        border-right: 1px solid #eee;
        vertical-align: top;
    }

    .grebion-data-table td:last-child {
        border-right: none;
    }

    .grebion-data-table tbody tr:hover {
        background: #f9f9f9;
    }

    .grebion-cell-input {
        width: 94%;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 3px;
        font-size: 13px;
        min-height: 32px;
        resize: vertical;
    }

    .grebion-cell-select {
        width: 94%;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 3px;
        font-size: 13px;
        background: white;
    }

    .grebion-cell-checkbox {
        transform: scale(1.2);
    }

    .grebion-row-actions {
        white-space: nowrap;
        text-align: center;
    }

    .grebion-add-row-container {
        margin-top: 15px;
        text-align: center;
    }

    .grebion-save-container {
        margin-top: 20px;
        text-align: center;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }

    .grebion-warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
        color: #856404;
        text-align: center;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary { background: #007bff; color: white; }
    .btn-success { background: #28a745; color: white; }
    .btn-danger { background: #dc3545; color: white; }
    .btn-sm { padding: 4px 8px; font-size: 11px; }
</style>

<div class="grebion-table-editor-wrapper" id="<?= htmlspecialcharsbx($componentId) ?>">
    <!-- Hidden поле для передачи TABLE_ID -->
    <input type="hidden" 
           name="<?= htmlspecialcharsbx($inputName) ?>" 
           id="<?= htmlspecialcharsbx($componentId) ?>-table-id" 
           value="<?= $tableId ?>">

    <?php if (empty($schema)): ?>
        <div class="grebion-warning">
            <?= Loc::getMessage('GREBION_TABLE_EDITOR_NO_SCHEMA') ?>
        </div>
    <?php else: ?>
        <!-- Заголовок таблицы -->
        <div class="grebion-table-header">
            <div class="grebion-table-title">
                <?= htmlspecialcharsbx($schema['NAME']) ?>
                <?php if (!empty($schema['DESCRIPTION'])): ?>
                    <small style="color: #666; font-weight: normal;">
                        - <?= htmlspecialcharsbx($schema['DESCRIPTION']) ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Таблица данных -->
        <table class="grebion-data-table" id="<?= htmlspecialcharsbx($componentId) ?>-table">
            <thead>
                <tr>
                    <?php foreach ($schema['COLUMNS'] as $column): ?>
                        <th title="<?= htmlspecialcharsbx($column['title']) ?> (<?= htmlspecialcharsbx($fieldTypes[$column['type']] ?? $column['type']) ?>)">
                            <?= htmlspecialcharsbx($column['title']) ?>
                            <?php if ($column['type'] === 'select' || $column['type'] === 'multiselect'): ?>
                                <small style="color: #666;">
                                    (<?= htmlspecialcharsbx($fieldTypes[$column['type']]) ?>)
                                </small>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <th style="width: 80px;"><?= Loc::getMessage('GREBION_TABLE_EDITOR_ACTIONS') ?></th>
                </tr>
            </thead>
            <tbody id="<?= htmlspecialcharsbx($componentId) ?>-tbody">
                <?php if (!empty($tableData['ROWS'])): ?>
                    <?php foreach ($tableData['ROWS'] as $rowIndex => $row): ?>
                        <tr data-row-index="<?= $rowIndex ?>">
                            <?php foreach ($schema['COLUMNS'] as $column): ?>
                                <td>
                                    <?php
                                    $fieldValue = $row['DATA'][$column['code']] ?? '';
                                    echo renderField($column, $fieldValue, $componentId, $rowIndex);
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="grebion-row-actions">
                                <button type="button" class="btn btn-danger btn-sm" 
                                        onclick="GrebionTableEditor.removeRow('<?= htmlspecialcharsbx($componentId) ?>', <?= $rowIndex ?>)">
                                    <?= Loc::getMessage('GREBION_TABLE_EDITOR_DELETE_ROW') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Кнопка добавления строки -->
        <div class="grebion-add-row-container">
            <button type="button" class="btn btn-primary" 
                    onclick="GrebionTableEditor.addRow('<?= htmlspecialcharsbx($componentId) ?>')">
                <?= Loc::getMessage('GREBION_TABLE_EDITOR_ADD_ROW') ?>
            </button>
        </div>

        <!-- Кнопка сохранения -->
        <div class="grebion-save-container">
            <button type="button" class="btn btn-success" 
                    id="<?= htmlspecialcharsbx($componentId) ?>-save-btn"
                    onclick="GrebionTableEditor.saveTable('<?= htmlspecialcharsbx($componentId) ?>')">
                <?= Loc::getMessage('GREBION_TABLE_EDITOR_SAVE_TABLE') ?>
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
// Инициализация компонента согласно соглашениям Битрикс
BX.ready(function() {
    // Передача данных в JavaScript объект
    if (window.GrebionTableEditor) {
        const componentId = '<?= htmlspecialcharsbx($componentId) ?>';
        const schemaId    = <?= (int)$schemaId ?>;
        const tableId     = <?= (int)$tableId ?>;
        const schema      = <?= json_encode($schema, JSON_UNESCAPED_UNICODE) ?>;
        const fieldTypes  = <?= json_encode($fieldTypes, JSON_UNESCAPED_UNICODE) ?>;
        
        // Инициализация компонента
        GrebionTableEditor.init(componentId, schemaId, tableId, schema, fieldTypes);
    }
});
</script>

<?php
/**
 * Функция рендеринга поля в зависимости от типа
 */
function renderField($column, $value, $componentId, $rowIndex) {
    $fieldName = htmlspecialcharsbx($componentId . '-field-' . $column['code'] . '-' . $rowIndex);
    $fieldId = $fieldName;
    
    switch ($column['type']) {
        case 'text':
        case 'email':
        case 'url':
        case 'phone':
            return '<input type="' . ($column['type'] === 'text' ? 'text' : $column['type']) . '" 
                           class="grebion-cell-input" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '"
                           value="' . htmlspecialcharsbx($value) . '">';
                           
        case 'number':
            return '<input type="number" 
                           class="grebion-cell-input" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '"
                           value="' . htmlspecialcharsbx($value) . '">';
                           
        case 'date':
            return '<input type="date" 
                           class="grebion-cell-input" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '"
                           value="' . htmlspecialcharsbx($value) . '">';
                           
        case 'datetime':
            return '<input type="datetime-local" 
                           class="grebion-cell-input" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '"
                           value="' . htmlspecialcharsbx($value) . '">';
                           
        case 'boolean':
            $checked = $value ? 'checked' : '';
            return '<input type="checkbox" 
                           class="grebion-cell-checkbox" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '"
                           value="1" ' . $checked . '>';
                           
        case 'select':
            $options = $column['options'] ?? [];
            $html = '<select class="grebion-cell-select" name="' . $fieldName . '" id="' . $fieldId . '">';
            $html .= '<option value="">Выберите...</option>';
            foreach ($options as $option) {
                $selected = ($value === $option) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialcharsbx($option) . '" ' . $selected . '>' . 
                         htmlspecialcharsbx($option) . '</option>';
            }
            $html .= '</select>';
            return $html;
            
        case 'multiselect':
            $options = $column['options'] ?? [];
            $selectedValues = is_array($value) ? $value : explode(',', $value);
            $html = '<select class="grebion-cell-select" name="' . $fieldName . '[]" id="' . $fieldId . '" multiple size="3">';
            foreach ($options as $option) {
                $selected = in_array($option, $selectedValues) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialcharsbx($option) . '" ' . $selected . '>' . 
                         htmlspecialcharsbx($option) . '</option>';
            }
            $html .= '</select>';
            return $html;
            
        case 'file':
            return '<input type="file" 
                           class="grebion-cell-input" 
                           name="' . $fieldName . '" 
                           id="' . $fieldId . '">';
                           
        default:
            return '<textarea class="grebion-cell-input" 
                             name="' . $fieldName . '" 
                             id="' . $fieldId . '" 
                             rows="2">' . htmlspecialcharsbx($value) . '</textarea>';
    }
}
?>
