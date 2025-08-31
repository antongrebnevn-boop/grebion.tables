<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\TableDataTable;
use Grebion\Tables\Model\RowTable;

Loc::loadMessages(__FILE__);

/**
 * Компонент редактора таблиц
 * 
 * Параметры:
 *  - SCHEMA_ID  (int)    ID схемы таблицы
 *  - TABLE_ID   (int)    ID существующей таблицы (0 для новой)
 *  - INPUT_NAME (string) Имя hidden-поля для сохранения TABLE_ID
 */
class TableEditorComponent extends CBitrixComponent implements Controllerable
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('grebion.tables')) {
            ShowError('Модуль grebion.tables не установлен');
            return;
        }

        $this->prepareResult();
        $this->includeComponentTemplate();
    }

    protected function prepareResult(): void
    {
        $schemaId = (int)($this->arParams['SCHEMA_ID'] ?? 0);
        $tableId  = (int)($this->arParams['TABLE_ID'] ?? 0);
        
        $this->arResult['SCHEMA_ID']  = $schemaId;
        $this->arResult['TABLE_ID']   = $tableId;
        $this->arResult['INPUT_NAME'] = $this->arParams['INPUT_NAME'] ?? '';

        // Загружаем схему таблицы
        if ($schemaId > 0) {
            $schemaData = TableSchemaTable::getById($schemaId)->fetch();
            if ($schemaData) {
                $schema = json_decode($schemaData['SCHEMA'], true);
                $this->arResult['SCHEMA'] = [
                    'ID'          => $schemaData['ID'],
                    'NAME'        => $schemaData['NAME'],
                    'DESCRIPTION' => $schemaData['DESCRIPTION'],
                    'COLUMNS'     => $schema['columns'] ?? [],
                ];
                
                // Сортируем колонки по полю SORT
                if (!empty($this->arResult['SCHEMA']['COLUMNS'])) {
                    usort($this->arResult['SCHEMA']['COLUMNS'], function($a, $b) {
                        return (int)($a['sort'] ?? 0) - (int)($b['sort'] ?? 0);
                    });
                }
            }
        }

        // Загружаем существующие данные таблицы
        $this->arResult['TABLE_DATA'] = [];
        if ($tableId > 0) {
            $tableData = TableDataTable::getById($tableId)->fetch();
            if ($tableData) {
                $this->arResult['TABLE_DATA'] = [
                    'ID'      => $tableData['ID'],
                    'NAME'    => $tableData['TITLE'],
                    'ROWS'    => $this->loadTableRows($tableId),
                ];
            }
        }

        // Определяем типы полей для JS
        $this->arResult['FIELD_TYPES'] = [
            'text'        => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_TEXT'),
            'number'      => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_NUMBER'),
            'date'        => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_DATE'),
            'datetime'    => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_DATETIME'),
            'boolean'     => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_BOOLEAN'),
            'file'        => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_FILE'),
            'select'      => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_SELECT'),
            'multiselect' => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_MULTISELECT'),
            'email'       => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_EMAIL'),
            'url'         => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_URL'),
            'phone'       => Loc::getMessage('GREBION_TABLE_EDITOR_TYPE_PHONE'),
        ];
    }

    /**
     * Загружает строки таблицы
     */
    protected function loadTableRows(int $tableId): array
    {
        $rows = [];
        $result = RowTable::getList([
            'filter' => ['TABLE_ID' => $tableId],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $result->fetch()) {
            $rowData = json_decode($row['DATA'], true) ?: [];
            $rows[] = [
                'ID'   => $row['ID'],
                'SORT' => $row['SORT'],
                'DATA' => $rowData,
            ];
        }

        return $rows;
    }

    /**
     * Настройка AJAX-действий
     */
    public function configureActions(): array
    {
        return [
            'saveTable' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                ],
            ],
            'loadTable' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                ],
            ],
        ];
    }

    /**
     * Сохранение таблицы
     * 
     * @param array  $rows      Массив строк таблицы
     * @param int    $tableId   ID существующей таблицы (0 для новой)
     * @param int    $schemaId  ID схемы
     * @return AjaxJson
     */
    public function saveTableAction(array $rows, int $tableId = 0, int $schemaId = 0): AjaxJson
    {
        // Проверка подключения модуля
        if (!Loader::includeModule('grebion.tables')) {
            return AjaxJson::createError(new ErrorCollection([new Error('MODULE_NOT_LOADED')]));
        }

        if (empty($rows)) {
            return AjaxJson::createError(new ErrorCollection([new Error('EMPTY_ROWS')]));
        }
        
        // Проверяем корректность schemaId
        if ($schemaId <= 0) {
            return AjaxJson::createError(new ErrorCollection([new Error('INVALID_SCHEMA_ID')]));
        }

        try {
            // Генерируем автоматическое название таблицы
            $tableName = $this->generateTableName($schemaId, $tableId);

            // Создание или обновление таблицы
            if ($tableId > 0) {
                // Проверяем, что таблица существует перед обновлением
                $existingTable = TableDataTable::getById($tableId)->fetch();
                if (!$existingTable) {
                    $tableId = 0; // Создаём новую таблицу
                } else {
                    
                    // Обновление существующей таблицы
                    $result = TableDataTable::update($tableId, [
                        'TITLE'      => $tableName,
                        'SCHEMA_ID'  => $schemaId,
                        'OWNER_TYPE' => 'IBLOCK_ELEMENT',
                        'OWNER_ID'   => 0, // Будет установлен при сохранении элемента инфоблока
                    ]);
                    
                    if (!$result->isSuccess()) {
                        return AjaxJson::createError($result->getErrorCollection());
                    }

                    // Удаляем старые строки
                    $oldRows = RowTable::getList(['filter' => ['TABLE_ID' => $tableId]]);
                    while ($row = $oldRows->fetch()) {
                        RowTable::delete($row['ID']);
                    }
                    
                    $action = 'UPDATED';
                }
            }
            
            if ($tableId == 0) {
                // Создание новой таблицы
                $result = TableDataTable::add([
                    'TITLE'      => $tableName,
                    'SCHEMA_ID'  => $schemaId,
                    'OWNER_TYPE' => 'IBLOCK_ELEMENT',
                    'OWNER_ID'   => 0, // Будет установлен при сохранении элемента инфоблока
                ]);
                
                if (!$result->isSuccess()) {
                    return AjaxJson::createError($result->getErrorCollection());
                }
                
                $tableId = $result->getId();
                $action = 'CREATED';
            }

            // Сохраняем строки таблицы
            foreach ($rows as $index => $rowData) {
                $rowResult = RowTable::add([
                    'TABLE_ID' => $tableId,
                    'SORT'     => ($index + 1) * 100,
                    'DATA'     => json_encode($rowData, JSON_UNESCAPED_UNICODE),
                ]);
                
                if (!$rowResult->isSuccess()) {
                    return AjaxJson::createError($rowResult->getErrorCollection());
                }
            }

            return AjaxJson::createSuccess([
                'ID'     => $tableId,
                'ACTION' => $action,
            ]);

        } catch (\Exception $e) {
            return AjaxJson::createError(new ErrorCollection([new Error($e->getMessage())]));
        }
    }

    /**
     * Загрузка данных таблицы
     * 
     * @param int $tableId ID таблицы
     * @return AjaxJson
     */
    public function loadTableAction(int $tableId): AjaxJson
    {
        // Проверка подключения модуля
        if (!Loader::includeModule('grebion.tables')) {
            return AjaxJson::createError(new ErrorCollection([new Error('MODULE_NOT_LOADED')]));
        }

        if ($tableId <= 0) {
            return AjaxJson::createError(new ErrorCollection([new Error('INVALID_TABLE_ID')]));
        }

        $tableData = TableDataTable::getById($tableId)->fetch();
        if (!$tableData) {
            return AjaxJson::createError(new ErrorCollection([new Error('TABLE_NOT_FOUND')]));
        }

        return AjaxJson::createSuccess([
            'ID'   => $tableData['ID'],
            'NAME' => $tableData['TITLE'],
            'ROWS' => $this->loadTableRows($tableId),
        ]);
    }

    /**
     * Генерирует автоматическое название таблицы
     * 
     * @param int $schemaId ID схемы
     * @param int $tableId ID таблицы (0 для новой)
     * @return string Название таблицы
     */
    protected function generateTableName(int $schemaId, int $tableId = 0): string
    {
        // Получаем название схемы
        $schemaName = 'Таблица';
        if ($schemaId > 0) {
            $schemaData = TableSchemaTable::getById($schemaId)->fetch();
            if ($schemaData) {
                $schemaName = $schemaData['NAME'];
            }
        }

        // Для обновления существующей таблицы добавляем её ID
        if ($tableId > 0) {
            return $schemaName . ' #' . $tableId;
        }

        // Для новой таблицы добавляем временную метку
        return $schemaName . ' ' . date('d.m.Y H:i');
    }
}
