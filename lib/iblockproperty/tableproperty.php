<?php

namespace Grebion\Tables\IblockProperty;

use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Service\TableService;
use Grebion\Tables\Repository\TableRepository;
use Grebion\Tables\Model\TableDataTable;

Loc::loadMessages(__FILE__);

/**
 * Пользовательский тип свойства инфоблока "Таблица"
 */
class TableProperty
{
    public const USER_TYPE = 'grebion_table';
    public const RENDER_COMPONENT = 'grebion:table.selector';

    /**
     * Возвращает описание пользовательского типа свойства
     *
     * @return array
     */
    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_DESCRIPTION'),
            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetPublicViewHTML' => [__CLASS__, 'GetPublicViewHTML'],
            'GetPublicEditHTML' => [__CLASS__, 'GetPublicEditHTML'],
            'GetAdminListViewHTML' => [__CLASS__, 'GetAdminListViewHTML'],
            'GetAdminFilterHTML' => [__CLASS__, 'GetAdminFilterHTML'],
            'GetSettingsHTML' => [__CLASS__, 'GetSettingsHTML'],
            'PrepareSettings' => [__CLASS__, 'PrepareSettings'],
            'CheckFields' => [__CLASS__, 'CheckFields'],
            'ConvertToDB' => [__CLASS__, 'ConvertToDB'],
            'ConvertFromDB' => [__CLASS__, 'ConvertFromDB'],
        ];
    }

    /**
     * Возвращает HTML для редактирования свойства в админке
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPropertyFieldHtml(array $arProperty, array $value, array $strHTMLControlName): string
    {
        global $APPLICATION;
        
        if (\CModule::IncludeModule('grebion.tables')) {
            ob_start();
            $APPLICATION->IncludeComponent(
                self::RENDER_COMPONENT,
                '',
                [
                    'PROPERTY' => $arProperty,
                    'VALUE' => $value,
                    'HTML_CONTROL_NAME' => $strHTMLControlName,
                ],
                false
            );
            return ob_get_clean();
        }
        
        return self::GetSimpleSelectHTML($arProperty, $value, $strHTMLControlName);
    }
    
    /**
     * Простой HTML селект как fallback
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    private static function GetSimpleSelectHTML(array $arProperty, array $value, array $strHTMLControlName): string
    {
        $tableRepository = new TableRepository();
        $tables = $tableRepository->getList();
        $currentValue = $value['VALUE'] ?? '';
        
        $html = '<select name="' . htmlspecialcharsbx($strHTMLControlName['VALUE']) . '">';
        $html .= '<option value="">' . Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_SELECT_TABLE') . '</option>';
        
        foreach ($tables as $table) {
            $selected = ($currentValue == $table->getId()) ? ' selected' : '';
            $html .= '<option value="' . $table->getId() . '"' . $selected . '>' . htmlspecialcharsbx($table->getName()) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Возвращает HTML для публичного просмотра
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPublicViewHTML(array $arProperty, array $value, array $strHTMLControlName): string
    {
        if (empty($value['VALUE'])) {
            return '';
        }

        $tableId = (int)$value['VALUE'];
        $tableRepository = new TableRepository();
        $table = $tableRepository->getById($tableId);
        
        if (!$table || !is_object($table)) {
            return '';
        }

        return '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $tableId . '" target="_blank">' . 
               htmlspecialcharsbx($table->getName()) . '</a>';
    }

    /**
     * Возвращает HTML для публичного редактирования
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPublicEditHTML(array $arProperty, array $value, array $strHTMLControlName): string
    {
        global $APPLICATION;
        
        if (\CModule::IncludeModule('grebion.tables')) {
            ob_start();
            $APPLICATION->IncludeComponent(
                self::RENDER_COMPONENT,
                '',
                [
                    'PROPERTY' => $arProperty,
                    'VALUE' => $value,
                    'HTML_CONTROL_NAME' => $strHTMLControlName,
                ],
                false
            );
            return ob_get_clean();
        }
        
        return self::GetSimpleSelectHTML($arProperty, $value, $strHTMLControlName);
    }

    /**
     * Возвращает HTML для отображения в списке админки
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetAdminListViewHTML(array $arProperty, array $value, array $strHTMLControlName): string
    {
        if (empty($value['VALUE'])) {
            return '';
        }

        $tableId = (int)$value['VALUE'];
        $tableRepository = new TableRepository();
        $table = $tableRepository->getById($tableId);
        
        if (!$table || !is_object($table)) {
            return Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_TABLE_NOT_FOUND');
        }

        return htmlspecialcharsbx($table->getName());
    }

    /**
     * Возвращает HTML для фильтра в админке
     *
     * @param array $arProperty
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetAdminFilterHTML(array $arProperty, array $strHTMLControlName): string
    {
        $tableRepository = new TableRepository();
        $tables = $tableRepository->getList();
        
        $html = '<select name="' . htmlspecialcharsbx($strHTMLControlName['VALUE']) . '">';
        $html .= '<option value="">' . Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_SELECT_TABLE') . '</option>';
        
        foreach ($tables as $table) {
            $html .= '<option value="' . $table->getId() . '">' . htmlspecialcharsbx($table->getName()) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Возвращает HTML для настроек свойства
     *
     * @param array $arProperty
     * @param array $strHTMLControlName
     * @param array $arPropertyFields
     * @return string
     */
    public static function GetSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields): string
    {
        global $APPLICATION;
        
        $arPropertyFields = [
            'HIDE' => ['ROW_COUNT', 'COL_COUNT', 'MULTIPLE_CNT']
        ];
        
        ob_start();
        $APPLICATION->IncludeComponent(
            'grebion:table.settings',
            '',
            [
                'PROPERTY_CODE' => $arProperty['CODE'] ?? '',
                'IBLOCK_ID' => $arProperty['IBLOCK_ID'] ?? 0,
                'PROPERTY' => $arProperty,
                'HTML_CONTROL' => $strHTMLControlName,
            ],
            false
        );
        
        return ob_get_clean() ?: '';
    }

    /**
     * Подготавливает настройки свойства
     *
     * @param array $arFields
     * @return array
     */
    public static function PrepareSettings(array $arFields): array
    {
        return [
            'DEFAULT_VALUE' => $arFields['DEFAULT_VALUE'] ?? ''
        ];
    }

    /**
     * Проверяет корректность значения
     *
     * @param array $arProperty
     * @param array $value
     * @return array
     */
    public static function CheckFields(array $arProperty, array $value): array
    {
        $arResult = [];
        
        if (!empty($value['VALUE'])) {
            $tableId = (int)$value['VALUE'];
            
            if ($tableId <= 0) {
                $arResult[] = [
                    'id' => $arProperty['ID'],
                    'text' => Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_INVALID_TABLE_ID')
                ];
            } else {
                $tableRepository = new TableRepository();
                $table = $tableRepository->getById($tableId);
                
                if (!$table || !is_object($table)) {
                    $arResult[] = [
                        'id' => $arProperty['ID'],
                        'text' => Loc::getMessage('GREBION_TABLES_IBLOCK_PROPERTY_TABLE_NOT_EXISTS')
                    ];
                } else {
                    $arResult[] = [
                        'id' => $arProperty['ID'],
                        'text' => htmlspecialcharsbx($table->getName())
                    ];
                }
            }
        }
        
        return $arResult;
    }

    /**
     * Конвертирует значение для сохранения в БД
     *
     * @param array $arProperty
     * @param array $value
     * @return array
     */
    public static function ConvertToDB(array $arProperty, array $value): array
    {
        if (!empty($value['VALUE'])) {
            // Сохраняем только ID таблицы
            $value['VALUE'] = (int)$value['VALUE'];
        }
        
        return $value;
    }

    /**
     * Конвертирует значение из БД для отображения
     *
     * @param array $arProperty
     * @param array $value
     * @return array
     */
    public static function ConvertFromDB(array $arProperty, array $value): array
    {
        if (!empty($value['VALUE'])) {
            $tableId = (int)$value['VALUE'];
            if ($tableId > 0) {
                // Проверяем существование таблицы
                $tableRepository = new TableRepository();
                $table = $tableRepository->getById($tableId);
                if ($table) {
                    $value['VALUE'] = $tableId;
                }
            }
        }
        
        return $value;
    }
}