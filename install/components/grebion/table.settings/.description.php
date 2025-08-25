<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => 'Настройки схемы таблицы',
    'DESCRIPTION' => 'Компонент для настройки схемы таблицы',
    'ICON' => '/images/icon.gif',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'grebion',
        'NAME' => 'Grebion',
        'CHILD' => [
            'ID' => 'tables',
            'NAME' => 'Таблицы'
        ]
    ],
    'CACHE_PATH' => 'Y',
    'COMPLEX' => 'N'
];