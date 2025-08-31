<?php

namespace Grebion\Tables\IblockProperty;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

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
        return '';
    }

    public static function getAdminListViewHtml($property, $value, $htmlControlName): string
    {
        return (string) $value['VALUE'];
    }

    public static function convertToDb($property, $value): array
    {
        return [
            'VALUE'       => (int) ($value['VALUE'] ?? 0),
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
        return (string) $value['VALUE'];
    }
}