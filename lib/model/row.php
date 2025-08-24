<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\EventResult;
use Grebion\Tables\Event\EventHandler;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Класс для работы со строками таблиц
 * 
 * Поля:
 * - ID - первичный ключ
 * - TABLE_ID - ID таблицы
 * - SORT - порядок сортировки
 * - DATA - JSON-данные строки (значения ячеек)
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class RowTable extends DataManager
{
    /**
     * Возвращает название таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'grebion_table_rows';
    }

    /**
     * Возвращает карту полей
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID'
            ]),
            
            new IntegerField('TABLE_ID', [
                'required' => true,
                'title' => 'ID таблицы'
            ]),
            
            new IntegerField('SORT', [
                'default_value' => 500,
                'title' => 'Порядок сортировки'
            ]),
            
            new TextField('DATA', [
                'title' => 'JSON-данные строки'
            ]),
            
            new DatetimeField('CREATED_AT', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
            
            new DatetimeField('UPDATED_AT', [
                'title' => 'Дата обновления'
            ]),
            
            // Связь с таблицей
            new Reference('TABLE', TableTable::class, Join::on('this.TABLE_ID', 'ref.ID')),
            
            // Связь с ячейками (если используется отдельная модель Cell)
            new OneToMany('CELLS', CellTable::class, 'ROW'),
        ];
    }

    /**
     * Найти строку по ID
     */
    public static function findById(int $id): ?array
    {
        try {
            $result = static::getByPrimary($id);
            return $result->fetch() ?: null;
        } catch (ArgumentException | SystemException $e) {
            return null;
        }
    }

    /**
     * Получить строки таблицы
     */
    public static function getByTableId(int $tableId): Result
    {
        return static::getList([
            'filter' => ['TABLE_ID' => $tableId],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);
    }

    /**
     * Создать новую строку
     */
    public static function createRow(int $tableId, array $data = [], int $sort = 500): \Bitrix\Main\ORM\Data\AddResult
    {
        return static::add([
            'TABLE_ID' => $tableId,
            'SORT' => $sort,
            'DATA' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'CREATED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime()
        ]);
    }

    /**
     * Обновить строку
     */
    public static function updateRow(int $id, array $fields): \Bitrix\Main\ORM\Data\UpdateResult
    {
        $fields['UPDATED_AT'] = new DateTime();
        
        if (isset($fields['DATA']) && is_array($fields['DATA'])) {
            $fields['DATA'] = json_encode($fields['DATA'], JSON_UNESCAPED_UNICODE);
        }
        
        return static::update($id, $fields);
    }

    /**
     * Удалить строку
     */
    public static function deleteRow(int $id): \Bitrix\Main\ORM\Data\DeleteResult
    {
        return static::delete($id);
    }

    /**
     * Получить данные строки в виде массива
     */
    public static function getRowData(int $id): array
    {
        $row = static::findById($id);
        if (!$row) {
            return [];
        }
        
        $data = json_decode($row['DATA'] ?? '{}', true);
        return is_array($data) ? $data : [];
    }

    /**
     * Обновить значение ячейки в строке
     */
    public static function updateCellValue(int $rowId, string $columnCode, $value): bool
    {
        $row = static::findById($rowId);
        if (!$row) {
            return false;
        }
        
        $data = static::getRowData($rowId);
        $data[$columnCode] = $value;
        
        $result = static::updateRow($rowId, ['DATA' => $data]);
        return $result->isSuccess();
    }

    /**
     * Получить значение ячейки
     */
    public static function getCellValue(int $rowId, string $columnCode)
    {
        $data = static::getRowData($rowId);
        return $data[$columnCode] ?? null;
    }

    /**
     * Переупорядочить строки
     */
    public static function reorderRows(int $tableId, array $rowIds): bool
    {
        $sort = 100;
        foreach ($rowIds as $rowId) {
            static::update($rowId, [
                'SORT' => $sort,
                'UPDATED_AT' => new DateTime()
            ]);
            $sort += 100;
        }
        return true;
    }

    /**
     * Массовое создание строк
     */
    public static function bulkInsert(int $tableId, array $rows): array
    {
        $results = [];
        $sort = 100;
        
        foreach ($rows as $rowData) {
            $result = static::createRow($tableId, $rowData, $sort);
            $results[] = $result;
            $sort += 100;
        }
        
        return $results;
    }

    /**
     * Получить строки с пагинацией
     */
    public static function getPagedRows(int $tableId, int $page = 1, int $limit = 50): Result
    {
        $offset = ($page - 1) * $limit;
        
        return static::getList([
            'filter' => ['TABLE_ID' => $tableId],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Подсчитать количество строк в таблице
     */
    public static function getRowsCount(int $tableId): int
    {
        try {
            $result = static::getList([
                'filter' => ['TABLE_ID' => $tableId],
                'select' => ['CNT'],
                'runtime' => [
                    new \Bitrix\Main\ORM\Fields\ExpressionField('CNT', 'COUNT(*)')
                ]
            ]);
            
            $row = $result->fetch();
            return (int)($row['CNT'] ?? 0);
        } catch (ArgumentException | SystemException $e) {
            return 0;
        }
    }

    /**
     * Поиск строк по значению
     */
    public static function searchRows(int $tableId, string $searchValue): Result
    {
        return static::getList([
            'filter' => [
                'TABLE_ID' => $tableId,
                '%DATA' => $searchValue
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);
    }

    /**
     * Обработчик события перед добавлением
     */
    public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event): EventResult
    {
        return EventHandler::onBeforeRowAdd($event);
    }
}