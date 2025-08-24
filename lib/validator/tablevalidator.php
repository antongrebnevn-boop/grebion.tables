<?php

namespace Grebion\Tables\Validator;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Grebion\Tables\Model\ColumnTable;

Loc::loadMessages(__FILE__);

class TableValidator
{
    /**
     * Валидация данных таблицы
     */
    public static function validateTableData(array $data): Result
    {
        $result = new Result();

        // Проверка обязательных полей
        if (empty($data['NAME'])) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_NAME_REQUIRED')));
        }

        // Проверка длины названия
        if (!empty($data['NAME']) && mb_strlen($data['NAME']) > 255) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_NAME_TOO_LONG')));
        }

        // Проверка символьного кода
        if (!empty($data['CODE']) && !preg_match('/^[a-z0-9_]+$/', $data['CODE'])) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_CODE_INVALID')));
        }

        return $result;
    }

    /**
     * Валидация данных колонки
     */
    public static function validateColumnData(array $data): Result
    {
        $result = new Result();

        if (empty($data['NAME'])) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_COLUMN_NAME_REQUIRED')));
        }

        if (empty($data['TYPE'])) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_COLUMN_TYPE_REQUIRED')));
        }

        $allowedTypes = array_keys(ColumnTable::getAvailableTypes());
        if (!empty($data['TYPE']) && !in_array($data['TYPE'], $allowedTypes)) {
            $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_COLUMN_TYPE_INVALID')));
        }

        return $result;
    }

    /**
     * Валидация значения ячейки
     */
    public static function validateCellValue($value, string $type, array $settings = []): Result
    {
        $result = new Result();

        switch ($type) {
            case 'integer':
                if (!is_numeric($value) || (int)$value != $value) {
                    $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_VALUE_NOT_INTEGER')));
                }
                break;

            case 'double':
                if (!is_numeric($value)) {
                    $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_VALUE_NOT_NUMERIC')));
                }
                break;

            case 'boolean':
                if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_VALUE_NOT_BOOLEAN')));
                }
                break;

            case 'datetime':
                if (!empty($value) && !strtotime($value)) {
                    $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_VALUE_NOT_DATETIME')));
                }
                break;

            case 'string':
                $maxLength = $settings['MAX_LENGTH'] ?? 255;
                if (mb_strlen($value) > $maxLength) {
                    $result->addError(new Error(Loc::getMessage('GREBION_TABLES_VALIDATOR_VALUE_TOO_LONG')));
                }
                break;
        }

        return $result;
    }
}