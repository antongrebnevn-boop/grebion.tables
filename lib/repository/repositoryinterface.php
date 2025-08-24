<?php

declare(strict_types=1);

namespace Grebion\Tables\Repository;

use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\Result as MainResult;

/**
 * Базовый интерфейс для всех репозиториев
 * Определяет стандартные CRUD операции
 */
interface RepositoryInterface
{
    /**
     * Получить список записей с фильтрацией
     *
     * @param array $filter Фильтр для выборки
     * @param array $order Сортировка
     * @param int|null $limit Лимит записей
     * @param int|null $offset Смещение
     * @return Result
     */
    public function getList(
        array $filter = [],
        array $order = ['ID' => 'ASC'],
        ?int $limit = null,
        ?int $offset = null
    ): Result;

    /**
     * Получить запись по ID
     *
     * @param int $id ID записи
     * @return array|null
     */
    public function getById(int $id): ?array;

    /**
     * Сохранить запись (создать или обновить)
     *
     * @param array $data Данные записи
     * @param int|null $id ID записи для обновления
     * @return MainResult
     */
    public function save(array $data, ?int $id = null): MainResult;

    /**
     * Удалить запись
     *
     * @param int $id ID записи
     * @return MainResult
     */
    public function delete(int $id): MainResult;

    /**
     * Получить количество записей
     *
     * @param array $filter Фильтр для подсчета
     * @return int
     */
    public function getCount(array $filter = []): int;

    /**
     * Очистить кеш
     *
     * @param int|null $id ID записи для очистки конкретного элемента
     * @param array $tags Дополнительные теги для очистки
     * @return void
     */
    public function clearCache(?int $id = null, array $tags = []): void;
}