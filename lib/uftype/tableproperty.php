<?php

namespace Grebion\Tables\Uftype;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserField\Types\BaseType;
use Grebion\Tables\Service\TableService;
use Grebion\Tables\Repository\TableRepository;

Loc::loadMessages(__FILE__);

/**
 * Пользовательский тип "Таблица" для привязки таблиц к любым сущностям Битрикс
 */
class TableProperty extends BaseType
{
    public const USER_TYPE_ID = 'grebion_table';
    public const RENDER_COMPONENT = 'grebion:table.selector';

    /**
     * Возвращает описание пользовательского типа
     *
     * @return array
     */
    public static function getUserTypeDescription(): array
    {
        return [
            'USER_TYPE_ID' => static::USER_TYPE_ID,
            'CLASS_NAME' => static::class,
            'DESCRIPTION' => Loc::getMessage('GREBION_TABLES_UF_TYPE_DESCRIPTION'),
            'BASE_TYPE' => 'int',
            'EDIT_CALLBACK' => [static::class, 'getEditFormHtml'],
            'VIEW_CALLBACK' => [static::class, 'getViewHtml'],
        ];
    }

    /**
     * Возвращает HTML для редактирования поля
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getEditFormHtml(array $userField, ?array $additionalParameters = null): string
    {
        $value = $additionalParameters['VALUE'] ?? '';
        $fieldName = $additionalParameters['NAME'] ?? $userField['FIELD_NAME'];
        
        // Получаем список доступных таблиц
        $tableRepository = new TableRepository();
        $tables = $tableRepository->getList();
        
        $html = '<select name="' . htmlspecialchars($fieldName) . '" class="grebion-table-selector">';
        $html .= '<option value="">' . Loc::getMessage('GREBION_TABLES_UF_SELECT_TABLE') . '</option>';
        
        foreach ($tables as $table) {
            $selected = ($value == $table['ID']) ? ' selected' : '';
            $html .= '<option value="' . $table['ID'] . '"' . $selected . '>' . htmlspecialchars($table['NAME']) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Возвращает HTML для просмотра поля
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getViewHtml(array $userField, ?array $additionalParameters = null): string
    {
        $value = $additionalParameters['VALUE'] ?? '';
        
        if (empty($value)) {
            return '';
        }
        
        // Получаем информацию о таблице
        $tableRepository = new TableRepository();
        $table = $tableRepository->getById((int)$value);
        
        if (!$table) {
            return Loc::getMessage('GREBION_TABLES_UF_TABLE_NOT_FOUND');
        }
        
        return '<a href="/bitrix/admin/grebion_tables_table_edit.php?ID=' . $table['ID'] . '">' . htmlspecialchars($table['NAME']) . '</a>';
    }

    /**
     * Валидация значения перед сохранением
     *
     * @param array $userField
     * @param mixed $value
     * @return mixed
     */
    public static function onBeforeSave(array $userField, $value)
    {
        // Пустое значение всегда валидно
        if (empty($value)) {
            return $value;
        }
        
        // Проверяем, что значение числовое (строго integer или numeric string)
        if (!is_numeric($value) || !ctype_digit((string)$value)) {
            return [Loc::getMessage('GREBION_TABLES_UF_INVALID_VALUE')];
        }
        
        // Проверяем существование таблицы
        $tableRepository = new TableRepository();
        $table = $tableRepository->getById((int)$value);
        
        if (!$table) {
            return [Loc::getMessage('GREBION_TABLES_UF_TABLE_NOT_EXISTS')];
        }
        
        return (int)$value;
    }

    /**
     * Обработка удаления связанной записи
     *
     * @param array $userField
     * @param mixed $value
     * @return bool
     */
    public static function onDelete(array $userField, $value): bool
    {
        // При удалении записи с UF-полем типа "таблица"
        // можно добавить логику каскадного удаления или очистки связей
        
        if (!empty($value)) {
            $tableService = new TableService();
            // Здесь можно добавить логику обработки удаления
            // например, удаление связанных записей или уведомления
        }
        
        return true;
    }

    /**
     * Получение значения для поиска
     *
     * @param array $userField
     * @param mixed $value
     * @return string
     */
    public static function getSearchContent(array $userField, $value): string
    {
        if (empty($value)) {
            return '';
        }
        
        $tableRepository = new TableRepository();
        $table = $tableRepository->getById((int)$value);
        
        return $table ? $table['NAME'] : '';
    }

    /**
     * Подготовка значения для сохранения в БД
     *
     * @param array $userField
     * @param mixed $value
     * @return mixed
     */
    public static function prepareSave(array $userField, $value)
    {
        return empty($value) ? null : (int)$value;
    }

    /**
     * Подготовка значения для вывода
     *
     * @param array $userField
     * @param mixed $value
     * @return mixed
     */
    public static function prepareView(array $userField, $value)
    {
        return empty($value) ? null : (int)$value;
    }

    /**
     * Возвращает настройки поля для административной части
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @param array $varsFromForm
     * @return string
     */
    public static function getSettingsHtml($userField, ?array $additionalParameters = null, $varsFromForm = []): string
    {
        return '<p>' . Loc::getMessage('GREBION_TABLES_UF_SETTINGS_DESC') . '</p>';
    }

    /**
     * Возвращает список для фильтрации
     *
     * @param array $userField
     * @return array
     */
    public static function getFilterData(array $userField): array
    {
        $tableRepository = new TableRepository();
        $tables = $tableRepository->getList();
        
        $result = [];
        foreach ($tables as $table) {
            $result[$table['ID']] = $table['NAME'];
        }
        
        return $result;
    }

    /**
     * Возвращает HTML для фильтра в списке
     *
     * @param array $userField
     * @param array $additionalParameters
     * @return string
     */
    public static function getFilterHtml(array $userField, ?array $additionalParameters): string
    {
        $value = $additionalParameters['VALUE'] ?? '';
        $fieldName = $additionalParameters['NAME'] ?? $userField['FIELD_NAME'];
        
        $tableRepository = new TableRepository();
        $tables = $tableRepository->getList();
        
        $html = '<select name="' . htmlspecialchars($fieldName) . '">';
        $html .= '<option value="">' . Loc::getMessage('GREBION_TABLES_UF_ALL_TABLES') . '</option>';
        
        foreach ($tables as $table) {
            $selected = ($value == $table['ID']) ? ' selected' : '';
            $html .= '<option value="' . $table['ID'] . '"' . $selected . '>' . htmlspecialchars($table['NAME']) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Возвращает тип колонки в базе данных
     *
     * @return string
     */
    public static function getDbColumnType(): string
    {
        return 'int';
    }

    /**
     * Возвращает HTML для публичного редактирования
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getPublicEdit(array $userField, ?array $additionalParameters = []): string
    {
        global $APPLICATION;
        
        $additionalParameters['mode'] = 'edit';
        
        ob_start();
        $APPLICATION->IncludeComponent(
            'grebion:table.selector',
            '',
            [
                'userField' => $userField,
                'additionalParameters' => $additionalParameters
            ],
            false
        );
        return ob_get_clean() ?: '';
    }

    /**
     * Возвращает HTML для публичного просмотра
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function getPublicView(array $userField, ?array $additionalParameters = []): string
    {
        global $APPLICATION;
        
        $additionalParameters['mode'] = 'main.view';
        
        ob_start();
        $APPLICATION->IncludeComponent(
            'grebion:table.selector',
            '',
            [
                'userField' => $userField,
                'additionalParameters' => $additionalParameters
            ],
            false
        );
        return ob_get_clean() ?: '';
    }
}