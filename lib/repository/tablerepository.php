<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Grebion\Tables\Model\TableDataTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;
use Bitrix\Main\ORM\Query\Result;

/**
 * Репозиторий для работы с таблицами (HighloadBlock)
 */
class TableRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected function getTableClass(): string
    {
        return TableDataTable::class;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCachePrefix(): string
    {
        return 'table';
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getCacheTags(?int $id = null, array $data = []): array
    {
        $tags = $this->getBaseCacheTags();
        
        if ($id !== null) {
            $tags[] = 'HLBLOCK_' . $id;
        }
        
        return $tags;
    }
    
    /**
     * Получить таблицу по имени
     *
     * @param string $name Имя таблицы
     * @return array|null
     * @throws ArgumentException
     * @throws SystemException
     */
    public function getByName(string $name): ?array
    {
        $cacheKey = $this->getCacheKey('by_name', ['name' => $name]);
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cacheDir = $this->getCacheDir();
        
        if ($cache->initCache(static::CACHE_TTL, $cacheKey, $cacheDir)) {
            return $cache->getVars()['item'];
        }
        
        $cache->startDataCache();
        
        /** @var Result $result */
        $result = TableDataTable::query()
            ->setSelect(['*'])
            ->setFilter(['TITLE' => $name])
            ->setLimit(1)
            ->exec();
            
        $item = $result->fetch();
        
        // Регистрируем теги для кеша
        $this->registerCacheTags($cacheDir, $this->getCacheTags($item ? (int)$item['ID'] : null, $item ?: []));
        
        $cache->endDataCache(['item' => $item]);
        
        return $item ?: null;
    }
}