<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\EventResult;
use Grebion\Tables\Event\EventHandler;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Класс для работы с таблицами
 * 
 * Поля:
 * - ID - первичный ключ
 * - OWNER_TYPE - тип владельца (USER, IBLOCK_ELEMENT, etc.)
 * - OWNER_ID - ID владельца
 * - TITLE - название таблицы
 * - DATA - JSON-данные таблицы
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class TableTable extends DataManager
{
    /**
     * Возвращает название таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'grebion_tables';
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
            
            new StringField('OWNER_TYPE', [
                'required' => true,
                'size' => 50,
                'title' => 'Тип владельца'
            ]),
            
            new IntegerField('OWNER_ID', [
                'required' => true,
                'title' => 'ID владельца'
            ]),
            
            new StringField('TITLE', [
                'size' => 255,
                'title' => 'Название таблицы'
            ]),
            
            new TextField('DATA', [
                'title' => 'JSON-данные таблицы'
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
            
            // Связь с колонками
            new OneToMany('COLUMNS', ColumnTable::class, 'TABLE'),
            
            // Связь со строками
            new OneToMany('ROWS', RowTable::class, 'TABLE'),
        ];
    }

    /**
     * Найти таблицу по ID
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
     * Получить таблицы по владельцу
     */
    public static function getByOwner(string $ownerType, int $ownerId): Result
    {
        return static::getList([
            'filter' => [
                'OWNER_TYPE' => $ownerType,
                'OWNER_ID' => $ownerId
            ],
            'order' => ['ID' => 'ASC']
        ]);
    }

    /**
     * Создать новую таблицу
     */
    public static function createTable(string $ownerType, int $ownerId, string $title = '', array $data = []): \Bitrix\Main\ORM\Data\AddResult
    {
        return static::add([
            'OWNER_TYPE' => $ownerType,
            'OWNER_ID' => $ownerId,
            'TITLE' => $title,
            'DATA' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'CREATED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime()
        ]);
    }

    /**
     * Обновить таблицу
     */
    public static function updateTable(int $id, array $fields): \Bitrix\Main\ORM\Data\UpdateResult
    {
        $fields['UPDATED_AT'] = new DateTime();
        
        if (isset($fields['DATA']) && is_array($fields['DATA'])) {
            $fields['DATA'] = json_encode($fields['DATA'], JSON_UNESCAPED_UNICODE);
        }
        
        return static::update($id, $fields);
    }

    /**
     * Удалить таблицу
     */
    public static function deleteTable(int $id): \Bitrix\Main\ORM\Data\DeleteResult
    {
        return static::delete($id);
    }

    /**
     * Получить данные таблицы в виде массива
     */
    public static function getTableData(int $id): ?array
    {
        $table = static::findById($id);
        if (!$table) {
            return null;
        }
        
        $data = json_decode($table['DATA'] ?? '[]', true);
        return is_array($data) ? $data : [];
    }

    /**
     * Обработчик события перед добавлением
     */
    public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event): EventResult
    {
        return EventHandler::onBeforeTableAdd($event);
    }

    /**
     * Обработчик события после добавления
     */
    public static function onAfterAdd(\Bitrix\Main\ORM\Event $event): EventResult
    {
        return EventHandler::onAfterTableAdd($event);
    }

    /**
     * Обработчик события перед удалением
     */
    public static function onBeforeDelete(\Bitrix\Main\ORM\Event $event): EventResult
    {
        return EventHandler::onBeforeTableDelete($event);
    }
}