<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$columnTypes = $arResult['COLUMN_TYPES'];
$currentSchema = $arResult['CURRENT_SCHEMA'];
$propertyCode = $arResult['PROPERTY_CODE'];
?>

<div class="grebion-table-settings" id="grebion-table-settings-<?= htmlspecialchars($propertyCode) ?>">
    <div class="grebion-table-settings-header">
        <h3>Настройка схемы таблицы</h3>
        <button type="button" class="btn btn-primary" onclick="GrebionTableSettings.addColumn('<?= htmlspecialchars($propertyCode) ?>')">
            Добавить колонку
        </button>
    </div>
    
    <div class="grebion-table-settings-columns" id="grebion-columns-<?= htmlspecialchars($propertyCode) ?>">
        <?php if (!empty($currentSchema)): ?>
            <?php foreach ($currentSchema as $index => $column): ?>
                <div class="grebion-column-item" data-index="<?= $index ?>">
                    <div class="grebion-column-header">
                        <span class="grebion-column-title"><?= htmlspecialchars($column['title'] ?? 'Колонка ' . ($index + 1)) ?></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="GrebionTableSettings.removeColumn('<?= htmlspecialchars($propertyCode) ?>', <?= $index ?>)">
                            Удалить
                        </button>
                    </div>
                    
                    <div class="grebion-column-fields">
                        <div class="form-group">
                            <label>Код колонки:</label>
                            <input type="text" 
                                   name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA][<?= $index ?>][code]" 
                                   value="<?= htmlspecialchars($column['code'] ?? '') ?>"
                                   class="form-control"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>Название:</label>
                            <input type="text" 
                                   name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA][<?= $index ?>][title]" 
                                   value="<?= htmlspecialchars($column['title'] ?? '') ?>"
                                   class="form-control"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>Тип:</label>
                            <select name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA][<?= $index ?>][type]" 
                                    class="form-control grebion-column-type"
                                    onchange="GrebionTableSettings.onTypeChange('<?= htmlspecialchars($propertyCode) ?>', <?= $index ?>, this.value)">
                                <?php foreach ($columnTypes as $typeCode => $typeName): ?>
                                    <option value="<?= htmlspecialchars($typeCode) ?>"
                                            <?= ($column['type'] ?? '') === $typeCode ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($typeName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Сортировка:</label>
                            <input type="number" 
                                   name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA][<?= $index ?>][sort]" 
                                   value="<?= (int)($column['sort'] ?? ($index + 1) * 100) ?>"
                                   class="form-control"
                                   min="0">
                        </div>
                        
                        <div class="grebion-column-settings" id="grebion-column-settings-<?= htmlspecialchars($propertyCode) ?>-<?= $index ?>">
                            <?php if (in_array($column['type'] ?? '', ['select', 'multiselect'])): ?>
                                <div class="form-group">
                                    <label>Варианты (по одному на строку):</label>
                                    <textarea name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA][<?= $index ?>][options]" 
                                              class="form-control" 
                                              rows="3"><?= htmlspecialchars(implode("\n", $column['options'] ?? [])) ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <input type="hidden" name="<?= htmlspecialchars($propertyCode) ?>[SCHEMA_JSON]" id="grebion-schema-json-<?= htmlspecialchars($propertyCode) ?>" value="<?= htmlspecialchars(json_encode($currentSchema)) ?>">
</div>

<script>
if (typeof GrebionTableSettings === 'undefined') {
    var GrebionTableSettings = {
        columnIndex: <?= count($currentSchema) ?>,
        
        addColumn: function(propertyCode) {
            var container = document.getElementById('grebion-columns-' + propertyCode);
            var index = this.columnIndex++;
            
            var columnHtml = this.getColumnTemplate(propertyCode, index);
            container.insertAdjacentHTML('beforeend', columnHtml);
            this.updateSchemaJson(propertyCode);
        },
        
        removeColumn: function(propertyCode, index) {
            var columnItem = document.querySelector('#grebion-columns-' + propertyCode + ' .grebion-column-item[data-index="' + index + '"]');
            if (columnItem) {
                columnItem.remove();
                this.updateSchemaJson(propertyCode);
            }
        },
        
        onTypeChange: function(propertyCode, index, type) {
            var settingsContainer = document.getElementById('grebion-column-settings-' + propertyCode + '-' + index);
            
            if (type === 'select' || type === 'multiselect') {
                settingsContainer.innerHTML = '<div class="form-group"><label>Варианты (по одному на строку):</label><textarea name="' + propertyCode + '[SCHEMA][' + index + '][options]" class="form-control" rows="3"></textarea></div>';
            } else {
                settingsContainer.innerHTML = '';
            }
            
            this.updateSchemaJson(propertyCode);
        },
        
        getColumnTemplate: function(propertyCode, index) {
            return '<div class="grebion-column-item" data-index="' + index + '">' +
                '<div class="grebion-column-header">' +
                    '<span class="grebion-column-title">Колонка ' + (index + 1) + '</span>' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="GrebionTableSettings.removeColumn(\'' + propertyCode + '\', ' + index + ')">Удалить</button>' +
                '</div>' +
                '<div class="grebion-column-fields">' +
                    '<div class="form-group">' +
                        '<label>Код колонки:</label>' +
                        '<input type="text" name="' + propertyCode + '[SCHEMA][' + index + '][code]" class="form-control" required>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Название:</label>' +
                        '<input type="text" name="' + propertyCode + '[SCHEMA][' + index + '][title]" class="form-control" required>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Тип:</label>' +
                        '<select name="' + propertyCode + '[SCHEMA][' + index + '][type]" class="form-control grebion-column-type" onchange="GrebionTableSettings.onTypeChange(\'' + propertyCode + '\', ' + index + ', this.value)">' +
                            '<?php foreach ($columnTypes as $typeCode => $typeName): ?>' +
                            '<option value="<?= htmlspecialchars($typeCode) ?>"><?= htmlspecialchars($typeName) ?></option>' +
                            '<?php endforeach; ?>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label>Сортировка:</label>' +
                        '<input type="number" name="' + propertyCode + '[SCHEMA][' + index + '][sort]" value="' + ((index + 1) * 100) + '" class="form-control" min="0">' +
                    '</div>' +
                    '<div class="grebion-column-settings" id="grebion-column-settings-' + propertyCode + '-' + index + '"></div>' +
                '</div>' +
            '</div>';
        },
        
        updateSchemaJson: function(propertyCode) {
            // Обновляем скрытое поле с JSON схемой
            var container = document.getElementById('grebion-columns-' + propertyCode);
            var columns = container.querySelectorAll('.grebion-column-item');
            var schema = [];
            
            columns.forEach(function(column, index) {
                var code = column.querySelector('input[name*="[code]"]').value;
                var title = column.querySelector('input[name*="[title]"]').value;
                var type = column.querySelector('select[name*="[type]"]').value;
                var sort = column.querySelector('input[name*="[sort]"]').value;
                
                var columnData = {
                    code: code,
                    title: title,
                    type: type,
                    sort: parseInt(sort) || (index + 1) * 100
                };
                
                var optionsTextarea = column.querySelector('textarea[name*="[options]"]');
                if (optionsTextarea && optionsTextarea.value) {
                    columnData.options = optionsTextarea.value.split('\n').filter(function(option) {
                        return option.trim() !== '';
                    });
                }
                
                schema.push(columnData);
            });
            
            document.getElementById('grebion-schema-json-' + propertyCode).value = JSON.stringify(schema);
        }
    };
}
</script>

<style>
.grebion-table-settings {
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    background: #f9f9f9;
}

.grebion-table-settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.grebion-column-item {
    border: 1px solid #ccc;
    margin-bottom: 10px;
    padding: 10px;
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
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.grebion-column-fields .form-group {
    margin-bottom: 10px;
}

.grebion-column-settings {
    grid-column: 1 / -1;
}
</style>