<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Grebion\Tables\Repository\TableRepository;
use Grebion\Tables\Repository\ColumnRepository;
use Grebion\Tables\Repository\RowRepository;
use Grebion\Tables\Repository\CellRepository;
use Grebion\Tables\Model\TableDataTable;
use Grebion\Tables\Model\ColumnTable;
use Grebion\Tables\Model\RowTable;
use Grebion\Tables\Model\CellTable;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Result\TableResult;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

/**
 * Основной сервис для работы с таблицами
 */
class TableService
{
    private TableRepository $tableRepository;
    private ColumnRepository $columnRepository;
    private RowRepository $rowRepository;
    private CellRepository $cellRepository;
    
    public function __construct(
        ?TableRepository $tableRepository = null,
        ?ColumnRepository $columnRepository = null,
        ?RowRepository $rowRepository = null,
        ?CellRepository $cellRepository = null
    ) {
        $this->tableRepository = $tableRepository ?? new TableRepository();
        $this->columnRepository = $columnRepository ?? new ColumnRepository();
        $this->rowRepository = $rowRepository ?? new RowRepository();
        $this->cellRepository = $cellRepository ?? new CellRepository();
    }
    
    /**
     * Создать новую таблицу с колонками
     *
     * @param string $title Название таблицы
     * @param string $ownerType Тип владельца (IBLOCK, USER, etc.)
     * @param int $ownerId ID владельца
     * @param array $columns Массив колонок для создания
     * @return Result
     */
    public function createTable(string $title, string $ownerType, int $ownerId, array $columns = []): TableResult
    {
        $result = new TableResult();
        
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            
            // Создаем схему по умолчанию
            $schemaName = 'Schema for ' . $title . ' ' . time();
            $schemaData = [
                 'NAME' => $schemaName,
                 'DESCRIPTION' => 'Auto-generated schema for table: ' . $title,
                 'SCHEMA' => json_encode([]),
                 'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
                 'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
             ];
            
            $schemaResult = TableSchemaTable::add($schemaData);
            if (!$schemaResult->isSuccess()) {
                $result->addErrors($schemaResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            $schemaId = $schemaResult->getId();
            
            // Создаем таблицу
            $tableData = [
                'SCHEMA_ID' => $schemaId,
                'TITLE' => $title,
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerId,
                'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ];
            
            $tableResult = $this->tableRepository->save($tableData);
            if (!$tableResult->isSuccess()) {
                $result->addErrors($tableResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            $tableId = $tableResult->getData()['id'];
            
            // Проверяем, что ID получен корректно
            if (!$tableId) {
                $result->addError(new Error('Не удалось получить ID созданной таблицы'));
                $connection->rollbackTransaction();
                return $result;
            }
            
            // Создаем колонки если переданы
            if (!empty($columns)) {
                foreach ($columns as $index => $columnData) {
                    $columnResult = $this->createColumn(
                        $tableId,
                        $columnData['code'] ?? 'column_' . $index,
                        $columnData['type'] ?? 'string',
                        $columnData['title'] ?? 'Колонка ' . ($index + 1),
                        $columnData['settings'] ?? [],
                        $columnData['sort'] ?? ($index + 1) * 100
                    );
                    
                    if (!$columnResult->isSuccess()) {
                        $result->addErrors($columnResult->getErrors());
                        $connection->rollbackTransaction();
                        return $result;
                    }
                }
            }
            
            $connection->commitTransaction();
            $result->setData(['table_id' => $tableId, 'id' => $tableId]);
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error('Ошибка создания таблицы: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Получить таблицу по ID
     *
     * @param int $id ID таблицы
     * @return Result
     */
    public function getTable(int $id): TableResult
    {
        $result = new TableResult();
        $table = $this->tableRepository->getById($id);
        
        if ($table) {
            $result->setData($table);
        } else {
            $result->addError(new Error('Таблица не найдена'));
        }
        
        return $result;
    }
    
    /**
     * Получить список всех таблиц
     *
     * @param array $filter Фильтр
     * @param array $order Сортировка
     * @param int $limit Лимит
     * @return Result
     */
    public function getTablesList(array $filter = [], array $order = ['ID' => 'DESC'], int $limit = 0): TableResult
    {
        $result = new TableResult();
        
        try {
            $tables = $this->tableRepository->getList($filter, $order, $limit);
            $result->setData($tables);
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка получения списка таблиц: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Обновить таблицу
     *
     * @param int $id ID таблицы
     * @param array $data Данные для обновления
     * @return Result
     */
    public function updateTable(int $id, array $data): TableResult
    {
        return $this->tableRepository->save($data, $id);
    }
    
    /**
     * Удалить таблицу со всеми данными
     *
     * @param int $id ID таблицы
     * @return Result
     */
    public function deleteTable(int $id): TableResult
    {
        $result = new TableResult();
        
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            
            // Удаляем все строки таблицы
            $rowsResult = $this->rowRepository->getList(['TABLE_ID' => $id]);
            while ($row = $rowsResult->fetch()) {
                $this->rowRepository->delete((int)$row['ID']);
            }
            
            // Удаляем все колонки
            $columnsResult = $this->columnRepository->getList(['TABLE_ID' => $id]);
            while ($column = $columnsResult->fetch()) {
                $this->columnRepository->delete((int)$column['ID']);
            }
            
            // Удаляем саму таблицу
            $deleteResult = $this->tableRepository->delete($id);
            if (!$deleteResult->isSuccess()) {
                $result->addErrors($deleteResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            $connection->commitTransaction();
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error('Ошибка удаления таблицы: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Создать колонку в таблице
     *
     * @param int $tableId ID таблицы
     * @param string $code Код колонки
     * @param string $type Тип колонки
     * @param string $title Название колонки
     * @param array $settings Настройки колонки
     * @param int $sort Сортировка
     * @return Result
     */
    public function createColumn(
        int $tableId,
        string $code,
        string $type,
        string $title,
        array $settings = [],
        int $sort = 100
    ): TableResult {
        $columnData = [
            'TABLE_ID' => $tableId,
            'CODE' => $code,
            'TYPE' => $type,
            'TITLE' => $title,
            'SETTINGS' => json_encode($settings),
            'SORT' => $sort,
        ];
        
        return $this->columnRepository->save($columnData);
    }
    
    /**
     * Получить колонки таблицы
     *
     * @param int $tableId ID таблицы
     * @return Result
     */
    public function getTableColumns(int $tableId): TableResult
    {
        $result = new TableResult();
        $ormResult = $this->columnRepository->getList(
            ['TABLE_ID' => $tableId],
            ['SORT' => 'ASC', 'ID' => 'ASC']
        );
        
        $columns = [];
        while ($column = $ormResult->fetch()) {
            $columns[] = $column;
        }
        
        $result->setData(['columns' => $columns]);
        return $result;
    }
    
    /**
     * Клонировать таблицу
     *
     * @param int $sourceTableId ID исходной таблицы
     * @param string $newTitle Название новой таблицы
     * @param string $ownerType Тип владельца
     * @param int $ownerId ID владельца
     * @param bool $copyData Копировать ли данные
     * @return Result
     */
    public function cloneTable(
        int $sourceTableId,
        string $newTitle,
        string $ownerType,
        int $ownerId,
        bool $copyData = false
    ): TableResult {
        $result = new TableResult();
        
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            
            // Получаем исходную таблицу
            $sourceTableResult = $this->getTable($sourceTableId);
            if (!$sourceTableResult->isSuccess()) {
                $result->addErrors($sourceTableResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            // Получаем колонки исходной таблицы
            $columnsResult = $this->getTableColumns($sourceTableId);
            if (!$columnsResult->isSuccess()) {
                $result->addErrors($columnsResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            $columnsData = $columnsResult->getData();
            $sourceColumns = $columnsData['columns'] ?? [];
            $columns = [];
            foreach ($sourceColumns as $column) {
                $columns[] = [
                    'code' => $column['CODE'],
                    'type' => $column['TYPE'],
                    'title' => $column['TITLE'],
                    'settings' => json_decode($column['SETTINGS'], true) ?: [],
                    'sort' => (int)$column['SORT'],
                ];
            }
            
            // Создаем новую таблицу
            $createResult = $this->createTable($newTitle, $ownerType, $ownerId, $columns);
            if (!$createResult->isSuccess()) {
                $result->addErrors($createResult->getErrors());
                $connection->rollbackTransaction();
                return $result;
            }
            
            $newTableId = $createResult->getData()['table_id'];
            
            // Копируем данные если нужно
            if ($copyData) {
                $rowsResult = $this->rowRepository->getList(['TABLE_ID' => $sourceTableId]);
                while ($row = $rowsResult->fetch()) {
                    $rowData = [
                        'TABLE_ID' => $newTableId,
                        'DATA' => $row['DATA'],
                        'SORT' => $row['SORT'],
                    ];
                    $this->rowRepository->save($rowData);
                }
            }
            
            $connection->commitTransaction();
            $result->setData(['table_id' => $newTableId]);
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error('Ошибка клонирования таблицы: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Получить список таблиц по владельцу
     *
     * @param string $ownerType Тип владельца
     * @param int $ownerId ID владельца
     * @return Result
     */
    public function getTablesByOwner(string $ownerType, int $ownerId): TableResult
    {
        $queryResult = $this->tableRepository->getList([
            'OWNER_TYPE' => $ownerType,
            'OWNER_ID' => $ownerId,
        ]);
        
        $result = new TableResult();
        $tables = [];
        while ($table = $queryResult->fetch()) {
            $tables[] = $table;
        }
        $result->setData($tables);
        
        return $result;
    }
    
    /**
     * Получить статистику по таблице
     *
     * @param int $tableId ID таблицы
     * @return Result
     */
    public function getTableStats(int $tableId): TableResult
    {
        $result = new TableResult();
        
        try {
            // Количество колонок
            $columnsCount = $this->columnRepository->getCount(['TABLE_ID' => $tableId]);
            
            // Количество строк
            $rowsCount = $this->rowRepository->getCount(['TABLE_ID' => $tableId]);
            
            $result->setData([
                'columns_count' => $columnsCount,
                'rows_count' => $rowsCount,
            ]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка получения статистики: ' . $e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Получить количество использований таблицы в UF-полях
     *
     * @param int $tableId ID таблицы
     * @return int
     */
    public function getTableUsageCount(int $tableId): int
    {
        $count = 0;
        
        try {
            // Поиск в пользовательских полях
            $userFields = \CUserTypeEntity::GetList(
                [],
                ['USER_TYPE_ID' => 'grebion_table']
            );
            
            while ($field = $userFields->Fetch()) {
                // Получаем все значения этого поля
                $entityId = $field['ENTITY_ID'];
                $fieldName = $field['FIELD_NAME'];
                
                // Для разных типов сущностей используем разные методы поиска
                if (strpos($entityId, 'HLBLOCK_') === 0) {
                    // HL-блоки
                    $hlblockId = (int)str_replace('HLBLOCK_', '', $entityId);
                    $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlblockId)->fetch();
                    
                    if ($hlblock) {
                        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
                        $dataClass = $entity->getDataClass();
                        
                        $result = $dataClass::getList([
                            'filter' => [$fieldName => $tableId],
                            'select' => ['ID']
                        ]);
                        
                        while ($result->fetch()) {
                            $count++;
                        }
                    }
                } elseif ($entityId === 'USER') {
                    // Пользователи
                    $users = \CUser::GetList(
                        'ID',
                        'ASC',
                        [$fieldName => $tableId],
                        ['FIELDS' => ['ID']]
                    );
                    
                    while ($users->Fetch()) {
                        $count++;
                    }
                }
            }
            
        } catch (\Exception $e) {
            // В случае ошибки возвращаем 1, чтобы не удалять таблицу
            return 1;
        }
        
        return $count;
    }

    /**
     * Создать таблицу с колонками
     */
    public static function createTableWithColumns(string $title, string $ownerType, int $ownerId, array $columns): TableResult
     {
         $result = new TableResult();
        
        try {
            // Создаем таблицу
            $tableResult = TableDataTable::add([
                'TITLE' => $title,
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerId
            ]);
            
            if (!$tableResult->isSuccess()) {
                $result->addErrors($tableResult->getErrors());
                return $result;
            }
            
            $tableId = $tableResult->getId();
            
            // Создаем колонки
            foreach ($columns as $column) {
                $columnResult = ColumnTable::add([
                    'TABLE_ID' => $tableId,
                    'TITLE' => $column['title'],
                    'CODE' => $column['code'],
                    'TYPE' => $column['type'],
                    'SETTINGS' => json_encode($column['settings'] ?? [], JSON_UNESCAPED_UNICODE),
                    'SORT' => $column['sort'] ?? 500
                ]);
                
                if (!$columnResult->isSuccess()) {
                    $result->addErrors($columnResult->getErrors());
                }
            }
            
            $result->setData(['table_id' => $tableId]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Получить полную информацию о таблице с колонками
     */
    public static function getTableInfo(int $tableId): ?array
    {
        try {
            $table = TableDataTable::getById($tableId)->fetch();
            if (!$table) {
                return null;
            }
            
            $columnsResult = ColumnTable::getByTableId($tableId);
            $columns = [];
            
            while ($column = $columnsResult->fetch()) {
                $columns[] = [
                    'id' => $column['ID'],
                    'title' => $column['TITLE'],
                    'code' => $column['CODE'],
                    'type' => $column['TYPE'],
                    'settings' => json_decode($column['SETTINGS'], true) ?? [],
                    'sort' => $column['SORT']
                ];
            }
            
            return [
                'ID' => $table['ID'],
                'TITLE' => $table['TITLE'],
                'OWNER_TYPE' => $table['OWNER_TYPE'],
                'OWNER_ID' => $table['OWNER_ID'],
                'CREATED_AT' => $table['CREATED_AT'],
                'UPDATED_AT' => $table['UPDATED_AT'],
                'COLUMNS' => $columns
            ];
            
        } catch (ArgumentException | SystemException $e) {
            return null;
        }
    }

    /**
     * Добавить колонку
     */
    public static function addColumn(int $tableId, string $title, string $code, string $type, array $settings = []): Result
    {
        return ColumnTable::add([
            'TABLE_ID' => $tableId,
            'TITLE' => $title,
            'CODE' => $code,
            'TYPE' => $type,
            'SETTINGS' => json_encode($settings, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Удалить колонку
     */
    public static function deleteColumn(int $columnId): TableResult
     {
         $result = new TableResult();
        
        try {
            // Удаляем все ячейки этой колонки
            CellTable::deleteByColumnId($columnId);
            
            // Удаляем саму колонку
            $deleteResult = ColumnTable::deleteColumn($columnId);
            if (!$deleteResult->isSuccess()) {
                $result->addErrors($deleteResult->getErrors());
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Добавить строку
     */
    public static function addRow(int $tableId, array $data, int $sort = 500): Result
    {
        return RowTable::add([
            'TABLE_ID' => $tableId,
            'DATA' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'SORT' => $sort
        ]);
    }

    /**
     * Обновить строку
     */
    public static function updateRow(int $rowId, array $data): Result
    {
        return RowTable::update($rowId, [
            'DATA' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Удалить строку
     */
    public static function deleteRow(int $rowId): TableResult
     {
         $result = new TableResult();
        
        try {
            // Сначала удаляем все ячейки этой строки
            CellTable::deleteByRowId($rowId);
            
            // Затем удаляем саму строку
            $deleteResult = RowTable::deleteRow($rowId);
            if (!$deleteResult->isSuccess()) {
                $result->addErrors($deleteResult->getErrors());
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Получить данные таблицы с пагинацией
     */
    public static function getTableData(int $tableId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        $rowsResult = RowTable::getList([
            'filter' => ['TABLE_ID' => $tableId],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $data = [];
        while ($row = $rowsResult->fetch()) {
            $data[] = [
                'id' => $row['ID'],
                'data' => $row['DATA'] ? json_decode($row['DATA'], true) : [],
                'sort' => $row['SORT'],
                'created_at' => $row['CREATED_AT'],
                'updated_at' => $row['UPDATED_AT']
            ];
        }
        
        return $data;
    }

    /**
     * Поиск по таблице
     */
    public static function searchTable(int $tableId, string $query): array
    {
        $rowsResult = RowTable::getByTableId($tableId);
        $results = [];
        
        while ($row = $rowsResult->fetch()) {
            $data = json_decode($row['DATA'], true) ?? [];
            
            // Простой поиск по всем полям
            foreach ($data as $value) {
                if (stripos((string)$value, $query) !== false) {
                    $results[] = [
                        'id' => $row['ID'],
                        'data' => $data,
                        'sort' => $row['SORT']
                    ];
                    break;
                }
            }
        }
        
        return $results;
    }

    /**
     * Копировать таблицу
     */
    public static function copyTable(int $sourceTableId, string $newTitle, string $ownerType, int $ownerId, bool $copyData = true): TableResult
     {
         $result = new TableResult();
        
        try {
            $sourceTable = static::getTableInfo($sourceTableId);
            if (!$sourceTable) {
                $result->addError(new Error('Исходная таблица не найдена'));
                return $result;
            }
            
            // Создаем новую таблицу
            $createResult = static::createTableWithColumns($newTitle, $ownerType, $ownerId, $sourceTable['COLUMNS']);
            if (!$createResult->isSuccess()) {
                $result->addErrors($createResult->getErrors());
                return $result;
            }
            
            $newTableId = $createResult->getData()['table_id'];
            
            // Копируем данные если нужно
            if ($copyData) {
                $rowsResult = RowTable::getByTableId($sourceTableId);
                while ($row = $rowsResult->fetch()) {
                    static::addRow($newTableId, json_decode($row['DATA'], true) ?? [], $row['SORT']);
                }
            }
            
            $result->setData(['table_id' => $newTableId]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
     }

    /**
     * Создать таблицу по схеме
     */
    public function createTableFromSchema(int $schemaId, string $ownerType, int $ownerId, array $data = []): TableResult
    {
        $result = new TableResult();
        
        try {
            $schema = TableSchemaTable::getSchemaArray($schemaId);
            if (!$schema) {
                $result->addError(new Error('Схема не найдена'));
                return $result;
            }
            
            // Создаем таблицу
            $tableResult = $this->tableRepository->add([
                'SCHEMA_ID' => $schemaId,
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerId,
                'TITLE' => $schema['title'] ?? 'Новая таблица'
            ]);
            
            if (!$tableResult->isSuccess()) {
                $result->addErrors($tableResult->getErrors());
                return $result;
            }
            
            $tableId = $tableResult->getId();
            
            // Добавляем данные если переданы
            if (!empty($data)) {
                $addDataResult = $this->addRows($tableId, $data, $schema);
                if (!$addDataResult->isSuccess()) {
                    $result->addErrors($addDataResult->getErrors());
                }
            }
            
            $result->setData(['table_id' => $tableId]);
            
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Добавить строки в таблицу
     */
    public function addRows(int $tableId, array $rows, ?array $schema = null): TableResult
    {
        $result = new TableResult();
        
        try {
            if ($schema === null) {
                $table = $this->tableRepository->getById($tableId);
                if (!$table) {
                    $result->addError(new Error('Таблица не найдена'));
                    return $result;
                }
                
                $tableData = json_decode($table['DATA'], true);
                if (isset($tableData['schema_id'])) {
                    $schema = TableSchemaTable::getSchemaArray($tableData['schema_id']);
                }
            }
            
            $addedRows = [];
            
            foreach ($rows as $rowData) {
                $validatedData = $this->validateRowData($rowData, $schema);
                if (!$validatedData['valid']) {
                    $result->addError(new Error('Некорректные данные строки: ' . implode(', ', $validatedData['errors'])));
                    continue;
                }
                
                $rowResult = $this->rowRepository->add([
                    'TABLE_ID' => $tableId,
                    'DATA' => json_encode($validatedData['data'], JSON_UNESCAPED_UNICODE)
                ]);
                
                if ($rowResult->isSuccess()) {
                    $addedRows[] = $rowResult->getId();
                } else {
                    $result->addErrors($rowResult->getErrors());
                }
            }
            
            $result->setData(['added_rows' => $addedRows]);
            
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Обновить строку с валидацией
     */
    public function updateRowWithValidation(int $rowId, array $data, ?array $schema = null): TableResult
    {
        $result = new TableResult();
        
        try {
            $row = $this->rowRepository->getById($rowId);
            if (!$row) {
                $result->addError(new Error('Строка не найдена'));
                return $result;
            }
            
            if ($schema === null) {
                $table = $this->tableRepository->getById($row['TABLE_ID']);
                if ($table) {
                    $tableData = json_decode($table['DATA'], true);
                    if (isset($tableData['schema_id'])) {
                        $schema = TableSchemaTable::getSchemaArray($tableData['schema_id']);
                    }
                }
            }
            
            $validatedData = $this->validateRowData($data, $schema);
            if (!$validatedData['valid']) {
                $result->addError(new Error('Некорректные данные: ' . implode(', ', $validatedData['errors'])));
                return $result;
            }
            
            $updateResult = $this->rowRepository->update($rowId, [
                'DATA' => json_encode($validatedData['data'], JSON_UNESCAPED_UNICODE)
            ]);
            
            if (!$updateResult->isSuccess()) {
                $result->addErrors($updateResult->getErrors());
            }
            
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Удалить строку
     */
    public function deleteRowById(int $rowId): TableResult
    {
        return $this->rowRepository->delete($rowId);
    }
    
    /**
     * Получить данные таблицы
     */
    public function getTableDataArray(int $tableId): array
    {
        $rows = $this->rowRepository->getByTableId($tableId);
        $data = [];
        
        foreach ($rows as $row) {
            $rowData = json_decode($row['DATA'], true);
            $data[] = [
                'id' => $row['ID'],
                'data' => $rowData,
                'created_at' => $row['CREATED_AT'],
                'updated_at' => $row['UPDATED_AT']
            ];
        }
        
        return $data;
    }
    
    /**
     * Получить строки таблицы
     *
     * @param int $tableId ID таблицы
     * @return TableResult
     */
    public function getTableRows(int $tableId): TableResult
    {
        $result = new TableResult();
        
        try {
            $rows = $this->rowRepository->getByTableId($tableId);
            $data = [];
            
            foreach ($rows as $row) {
                $rowData = !empty($row['DATA']) ? json_decode($row['DATA'], true) : [];
                $data[] = [
                    'id' => $row['ID'],
                    'data' => $rowData,
                    'created_at' => $row['CREATED_AT'],
                    'updated_at' => $row['UPDATED_AT']
                ];
            }
            
            $result->setData($data);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка получения строк таблицы: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Валидация данных строки по схеме
     */
    private function validateRowData(array $data, ?array $schema): array
    {
        $result = [
            'valid' => true,
            'data' => $data,
            'errors' => []
        ];
        
        if (!$schema || !isset($schema['columns'])) {
            return $result;
        }
        
        $validatedData = [];
        
        foreach ($schema['columns'] as $column) {
            $code = $column['code'];
            $type = $column['type'];
            $required = $column['required'] ?? false;
            
            if ($required && (!isset($data[$code]) || $data[$code] === '')) {
                $result['errors'][] = "Поле {$column['title']} обязательно для заполнения";
                $result['valid'] = false;
                continue;
            }
            
            if (!isset($data[$code])) {
                continue;
            }
            
            $value = $data[$code];
            $validatedValue = $this->validateFieldValue($value, $type, $column);
            
            if ($validatedValue === false) {
                $result['errors'][] = "Некорректное значение в поле {$column['title']}";
                $result['valid'] = false;
            } else {
                $validatedData[$code] = $validatedValue;
            }
        }
        
        $result['data'] = $validatedData;
        return $result;
    }
    
    /**
     * Валидация значения поля по типу (расширенная из DataManager)
     */
    private function validateFieldValue($value, string $type, array $column)
    {
        $settings = $column['settings'] ?? [];
        
        switch ($type) {
            case ColumnTable::TYPE_STRING:
                $validatedValue = (string)$value;
                if (isset($settings['max_length']) && mb_strlen($validatedValue) > $settings['max_length']) {
                    return false;
                }
                return $validatedValue;
                
            case ColumnTable::TYPE_INTEGER:
                if (!is_numeric($value)) {
                    return false;
                }
                $validatedValue = (int)$value;
                if (isset($settings['min_value']) && $validatedValue < $settings['min_value']) {
                    return false;
                }
                if (isset($settings['max_value']) && $validatedValue > $settings['max_value']) {
                    return false;
                }
                return $validatedValue;
                
            case ColumnTable::TYPE_FLOAT:
                if (!is_numeric($value)) {
                    return false;
                }
                $validatedValue = (float)$value;
                if (isset($settings['min_value']) && $validatedValue < $settings['min_value']) {
                    return false;
                }
                if (isset($settings['max_value']) && $validatedValue > $settings['max_value']) {
                    return false;
                }
                return $validatedValue;
                
            case ColumnTable::TYPE_BOOLEAN:
                return (bool)$value;
                
            case ColumnTable::TYPE_DATE:
            case ColumnTable::TYPE_DATETIME:
                if (is_string($value)) {
                    try {
                        return new DateTime($value);
                    } catch (\Exception $e) {
                        return false;
                    }
                } elseif (!($value instanceof DateTime)) {
                    return false;
                }
                return $value;
                
            case ColumnTable::TYPE_SELECT:
                if (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_keys($settings['options']);
                    if (!in_array($value, $validOptions)) {
                        return false;
                    }
                }
                return $value;
                
            case ColumnTable::TYPE_MULTISELECT:
                if (!is_array($value)) {
                    return false;
                }
                if (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_keys($settings['options']);
                    foreach ($value as $item) {
                        if (!in_array($item, $validOptions)) {
                            return false;
                        }
                    }
                }
                return $value;
                
            case ColumnTable::TYPE_FILE:
                if (!empty($value) && !is_numeric($value)) {
                    return false;
                }
                return (int)$value;
                
            case ColumnTable::TYPE_JSON:
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return false;
                    }
                    return $decoded;
                } elseif (!is_array($value) && !is_object($value)) {
                    return false;
                }
                return $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Валидация данных строки по колонкам таблицы (из DataManager)
     */
    public function validateRowDataByColumns(int $tableId, array $data): TableResult
    {
        $result = new TableResult();
        $validatedData = [];
        
        try {
            $columnsResult = ColumnTable::getByTableId($tableId);
            $columns = [];
            
            while ($column = $columnsResult->fetch()) {
                $columns[$column['CODE']] = $column;
            }
            
            foreach ($data as $columnCode => $value) {
                if (!isset($columns[$columnCode])) {
                    $result->addError(new Error("Колонка '{$columnCode}' не найдена в таблице"));
                    continue;
                }
                
                $column = $columns[$columnCode];
                $settings = json_decode($column['SETTINGS'], true) ?? [];
                
                // Проверяем обязательность
                if (isset($settings['required']) && $settings['required'] && empty($value)) {
                    $result->addError(new Error("Поле '{$column['TITLE']}' обязательно для заполнения"));
                    continue;
                }
                
                $validatedValue = $this->validateFieldValue($value, $column['TYPE'], ['settings' => $settings]);
                
                if ($validatedValue === false) {
                    $result->addError(new Error("Некорректное значение в поле '{$column['TITLE']}'"));
                } else {
                    $validatedData[$columnCode] = $validatedValue;
                }
            }
            
            if ($result->isSuccess()) {
                $result->setData(['validated_data' => $validatedData]);
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Форматировать значение для отображения (из DataManager)
     */
    public static function formatValue($value, string $type, array $settings = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        switch ($type) {
            case ColumnTable::TYPE_STRING:
                return (string)$value;
                
            case ColumnTable::TYPE_INTEGER:
                return number_format((int)$value, 0, ',', ' ');
                
            case ColumnTable::TYPE_FLOAT:
                $decimals = $settings['decimals'] ?? 2;
                return number_format((float)$value, $decimals, ',', ' ');
                
            case ColumnTable::TYPE_BOOLEAN:
                return $value ? 'Да' : 'Нет';
                
            case ColumnTable::TYPE_DATE:
                if ($value instanceof DateTime) {
                    return $value->format('d.m.Y');
                }
                return (string)$value;
                
            case ColumnTable::TYPE_DATETIME:
                if ($value instanceof DateTime) {
                    return $value->format('d.m.Y H:i:s');
                }
                return (string)$value;
                
            case ColumnTable::TYPE_SELECT:
                if (isset($settings['options'][$value])) {
                    return $settings['options'][$value];
                }
                return (string)$value;
                
            case ColumnTable::TYPE_MULTISELECT:
                if (is_array($value) && isset($settings['options'])) {
                    $formatted = [];
                    foreach ($value as $item) {
                        $formatted[] = $settings['options'][$item] ?? $item;
                    }
                    return implode(', ', $formatted);
                }
                return is_array($value) ? implode(', ', $value) : (string)$value;
                
            case ColumnTable::TYPE_FILE:
                if (is_numeric($value) && $value > 0) {
                    return "Файл #{$value}";
                }
                return '';
                
            case ColumnTable::TYPE_JSON:
                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                return (string)$value;
                
            default:
                return (string)$value;
        }
    }
    
    /**
     * Массовое обновление строк с валидацией (из DataManager)
     */
    public function bulkUpdateRows(int $tableId, array $rowsData): TableResult
     {
         $result = new TableResult();
        $updatedRows = [];
        
        foreach ($rowsData as $rowId => $data) {
            $validationResult = $this->validateRowDataByColumns($tableId, $data);
            if (!$validationResult->isSuccess()) {
                $result->addError(new Error("Ошибка валидации строки {$rowId}: " . implode(', ', array_map(fn($e) => $e->getMessage(), $validationResult->getErrors()))));
                continue;
            }
            
            $updateResult = $this->rowRepository->update($rowId, [
                'DATA' => json_encode($validationResult->getData()['validated_data'], JSON_UNESCAPED_UNICODE)
            ]);
            
            if ($updateResult->isSuccess()) {
                $updatedRows[] = $rowId;
            } else {
                $result->addErrors($updateResult->getErrors());
            }
        }
        
        $result->setData(['updated_rows' => $updatedRows]);
        return $result;
    }
    
    /**
     * Валидация данных таблицы
     */
    public function validateTableData(array $data): TableResult
     {
         $result = new TableResult();

        // Проверка обязательных полей
        if (empty($data['NAME'])) {
            $result->addError(new Error('Название таблицы обязательно'));
        }

        // Проверка длины названия
        if (!empty($data['NAME']) && mb_strlen($data['NAME']) > 255) {
            $result->addError(new Error('Название таблицы слишком длинное'));
        }

        // Проверка символьного кода
        if (!empty($data['CODE']) && !preg_match('/^[a-z0-9_]+$/', $data['CODE'])) {
            $result->addError(new Error('Символьный код может содержать только латинские буквы, цифры и подчеркивания'));
        }

        return $result;
    }

    /**
     * Валидация данных колонки
     */
    public function validateColumnData(array $data): TableResult
     {
         $result = new TableResult();

        if (empty($data['TITLE'])) {
            $result->addError(new Error('Название колонки обязательно'));
        }

        if (empty($data['TYPE'])) {
            $result->addError(new Error('Тип колонки обязателен'));
        }

        if (!empty($data['TYPE']) && !array_key_exists($data['TYPE'], ColumnTable::getAvailableTypes())) {
            $result->addError(new Error('Недопустимый тип колонки'));
        }

        return $result;
    }

    /**
     * Валидация значения ячейки
     */
    public function validateCellValue($value, string $type, array $settings = []): TableResult
     {
         $result = new TableResult();

        switch ($type) {
            case ColumnTable::TYPE_INTEGER:
                if (!is_numeric($value) || (int)$value != $value) {
                    $result->addError(new Error('Значение должно быть целым числом'));
                }
                break;

            case ColumnTable::TYPE_FLOAT:
                if (!is_numeric($value)) {
                    $result->addError(new Error('Значение должно быть числом'));
                }
                break;

            case ColumnTable::TYPE_BOOLEAN:
                if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $result->addError(new Error('Значение должно быть булевым'));
                }
                break;

            case ColumnTable::TYPE_DATE:
            case ColumnTable::TYPE_DATETIME:
                if (!empty($value) && !strtotime($value)) {
                    $result->addError(new Error('Некорректный формат даты'));
                }
                break;

            case ColumnTable::TYPE_STRING:
                $maxLength = $settings['MAX_LENGTH'] ?? 255;
                if (mb_strlen($value) > $maxLength) {
                    $result->addError(new Error('Значение слишком длинное'));
                }
                break;
        }

        return $result;
    }
}