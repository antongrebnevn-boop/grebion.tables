<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Grebion\Tables\Model\ColumnTable;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Error;
use Bitrix\Main\Result as MainResult;
use Bitrix\Main\Type\DateTime;

/**
 * Репозиторий для работы с колонками таблиц
 */
class ColumnRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected function getTableClass(): string
    {
        return ColumnTable::class;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCachePrefix(): string
    {
        return 'column';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCacheTags(?int $id = null, array $data = []): array
    {
        $tags = $this->getBaseCacheTags();
        
        if ($id !== null) {
            $tags[] = 'GREBION_COLUMN_' . $id;
        }
        
        if (!empty($data['TABLE_ID'])) {
            $tags[] = 'GREBION_TABLE_' . $data['TABLE_ID'];
        }
        
        return $tags;
    }

    
    /**
     * Получить колонки по ID таблицы
     *
     * @param int $tableId ID таблицы
     * @param array $order Сортировка
     * @return Result
     * @throws ArgumentException
     * @throws SystemException
     */
    public function getByTableId(int $tableId, array $order = ['SORT' => 'ASC']): Result
    {
        return $this->getList(
            ['TABLE_ID' => $tableId],
            $order
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
     * Обновить сортировку колонок
     *
     * @param array $sortData Массив [id => sort_value]
     * @return MainResult
     * @throws ArgumentException
     * @throws SystemException
     */
    public function updateSort(array $sortData): MainResult
    {
        $result = new MainResult();
        
        try {
            foreach ($sortData as $id => $sort) {
                $updateResult = ColumnTable::update($id, [
                    'SORT' => (int)$sort,
                    'UPDATED_AT' => new DateTime()
                ]);
                
                if (!$updateResult->isSuccess()) {
                    foreach ($updateResult->getErrors() as $error) {
                        $result->addError($error);
                    }
                    break;
                }
            }
            
            if ($result->isSuccess()) {
                $this->clearCache();
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
}