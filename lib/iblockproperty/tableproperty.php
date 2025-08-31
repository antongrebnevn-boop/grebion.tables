<?php

namespace Grebion\Tables\IblockProperty;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Grebion\Tables\Model\TableSchemaTable;
use Grebion\Tables\Model\TableDataTable;
use Grebion\Tables\Model\RowTable;

Loc::loadMessages(__FILE__);

/**
 * Пользовательский тип свойства инфоблока "Таблица"
 */
class TableProperty
{
    public const USER_TYPE = 'grebion_table';

    /**
     * Возвращает описание пользовательского типа свойства.
     *
     * @return array<string, mixed>
     */
    public static function getUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE'          => 'S',
            'USER_TYPE'              => self::USER_TYPE,
            'DESCRIPTION'            => Loc::getMessage('GREBION_TABLE_PROP_TITLE') ?? 'Table',
            'CLASS_NAME'             => __CLASS__,

            // callbacks
            'GetSettingsHTML'        => [__CLASS__, 'getSettingsHtml'],
            'PrepareSettings'        => [__CLASS__, 'prepareSettings'],
            'GetPropertyFieldHtml'   => [__CLASS__, 'getPropertyFieldHtml'],
            'GetAdminListViewHTML'   => [__CLASS__, 'getAdminListViewHtml'],
            'ConvertToDB'            => [__CLASS__, 'convertToDb'],
            'ConvertFromDB'          => [__CLASS__, 'convertFromDb'],
            'GetPublicViewHTML'      => [__CLASS__, 'getPublicViewHtml'],
        ];
    }

    /**
     * Формирует HTML настроек свойства в административной форме.
     *
     * @param array<string, mixed> $property            Описание свойства.
     * @param array<string, mixed> $htmlControlName     Массив с ключами для имён инпутов.
     * @param array<string, mixed> $propertyFields      Доп. параметры (по ссылке).
     */
    public static function getSettingsHtml(array $property, array $htmlControlName, array &$propertyFields): string
    {
        global $APPLICATION;

        // Скрываем ненужные поля в настройках свойства
        $propertyFields = [
            "HIDE" => ["ROW_COUNT", "WITH_DESCRIPTION", "DEFAULT_VALUE", "MULTIPLE_CNT"],
            "USER_TYPE_SETTINGS_TITLE" => "Настройки схемы таблицы"
        ];

        // Битрикс может сохранять настройки в разных местах, проверяем все варианты
        $schemaId = 0;
        
        // Вариант 1: в USER_TYPE_SETTINGS (при создании/редактировании)
        if (isset($property['USER_TYPE_SETTINGS']['SCHEMA_ID'])) {
            $schemaId = (int) $property['USER_TYPE_SETTINGS']['SCHEMA_ID'];
        }
        // Вариант 2: в SETTINGS (сохранённые настройки при повторном открытии)
        elseif (isset($property['SETTINGS']['SCHEMA_ID'])) {
            $schemaId = (int) $property['SETTINGS']['SCHEMA_ID'];
        }
        // Вариант 3: непосредственно в массиве property
        elseif (isset($property['SCHEMA_ID'])) {
            $schemaId = (int) $property['SCHEMA_ID'];
        }

        // Правильное формирование имени поля
        $inputName = $htmlControlName['NAME'] . '[SCHEMA_ID]';

        ob_start();
        $APPLICATION->IncludeComponent(
            'grebion:table.settings',
            '',
            [
                'SCHEMA_ID'     => $schemaId,
                // Правильное имя для поля SCHEMA_ID в настройках свойства
                'INPUT_NAME'    => $inputName,
            ],
            null,
            ['HIDE_ICONS' => 'Y']
        );

        return ob_get_clean() ?: '';
    }

    /**
     * Подготавливает данные настроек для сохранения.
     *
     * @param array<string, mixed> $property Параметры свойства после сабмита формы.
     * @return array<string, int>
     */
    public static function prepareSettings(array $property): array
    {
        // Битрикс передаёт сюда массив с данными из $_POST
        $settings = $property['USER_TYPE_SETTINGS'] ?? [];
        
        // Если пришли новые данные из формы - используем их
        if (isset($settings['SCHEMA_ID'])) {
            $schemaId = (int) $settings['SCHEMA_ID'];
        } else {
            // Иначе берём текущие сохранённые настройки (при редактировании)
            $schemaId = (int) ($property['SETTINGS']['SCHEMA_ID'] ?? 0);
        }

        $result = [
            'SCHEMA_ID' => $schemaId,
        ];

        return $result;
    }

    public static function getPropertyFieldHtml($property, $value, $htmlControlName): string
    {
        global $APPLICATION;

        // Получаем ID схемы из настроек свойства
        $settings = $property['USER_TYPE_SETTINGS'] ?? [];
        $schemaId = (int)($settings['SCHEMA_ID'] ?? 0);

        if ($schemaId === 0) {
            return '<div style="color: red; padding: 10px; border: 1px solid #ff0000; background: #ffe0e0;">
                        Схема таблицы не настроена. Настройте схему в свойствах инфоблока.
                    </div>';
        }

        // Получаем ID существующей таблицы из значения свойства
        $tableId = (int)($value['VALUE'] ?? 0);

        // Формируем имя input для сохранения TABLE_ID
        $inputName = $htmlControlName['VALUE'];

        ob_start();
        $APPLICATION->IncludeComponent(
            'grebion:table.editor',
            '',
            [
                'SCHEMA_ID'  => $schemaId,
                'TABLE_ID'   => $tableId,
                'INPUT_NAME' => $inputName,
            ],
            null,
            ['HIDE_ICONS' => 'Y']
        );

        return ob_get_clean() ?: '';
    }

    public static function getAdminListViewHtml($property, $value, $htmlControlName): string
    {
        $tableId = (int)($value['VALUE'] ?? 0);
        
        if ($tableId === 0) {
            return '<span style="color: #999;">Не заполнено</span>';
        }

        // Загружаем краткую информацию о таблице
        if (Loader::includeModule('grebion.tables')) {
            $tableData = TableDataTable::getById($tableId)->fetch();
            if ($tableData) {
                $rowsCount = RowTable::getList([
                    'filter' => ['TABLE_ID' => $tableId],
                    'count_total' => true,
                ])->getCount();
                return htmlspecialcharsbx($tableData['TITLE']) . ' <small style="color: #666;">(' . $rowsCount . ' строк)</small>';
            }
        }

        return '<span style="color: #ff0000;">Таблица #' . $tableId . ' не найдена</span>';
    }

    public static function convertToDb($property, $value): array
    {
        // Сохраняем ID таблицы как строку (стандарт Битрикс)
        return [
            'VALUE'       => (string)((int)($value['VALUE'] ?? 0)),
            'DESCRIPTION' => null,
        ];
    }

    public static function convertFromDb($property, $value): array
    {
        $value['VALUE'] = (int) $value['VALUE'];

        return $value;
    }

    public static function getPublicViewHtml($property, $value, $htmlControlName): string
    {
        $tableId = (int)($value['VALUE'] ?? 0);
        
        if ($tableId === 0) {
            return '';
        }

        // В публичной части показываем таблицу только для чтения
        if (Loader::includeModule('grebion.tables')) {
            $tableData = TableDataTable::getById($tableId)->fetch();
            $schemaData = null;
            
            if ($tableData) {
                $schemaData = TableSchemaTable::getById($tableData['SCHEMA_ID'])->fetch();
            }
            
            if ($tableData && $schemaData) {
                $schema = json_decode($schemaData['SCHEMA'], true);
                $rows = [];
                
                $result = RowTable::getList([
                    'filter' => ['TABLE_ID' => $tableId],
                    'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
                ]);
                
                while ($row = $result->fetch()) {
                    $rows[] = json_decode($row['DATA'], true) ?: [];
                }
                
                return self::renderPublicTable($tableData['TITLE'], $schema['columns'] ?? [], $rows);
            }
        }

        return '<span style="color: #ff0000;">Ошибка загрузки таблицы</span>';
    }

    /**
     * Рендерит таблицу для публичного просмотра.
     */
    private static function renderPublicTable(string $tableName, array $columns, array $rows): string
    {
        if (empty($columns) || empty($rows)) {
            return '<div style="color: #999; font-style: italic;">Таблица пуста</div>';
        }

        // Сортируем колонки по полю sort
        usort($columns, function($a, $b) {
            return (int)($a['sort'] ?? 0) - (int)($b['sort'] ?? 0);
        });

        $html = '<div style="margin: 15px 0;">';
        $html .= '<h4 style="margin-bottom: 10px;">' . htmlspecialcharsbx($tableName) . '</h4>';
        $html .= '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">';
        
        // Заголовки
        $html .= '<thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th style="padding: 8px; background: #f5f5f5; border: 1px solid #ddd; text-align: left;">';
            $html .= htmlspecialcharsbx($column['title']);
            $html .= '</th>';
        }
        $html .= '</tr></thead>';
        
        // Данные
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $value = $row[$column['code']] ?? '';
                $html .= '<td style="padding: 8px; border: 1px solid #ddd;">';
                
                // Форматируем значение в зависимости от типа
                if ($column['type'] === 'boolean') {
                    $html .= $value ? 'Да' : 'Нет';
                } elseif ($column['type'] === 'multiselect' && is_array($value)) {
                    $html .= htmlspecialcharsbx(implode(', ', $value));
                } elseif ($column['type'] === 'url' && $value) {
                    $html .= '<a href="' . htmlspecialcharsbx($value) . '" target="_blank">' . htmlspecialcharsbx($value) . '</a>';
                } elseif ($column['type'] === 'email' && $value) {
                    $html .= '<a href="mailto:' . htmlspecialcharsbx($value) . '">' . htmlspecialcharsbx($value) . '</a>';
                } else {
                    $html .= htmlspecialcharsbx($value);
                }
                
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
}