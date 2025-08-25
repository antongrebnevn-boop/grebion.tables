<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\ColumnTable;

/**
 * Компонент для настройки схемы таблицы
 */
class TableSettingsComponent extends CBitrixComponent
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
        $currentSettings = $this->arParams['CURRENT_SETTINGS'] ?? [];
        
        // Получаем доступные типы колонок
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
        
        // Получаем ID текущей схемы из настроек
        $currentSchemaId = 0;
        if (!empty($currentSettings['SCHEMA_ID'])) {
            $currentSchemaId = (int)$currentSettings['SCHEMA_ID'];
        }
        
        // Получаем список доступных схем
        $availableSchemas = TableSchemaTable::getList([
            'select' => ['ID', 'NAME', 'DESCRIPTION'],
            'order' => ['NAME' => 'ASC']
        ])->fetchAll();
        
        // Получаем текущую схему
        $currentSchema = [];
        if ($currentSchemaId > 0) {
            $schemaData = TableSchemaTable::getById($currentSchemaId)->fetch();
            if ($schemaData) {
                $currentSchema = json_decode($schemaData['SCHEMA'], true) ?: [];
            }
        }
        
        $this->arResult['CURRENT_SCHEMA'] = $currentSchema;
        $this->arResult['CURRENT_SCHEMA_ID'] = $currentSchemaId;
        $this->arResult['AVAILABLE_SCHEMAS'] = $availableSchemas;
        $this->arResult['PROPERTY_CODE'] = $propertyCode;
        $this->arResult['IBLOCK_ID'] = $iblockId;
        $this->arResult['USER_FIELD'] = $userField;
    }
}