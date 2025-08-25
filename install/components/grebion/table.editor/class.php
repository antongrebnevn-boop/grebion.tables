<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\TableDataTable;
use Grebion\Tables\Model\ColumnTable;
use Grebion\Tables\Model\RowTable;
use Grebion\Tables\Service\TableService;

/**
 * Компонент для редактирования данных таблицы
 */
class TableEditorComponent extends CBitrixComponent
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
        // Получаем параметры
        $propertyCode = $this->arParams['PROPERTY_CODE'] ?? '';
        $iblockId = $this->arParams['IBLOCK_ID'] ?? 0;
        $userField = $this->arParams['USER_FIELD'] ?? [];
        $currentValue = $this->arParams['CURRENT_VALUE'] ?? '';
        $currentSettings = $this->arParams['CURRENT_SETTINGS'] ?? [];
        $mode = $this->arParams['MODE'] ?? 'edit';
        
        // Получаем ID схемы из настроек
        $schemaId = 0;
        if (!empty($currentSettings['SCHEMA_ID'])) {
            $schemaId = (int)$currentSettings['SCHEMA_ID'];
        }
        
        // Получаем схему из БД
        $schema = [];
        if ($schemaId > 0) {
            $schemaData = TableSchemaTable::getById($schemaId)->fetch();
            if ($schemaData) {
                $schema = json_decode($schemaData['SCHEMA'], true) ?: [];
            }
        }
        
        // Получаем ID таблицы и данные
        $tableId = 0;
        $tableData = [];
        if (!empty($currentValue)) {
            if (is_array($currentValue) && isset($currentValue['id'])) {
                $tableId = (int)$currentValue['id'];
            } else {
                $tableId = (int)$currentValue;
            }
            
            if ($tableId > 0) {
                // Загружаем данные таблицы из БД
                $tableInfo = TableDataTable::getById($tableId)->fetch();
                if ($tableInfo) {
                    $rows = RowTable::getList([
                        'filter' => ['TABLE_ID' => $tableId],
                        'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
                    ])->fetchAll();
                    
                    $tableData = [
                        'id' => $tableId,
                        'title' => $tableInfo['TITLE'],
                        'rows' => $rows
                    ];
                }
            }
        }
        
        // Получаем доступные типы колонок для отображения
        $this->arResult['COLUMN_TYPES'] = [
            ColumnTable::TYPE_TEXT => 'Текст',
            ColumnTable::TYPE_NUMBER => 'Число',
            ColumnTable::TYPE_DATE => 'Дата',
            ColumnTable::TYPE_DATETIME => 'Дата и время',
            ColumnTable::TYPE_BOOLEAN => 'Да/Нет',
            ColumnTable::TYPE_FILE => 'Файл',
            ColumnTable::TYPE_SELECT => 'Список',
            ColumnTable::TYPE_MULTISELECT => 'Множественный список'
        ];
        
        $this->arResult['SCHEMA'] = $schema;
        $this->arResult['SCHEMA_ID'] = $schemaId;
        $this->arResult['TABLE_DATA'] = $tableData;
        $this->arResult['TABLE_ID'] = $tableId;
        $this->arResult['PROPERTY_CODE'] = $propertyCode;
        $this->arResult['IBLOCK_ID'] = $iblockId;
        $this->arResult['USER_FIELD'] = $userField;
        $this->arResult['MODE'] = $mode;
        
        // Подготавливаем данные для отображения
        $this->prepareDisplayData();
    }
    
    protected function prepareDisplayData()
    {
        $schema = $this->arResult['SCHEMA'];
        $tableData = $this->arResult['TABLE_DATA'];
        
        // Сортируем колонки по полю sort
        usort($schema, function($a, $b) {
            return ($a['sort'] ?? 0) - ($b['sort'] ?? 0);
        });
        
        // Подготавливаем структуру для отображения
        $displayData = [
            'columns' => $schema,
            'rows' => $tableData['rows'] ?? []
        ];
        
        $this->arResult['DISPLAY_DATA'] = $displayData;
    }
}