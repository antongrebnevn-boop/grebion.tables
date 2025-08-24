<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Grebion\Tables\Model\ColumnTable;
use Grebion\Tables\Model\RowTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Error;
use Bitrix\Main\Result;

/**
 * Менеджер для работы с данными таблиц
 * Предоставляет методы для валидации, форматирования и обработки данных
 */
class DataManager
{
    /**
     * Валидировать данные строки согласно типам колонок
     *
     * @param int $tableId ID таблицы
     * @param array $data Данные для валидации
     * @return Result
     */
    public static function validateRowData(int $tableId, array $data): Result
    {
        $result = new Result();
        $validatedData = [];
        
        try {
            // Получаем колонки таблицы
            $columnsResult = ColumnTable::getByTableId($tableId);
            $columns = [];
            
            while ($column = $columnsResult->fetch()) {
                $columns[$column['CODE']] = $column;
            }
            
            // Валидируем каждое поле
            foreach ($data as $columnCode => $value) {
                if (!isset($columns[$columnCode])) {
                    $result->addError(new Error("Колонка '{$columnCode}' не найдена в таблице"));
                    continue;
                }
                
                $column = $columns[$columnCode];
                $validationResult = static::validateFieldValue($value, $column['TYPE'], $column['SETTINGS']);
                
                if (!$validationResult->isSuccess()) {
                    foreach ($validationResult->getErrors() as $error) {
                        $result->addError(new Error("Колонка '{$columnCode}': " . $error->getMessage()));
                    }
                } else {
                    $validatedData[$columnCode] = $validationResult->getData()['value'];
                }
            }
            
            if ($result->isSuccess()) {
                $result->setData(['validated_data' => $validatedData]);
            }
            
        } catch (ArgumentException | SystemException $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }

    /**
     * Валидировать значение поля по типу
     */
    public static function validateFieldValue($value, string $type, array $settings = []): Result
    {
        $result = new Result();
        $validatedValue = $value;
        
        switch ($type) {
            case ColumnTable::TYPE_STRING:
                $validatedValue = (string)$value;
                
                // Проверяем максимальную длину
                if (isset($settings['max_length']) && mb_strlen($validatedValue) > $settings['max_length']) {
                    $result->addError(new Error("Превышена максимальная длина ({$settings['max_length']} символов)"));
                }
                break;
                
            case ColumnTable::TYPE_INTEGER:
                if (!is_numeric($value)) {
                    $result->addError(new Error('Значение должно быть числом'));
                } else {
                    $validatedValue = (int)$value;
                    
                    // Проверяем диапазон
                    if (isset($settings['min_value']) && $validatedValue < $settings['min_value']) {
                        $result->addError(new Error("Значение меньше минимального ({$settings['min_value']})"));
                    }
                    if (isset($settings['max_value']) && $validatedValue > $settings['max_value']) {
                        $result->addError(new Error("Значение больше максимального ({$settings['max_value']})"));
                    }
                }
                break;
                
            case ColumnTable::TYPE_FLOAT:
                if (!is_numeric($value)) {
                    $result->addError(new Error('Значение должно быть числом'));
                } else {
                    $validatedValue = (float)$value;
                    
                    // Проверяем диапазон
                    if (isset($settings['min_value']) && $validatedValue < $settings['min_value']) {
                        $result->addError(new Error("Значение меньше минимального ({$settings['min_value']})"));
                    }
                    if (isset($settings['max_value']) && $validatedValue > $settings['max_value']) {
                        $result->addError(new Error("Значение больше максимального ({$settings['max_value']})"));
                    }
                }
                break;
                
            case ColumnTable::TYPE_BOOLEAN:
                $validatedValue = (bool)$value;
                break;
                
            case ColumnTable::TYPE_DATE:
                if (is_string($value)) {
                    try {
                        $validatedValue = new DateTime($value);
                    } catch (\Exception $e) {
                        $result->addError(new Error('Неверный формат даты'));
                    }
                } elseif (!($value instanceof DateTime)) {
                    $result->addError(new Error('Значение должно быть датой'));
                }
                break;
                
            case ColumnTable::TYPE_DATETIME:
                if (is_string($value)) {
                    try {
                        $validatedValue = new DateTime($value);
                    } catch (\Exception $e) {
                        $result->addError(new Error('Неверный формат даты и времени'));
                    }
                } elseif (!($value instanceof DateTime)) {
                    $result->addError(new Error('Значение должно быть датой и временем'));
                }
                break;
                
            case ColumnTable::TYPE_SELECT:
                if (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_keys($settings['options']);
                    if (!in_array($value, $validOptions)) {
                        $result->addError(new Error('Недопустимое значение для списка'));
                    }
                }
                break;
                
            case ColumnTable::TYPE_MULTISELECT:
                if (!is_array($value)) {
                    $result->addError(new Error('Значение должно быть массивом'));
                } elseif (isset($settings['options']) && is_array($settings['options'])) {
                    $validOptions = array_keys($settings['options']);
                    foreach ($value as $item) {
                        if (!in_array($item, $validOptions)) {
                            $result->addError(new Error("Недопустимое значение '{$item}' для множественного списка"));
                            break;
                        }
                    }
                }
                break;
                
            case ColumnTable::TYPE_FILE:
                // Для файлов проверяем ID файла
                if (!empty($value) && !is_numeric($value)) {
                    $result->addError(new Error('ID файла должен быть числом'));
                } else {
                    $validatedValue = (int)$value;
                }
                break;
                
            case ColumnTable::TYPE_JSON:
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $result->addError(new Error('Неверный формат JSON'));
                    } else {
                        $validatedValue = $decoded;
                    }
                } elseif (!is_array($value) && !is_object($value)) {
                    $result->addError(new Error('Значение должно быть JSON'));
                }
                break;
        }
        
        // Проверяем обязательность поля
        if (isset($settings['required']) && $settings['required'] && empty($validatedValue)) {
            $result->addError(new Error('Поле обязательно для заполнения'));
        }
        
        if ($result->isSuccess()) {
            $result->setData(['value' => $validatedValue]);
        }
        
        return $result;
    }

    /**
     * Форматировать значение для отображения
     */
    public static function formatValue($value, string $type, array $settings = []): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        switch ($type) {
            case ColumnTable::TYPE_STRING:
                return (string)$value;
                
            case ColumnTable::TYPE_INTEGER:
                return number_format((int)$value, 0, ',', ' ');
                
            case ColumnTable::TYPE_FLOAT:
                $decimals = $settings['decimals'] ?? 2;
                return number_format((float)$value, $decimals, ',', ' ');
                
            case ColumnTable::TYPE_BOOLEAN:
                return $value ? 'Да' : 'Нет';
                
            case ColumnTable::TYPE_DATE:
                if ($value instanceof DateTime) {
                    return $value->format('d.m.Y');
                }
                return (string)$value;
                
            case ColumnTable::TYPE_DATETIME:
                if ($value instanceof DateTime) {
                    return $value->format('d.m.Y H:i:s');
                }
                return (string)$value;
                
            case ColumnTable::TYPE_SELECT:
                if (isset($settings['options'][$value])) {
                    return $settings['options'][$value];
                }
                return (string)$value;
                
            case ColumnTable::TYPE_MULTISELECT:
                if (is_array($value) && isset($settings['options'])) {
                    $formatted = [];
                    foreach ($value as $item) {
                        $formatted[] = $settings['options'][$item] ?? $item;
                    }
                    return implode(', ', $formatted);
                }
                return is_array($value) ? implode(', ', $value) : (string)$value;
                
            case ColumnTable::TYPE_FILE:
                // Для файлов возвращаем ссылку или имя файла
                if (is_numeric($value) && $value > 0) {
                    return "Файл #{$value}";
                }
                return '';
                
            case ColumnTable::TYPE_JSON:
                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }
                return (string)$value;
                
            default:
                return (string)$value;
        }
    }

    /**
     * Получить значение ячейки с форматированием
     */
    public static function getCellValueFormatted(int $rowId, int $columnId): array
    {
        $column = ColumnTable::findById($columnId);
        if (!$column) {
            return ['value' => null, 'formatted' => ''];
        }
        
        $value = RowTable::getCellValue($rowId, $column['CODE']);
        $formatted = static::formatValue($value, $column['TYPE'], $column['SETTINGS']);
        
        return [
            'value' => $value,
            'formatted' => $formatted,
            'type' => $column['TYPE']
        ];
    }

    /**
     * Массовое обновление данных с валидацией
     */
    public static function bulkUpdateRows(int $tableId, array $rowsData): Result
    {
        $result = new Result();
        $successCount = 0;
        $errors = [];
        
        foreach ($rowsData as $rowId => $data) {
            $validationResult = static::validateRowData($tableId, $data);
            
            if ($validationResult->isSuccess()) {
                $validatedData = $validationResult->getData()['validated_data'];
                $updateResult = RowTable::updateRow((int)$rowId, $validatedData);
                
                if ($updateResult->isSuccess()) {
                    $successCount++;
                } else {
                    $errors[] = "Строка {$rowId}: " . implode(', ', array_map(fn($e) => $e->getMessage(), $updateResult->getErrors()));
                }
            } else {
                $errors[] = "Строка {$rowId}: " . implode(', ', array_map(fn($e) => $e->getMessage(), $validationResult->getErrors()));
            }
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $result->addError(new Error($error));
            }
        }
        
        $result->setData([
            'success_count' => $successCount,
            'total_count' => count($rowsData),
            'error_count' => count($errors)
        ]);
        
        return $result;
    }

    /**
     * Получить схему валидации для таблицы
     */
    public static function getValidationSchema(int $tableId): array
    {
        $schema = [];
        
        try {
            $columnsResult = ColumnTable::getByTableId($tableId);
            while ($column = $columnsResult->fetch()) {
                $schema[$column['CODE']] = [
                    'type' => $column['TYPE'],
                    'title' => $column['TITLE'],
                    'settings' => $column['SETTINGS'],
                    'required' => $column['SETTINGS']['required'] ?? false
                ];
            }
        } catch (ArgumentException | SystemException $e) {
            // Игнорируем ошибки
        }
        
        return $schema;
    }

    /**
     * Конвертировать данные из одного типа в другой
     */
    public static function convertValue($value, string $fromType, string $toType): Result
    {
        $result = new Result();
        $convertedValue = $value;
        
        try {
            // Простые конвертации
            if ($fromType === $toType) {
                $result->setData(['value' => $value]);
                return $result;
            }
            
            switch ($toType) {
                case ColumnTable::TYPE_STRING:
                    $convertedValue = (string)$value;
                    break;
                    
                case ColumnTable::TYPE_INTEGER:
                    if (is_numeric($value)) {
                        $convertedValue = (int)$value;
                    } else {
                        $result->addError(new Error('Невозможно конвертировать в число'));
                    }
                    break;
                    
                case ColumnTable::TYPE_FLOAT:
                    if (is_numeric($value)) {
                        $convertedValue = (float)$value;
                    } else {
                        $result->addError(new Error('Невозможно конвертировать в дробное число'));
                    }
                    break;
                    
                case ColumnTable::TYPE_BOOLEAN:
                    $convertedValue = (bool)$value;
                    break;
                    
                case ColumnTable::TYPE_JSON:
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $convertedValue = $decoded;
                        } else {
                            $convertedValue = ['value' => $value];
                        }
                    } elseif (is_array($value) || is_object($value)) {
                        $convertedValue = $value;
                    } else {
                        $convertedValue = ['value' => $value];
                    }
                    break;
                    
                default:
                    $convertedValue = (string)$value;
            }
            
            if ($result->isSuccess()) {
                $result->setData(['value' => $convertedValue]);
            }
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка конвертации: ' . $e->getMessage()));
        }
        
        return $result;
    }
}