<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Grebion\Tables\Model\RowTable;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Error;
use Bitrix\Main\Result as MainResult;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

/**
 * Репозиторий для работы со строками таблиц
 */
class RowRepository extends AbstractRepository
{
    
    /**
     * {@inheritdoc}
     */
    protected function getTableClass(): string
    {
        return RowTable::class;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCachePrefix(): string
    {
        return 'row';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCacheTags(?int $id = null, array $data = []): array
    {
        $tags = $this->getBaseCacheTags();
        
        if ($id !== null) {
            $tags[] = 'GREBION_ROW_' . $id;
        }
        
        if (!empty($data['TABLE_ID'])) {
            $tags[] = 'GREBION_TABLE_' . $data['TABLE_ID'];
        }
        
        return $tags;
    }
    
    /**
     * Получить строки по ID таблицы
     *
     * @param int $tableId ID таблицы
     * @param array $order Сортировка
     * @param int|null $limit Лимит записей
     * @param int|null $offset Смещение
     * @return Result
     * @throws ArgumentException
     * @throws SystemException
     */
    public function getByTableId(
        int $tableId,
        array $order = ['SORT' => 'ASC', 'ID' => 'ASC'],
        ?int $limit = null,
        ?int $offset = null
    ): Result {
        return $this->getList(
            ['TABLE_ID' => $tableId],
            $order,
            $limit,
            $offset
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(array $data, ?int $id = null): MainResult
    {
        // Устанавливаем SORT для новых записей
        if ($id === null && !isset($data['SORT']) && !empty($data['TABLE_ID'])) {
            $data['SORT'] = $this->getNextSortValue(['TABLE_ID' => $data['TABLE_ID']]);
        }
        
        return parent::save($data, $id);
    }
    
    /**
     * Массовое добавление строк
     *
     * @param array $rows Массив данных строк
     * @param int $tableId ID таблицы
     * @return MainResult
     * @throws ArgumentException
     * @throws SystemException
     */
    public function bulkInsert(array $rows, int $tableId): MainResult
    {
        $result = new MainResult();
        $insertedIds = [];
        
        /** @var Connection $connection */
        $connection = Application::getConnection();
        $connection->startTransaction();
        
        try {
            $sortValue = $this->getNextSortValue(['TABLE_ID' => $tableId]);
            
            foreach ($rows as $rowData) {
                $rowData['TABLE_ID'] = $tableId;
                $rowData['CREATED_AT'] = new DateTime();
                $rowData['SORT'] = $sortValue;
                
                $addResult = RowTable::add($rowData);
                
                if ($addResult->isSuccess()) {
                    $insertedIds[] = $addResult->getData()['id'];
                    $sortValue += 100; // Увеличиваем сортировку
                } else {
                    throw new \Exception('Ошибка при добавлении строки: ' . implode(', ', $addResult->getErrorMessages()));
                }
            }
            
            $connection->commitTransaction();
            $result->setData(['ids' => $insertedIds]);
            $this->clearCache(null, $this->getCacheTags(null, ['TABLE_ID' => $tableId]));
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error($e->getMessage()));
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Массовое удаление строк
     *
     * @param array $ids Массив ID строк
     * @param int|null $tableId ID таблицы для оптимизации очистки кеша
     * @return MainResult
     * @throws ArgumentException
     * @throws SystemException
     */
    public function bulkDelete(array $ids, ?int $tableId = null): MainResult
    {
        $result = new MainResult();
        
        if (empty($ids)) {
            return $result;
        }
        
        /** @var Connection $connection */
        $connection = Application::getConnection();
        $connection->startTransaction();
        
        try {
            // Если tableId не передан, получаем его одним запросом
            if ($tableId === null) {
                $tableIds = RowTable::query()
                    ->setSelect(['TABLE_ID'])
                    ->setFilter(['ID' => $ids])
                    ->exec()
                    ->fetchAll();
                
                $tableId = !empty($tableIds) ? $tableIds[0]['TABLE_ID'] : null;
            }
            
            // Массовое удаление одним запросом
            $sql = sprintf(
                "DELETE FROM %s WHERE ID IN (%s)",
                RowTable::getTableName(),
                implode(',', array_map('intval', $ids))
            );
            
            $connection->query($sql);
            
            $connection->commitTransaction();
            $this->clearCache(null, $this->getCacheTags(null, ['TABLE_ID' => $tableId]));
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error($e->getMessage()));
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Обновить порядок сортировки строк
     *
     * @param array $sortData Массив [id => sort_value]
     * @return MainResult
     * @throws ArgumentException
     * @throws SystemException
     */
    public function updateSort(array $sortData): MainResult
    {
        $result = new MainResult();
        
        if (empty($sortData)) {
            return $result;
        }
        
        /** @var Connection $connection */
        $connection = Application::getConnection();
        $connection->startTransaction();
        
        try {
            // Получаем TABLE_ID одним запросом
            $rowIds = array_keys($sortData);
            $rows = RowTable::query()
                ->setSelect(['ID', 'TABLE_ID'])
                ->setFilter(['ID' => $rowIds])
                ->exec()
                ->fetchAll();
            
            $tableIds = [];
            $validRows = [];
            
            foreach ($rows as $row) {
                $tableIds[] = $row['TABLE_ID'];
                $validRows[$row['ID']] = $row['TABLE_ID'];
            }
            
            // Массовое обновление через SQL
            $updateTime = new DateTime();
            $sqlParts = [];
            
            foreach ($sortData as $rowId => $sortValue) {
                if (isset($validRows[$rowId])) {
                    $sqlParts[] = sprintf(
                        "WHEN %d THEN %d",
                        (int)$rowId,
                        (int)$sortValue
                    );
                }
            }
            
            if (!empty($sqlParts)) {
                $sql = sprintf(
                    "UPDATE %s SET SORT = CASE ID %s END, UPDATED_AT = '%s' WHERE ID IN (%s)",
                    RowTable::getTableName(),
                    implode(' ', $sqlParts),
                    $updateTime->format('Y-m-d H:i:s'),
                    implode(',', array_keys($validRows))
                );
                
                $connection->query($sql);
            }
            
            $connection->commitTransaction();
            
            // Очищаем кеш для всех затронутых таблиц
            foreach (array_unique($tableIds) as $tableId) {
                $this->clearCache(null, $this->getCacheTags(null, ['TABLE_ID' => $tableId]));
            }
            
        } catch (\Exception $e) {
            $connection->rollbackTransaction();
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    

    
    /**
     * Получить следующее значение сортировки
     *
     * @param array $filter Фильтр для поиска максимального SORT
     * @return int
     * @throws ArgumentException
     * @throws SystemException
     */
    protected function getNextSortValue(array $filter = []): int
    {
        /** @var Result $result */
        $result = RowTable::query()
            ->setSelect(['SORT'])
            ->setFilter($filter)
            ->setOrder(['SORT' => 'DESC'])
            ->setLimit(1)
            ->exec();
            
        $lastRow = $result->fetch();
        
        return $lastRow ? (int)$lastRow['SORT'] + 100 : 500;
    }
}