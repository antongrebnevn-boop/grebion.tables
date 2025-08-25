<?php

namespace Grebion\Tables\Result;

use Bitrix\Main\Result;

/**
 * Класс результата операций с таблицами
 */
class TableResult extends Result
{
    /**
     * Получить ID созданной/обновленной записи
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        $data = $this->getData();
        
        if (isset($data['id'])) {
            return (int)$data['id'];
        }
        
        if (isset($data['table_id'])) {
            return (int)$data['table_id'];
        }
        
        return null;
    }
    
    /**
     * Получить ID таблицы
     *
     * @return int|null
     */
    public function getTableId(): ?int
    {
        $data = $this->getData();
        return isset($data['table_id']) ? (int)$data['table_id'] : null;
    }
}