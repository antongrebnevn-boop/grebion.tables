<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Grebion\Tables\Model\CellTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\ORM\Query\Result;

/**
 * Репозиторий для работы с ячейками таблиц
 */
class CellRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected function getTableClass(): string
    {
        return CellTable::class;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCachePrefix(): string
    {
        return 'cell';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCacheTags(?int $id = null, array $data = []): array
    {
        $tags = $this->getBaseCacheTags();
        
        if ($id !== null) {
            $tags[] = 'CELL_' . $id;
        }
        
        if (!empty($data['ROW_ID'])) {
            $tags[] = 'ROW_' . $data['ROW_ID'];
        }
        
        if (!empty($data['COLUMN_ID'])) {
            $tags[] = 'COLUMN_' . $data['COLUMN_ID'];
        }
        
        return $tags;
    }
    
    /**
     * Получить ячейки по ID строки
     *
     * @param int $rowId
     * @return array
     * @throws ArgumentException
     * @throws SystemException
     */
    public function getByRowId(int $rowId): array
    {
        $cacheKey = $this->getCacheKey('row_' . $rowId);
        $cache = $this->getCache();
        
        if ($cache->initCache($this->getCacheTtl(), $cacheKey)) {
            return $cache->getVars();
        }
        
        $cache->startDataCache();
        
        $result = $this->getTableClass()::getList([
            'filter' => ['ROW_ID' => $rowId],
            'order' => ['COLUMN_ID' => 'ASC']
        ])->fetchAll();
        
        $cache->endDataCache($result);
        
        return $result;
    }
    
    /**
     * Получить ячейки по ID колонки
     *
     * @param int $columnId
     * @return array
     * @throws ArgumentException
     * @throws SystemException
     */
    public function getByColumnId(int $columnId): array
    {
        $cacheKey = $this->getCacheKey('column_' . $columnId);
        $cache = $this->getCache();
        
        if ($cache->initCache($this->getCacheTtl(), $cacheKey)) {
            return $cache->getVars();
        }
        
        $cache->startDataCache();
        
        $result = $this->getTableClass()::getList([
            'filter' => ['COLUMN_ID' => $columnId],
            'order' => ['ROW_ID' => 'ASC']
        ])->fetchAll();
        
        $cache->endDataCache($result);
        
        return $result;
    }
}