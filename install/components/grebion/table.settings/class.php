<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\ColumnTable;
use CBitrixComponent;

Loc::loadMessages(__FILE__);

/**
 * Компонент настройки схемы таблицы для пользовательского свойства «Таблица».
 *
 * Параметры:
 *  - SCHEMA_ID  (int)    ID выбранной схемы (0, если не выбрана)
 *  - INPUT_NAME (string) Имя hidden-поля для сохранения выбранного SCHEMA_ID
 */
class TableSettingsComponent extends CBitrixComponent implements Controllerable
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
        $schemaId                = (int)($this->arParams['SCHEMA_ID'] ?? 0);
        $this->arResult['SCHEMA_ID']  = $schemaId;
        $this->arResult['INPUT_NAME'] = $this->arParams['INPUT_NAME'];

        // Список существующих схем
        $this->arResult['SCHEMAS'] = TableSchemaTable::getList([
            'select' => ['ID', 'NAME', 'DESCRIPTION'],
            'order'  => ['NAME' => 'ASC'],
        ])->fetchAll();

        // Проверяем существование схемы (для отображения предупреждения)
        $this->arResult['CURRENT_SCHEMA'] = null;
        if ($schemaId > 0) {
            $schemaData = TableSchemaTable::getById($schemaId)->fetch();
            if ($schemaData) {
                // Схема существует, но данные будут загружены через AJAX
                $this->arResult['CURRENT_SCHEMA'] = ['EXISTS' => true];
            } else {
                // Схема не найдена - сбрасываем ID и работаем как с пустой настройкой
                $schemaId = 0;
                $this->arResult['SCHEMA_ID'] = 0;
            }
        }

        // Типы колонок
        $this->arResult['COLUMN_TYPES'] = [
            ColumnTable::TYPE_TEXT        => Loc::getMessage('GREBION_TABLE_COL_TEXT'),
            ColumnTable::TYPE_NUMBER      => Loc::getMessage('GREBION_TABLE_COL_NUMBER'),
            ColumnTable::TYPE_DATE        => Loc::getMessage('GREBION_TABLE_COL_DATE'),
            ColumnTable::TYPE_DATETIME    => Loc::getMessage('GREBION_TABLE_COL_DATETIME'),
            ColumnTable::TYPE_BOOLEAN     => Loc::getMessage('GREBION_TABLE_COL_BOOL'),
            ColumnTable::TYPE_FILE        => Loc::getMessage('GREBION_TABLE_COL_FILE'),
            ColumnTable::TYPE_SELECT      => Loc::getMessage('GREBION_TABLE_COL_SELECT'),
            ColumnTable::TYPE_MULTISELECT => Loc::getMessage('GREBION_TABLE_COL_MULTISELECT'),
            ColumnTable::TYPE_EMAIL       => Loc::getMessage('GREBION_TABLE_COL_EMAIL'),
            ColumnTable::TYPE_URL         => Loc::getMessage('GREBION_TABLE_COL_URL'),
            ColumnTable::TYPE_PHONE       => Loc::getMessage('GREBION_TABLE_COL_PHONE'),
        ];
    }

    /* Controllerable */

    public function configureActions(): array
    {
        return [
            'saveSchema' => [
                'prefilters' => [
                    new Csrf(),
                    new Authentication(),
                ],
            ],
            'loadSchema' => [
                'prefilters' => [
                    new Authentication(),
                ],
            ],
        ];
    }

    /**
     * Сохраняет новую схему и возвращает её ID.
     *
     * @param string $schemaName        Название схемы
     * @param array  $columns           Массив колонок
     * @param string $schemaDescription Описание (необязательно)
     * @return AjaxJson
     */
    public function saveSchemaAction(string $schemaName, array $columns, string $schemaDescription = '', int $schemaId = 0): AjaxJson
    {
        // Проверка подключения модуля
        if (!Loader::includeModule('grebion.tables')) {
            return AjaxJson::createError(new ErrorCollection([new Error('MODULE_NOT_LOADED')]));
        }

        if ($schemaName === '') {
            return AjaxJson::createError(new ErrorCollection([new Error('EMPTY_NAME')]));
        }
        if (empty($columns)) {
            return AjaxJson::createError(new ErrorCollection([new Error('EMPTY_COLUMNS')]));
        }

        // Валидация на дублирующиеся коды и названия колонок
        $codes = [];
        $titles = [];
        foreach ($columns as $column) {
            $code = trim($column['code'] ?? '');
            $title = trim($column['title'] ?? '');
            
            // Проверка базовой структуры колонки
            if (!TableSchemaTable::validateColumn($column)) {
                return AjaxJson::createError(new ErrorCollection([new Error('INVALID_COLUMN')]));
            }
            
            // Проверка на дублирующиеся коды
            if (in_array($code, $codes)) {
                return AjaxJson::createError(new ErrorCollection([new Error('DUPLICATE_CODE', null, ['CODE' => $code])]));
            }
            $codes[] = $code;
            
            // Проверка на дублирующиеся названия
            if (in_array($title, $titles)) {
                return AjaxJson::createError(new ErrorCollection([new Error('DUPLICATE_TITLE', null, ['TITLE' => $title])]));
            }
            $titles[] = $title;
        }

        $schemaData = [
            'NAME'        => $schemaName,
            'DESCRIPTION' => $schemaDescription,
            'SCHEMA'      => json_encode(['columns' => $columns], JSON_UNESCAPED_UNICODE),
        ];

        // Если передан ID схемы - обновляем существующую, иначе создаём новую
        if ($schemaId > 0) {
            $updateResult = TableSchemaTable::update($schemaId, $schemaData);
            
            if (!$updateResult->isSuccess()) {
                return AjaxJson::createError(new ErrorCollection($updateResult->getErrors()));
            }
            
            return AjaxJson::createSuccess([
                'ID' => $schemaId,
                'ACTION' => 'UPDATED',
            ]);
        } else {
            $addResult = TableSchemaTable::add($schemaData);
            
            if (!$addResult->isSuccess()) {
                return AjaxJson::createError(new ErrorCollection($addResult->getErrors()));
            }
            
            return AjaxJson::createSuccess([
                'ID' => $addResult->getId(),
                'ACTION' => 'CREATED',
            ]);
        }
    }

    /**
     * Загружает данные схемы по ID.
     *
     * @param int $schemaId ID схемы
     * @return AjaxJson
     */
    public function loadSchemaAction(int $schemaId): AjaxJson
    {
        // Проверка подключения модуля
        if (!Loader::includeModule('grebion.tables')) {
            return AjaxJson::createError(new ErrorCollection([new Error('MODULE_NOT_LOADED')]));
        }

        if ($schemaId <= 0) {
            return AjaxJson::createError(new ErrorCollection([new Error('INVALID_SCHEMA_ID')]));
        }

        $schemaData = TableSchemaTable::getById($schemaId)->fetch();
        if (!$schemaData) {
            return AjaxJson::createError(new ErrorCollection([new Error('SCHEMA_NOT_FOUND')]));
        }

        $schema = json_decode($schemaData['SCHEMA'], true);
        
        return AjaxJson::createSuccess([
            'ID'          => $schemaData['ID'],
            'NAME'        => $schemaData['NAME'],
            'DESCRIPTION' => $schemaData['DESCRIPTION'],
            'COLUMNS'     => $schema['columns'] ?? [],
        ]);
    }
}
