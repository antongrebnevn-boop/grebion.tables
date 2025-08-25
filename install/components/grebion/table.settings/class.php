<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Error;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\ColumnTable;

/**
 * Компонент для настройки схемы таблицы
 */
class TableSettingsComponent extends CBitrixComponent implements Controllerable
{
    public function executeComponent()
    {
        if (!Loader::includeModule('grebion.tables')) {
            return;
        }
        
        $this->prepareResult();
        $this->includeComponentTemplate();
    }

    protected function prepareResult()
    {
        // Получаем параметры из свойства инфоблока
        $property = $this->arParams['PROPERTY'] ?? [];
        $value = $this->arParams['VALUE'] ?? [];
        $htmlControlName = $this->arParams['HTML_CONTROL_NAME'] ?? [];
        
        // Получаем доступные типы колонок
        $this->arResult['COLUMN_TYPES'] = ColumnTable::getAvailableTypes();
        
        // Получаем текущую схему из значения свойства
        $currentSchemaId = 0;
        $currentSchema = [];
        
        if (!empty($value['VALUE'])) {
            $currentSchemaId = (int)$value['VALUE'];
            if ($currentSchemaId > 0) {
                $schemaData = TableSchemaTable::getById($currentSchemaId)->fetch();
                if ($schemaData) {
                    $schemaJson = Json::decode($schemaData['SCHEMA']);
                    $currentSchema = $schemaJson['columns'] ?? $schemaJson ?? [];
                }
            }
        }
        
        // Получаем список доступных схем
        $availableSchemas = TableSchemaTable::getList([
            'select' => ['ID', 'NAME', 'DESCRIPTION', 'CREATED_AT'],
            'order' => ['CREATED_AT' => 'DESC']
        ])->fetchAll();
        
        $this->arResult['CURRENT_SCHEMA'] = $currentSchema;
        $this->arResult['CURRENT_SCHEMA_ID'] = $currentSchemaId;
        $this->arResult['AVAILABLE_SCHEMAS'] = $availableSchemas;
        $this->arResult['PROPERTY'] = $property;
        $this->arResult['VALUE'] = $value;
        $this->arResult['HTML_CONTROL_NAME'] = $htmlControlName;
        $this->arResult['COMPONENT_ID'] = 'grebion_table_settings_' . md5(serialize($htmlControlName));
    }
    
    /**
     * Рендерит элемент колонки для шаблона
     */
    public function renderColumnItem($componentId, $index, $column, $columnTypes)
    {
        ob_start();
        ?>
        <div class="grebion-column-item" data-index="<?= $index ?>">
            <div class="grebion-column-header">
                <div class="grebion-column-drag">
                    <span class="ui-icon-set --move"></span>
                </div>
                <div class="grebion-column-title">
                    <strong><?= htmlspecialchars($column['title'] ?? 'Колонка ' . ($index + 1)) ?></strong>
                    <span class="grebion-column-type-badge"><?= htmlspecialchars($columnTypes[$column['type']] ?? $column['type']) ?></span>
                </div>
                <div class="grebion-column-actions">
                    <button type="button" 
                            class="ui-btn ui-btn-sm ui-btn-light ui-btn-icon-edit"
                            data-component-id="<?= htmlspecialchars($componentId) ?>"
                            data-action="toggle-column"
                            data-index="<?= $index ?>"
                            title="Редактировать">
                    </button>
                    <button type="button" 
                            class="ui-btn ui-btn-sm ui-btn-danger ui-btn-icon-remove"
                            data-component-id="<?= htmlspecialchars($componentId) ?>"
                            data-action="remove-column"
                            data-index="<?= $index ?>"
                            title="Удалить">
                    </button>
                </div>
            </div>
            
            <div class="grebion-column-content" style="display: none;">
                <div class="ui-form-row-group">
                    <div class="ui-form-row">
                        <div class="ui-form-label">
                            <div class="ui-form-label-text">Код колонки:</div>
                        </div>
                        <div class="ui-form-content">
                            <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                                <input type="text" 
                                       class="ui-ctl-element column-code" 
                                       value="<?= htmlspecialchars($column['code'] ?? '') ?>"
                                       placeholder="column_code"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ui-form-row">
                        <div class="ui-form-label">
                            <div class="ui-form-label-text">Название:</div>
                        </div>
                        <div class="ui-form-content">
                            <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                                <input type="text" 
                                       class="ui-ctl-element column-title" 
                                       value="<?= htmlspecialchars($column['title'] ?? '') ?>"
                                       placeholder="Название колонки"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ui-form-row">
                        <div class="ui-form-label">
                            <div class="ui-form-label-text">Тип:</div>
                        </div>
                        <div class="ui-form-content">
                            <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown">
                                <div class="ui-ctl-after ui-ctl-icon-angle"></div>
                                <select class="ui-ctl-element column-type" 
                                        data-component-id="<?= htmlspecialchars($componentId) ?>"
                                        data-action="type-change"
                                        data-index="<?= $index ?>">
                                    <?php foreach ($columnTypes as $typeCode => $typeName): ?>
                                        <option value="<?= htmlspecialchars($typeCode) ?>"
                                                <?= ($column['type'] ?? '') === $typeCode ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($typeName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ui-form-row">
                        <div class="ui-form-label">
                            <div class="ui-form-label-text">Сортировка:</div>
                        </div>
                        <div class="ui-form-content">
                            <div class="ui-ctl ui-ctl-textbox">
                                <input type="number" 
                                       class="ui-ctl-element column-sort" 
                                       value="<?= (int)($column['sort'] ?? ($index + 1) * 100) ?>"
                                       min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="grebion-column-type-settings" id="type-settings-<?= htmlspecialchars($componentId) ?>-<?= $index ?>">
                        <?php if (in_array($column['type'] ?? '', ['select', 'multiselect'])): ?>
                            <div class="ui-form-row">
                                <div class="ui-form-label">
                                    <div class="ui-form-label-text">Варианты (по одному на строку):</div>
                                </div>
                                <div class="ui-form-content">
                                    <div class="ui-ctl ui-ctl-textarea ui-ctl-w100">
                                        <textarea class="ui-ctl-element column-options" 
                                                  rows="3"
                                                  placeholder="Вариант 1\nВариант 2\nВариант 3"><?= htmlspecialchars(implode("\n", $column['options'] ?? [])) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Конфигурация экшенов
     */
    public function configureActions()
    {
        return [
            'saveSchema' => [
                'prefilters' => [
                    new Csrf()
                ]
            ]
        ];
    }



    /**
     * Экшен для сохранения схемы
     */
    public function saveSchemaAction($schemaData, $schemaName, $schemaDescription = '')
    {
        if (!Loader::includeModule('grebion.tables')) {
            return new AjaxJson(null, [new Error('Модуль grebion.tables не подключен')]);
        }

        if (empty($schemaName)) {
            return new AjaxJson(null, [new Error('Название схемы не может быть пустым')]);
        }

        if (empty($schemaData)) {
            return new AjaxJson(null, [new Error('Данные схемы не могут быть пустыми')]);
        }

        try {
            $schema = Json::decode($schemaData);
            if (!is_array($schema) || empty($schema['columns'])) {
                return new AjaxJson(null, [new Error('Некорректный формат данных схемы')]);
            }
        } catch (\Exception $e) {
            return new AjaxJson(null, [new Error('Ошибка парсинга JSON: ' . $e->getMessage())]);
        }

        $result = TableSchemaTable::add([
            'NAME' => $schemaName,
            'DESCRIPTION' => $schemaDescription,
            'SCHEMA' => $schemaData
        ]);

        if ($result->isSuccess()) {
            return new AjaxJson([
                'schema_id' => $result->getId(),
                'message' => 'Схема успешно сохранена'
            ]);
        } else {
            $errors = [];
            foreach ($result->getErrors() as $error) {
                $errors[] = new Error($error->getMessage());
            }
            return new AjaxJson(null, $errors);
        }
    }
}