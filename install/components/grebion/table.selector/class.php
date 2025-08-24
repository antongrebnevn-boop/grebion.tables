<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Component\BaseUfComponent;
use Grebion\Tables\Uftype\TableProperty;

/**
 * Компонент для отображения селектора таблиц
 */
class TableSelectorComponent extends BaseUfComponent
{
    protected static function getUserTypeId(): string
    {
        return TableProperty::USER_TYPE_ID;
    }
}