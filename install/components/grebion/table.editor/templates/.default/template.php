<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$displayData = $arResult['DISPLAY_DATA'];
$propertyCode = $arResult['PROPERTY_CODE'];
$mode = $arResult['MODE'];
$columns = $displayData['columns'];
$rows = $displayData['rows'];

if (empty($columns)) {
    echo '<div class="grebion-table-editor-empty">Сначала настройте схему таблицы</div>';
    return;
}
?>

<div class="grebion-table-editor" id="grebion-table-editor-<?= htmlspecialchars($propertyCode) ?>">
    <?php if ($mode !== 'view'): ?>
        <div class="grebion-table-editor-header">
            <h4>Редактирование данных таблицы</h4>
            <button type="button" class="btn btn-primary" onclick="GrebionTableEditor.addRow('<?= htmlspecialchars($propertyCode) ?>')">
                Добавить строку
            </button>
        </div>
    <?php endif; ?>
    
    <div class="grebion-table-container">
        <table class="grebion-table" id="grebion-table-<?= htmlspecialchars($propertyCode) ?>">
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <th><?= htmlspecialchars($column['title']) ?></th>
                    <?php endforeach; ?>
                    <?php if ($mode !== 'view'): ?>
                        <th width="50">Действия</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="grebion-table-body-<?= htmlspecialchars($propertyCode) ?>">
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $rowIndex => $row): ?>
                        <tr data-row-index="<?= $rowIndex ?>">
                            <?php foreach ($columns as $column): ?>
                                <td>
                                    <?php 
                                    $fieldName = $propertyCode . '[DATA][rows][' . $rowIndex . '][' . $column['code'] . ']';
                                    $fieldValue = $row[$column['code']] ?? '';
                                    echo $this->renderField($column, $fieldName, $fieldValue, $mode);
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($mode !== 'view'): ?>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="GrebionTableEditor.removeRow('<?= htmlspecialchars($propertyCode) ?>', <?= $rowIndex ?>)">
                                        ×
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <input type="hidden" name="<?= htmlspecialchars($propertyCode) ?>[DATA_JSON]" id="grebion-data-json-<?= htmlspecialchars($propertyCode) ?>" value="<?= htmlspecialchars(json_encode(['rows' => $rows])) ?>">
</div>

<?php if ($mode !== 'view'): ?>
<script>
if (typeof GrebionTableEditor === 'undefined') {
    var GrebionTableEditor = {
        rowIndex: <?= count($rows) ?>,
        
        addRow: function(propertyCode) {
            var tbody = document.getElementById('grebion-table-body-' + propertyCode);
            var index = this.rowIndex++;
            
            var rowHtml = this.getRowTemplate(propertyCode, index);
            tbody.insertAdjacentHTML('beforeend', rowHtml);
            this.updateDataJson(propertyCode);
        },
        
        removeRow: function(propertyCode, index) {
            var row = document.querySelector('#grebion-table-body-' + propertyCode + ' tr[data-row-index="' + index + '"]');
            if (row) {
                row.remove();
                this.updateDataJson(propertyCode);
            }
        },
        
        getRowTemplate: function(propertyCode, index) {
            var columns = <?= json_encode($columns) ?>;
            var html = '<tr data-row-index="' + index + '">';
            
            columns.forEach(function(column) {
                html += '<td>';
                html += GrebionTableEditor.renderFieldHtml(column, propertyCode + '[DATA][rows][' + index + '][' + column.code + ']', '');
                html += '</td>';
            });
            
            html += '<td><button type="button" class="btn btn-sm btn-danger" onclick="GrebionTableEditor.removeRow(\'' + propertyCode + '\', ' + index + ')">×</button></td>';
            html += '</tr>';
            
            return html;
        },
        
        renderFieldHtml: function(column, fieldName, value) {
            switch (column.type) {
                case 'text':
                    return '<input type="text" name="' + fieldName + '" value="' + (value || '') + '" class="form-control">';
                case 'number':
                    return '<input type="number" name="' + fieldName + '" value="' + (value || '') + '" class="form-control">';
                case 'date':
                    return '<input type="date" name="' + fieldName + '" value="' + (value || '') + '" class="form-control">';
                case 'datetime':
                    return '<input type="datetime-local" name="' + fieldName + '" value="' + (value || '') + '" class="form-control">';
                case 'boolean':
                    var checked = value ? 'checked' : '';
                    return '<input type="checkbox" name="' + fieldName + '" value="1" ' + checked + ' class="form-check-input">';
                case 'select':
                    var html = '<select name="' + fieldName + '" class="form-control">';
                    html += '<option value="">Выберите...</option>';
                    if (column.options) {
                        column.options.forEach(function(option) {
                            var selected = value === option ? 'selected' : '';
                            html += '<option value="' + option + '" ' + selected + '>' + option + '</option>';
                        });
                    }
                    html += '</select>';
                    return html;
                case 'multiselect':
                    var html = '<select name="' + fieldName + '[]" class="form-control" multiple>';
                    if (column.options) {
                        var selectedValues = Array.isArray(value) ? value : [];
                        column.options.forEach(function(option) {
                            var selected = selectedValues.includes(option) ? 'selected' : '';
                            html += '<option value="' + option + '" ' + selected + '>' + option + '</option>';
                        });
                    }
                    html += '</select>';
                    return html;
                default:
                    return '<input type="text" name="' + fieldName + '" value="' + (value || '') + '" class="form-control">';
            }
        },
        
        updateDataJson: function(propertyCode) {
            var tbody = document.getElementById('grebion-table-body-' + propertyCode);
            var rows = tbody.querySelectorAll('tr');
            var data = { rows: [] };
            
            rows.forEach(function(row) {
                var rowData = {};
                var inputs = row.querySelectorAll('input, select, textarea');
                
                inputs.forEach(function(input) {
                    var name = input.name;
                    if (name && name.includes('[DATA][rows]')) {
                        var matches = name.match(/\[([^\]]+)\]$/); // Получаем код колонки
                        if (matches) {
                            var columnCode = matches[1];
                            
                            if (input.type === 'checkbox') {
                                rowData[columnCode] = input.checked;
                            } else if (input.multiple) {
                                var selectedOptions = Array.from(input.selectedOptions).map(option => option.value);
                                rowData[columnCode] = selectedOptions;
                            } else {
                                rowData[columnCode] = input.value;
                            }
                        }
                    }
                });
                
                data.rows.push(rowData);
            });
            
            document.getElementById('grebion-data-json-' + propertyCode).value = JSON.stringify(data);
        }
    };
    
    // Обновляем JSON при изменении полей
    document.addEventListener('change', function(e) {
        if (e.target.closest('.grebion-table-editor')) {
            var propertyCode = e.target.closest('.grebion-table-editor').id.replace('grebion-table-editor-', '');
            GrebionTableEditor.updateDataJson(propertyCode);
        }
    });
}
</script>
<?php endif; ?>

<style>
.grebion-table-editor {
    border: 1px solid #ddd;
    padding: 15px;
    margin: 10px 0;
    background: #f9f9f9;
}

.grebion-table-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.grebion-table-container {
    overflow-x: auto;
    max-height: 400px;
    overflow-y: auto;
}

.grebion-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.grebion-table th,
.grebion-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}

.grebion-table th {
    background-color: #f5f5f5;
    font-weight: bold;
    position: sticky;
    top: 0;
    z-index: 1;
}

.grebion-table input,
.grebion-table select,
.grebion-table textarea {
    width: 100%;
    min-width: 120px;
    border: 1px solid #ccc;
    padding: 4px;
}

.grebion-table-editor-empty {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
}
</style>

<?php
// Метод для рендеринга полей (добавляем в класс компонента)
if (!function_exists('renderField')) {
    function renderField($column, $fieldName, $value, $mode) {
        if ($mode === 'view') {
            return htmlspecialchars($value);
        }
        
        switch ($column['type']) {
            case 'text':
                return '<input type="text" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '" class="form-control">';
            case 'number':
                return '<input type="number" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '" class="form-control">';
            case 'date':
                return '<input type="date" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '" class="form-control">';
            case 'datetime':
                return '<input type="datetime-local" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '" class="form-control">';
            case 'boolean':
                $checked = $value ? 'checked' : '';
                return '<input type="checkbox" name="' . htmlspecialchars($fieldName) . '" value="1" ' . $checked . ' class="form-check-input">';
            case 'select':
                $html = '<select name="' . htmlspecialchars($fieldName) . '" class="form-control">';
                $html .= '<option value="">Выберите...</option>';
                if (!empty($column['options'])) {
                    foreach ($column['options'] as $option) {
                        $selected = $value === $option ? 'selected' : '';
                        $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                    }
                }
                $html .= '</select>';
                return $html;
            case 'multiselect':
                $html = '<select name="' . htmlspecialchars($fieldName) . '[]" class="form-control" multiple>';
                if (!empty($column['options'])) {
                    $selectedValues = is_array($value) ? $value : [];
                    foreach ($column['options'] as $option) {
                        $selected = in_array($option, $selectedValues) ? 'selected' : '';
                        $html .= '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                    }
                }
                $html .= '</select>';
                return $html;
            default:
                return '<input type="text" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '" class="form-control">';
        }
    }
}
?>