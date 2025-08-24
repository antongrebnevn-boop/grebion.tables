<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\Result as MainResult;
use Bitrix\Main\Error;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\SystemException;

/**
 * Абстрактный базовый класс для всех репозиториев
 * Содержит общую логику кеширования и вспомогательные методы
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected const CACHE_TTL = 3600; // 1 час
    protected const CACHE_DIR_PREFIX = '/grebion.tables/';
    
    /**
     * Получить класс ORM таблицы
     *
     * @return string
     */
    abstract protected function getTableClass(): string;
    
    /**
     * Получить префикс для кеша
     *
     * @return string
     */
    abstract protected function getCachePrefix(): string;
    
    /**
     * Получить теги кеша для записи
     *
     * @param int|null $id ID записи
     * @param array $data Данные записи
     * @return array
     */
    abstract protected function getCacheTags(?int $id = null, array $data = []): array;
    
    /**
     * {@inheritdoc}
     */
    public function getList(
        array $filter = [],
        array $order = ['ID' => 'ASC'],
        ?int $limit = null,
        ?int $offset = null
    ): Result {
        $cacheKey = $this->getCacheKey('list', [
            'filter' => $filter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $tableClass = $this->getTableClass();
        /** @var Query $query */
        $query = $tableClass::query()
            ->setSelect(['*'])
            ->setFilter($filter)
            ->setOrder($order);
        
        $cache = Cache::createInstance();
        $cacheDir = $this->getCacheDir();
        
        if ($cache->initCache(static::CACHE_TTL, $cacheKey, $cacheDir)) {
            $cached = $cache->getVars();
            // Создаем ArrayResult с кешированными данными
            $data = $cached['data'] ?? [];
            $count = $cached['count'] ?? 0;
            $arrayResult = new \Bitrix\Main\DB\ArrayResult($data);
            $arrayResult->setCount($count);
            
            // Создаем ORM Result объект
            $result = new Result($query, $arrayResult);
            return $result;
        }
        
        $cache->startDataCache();
            
        if ($limit !== null) {
            $query->setLimit($limit);
        }
        
        if ($offset !== null) { 
            $query->setOffset($offset);
        }
        
        $result = $query->exec();
        
        // Сохраняем количество строк до обработки
        $count = $result->getSelectedRowsCount();
        
        // Получаем все данные для кеширования
        $data = [];
        while ($row = $result->fetch()) {
            $data[] = $row;
        }
        
        // Регистрируем теги для кеша
        $this->registerCacheTags($cacheDir, $this->getCacheTags());
        
        $cache->endDataCache(['data' => $data, 'count' => $count]);
        
        // Создаем ArrayResult с данными из кеша
        $arrayResult = new \Bitrix\Main\DB\ArrayResult($data);
        $arrayResult->setCount($count);
        
        // Создаем новый ORM Result объект
        $newResult = new Result($query, $arrayResult);
        
        return $newResult;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getById(int $id): ?array
    {
        $cacheKey = $this->getCacheKey('item', ['id' => $id]);
        $cache = Cache::createInstance();
        $cacheDir = $this->getCacheDir();
        
        if ($cache->initCache(static::CACHE_TTL, $cacheKey, $cacheDir)) {
            return $cache->getVars()['item'];
        }
        
        $cache->startDataCache();
        
        $tableClass = $this->getTableClass();
        /** @var Result $result */
        $result = $tableClass::getById($id);
        $item = $result->fetch();
        
        // Регистрируем теги для кеша
        $this->registerCacheTags($cacheDir, $this->getCacheTags());
        
        $cache->endDataCache(['item' => $item]);
        
        return $item ?: null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(array $data, ?int $id = null): MainResult
    {
        $result = new MainResult();
        
        try {
            $tableClass = $this->getTableClass();
            
            if ($id !== null) {
                // Обновление
                $data['UPDATED_AT'] = new DateTime();
                $updateResult = $tableClass::update($id, $data);
                
                if ($updateResult->isSuccess()) {
                    $result->setData(['id' => $id]);
                    $this->clearCache($id, $this->getCacheTags($id, $data));
                } else {
                    foreach ($updateResult->getErrors() as $error) {
                        $result->addError($error);
                    }
                }
            } else {
                // Создание
                $data['CREATED_AT'] = new DateTime();
                $addResult = $tableClass::add($data);
                
                if ($addResult->isSuccess()) {
                    $newId = $addResult->getId();
                    $result->setData(['id' => $newId]);
                    $this->clearCache(null, $this->getCacheTags($newId, $data));
                } else {
                    foreach ($addResult->getErrors() as $error) {
                        $result->addError($error);
                    }
                }
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(int $id): MainResult
    {
        $result = new MainResult();
        
        try {
            // Получаем данные перед удалением для очистки кеша
            $item = $this->getById($id);
            
            $tableClass = $this->getTableClass();
            $deleteResult = $tableClass::delete($id);
            
            if ($deleteResult->isSuccess()) {
                $this->clearCache($id, $this->getCacheTags($id, $item ?: []));
            } else {
                foreach ($deleteResult->getErrors() as $error) {
                    $result->addError($error);
                }
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCount(array $filter = []): int
    {
        $cacheKey = $this->getCacheKey('count', ['filter' => $filter]);
        $cache = Cache::createInstance();
        $cacheDir = $this->getCacheDir();
        
        if ($cache->initCache(static::CACHE_TTL, $cacheKey, $cacheDir)) {
            return $cache->getVars()['count'];
        }
        
        $cache->startDataCache();
        
        $tableClass = $this->getTableClass();
        $count = $tableClass::getCount($filter);
        
        // Регистрируем теги для кеша
        $this->registerCacheTags($cacheDir, $this->getCacheTags());
        
        $cache->endDataCache(['count' => $count]);
        
        return $count;
    }
    
    /**
     * {@inheritdoc}
     */
    public function clearCache(?int $id = null, array $tags = []): void
    {
        $cache = Cache::createInstance();
        $cacheDir = $this->getCacheDir();
        
        if ($id !== null) {
            // Очищаем кеш конкретной записи
            $cache->clean($this->getCacheKey('item', ['id' => $id]), $cacheDir);
        }
        
        // Очищаем кеш по тегам
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                Application::getInstance()->getTaggedCache()->clearByTag($tag);
            }
        }
        
        // Очищаем весь кеш директории для списков и счетчиков
        $cache->cleanDir($cacheDir);
    }
    
    /**
     * Генерация ключа кеша
     *
     * @param string $type Тип операции
     * @param array $params Параметры
     * @return string
     */
    protected function getCacheKey(string $type, array $params = []): string
    {
        return $this->getCachePrefix() . '_' . $type . '_' . md5(serialize($params));
    }
    
    /**
     * Получить директорию кеша
     *
     * @return string
     */
    protected function getCacheDir(): string
    {
        return static::CACHE_DIR_PREFIX . $this->getCachePrefix();
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
        $tableClass = $this->getTableClass();
        /** @var Result $result */
        $result = $tableClass::query()
            ->setSelect(['SORT'])
            ->setFilter($filter)
            ->setOrder(['SORT' => 'DESC'])
            ->setLimit(1)
            ->exec();
            
        $lastItem = $result->fetch();
        
        return $lastItem ? (int)$lastItem['SORT'] + 100 : 500;
    }
    
    /**
     * Получить базовые теги кеша
     *
     * @return array
     */
    protected function getBaseCacheTags(): array
    {
        return ['GREBION_TABLES', 'GREBION_' . strtoupper($this->getCachePrefix())];
    }
    
    /**
     * Регистрация тегов кеша
     *
     * @param string $cacheDir Директория кеша
     * @param array $tags Теги для регистрации
     * @return void
     */
    protected function registerCacheTags(string $cacheDir, array $tags): void
    {
        if (empty($tags)) {
            return;
        }
        
        $taggedCache = Application::getInstance()->getTaggedCache();
        $taggedCache->startTagCache($cacheDir);
        
        foreach ($tags as $tag) {
            $taggedCache->registerTag($tag);
        }
        
        $taggedCache->endTagCache();
    }
}