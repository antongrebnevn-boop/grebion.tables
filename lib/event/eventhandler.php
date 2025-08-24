<?php

namespace Grebion\Tables\Event;

use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\Loader;
use Grebion\Tables\Model\RowTable;
use Grebion\Tables\Model\ColumnTable;
use Grebion\Tables\Model\CellTable;

class EventHandler
{
    /**
     * Обработчик события перед добавлением таблицы
     */
    public static function onBeforeTableAdd(Event $event): EventResult
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
            $fields['CREATED_AT'] = new \Bitrix\Main\Type\DateTime();
            $result->modifyFields($fields);
        }

        return $result;
    }

    /**
     * Обработчик события после добавления таблицы
     */
    public static function onAfterTableAdd(Event $event): EventResult
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
     * Обработчик события перед удалением таблицы
     */
    public static function onBeforeTableDelete(Event $event): EventResult
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
     * Обработчик события перед добавлением строки
     */
    public static function onBeforeRowAdd(Event $event): EventResult
    {
        $result = new EventResult(EventResult::SUCCESS);
        $fields = $event->getParameter('fields');

        // Автоматическая установка порядка сортировки
        if (empty($fields['SORT']) && !empty($fields['TABLE_ID'])) {
            $maxSort = RowTable::getList([
                'select' => ['SORT'],
                'filter' => ['TABLE_ID' => $fields['TABLE_ID']],
                'order' => ['SORT' => 'DESC'],
                'limit' => 1
            ])->fetch();
            
            $fields['SORT'] = ($maxSort['SORT'] ?? 0) + 100;
            $result->modifyFields($fields);
        }

        return $result;
    }

    /**
     * Обработчик события перед добавлением колонки
     */
    public static function onBeforeColumnAdd(Event $event): EventResult
    {
        $result = new EventResult(EventResult::SUCCESS);
        $fields = $event->getParameter('fields');

        // Автоматическая установка порядка сортировки
        if (empty($fields['SORT']) && !empty($fields['TABLE_ID'])) {
            $maxSort = ColumnTable::getList([
                'select' => ['SORT'],
                'filter' => ['TABLE_ID' => $fields['TABLE_ID']],
                'order' => ['SORT' => 'DESC'],
                'limit' => 1
            ])->fetch();
            
            $fields['SORT'] = ($maxSort['SORT'] ?? 0) + 100;
            $result->modifyFields($fields);
        }

        // Автоматическое создание символьного кода
        if (empty($fields['CODE']) && !empty($fields['NAME'])) {
            $fields['CODE'] = self::generateCode($fields['NAME']);
            $result->modifyFields($fields);
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