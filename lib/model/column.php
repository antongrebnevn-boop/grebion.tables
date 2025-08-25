<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\ORM\EntityError;

/**
 * Класс для работы с колонками таблиц
 * 
 * Поля:
 * - ID - первичный ключ
 * - TABLE_ID - ID таблицы
 * - CODE - символьный код колонки
 * - TYPE - тип колонки (text, number, date, file, etc.)
 * - TITLE - название колонки
 * - SORT - порядок сортировки
 * - SETTINGS - JSON-настройки колонки
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class ColumnTable extends DataManager
{
    // Типы колонок
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_FILE = 'file';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_EMAIL = 'email';
    public const TYPE_URL = 'url';
    public const TYPE_PHONE = 'phone';

    // Алиасы и дополнительные типы для совместимости
    public const TYPE_STRING = self::TYPE_TEXT;
    public const TYPE_INTEGER = self::TYPE_NUMBER;
    public const TYPE_FLOAT = 'float';
    public const TYPE_JSON = 'json';

     private static array $typeAliases = [
         'string'  => self::TYPE_TEXT,
         'integer' => self::TYPE_NUMBER,
         'int'     => self::TYPE_NUMBER,
         'double'  => self::TYPE_FLOAT,
         'bool'    => self::TYPE_BOOLEAN,
     ];

     private static function resolveType(string $type): string
     {
         return self::$typeAliases[$type] ?? $type;
     }

    /**
     * Возвращает название таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'grebion_table_columns';
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
            
            new StringField('CODE', [
                'required' => true,
                'size' => 50,
                'title' => 'Символьный код'
            ]),
            
            new StringField('TYPE', [
                'required' => true,
                'size' => 20,
                'default_value' => self::TYPE_TEXT,
                'title' => 'Тип колонки'
            ]),
            
            new StringField('TITLE', [
                'required' => true,
                'size' => 255,
                'title' => 'Название колонки'
            ]),
            
            new IntegerField('SORT', [
                'default_value' => 500,
                'title' => 'Порядок сортировки'
            ]),
            
            new TextField('SETTINGS', [
                'title' => 'JSON-настройки колонки'
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
            new Reference('TABLE', TableDataTable::class, Join::on('this.TABLE_ID', 'ref.ID')),
        ];
    }

    /**
     * Найти колонку по ID
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
     * Получить колонки таблицы
     */
    public static function getByTableId(int $tableId): Result
    {
        return static::getList([
            'filter' => ['TABLE_ID' => $tableId],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC']
        ]);
    }

    /**
     * Найти колонку по коду
     */
    public static function findByCode(int $tableId, string $code): ?array
    {
        try {
            $result = static::getList([
                'filter' => [
                    'TABLE_ID' => $tableId,
                    'CODE' => $code
                ],
                'limit' => 1
            ]);
            return $result->fetch() ?: null;
        } catch (ArgumentException | SystemException $e) {
            return null;
        }
    }

    /**
     * Создать новую колонку
     */
    public static function createColumn(int $tableId, string $code, string $type, string $title, array $settings = [], int $sort = 500): \Bitrix\Main\ORM\Data\AddResult
    {
        return static::add([
            'TABLE_ID' => $tableId,
            'CODE' => $code,
            'TYPE' => $type,
            'TITLE' => $title,
            'SORT' => $sort,
            'SETTINGS' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'CREATED_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime()
        ]);
    }

    /**
     * Обновить колонку
     */
    public static function updateColumn(int $id, array $fields): \Bitrix\Main\ORM\Data\UpdateResult
    {
        $fields['UPDATED_AT'] = new DateTime();
        
        if (isset($fields['SETTINGS']) && is_array($fields['SETTINGS'])) {
            $fields['SETTINGS'] = json_encode($fields['SETTINGS'], JSON_UNESCAPED_UNICODE);
        }
        
        return static::update($id, $fields);
    }

    /**
     * Удалить колонку
     */
    public static function deleteColumn(int $id): \Bitrix\Main\ORM\Data\DeleteResult
    {
        return static::delete($id);
    }

    /**
     * Получить настройки колонки в виде массива
     */
    public static function getColumnSettings(int $id): array
    {
        $column = static::findById($id);
        if (!$column) {
            return [];
        }
        
        $settings = json_decode($column['SETTINGS'] ?? '{}', true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Получить доступные типы колонок
     */
    public static function getAvailableTypes(): array
    {
        $types = [
            self::TYPE_TEXT => 'Текст',
            self::TYPE_NUMBER => 'Число',
            self::TYPE_DATE => 'Дата',
            self::TYPE_DATETIME => 'Дата и время',
            self::TYPE_FILE => 'Файл',
            self::TYPE_BOOLEAN => 'Да/Нет',
            self::TYPE_SELECT => 'Список',
            self::TYPE_MULTISELECT => 'Множественный список',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_URL => 'URL',
            self::TYPE_PHONE => 'Телефон',
            self::TYPE_FLOAT => 'Число (float)',
            self::TYPE_JSON => 'JSON',
        ];
        
        // Добавляем алиасы для валидации
        foreach (self::$typeAliases as $alias => $realType) {
            if (isset($types[$realType])) {
                $types[$alias] = $types[$realType];
            }
        }
        
        return $types;
    }

    /**
     * Переупорядочить колонки
     */
    public static function reorderColumns(int $tableId, array $columnIds): bool
    {
        $sort = 100;
        foreach ($columnIds as $columnId) {
            static::update($columnId, [
                'SORT' => $sort,
                'UPDATED_AT' => new DateTime()
            ]);
            $sort += 100;
        }
        return true;
    }

    /**
     * Обработчик события перед добавлением
     */
    public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event): \Bitrix\Main\ORM\EventResult
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $fields = $event->getParameter('fields');
        
        // Нормализация алиасов типов (должна быть перед валидацией)
        if (!empty($fields['TYPE'])) {
            $fields['TYPE'] = static::resolveType((string)$fields['TYPE']);
        }
         
        // Валидация обязательных полей
        if (empty($fields['TABLE_ID'])) {
            $result->addError(new EntityError('Не указан ID таблицы'));
        }
        
        if (empty($fields['CODE'])) {
            $result->addError(new EntityError('Не указан код колонки'));
        }
        
        if (empty($fields['TITLE'])) {
            $result->addError(new EntityError('Не указано название колонки'));
        }
        
        // Проверка уникальности кода в рамках таблицы
        if (!empty($fields['TABLE_ID']) && !empty($fields['CODE'])) {
            $existing = static::findByCode((int)$fields['TABLE_ID'], $fields['CODE']);
            if ($existing) {
                $result->addError(new EntityError('Колонка с таким кодом уже существует'));
            }
        }
        
        // Валидация типа колонки
        if (!empty($fields['TYPE']) && !array_key_exists($fields['TYPE'], static::getAvailableTypes())) {
            $result->addError(new EntityError('Недопустимый тип колонки'));
        }
        
        // Автоматическая установка порядка сортировки
        if (empty($fields['SORT']) && !empty($fields['TABLE_ID'])) {
            $maxSort = static::getList([
                'select' => ['SORT'],
                'filter' => ['TABLE_ID' => $fields['TABLE_ID']],
                'order' => ['SORT' => 'DESC'],
                'limit' => 1
            ])->fetch();
            
            $fields['SORT'] = ($maxSort['SORT'] ?? 0) + 100;
        }

        // Автоматическое создание символьного кода
        if (empty($fields['CODE']) && !empty($fields['TITLE'])) {
            $fields['CODE'] = self::generateCode($fields['TITLE']);
        }
        
        $result->modifyFields($fields);
        
        return $result;
    }

    /**
     * Обработчик события перед обновлением
     */
    public static function onBeforeUpdate(\Bitrix\Main\ORM\Event $event): \Bitrix\Main\ORM\EventResult
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $fields = $event->getParameter('fields');
        $primary = $event->getParameter('primary');
        
        // Автоматически обновляем дату изменения
        $fields['UPDATED_AT'] = new DateTime();
        
        // Нормализация алиасов типов (должна быть перед валидацией)
        if (!empty($fields['TYPE'])) {
            $fields['TYPE'] = static::resolveType((string)$fields['TYPE']);
        }
        
        // Проверка уникальности кода при изменении
        if (!empty($fields['CODE'])) {
            $current = static::findById($primary['ID']);
            if ($current) {
                $existing = static::findByCode((int)$current['TABLE_ID'], $fields['CODE']);
                if ($existing && $existing['ID'] !== $primary['ID']) {
                    $result->addError(new EntityError('Колонка с таким кодом уже существует'));
                }
            }
        }
        
        // Валидация типа колонки
        if (!empty($fields['TYPE']) && !array_key_exists($fields['TYPE'], static::getAvailableTypes())) {
            $result->addError(new EntityError('Недопустимый тип колонки'));
        }
        
        $result->modifyFields($fields);
        
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