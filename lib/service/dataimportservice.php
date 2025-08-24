<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Grebion\Tables\Repository\RowRepository;
use Grebion\Tables\Repository\ColumnRepository;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Application;
use Bitrix\Main\IO\File;
use Bitrix\Main\Web\Json;

/**
 * Сервис для импорта данных в таблицы
 * Поддерживает импорт из CSV и XLSX файлов
 */
class DataImportService
{
    private RowRepository $rowRepository;
    private ColumnRepository $columnRepository;
    
    public function __construct(
        ?RowRepository $rowRepository = null,
        ?ColumnRepository $columnRepository = null
    ) {
        $this->rowRepository = $rowRepository ?? new RowRepository();
        $this->columnRepository = $columnRepository ?? new ColumnRepository();
    }
    
    /**
     * Импортировать данные из CSV файла
     *
     * @param int $tableId ID таблицы
     * @param string $filePath Путь к CSV файлу
     * @param array $options Опции импорта (delimiter, enclosure, escape, encoding)
     * @return Result
     */
    public function importFromCsv(int $tableId, string $filePath, array $options = []): Result
    {
        $result = new Result();
        
        try {
            if (!File::isFileExists($filePath)) {
                $result->addError(new Error('Файл не найден: ' . $filePath));
                return $result;
            }
            
            $delimiter = $options['delimiter'] ?? ';';
            $enclosure = $options['enclosure'] ?? '"';
            $escape = $options['escape'] ?? '\\';
            $encoding = $options['encoding'] ?? 'UTF-8';
            $hasHeader = $options['has_header'] ?? true;
            
            // Получаем колонки таблицы
            $columnsResult = $this->getTableColumns($tableId);
            if (!$columnsResult->isSuccess()) {
                $result->addErrors($columnsResult->getErrors());
                return $result;
            }
            
            $columns = $columnsResult->getData();
            $columnCodes = array_column($columns, 'CODE');
            
            // Читаем CSV файл
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                $result->addError(new Error('Не удалось открыть файл для чтения'));
                return $result;
            }
            
            $rows = [];
            $lineNumber = 0;
            $headerMapping = [];
            
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                $lineNumber++;
                
                // Конвертируем кодировку если нужно
                if ($encoding !== 'UTF-8') {
                    $data = array_map(function($item) use ($encoding) {
                        return mb_convert_encoding($item, 'UTF-8', $encoding);
                    }, $data);
                }
                
                // Первая строка - заголовки
                if ($lineNumber === 1 && $hasHeader) {
                    $headerMapping = $this->createHeaderMapping($data, $columnCodes);
                    continue;
                }
                
                // Преобразуем строку в данные для таблицы
                $rowData = $this->mapCsvRowToTableData($data, $headerMapping, $columns);
                if (!empty($rowData)) {
                    $rows[] = [
                        'TABLE_ID' => $tableId,
                        'DATA' => Json::encode($rowData),
                        'SORT' => $lineNumber * 100,
                    ];
                }
            }
            
            fclose($handle);
            
            // Массовая вставка данных
            $importResult = $this->bulkInsert($rows);
            if (!$importResult->isSuccess()) {
                $result->addErrors($importResult->getErrors());
                return $result;
            }
            
            $result->setData([
                'imported_rows' => count($rows),
                'total_lines' => $lineNumber,
            ]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка импорта CSV: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Импортировать данные из XLSX файла
     *
     * @param int $tableId ID таблицы
     * @param string $filePath Путь к XLSX файлу
     * @param array $options Опции импорта (sheet_index, has_header)
     * @return Result
     */
    public function importFromXlsx(int $tableId, string $filePath, array $options = []): Result
    {
        $result = new Result();
        
        try {
            if (!File::isFileExists($filePath)) {
                $result->addError(new Error('Файл не найден: ' . $filePath));
                return $result;
            }
            
            // Проверяем наличие PhpSpreadsheet
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                $result->addError(new Error('Для работы с XLSX файлами требуется библиотека PhpSpreadsheet'));
                return $result;
            }
            
            $sheetIndex = $options['sheet_index'] ?? 0;
            $hasHeader = $options['has_header'] ?? true;
            
            // Получаем колонки таблицы
            $columnsResult = $this->getTableColumns($tableId);
            if (!$columnsResult->isSuccess()) {
                $result->addErrors($columnsResult->getErrors());
                return $result;
            }
            
            $columns = $columnsResult->getData();
            $columnCodes = array_column($columns, 'CODE');
            
            // Читаем XLSX файл
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheet($sheetIndex);
            
            $rows = [];
            $headerMapping = [];
            $rowNumber = 0;
            
            foreach ($worksheet->getRowIterator() as $row) {
                $rowNumber++;
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = $cell->getCalculatedValue();
                }
                
                // Первая строка - заголовки
                if ($rowNumber === 1 && $hasHeader) {
                    $headerMapping = $this->createHeaderMapping($data, $columnCodes);
                    continue;
                }
                
                // Преобразуем строку в данные для таблицы
                $rowData = $this->mapCsvRowToTableData($data, $headerMapping, $columns);
                if (!empty($rowData)) {
                    $rows[] = [
                        'TABLE_ID' => $tableId,
                        'DATA' => Json::encode($rowData),
                        'SORT' => $rowNumber * 100,
                    ];
                }
            }
            
            // Массовая вставка данных
            $importResult = $this->bulkInsert($rows);
            if (!$importResult->isSuccess()) {
                $result->addErrors($importResult->getErrors());
                return $result;
            }
            
            $result->setData([
                'imported_rows' => count($rows),
                'total_lines' => $rowNumber,
            ]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка импорта XLSX: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Массовая вставка строк в таблицу
     *
     * @param array $rows Массив строк для вставки
     * @return Result
     */
    public function bulkInsert(array $rows): Result
    {
        $result = new Result();
        
        if (empty($rows)) {
            $result->setData(['inserted_count' => 0]);
            return $result;
        }
        
        try {
            $connection = Application::getConnection();
            $connection->startTransaction();
            
            $insertedCount = 0;
            
            foreach ($rows as $rowData) {
                $insertResult = $this->rowRepository->save($rowData);
                if ($insertResult->isSuccess()) {
                    $insertedCount++;
                } else {
                    // Логируем ошибку, но продолжаем импорт
                    foreach ($insertResult->getErrors() as $error) {
                        $result->addError(new Error('Ошибка вставки строки: ' . $error->getMessage()));
                    }
                }
            }
            
            $connection->commitTransaction();
            $result->setData(['inserted_count' => $insertedCount]);
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error('Ошибка массовой вставки: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Экспортировать данные таблицы в CSV
     *
     * @param int $tableId ID таблицы
     * @param string $filePath Путь для сохранения файла
     * @param array $options Опции экспорта (delimiter, enclosure, encoding)
     * @return Result
     */
    public function exportToCsv(int $tableId, string $filePath, array $options = []): Result
    {
        $result = new Result();
        
        try {
            $delimiter = $options['delimiter'] ?? ';';
            $enclosure = $options['enclosure'] ?? '"';
            $encoding = $options['encoding'] ?? 'UTF-8';
            $includeHeader = $options['include_header'] ?? true;
            
            // Получаем колонки таблицы
            $columnsResult = $this->getTableColumns($tableId);
            if (!$columnsResult->isSuccess()) {
                $result->addErrors($columnsResult->getErrors());
                return $result;
            }
            
            $columns = $columnsResult->getData();
            
            // Открываем файл для записи
            $handle = fopen($filePath, 'w');
            if (!$handle) {
                $result->addError(new Error('Не удалось создать файл для записи'));
                return $result;
            }
            
            // Записываем заголовки
            if ($includeHeader) {
                $headers = array_column($columns, 'TITLE');
                if ($encoding !== 'UTF-8') {
                    $headers = array_map(function($item) use ($encoding) {
                        return mb_convert_encoding($item, $encoding, 'UTF-8');
                    }, $headers);
                }
                fputcsv($handle, $headers, $delimiter, $enclosure);
            }
            
            // Получаем и записываем данные
            $rowsResult = $this->rowRepository->getList(
                ['TABLE_ID' => $tableId],
                ['SORT' => 'ASC', 'ID' => 'ASC']
            );
            
            $exportedRows = 0;
            while ($row = $rowsResult->fetch()) {
                $data = Json::decode($row['DATA']) ?: [];
                $csvRow = [];
                
                foreach ($columns as $column) {
                    $value = $data[$column['CODE']] ?? '';
                    $csvRow[] = (string)$value;
                }
                
                if ($encoding !== 'UTF-8') {
                    $csvRow = array_map(function($item) use ($encoding) {
                        return mb_convert_encoding($item, $encoding, 'UTF-8');
                    }, $csvRow);
                }
                
                fputcsv($handle, $csvRow, $delimiter, $enclosure);
                $exportedRows++;
            }
            
            fclose($handle);
            
            $result->setData([
                'exported_rows' => $exportedRows,
                'file_path' => $filePath,
            ]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка экспорта CSV: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Получить колонки таблицы
     *
     * @param int $tableId ID таблицы
     * @return Result
     */
    private function getTableColumns(int $tableId): Result
    {
        $result = new Result();
        
        $columnsResult = $this->columnRepository->getList(
            ['TABLE_ID' => $tableId],
            ['SORT' => 'ASC', 'ID' => 'ASC']
        );
        
        $columns = [];
        while ($column = $columnsResult->fetch()) {
            $columns[] = $column;
        }
        
        $result->setData($columns);
        return $result;
    }
    
    /**
     * Создать маппинг заголовков CSV к кодам колонок
     *
     * @param array $headers Заголовки из CSV
     * @param array $columnCodes Коды колонок таблицы
     * @return array
     */
    private function createHeaderMapping(array $headers, array $columnCodes): array
    {
        $mapping = [];
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            // Ищем точное совпадение
            if (in_array($header, $columnCodes)) {
                $mapping[$index] = $header;
                continue;
            }
            
            // Ищем похожее совпадение (без учета регистра)
            foreach ($columnCodes as $code) {
                if (strcasecmp($header, $code) === 0) {
                    $mapping[$index] = $code;
                    break;
                }
            }
        }
        
        return $mapping;
    }
    
    /**
     * Преобразовать строку CSV в данные для таблицы
     *
     * @param array $csvRow Строка из CSV
     * @param array $headerMapping Маппинг заголовков
     * @param array $columns Колонки таблицы
     * @return array
     */
    private function mapCsvRowToTableData(array $csvRow, array $headerMapping, array $columns): array
    {
        $data = [];
        
        foreach ($headerMapping as $csvIndex => $columnCode) {
            if (isset($csvRow[$csvIndex])) {
                $value = trim($csvRow[$csvIndex]);
                
                // Находим колонку по коду
                $column = null;
                foreach ($columns as $col) {
                    if ($col['CODE'] === $columnCode) {
                        $column = $col;
                        break;
                    }
                }
                
                if ($column) {
                    // Преобразуем значение согласно типу колонки
                    $data[$columnCode] = $this->convertValueByType($value, $column['TYPE']);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Преобразовать значение согласно типу колонки
     *
     * @param string $value Значение
     * @param string $type Тип колонки
     * @return mixed
     */
    private function convertValueByType(string $value, string $type)
    {
        switch ($type) {
            case 'integer':
                return (int)$value;
            
            case 'double':
                return (float)$value;
            
            case 'boolean':
                return in_array(strtolower($value), ['1', 'true', 'да', 'yes', 'y']);
            
            case 'date':
                if (empty($value)) {
                    return null;
                }
                try {
                    return new \Bitrix\Main\Type\Date($value);
                } catch (\Exception $e) {
                    return $value; // Возвращаем как строку если не удалось преобразовать
                }
            
            case 'datetime':
                if (empty($value)) {
                    return null;
                }
                try {
                    return new \Bitrix\Main\Type\DateTime($value);
                } catch (\Exception $e) {
                    return $value; // Возвращаем как строку если не удалось преобразовать
                }
            
            default:
                return $value;
        }
    }
}