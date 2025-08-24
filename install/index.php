<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;

Loc::loadMessages(__FILE__);

/**
 * Класс установки модуля Grebion.Tables NextGen
 */
class grebion_tables extends CModule
{
    public $MODULE_ID = 'grebion.tables';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_SORT = 1000;
    public $SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        
        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }
        
        $this->MODULE_NAME = Loc::getMessage('GREBION_TABLES_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('GREBION_TABLES_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('GREBION_TABLES_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('GREBION_TABLES_PARTNER_URI');
    }

    /**
     * Установка модуля
     */
    public function DoInstall(): bool
    {
        global $APPLICATION;
        
        try {
            // Проверяем версию PHP
            if (version_compare(PHP_VERSION, '7.4.0') < 0) {
                $APPLICATION->ThrowException(Loc::getMessage('GREBION_TABLES_INSTALL_ERROR_PHP'));
                return false;
            }
            
            // Проверяем версию ядра Bitrix
            if (!CheckVersion(ModuleManager::getVersion('main'), '20.0.0')) {
                $APPLICATION->ThrowException(Loc::getMessage('GREBION_TABLES_INSTALL_ERROR_VERSION'));
                return false;
            }
            
            // Устанавливаем таблицы БД
            $this->InstallDB();
            
            // Копируем файлы
            $this->InstallFiles();
            
            // Регистрируем модуль
            ModuleManager::registerModule($this->MODULE_ID);
            
            // Устанавливаем права доступа
            $this->InstallEvents();
            
            return true;
            
        } catch (Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }
    }

    /**
     * Удаление модуля
     */
    public function DoUninstall(): bool
    {
        global $APPLICATION;
        
        try {
            // Удаляем события
            $this->UnInstallEvents();
            
            // Удаляем файлы
            $this->UnInstallFiles();
            
            // Удаляем таблицы БД
            $this->UnInstallDB();
            
            // Удаляем модуль из реестра
            ModuleManager::unRegisterModule($this->MODULE_ID);
            
            return true;
            
        } catch (Exception $e) {
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }
    }

    /**
     * Установка таблиц БД
     */
    public function InstallDB(): bool
    {
        global $DB;
        
        try {
            $DB->RunSQLBatch(__DIR__ . '/db/mysql/install.sql');
            return true;
        } catch (SqlQueryException $e) {
            throw new Exception('Ошибка создания таблиц БД: ' . $e->getMessage());
        }
    }

    /**
     * Удаление таблиц БД
     */
    public function UnInstallDB(): bool
    {
        global $DB;
        
        try {
            $DB->RunSQLBatch(__DIR__ . '/db/mysql/uninstall.sql');
            return true;
        } catch (SqlQueryException $e) {
            throw new Exception('Ошибка удаления таблиц БД: ' . $e->getMessage());
        }
    }

    /**
     * Копирование файлов
     */
    public function InstallFiles(): bool
    {
        // Копируем компоненты
        CopyDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/',
            true,
            true
        );
        
        // Копируем административные файлы
        CopyDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/',
            true,
            true
        );
        
        return true;
    }

    /**
     * Удаление файлов
     */
    public function UnInstallFiles(): bool
    {
        // Удаляем компоненты
        DeleteDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/'
        );
        
        // Удаляем административные файлы
        DeleteDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/'
        );
        
        return true;
    }

    /**
     * Установка событий
     */
    public function InstallEvents(): bool
    {
        // Регистрируем обработчики событий если нужно
        return true;
    }

    /**
     * Удаление событий
     */
    public function UnInstallEvents(): bool
    {
        // Удаляем обработчики событий если нужно
        return true;
    }

    /**
     * Получить права доступа
     */
    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                '[D] ' . Loc::getMessage('GREBION_TABLES_DENIED'),
                '[R] ' . Loc::getMessage('GREBION_TABLES_READ'),
                '[W] ' . Loc::getMessage('GREBION_TABLES_WRITE')
            ]
        ];
    }
}