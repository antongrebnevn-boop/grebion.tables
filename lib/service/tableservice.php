<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Grebion\Tables\Repository\TableRepository;
use Grebion\Tables\Repository\ColumnRepository;
use Grebion\Tables\Repository\RowRepository;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Application;

/**
 * Основной сервис для работы с таблицами
 * Агрегирует репозитории и предоставляет высокоуровневые методы
 */
class TableService
{
    private TableRepository $tableRepository;
    private ColumnRepository $columnRepository;
    private RowRepository $rowRepository;
    
    public function __construct(
        ?TableRepository $tableRepository = null,
        ?ColumnRepository $columnRepository = null,
        ?RowRepository $rowRepository = null
    ) {
        $this->tableRepository = $tableRepository ?? new TableRepository();
        $this->columnRepository = $columnRepository ?? new ColumnRepository();
        $this->rowRepository = $rowRepository ?? new RowRepository();
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
    public function createTable(string $title, string $ownerType, int $ownerId, array $columns = []): Result
    {
        $result = new Result();
        
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            
            // Создаем таблицу
            $tableData = [
                'TITLE' => $title,
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerId,
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
            $result->setData(['table_id' => $tableId]);
            
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
    public function getTable(int $id): Result
    {
        $result = new Result();
        $table = $this->tableRepository->getById($id);
        
        if ($table) {
            $result->setData($table);
        } else {
            $result->addError(new Error('Таблица не найдена'));
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
    public function updateTable(int $id, array $data): Result
    {
        return $this->tableRepository->save($data, $id);
    }
    
    /**
     * Удалить таблицу со всеми данными
     *
     * @param int $id ID таблицы
     * @return Result
     */
    public function deleteTable(int $id): Result
    {
        $result = new Result();
        
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
    ): Result {
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
    public function getTableColumns(int $tableId): Result
    {
        $result = new Result();
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
    ): Result {
        $result = new Result();
        
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
    public function getTablesByOwner(string $ownerType, int $ownerId): Result
    {
        $queryResult = $this->tableRepository->getList([
            'OWNER_TYPE' => $ownerType,
            'OWNER_ID' => $ownerId,
        ]);
        
        $result = new Result();
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
    public function getTableStats(int $tableId): Result
    {
        $result = new Result();
        
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
}