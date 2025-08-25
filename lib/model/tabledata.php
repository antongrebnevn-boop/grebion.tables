<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Класс для работы с таблицами
 * 
 * Поля:
 * - ID - первичный ключ
 * - SCHEMA_ID - ID схемы таблицы
 * - OWNER_TYPE - тип владельца (USER, IBLOCK_ELEMENT, etc.)
 * - OWNER_ID - ID владельца
 * - TITLE - название таблицы
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class TableDataTable extends DataManager
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
            
            new IntegerField('SCHEMA_ID', [
                'required' => true,
                'title' => 'ID схемы таблицы'
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
            
            new DatetimeField('CREATED_AT', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
            
            new DatetimeField('UPDATED_AT', [
                'title' => 'Дата обновления'
            ]),
            
            // Связь со схемой
            new Reference('SCHEMA', TableSchemaTable::class, Join::on('this.SCHEMA_ID', 'ref.ID')),
            
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
        $result = new EventResult(EventResult::SUCCESS);
        $fields = $event->getParameter('fields');

        // Автоматическое создание символьного кода
        if (empty($fields['CODE']) && !empty($fields['NAME'])) {
            $fields['CODE'] = self::generateCode($fields['NAME']);
            $result->modifyFields($fields);
        }

        // Установка даты создания
        if (empty($fields['CREATED_AT'])) {
            $fields['CREATED_AT'] = new DateTime();
            $result->modifyFields($fields);
        }

        return $result;
    }

    /**
     * Обработчик события после добавления
     */
    public static function onAfterAdd(\Bitrix\Main\ORM\Event $event): EventResult
    {
        $result = new EventResult(EventResult::SUCCESS);
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');

        // Логирование создания таблицы
        if (Loader::includeModule('main')) {
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'GREBION_TABLE_ADD',
                'MODULE_ID' => 'grebion.tables',
                'DESCRIPTION' => "Создана таблица: {$fields['NAME']} (ID: {$id})"
            ]);
        }

        return $result;
    }

    /**
     * Обработчик события перед удалением
     */
    public static function onBeforeDelete(\Bitrix\Main\ORM\Event $event): EventResult
    {
        $result = new EventResult(EventResult::SUCCESS);
        $primary = $event->getParameter('primary');

        // Удаление связанных данных
        if (is_array($primary) && isset($primary['ID'])) {
            $tableId = $primary['ID'];
            
            // Удаляем все ячейки
            $cells = CellTable::getList([
                'filter' => ['ROW.TABLE_ID' => $tableId]
            ]);
            while ($cell = $cells->fetch()) {
                CellTable::delete($cell['ID']);
            }

            // Удаляем все строки
            $rows = RowTable::getList([
                'filter' => ['TABLE_ID' => $tableId]
            ]);
            while ($row = $rows->fetch()) {
                RowTable::delete($row['ID']);
            }

            // Удаляем все колонки
            $columns = ColumnTable::getList([
                'filter' => ['TABLE_ID' => $tableId]
            ]);
            while ($column = $columns->fetch()) {
                ColumnTable::delete($column['ID']);
            }
        }

        return $result;
    }

    /**
     * Генерация символьного кода из названия
     */
    private static function generateCode(string $name): string
    {
        $code = mb_strtolower($name);
        $code = preg_replace('/[^a-z0-9а-я]/ui', '_', $code);
        $code = preg_replace('/_{2,}/', '_', $code);
        $code = trim($code, '_');
        
        // Транслитерация
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];
        
        $code = strtr($code, $translitMap);
        
        return mb_substr($code, 0, 50);
    }
}