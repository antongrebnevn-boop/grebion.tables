<?php

declare(strict_types=1);

namespace Grebion\Tables\Model;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Класс для работы со схемами таблиц
 * 
 * Поля:
 * - ID - первичный ключ
 * - NAME - название схемы
 * - DESCRIPTION - описание схемы
 * - SCHEMA - JSON-схема колонок
 * - CREATED_AT - дата создания
 * - UPDATED_AT - дата обновления
 */
class TableSchemaTable extends DataManager
{
    /**
     * Возвращает название таблицы в БД
     */
    public static function getTableName(): string
    {
        return 'grebion_table_schemas';
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
            
            new StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new \Bitrix\Main\Entity\Validator\Length(1, 255),
                    ];
                },
                'title' => 'Название схемы'
            ]),
            
            new TextField('DESCRIPTION', [
                'required' => false,
                'title' => 'Описание схемы'
            ]),
            
            new TextField('SCHEMA', [
                'required' => true,
                'title' => 'JSON-схема колонок'
            ]),
            
            new DatetimeField('CREATED_AT', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
            
            new DatetimeField('UPDATED_AT', [
                'required' => true,
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата обновления'
            ])
        ];
    }
    
    /**
     * Обработчик события перед добавлением
     */
    public static function onBeforeAdd(\Bitrix\Main\ORM\Event $event)
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $data = $event->getParameter('fields');
        
        // Валидация JSON-схемы
        if (isset($data['SCHEMA'])) {
            $schema = json_decode($data['SCHEMA'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result->addError(new \Bitrix\Main\Error('Некорректный JSON в поле SCHEMA'));
            }
        }
        
        return $result;
    }
    
    /**
     * Обработчик события перед обновлением
     */
    public static function onBeforeUpdate(\Bitrix\Main\ORM\Event $event)
    {
        $result = new \Bitrix\Main\ORM\EventResult();
        $data = $event->getParameter('fields');
        
        // Обновляем дату изменения
        $data['UPDATED_AT'] = new DateTime();
        $result->modifyFields($data);
        
        // Валидация JSON-схемы
        if (isset($data['SCHEMA'])) {
            $schema = json_decode($data['SCHEMA'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result->addError(new \Bitrix\Main\Error('Некорректный JSON в поле SCHEMA'));
            }
        }
        
        return $result;
    }
    
    /**
     * Получить схему как массив
     */
    public static function getSchemaArray(int $id): ?array
    {
        $schema = static::getById($id)->fetch();
        if (!$schema) {
            return null;
        }
        
        return json_decode($schema['SCHEMA'], true);
    }
    
    /**
     * Создать схему из массива колонок
     */
    public static function createFromColumns(string $name, array $columns, string $description = ''): \Bitrix\Main\Result
    {
        $schema = [
            'columns' => $columns
        ];
        
        return static::add([
            'NAME' => $name,
            'DESCRIPTION' => $description,
            'SCHEMA' => json_encode($schema, JSON_UNESCAPED_UNICODE)
        ]);
    }
    
    /**
     * Валидация структуры колонки
     */
    public static function validateColumn(array $column): bool
    {
        $requiredFields = ['code', 'type', 'title'];
        
        foreach ($requiredFields as $field) {
            if (!isset($column[$field]) || empty($column[$field])) {
                return false;
            }
        }
        
        $allowedTypes = [
            'text', 'number', 'date', 'datetime', 'file', 
            'boolean', 'select', 'multiselect', 'email', 'url', 'phone'
        ];
        
        return in_array($column['type'], $allowedTypes);
    }
}