<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Grebion\Tables\Model\TableDataTable;
use Grebion\Tables\Model\ColumnTable;
use Grebion\Tables\Model\RowTable;
use Grebion\Tables\Model\CellTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Error;
use Bitrix\Main\Result;

/**
 * Менеджер для работы с таблицами
 * Предоставляет высокоуровневые методы для управления таблицами, колонками и данными
 */
class TableManager
{
    /**
     * Создать новую таблицу с колонками
     *
     * @param string $title Название таблицы
     * @param string $ownerType Тип владельца (user, group, etc.)
     * @param int $ownerId ID владельца
     * @param array $columns Массив колонок для создания
     * @return Result
     */
    public static function createTable(string $title, string $ownerType, int $ownerId, array $columns = []): Result
    {
        $result = new Result();
        
        try {
            // Создаем таблицу
            $tableResult = TableDataTable::createTable($ownerType, $ownerId, $title);
            if (!$tableResult->isSuccess()) {
                $result->addErrors($tableResult->getErrors());
                return $result;
            }
            
            $tableId = $tableResult->getData()['id'];
            
            // Создаем колонки если переданы
            if (!empty($columns)) {
                foreach ($columns as $columnData) {
                    $columnResult = ColumnTable::createColumn(
                        $tableId,
                        $columnData['code'] ?? '',
                        $columnData['type'] ?? ColumnTable::TYPE_STRING,
                        $columnData['title'] ?? '',
                        $columnData['settings'] ?? [],
                        $columnData['sort'] ?? 100
                    );
                    
                    if (!$columnResult->isSuccess()) {
                        $result->addErrors($columnResult->getErrors());
                    }
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
        $table = TableDataTable::findById($tableId);
        if (!$table) {
            return null;
        }
        
        $columns = [];
        $columnsResult = ColumnTable::getByTableId($tableId);
        while ($column = $columnsResult->fetch()) {
            $columns[] = $column;
        }
        
        $table['COLUMNS'] = $columns;
        $table['COLUMNS_COUNT'] = count($columns);
        
        // Подсчитаем количество строк
        $rowsCount = RowTable::getRowsCount($tableId);
        $table['ROWS_COUNT'] = $rowsCount;
        
        return $table;
    }

    /**
     * Добавить строку с данными
     */
    public static function addRow(int $tableId, array $data, int $sort = 100): Result
    {
        $result = new Result();
        
        try {
            // Создаем строку
            $rowResult = RowTable::createRow($tableId, $data, $sort);
            if (!$rowResult->isSuccess()) {
                $result->addErrors($rowResult->getErrors());
                return $result;
            }
            
            $rowId = $rowResult->getData()['id'];
            $result->setData(['row_id' => $rowId]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Обновить данные строки
     */
    public static function updateRow(int $rowId, array $data): Result
    {
        $result = new Result();
        
        try {
            $updateResult = RowTable::updateRow($rowId, $data);
            if (!$updateResult->isSuccess()) {
                $result->addErrors($updateResult->getErrors());
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Получить данные таблицы в виде массива
     */
    public static function getTableData(int $tableId, int $page = 1, int $limit = 50): array
    {
        $tableInfo = static::getTableInfo($tableId);
        if (!$tableInfo) {
            return [];
        }
        
        $rows = [];
        $rowsResult = RowTable::getPagedRows($tableId, $page, $limit);
        while ($row = $rowsResult->fetch()) {
            $rows[] = $row;
        }
        
        return [
            'table' => $tableInfo,
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $tableInfo['ROWS_COUNT']
            ]
        ];
    }

    /**
     * Поиск по таблице
     */
    public static function searchTable(int $tableId, string $searchValue): array
    {
        $tableInfo = static::getTableInfo($tableId);
        if (!$tableInfo) {
            return [];
        }
        
        $rows = [];
        $rowsResult = RowTable::searchRows($tableId, $searchValue);
        while ($row = $rowsResult->fetch()) {
            $rows[] = $row;
        }
        
        return [
            'table' => $tableInfo,
            'rows' => $rows,
            'search_value' => $searchValue
        ];
    }

    /**
     * Добавить колонку в существующую таблицу
     */
    public static function addColumn(int $tableId, string $code, string $type, string $title, array $settings = []): Result
    {
        $result = new Result();
        
        try {
            // Получаем максимальную сортировку
            $maxSort = 100;
            $columnsResult = ColumnTable::getByTableId($tableId);
            while ($column = $columnsResult->fetch()) {
                if ($column['SORT'] > $maxSort) {
                    $maxSort = $column['SORT'];
                }
            }
            
            $columnResult = ColumnTable::createColumn($tableId, $code, $type, $title, $settings, $maxSort + 100);
            if (!$columnResult->isSuccess()) {
                $result->addErrors($columnResult->getErrors());
                return $result;
            }
            
            $result->setData(['column_id' => $columnResult->getData()['id']]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Удалить колонку
     */
    public static function deleteColumn(int $columnId): Result
    {
        $result = new Result();
        
        try {
            // Сначала удаляем все ячейки этой колонки
            CellTable::deleteByColumnId($columnId);
            
            // Затем удаляем саму колонку
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
     * Удалить строку
     */
    public static function deleteRow(int $rowId): Result
    {
        $result = new Result();
        
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
     * Удалить таблицу полностью
     */
    public static function deleteTable(int $tableId): Result
    {
        $result = new Result();
        
        try {
            // Получаем все строки таблицы
            $rowsResult = RowTable::getByTableId($tableId);
            while ($row = $rowsResult->fetch()) {
                // Удаляем ячейки строки
                CellTable::deleteByRowId($row['ID']);
                // Удаляем строку
                RowTable::deleteRow($row['ID']);
            }
            
            // Получаем все колонки таблицы
            $columnsResult = ColumnTable::getByTableId($tableId);
            while ($column = $columnsResult->fetch()) {
                // Удаляем колонку
                ColumnTable::deleteColumn($column['ID']);
            }
            
            // Удаляем саму таблицу
            $deleteResult = TableDataTable::deleteTable($tableId);
            if (!$deleteResult->isSuccess()) {
                $result->addErrors($deleteResult->getErrors());
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Копировать таблицу
     */
    public static function copyTable(int $sourceTableId, string $newTitle, string $ownerType, int $ownerId, bool $copyData = true): Result
    {
        $result = new Result();
        
        try {
            $sourceTable = static::getTableInfo($sourceTableId);
            if (!$sourceTable) {
                $result->addError(new Error('Исходная таблица не найдена'));
                return $result;
            }
            
            // Создаем новую таблицу
            $createResult = static::createTable($newTitle, $ownerType, $ownerId, $sourceTable['COLUMNS']);
            if (!$createResult->isSuccess()) {
                $result->addErrors($createResult->getErrors());
                return $result;
            }
            
            $newTableId = $createResult->getData()['table_id'];
            
            // Копируем данные если нужно
            if ($copyData) {
                $rowsResult = RowTable::getByTableId($sourceTableId);
                while ($row = $rowsResult->fetch()) {
                    static::addRow($newTableId, $row['DATA'], $row['SORT']);
                }
            }
            
            $result->setData(['table_id' => $newTableId]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Экспорт таблицы в массив
     */
    public static function exportTable(int $tableId): array
    {
        $tableData = static::getTableData($tableId, 1, 1000); // Экспортируем до 1000 строк
        
        $export = [
            'table' => [
                'title' => $tableData['table']['TITLE'],
                'owner_type' => $tableData['table']['OWNER_TYPE'],
                'owner_id' => $tableData['table']['OWNER_ID'],
            ],
            'columns' => [],
            'rows' => []
        ];
        
        // Экспортируем колонки
        foreach ($tableData['table']['COLUMNS'] as $column) {
            $export['columns'][] = [
                'code' => $column['CODE'],
                'type' => $column['TYPE'],
                'title' => $column['TITLE'],
                'sort' => $column['SORT'],
                'settings' => $column['SETTINGS']
            ];
        }
        
        // Экспортируем строки
        foreach ($tableData['rows'] as $row) {
            $export['rows'][] = [
                'data' => $row['DATA'],
                'sort' => $row['SORT']
            ];
        }
        
        return $export;
    }

    /**
     * Импорт таблицы из массива
     */
    public static function importTable(array $importData, string $ownerType, int $ownerId): Result
    {
        $result = new Result();
        
        try {
            if (empty($importData['table']['title'])) {
                $result->addError(new Error('Не указано название таблицы'));
                return $result;
            }
            
            // Создаем таблицу с колонками
            $createResult = static::createTable(
                $importData['table']['title'],
                $ownerType,
                $ownerId,
                $importData['columns'] ?? []
            );
            
            if (!$createResult->isSuccess()) {
                $result->addErrors($createResult->getErrors());
                return $result;
            }
            
            $tableId = $createResult->getData()['table_id'];
            
            // Импортируем строки
            if (!empty($importData['rows'])) {
                foreach ($importData['rows'] as $rowData) {
                    static::addRow(
                        $tableId,
                        $rowData['data'] ?? [],
                        $rowData['sort'] ?? 100
                    );
                }
            }
            
            $result->setData(['table_id' => $tableId]);
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Получить статистику таблицы
     */
    public static function getTableStats(int $tableId): array
    {
        $tableInfo = static::getTableInfo($tableId);
        if (!$tableInfo) {
            return [];
        }
        
        return [
            'table_id' => $tableId,
            'title' => $tableInfo['TITLE'],
            'columns_count' => $tableInfo['COLUMNS_COUNT'],
            'rows_count' => $tableInfo['ROWS_COUNT'],
            'created_at' => $tableInfo['CREATED_AT'],
            'updated_at' => $tableInfo['UPDATED_AT'],
            'owner_type' => $tableInfo['OWNER_TYPE'],
            'owner_id' => $tableInfo['OWNER_ID']
        ];
    }
}