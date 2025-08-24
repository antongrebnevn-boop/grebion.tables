<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ORM\EntityError;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Класс для работы с ячейками таблиц (опциональная модель)
 * 
 * Используется для детального управления ячейками, когда нужно:
 * - Отслеживать историю изменений каждой ячейки
 * - Хранить метаданные ячеек
 * - Реализовать сложную валидацию на уровне ячеек
 * 
 * Поля:
 * - ID - первичный ключ
 * - ROW_ID - ID строки
 * - COLUMN_ID - ID колонки
 * - VALUE - значение ячейки
 * - FORMATTED_VALUE - отформатированное значение
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class CellTable extends DataManager
{
    /**
     * Возвращает название таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'grebion_table_cells';
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
            
            new IntegerField('ROW_ID', [
                'required' => true,
                'title' => 'ID строки'
            ]),
            
            new IntegerField('COLUMN_ID', [
                'required' => true,
                'title' => 'ID колонки'
            ]),
            
            new TextField('VALUE', [
                'title' => 'Значение ячейки'
            ]),
            
            new TextField('FORMATTED_VALUE', [
                'title' => 'Отформатированное значение'
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
            
            // Связь со строкой
            new Reference('ROW', RowTable::class, Join::on('this.ROW_ID', 'ref.ID')),
            
            // Связь с колонкой
            new Reference('COLUMN', ColumnTable::class, Join::on('this.COLUMN_ID', 'ref.ID')),
        ];
    }

    /**
     * Найти ячейку по ID
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
     * Найти ячейку по строке и колонке
     */
    public static function findByRowAndColumn(int $rowId, int $columnId): ?array
    {
        try {
            $result = static::getList([
                'filter' => [
                    'ROW_ID' => $rowId,
                    'COLUMN_ID' => $columnId
                ],
                'limit' => 1
            ]);
            return $result->fetch() ?: null;
        } catch (ArgumentException | SystemException $e) {
            return null;
        }
    }

    /**
     * Получить ячейки строки
     */
    public static function getByRowId(int $rowId): Result
    {
        return static::getList([
            'filter' => ['ROW_ID' => $rowId],
            'select' => ['*', 'COLUMN'],
            'order' => ['COLUMN.SORT' => 'ASC', 'COLUMN.ID' => 'ASC']
        ]);
    }

    /**
     * Получить ячейки колонки
     */
    public static function getByColumnId(int $columnId): Result
    {
        return static::getList([
            'filter' => ['COLUMN_ID' => $columnId],
            'select' => ['*', 'ROW'],
            'order' => ['ROW.SORT' => 'ASC', 'ROW.ID' => 'ASC']
        ]);
    }

    /**
     * Создать новую ячейку
     */
    public static function createCell(int $rowId, int $columnId, $value = null, string $formattedValue = ''): \Bitrix\Main\ORM\Data\AddResult
    {
        return static::add([
            'ROW_ID' => $rowId,
            'COLUMN_ID' => $columnId,
            'VALUE' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            'FORMATTED_VALUE' => $formattedValue,
            'CREATED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime()
        ]);
    }

    /**
     * Обновить ячейку
     */
    public static function updateCell(int $id, $value = null, string $formattedValue = ''): \Bitrix\Main\ORM\Data\UpdateResult
    {
        $fields = [
            'UPDATED_AT' => new DateTime()
        ];
        
        if ($value !== null) {
            $fields['VALUE'] = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        if ($formattedValue !== '') {
            $fields['FORMATTED_VALUE'] = $formattedValue;
        }
        
        return static::update($id, $fields);
    }

    /**
     * Установить значение ячейки (создать или обновить)
     */
    public static function setCellValue(int $rowId, int $columnId, $value = null, string $formattedValue = ''): bool
    {
        $cell = static::findByRowAndColumn($rowId, $columnId);
        
        if ($cell) {
            $result = static::updateCell($cell['ID'], $value, $formattedValue);
        } else {
            $result = static::createCell($rowId, $columnId, $value, $formattedValue);
        }
        
        return $result->isSuccess();
    }

    /**
     * Получить значение ячейки
     */
    public static function getCellValue(int $rowId, int $columnId)
    {
        $cell = static::findByRowAndColumn($rowId, $columnId);
        if (!$cell) {
            return null;
        }
        
        $value = $cell['VALUE'];
        
        // Попытаемся декодировать JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return $value;
    }

    /**
     * Удалить ячейку
     */
    public static function deleteCell(int $id): \Bitrix\Main\ORM\Data\DeleteResult
    {
        return static::delete($id);
    }

    /**
     * Удалить ячейки строки
     */
    public static function deleteByRowId(int $rowId): bool
    {
        try {
            $cells = static::getByRowId($rowId);
            while ($cell = $cells->fetch()) {
                static::delete($cell['ID']);
            }
            return true;
        } catch (ArgumentException | SystemException $e) {
            return false;
        }
    }

    /**
     * Удалить ячейки колонки
     */
    public static function deleteByColumnId(int $columnId): bool
    {
        try {
            $cells = static::getByColumnId($columnId);
            while ($cell = $cells->fetch()) {
                static::delete($cell['ID']);
            }
            return true;
        } catch (ArgumentException | SystemException $e) {
            return false;
        }
    }

    /**
     * Массовое создание ячеек для строки
     */
    public static function createRowCells(int $rowId, array $columnValues): array
    {
        $results = [];
        
        foreach ($columnValues as $columnId => $value) {
            $result = static::createCell($rowId, (int)$columnId, $value);
            $results[$columnId] = $result;
        }
        
        return $results;
    }

    /**
     * Получить все ячейки таблицы в виде матрицы
     */
    public static function getTableMatrix(int $tableId): array
    {
        try {
            $result = static::getList([
                'select' => ['*', 'ROW', 'COLUMN'],
                'filter' => ['ROW.TABLE_ID' => $tableId],
                'order' => ['ROW.SORT' => 'ASC', 'COLUMN.SORT' => 'ASC']
            ]);
            
            $matrix = [];
            while ($cell = $result->fetch()) {
                $rowId = $cell['ROW_ID'];
                $columnCode = $cell['COLUMN_CODE'] ?? $cell['COLUMN_ID'];
                
                if (!isset($matrix[$rowId])) {
                    $matrix[$rowId] = [];
                }
                
                $matrix[$rowId][$columnCode] = static::getCellValue($cell['ROW_ID'], $cell['COLUMN_ID']);
            }
            
            return $matrix;
        } catch (ArgumentException | SystemException $e) {
            return [];
        }
    }

    /**
     * Поиск ячеек по значению
     */
    public static function searchCells(int $tableId, string $searchValue): Result
    {
        return static::getList([
            'select' => ['*', 'ROW', 'COLUMN'],
            'filter' => [
                'ROW.TABLE_ID' => $tableId,
                '%VALUE' => $searchValue
            ],
            'order' => ['ROW.SORT' => 'ASC', 'COLUMN.SORT' => 'ASC']
        ]);
    }

    /**
     * Обработчик события перед добавлением
     */
    public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event): \Bitrix\Main\ORM\EventResult
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $fields = $event->getParameter('fields');
        
        // Валидация обязательных полей
        if (empty($fields['ROW_ID'])) {
            $result->addError(new EntityError('Не указан ID строки'));
        }
        
        if (empty($fields['COLUMN_ID'])) {
            $result->addError(new EntityError('Не указан ID колонки'));
        }
        
        // Проверка уникальности комбинации ROW_ID + COLUMN_ID
        if (!empty($fields['ROW_ID']) && !empty($fields['COLUMN_ID'])) {
            $existing = static::findByRowAndColumn((int)$fields['ROW_ID'], (int)$fields['COLUMN_ID']);
            if ($existing) {
                $result->addError(new EntityError('Ячейка для данной строки и колонки уже существует'));
            }
        }
        
        return $result;
    }

    /**
     * Обработчик события перед обновлением
     */
    public static function onBeforeUpdate(\Bitrix\Main\ORM\Event $event): \Bitrix\Main\ORM\EventResult
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $fields = $event->getParameter('fields');
        
        // Автоматически обновляем дату изменения
        $fields['UPDATED_AT'] = new DateTime();
        $result->modifyFields($fields);
        
        return $result;
    }
}