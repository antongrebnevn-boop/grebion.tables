<?php

declare(strict_types=1);

namespace Grebion\Tables\Service;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\GroupTable;
use Bitrix\Main\Loader;
use Grebion\Tables\Repository\TableRepository;

/**
 * Сервис для управления правами доступа к таблицам
 * Использует систему прав доступа Bitrix D7
 */
class PermissionService
{
    public const ACTION_READ = 'read';
    public const ACTION_WRITE = 'write';
    public const ACTION_DELETE = 'delete';
    public const ACTION_ADMIN = 'admin';
    
    public const ROLE_OWNER = 'owner';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_ADMIN = 'admin';
    
    private TableRepository $tableRepository;
    
    public function __construct(?TableRepository $tableRepository = null)
    {
        Loader::includeModule('highloadblock');
        $this->tableRepository = $tableRepository ?? new TableRepository();
    }
    
    /**
     * Проверить права доступа пользователя к таблице
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @param string $action Действие (read, write, delete, admin)
     * @return bool
     */
    public function checkAccess(int $userId, int $tableId, string $action): bool
    {
        // Администраторы имеют полный доступ
        if ($this->isAdmin($userId)) {
            return true;
        }
        
        // Получаем информацию о таблице
        $table = $this->tableRepository->getById($tableId);
        if (!$table) {
            return false;
        }
        
        // Владелец таблицы имеет полный доступ
        if ($table['OWNER_ID'] == $userId) {
            return true;
        }
        
        // Проверяем права через систему доступа
        $userRole = $this->getUserRoleForTable($userId, $tableId);
        
        return $this->checkRolePermission($userRole, $action);
    }
    
    /**
     * Назначить роль пользователю для таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @param string $role Роль (owner, editor, viewer, admin)
     * @return Result
     */
    public function assignRole(int $userId, int $tableId, string $role): Result
    {
        $result = new Result();
        
        try {
            if (!in_array($role, [self::ROLE_OWNER, self::ROLE_EDITOR, self::ROLE_VIEWER, self::ROLE_ADMIN])) {
                $result->addError(new Error('Неверная роль: ' . $role));
                return $result;
            }
            
            // Проверяем существование таблицы
            $table = $this->tableRepository->getById($tableId);
            if (!$table) {
                $result->addError(new Error('Таблица не найдена'));
                return $result;
            }
            
            // Используем стандартный битриксовый подход - добавляем пользователя в группу
            $groupCode = 'GREBION_TABLE_' . $tableId . '_' . strtoupper($role);
            
            // Создаем группу если не существует
            $this->createGroupIfNotExists($groupCode, $tableId, $role);
            
            // Добавляем пользователя в группу
            \CUser::SetUserGroup($userId, array_merge(\CUser::GetUserGroup($userId), [$this->getGroupIdByCode($groupCode)]));
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка назначения роли: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Удалить роль пользователя для таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return Result
     */
    public function removeUserRole(int $userId, int $tableId): Result
    {
        $result = new Result();
        
        try {
            $userGroups = \CUser::GetUserGroup($userId);
            $newGroups = [];
            
            foreach ($userGroups as $groupId) {
                $group = GroupTable::getById($groupId)->fetch();
                if ($group && strpos($group['STRING_ID'], 'GREBION_TABLE_' . $tableId . '_') !== 0) {
                    $newGroups[] = $groupId;
                }
            }
            
            \CUser::SetUserGroup($userId, $newGroups);
            
            $result->setData(['removed' => true]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка удаления роли: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Получить роль пользователя для таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return string|null
     */
    public function getUserRoleForTable(int $userId, int $tableId): ?string
    {
        try {
            $userGroups = \CUser::GetUserGroup($userId);
            
            foreach ($userGroups as $groupId) {
                $group = GroupTable::getById($groupId)->fetch();
                if ($group && !empty($group['STRING_ID']) && strpos($group['STRING_ID'], 'GREBION_TABLE_' . $tableId . '_') === 0) {
                    $parts = explode('_', $group['STRING_ID']);
                    return strtolower(end($parts));
                }
            }
            
        } catch (\Exception $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * Создать группу если не существует
     *
     * @param string $groupCode
     * @param int $tableId
     * @param string $role
     * @return void
     */
    private function createGroupIfNotExists(string $groupCode, int $tableId, string $role): void
    {
        $existingGroup = GroupTable::getList([
            'filter' => ['STRING_ID' => $groupCode],
            'limit' => 1
        ])->fetch();
        
        if (!$existingGroup) {
            $group = new \CGroup();
            $group->Add([
                'ACTIVE' => 'Y',
                'NAME' => 'Таблица ' . $tableId . ' - ' . $role,
                'STRING_ID' => $groupCode,
                'DESCRIPTION' => 'Группа для доступа к таблице ' . $tableId . ' с ролью ' . $role
            ]);
        }
    }
    
    /**
     * Получить ID группы по коду
     *
     * @param string $groupCode
     * @return int|null
     */
    private function getGroupIdByCode(string $groupCode): ?int
    {
        $group = GroupTable::getList([
            'filter' => ['STRING_ID' => $groupCode],
            'limit' => 1
        ])->fetch();
        
        return $group ? (int)$group['ID'] : null;
    }
    
    /**
     * Получить список пользователей с доступом к таблице
     *
     * @param int $tableId ID таблицы
     * @return array
     */
    public function getTableUsers(int $tableId): array
    {
        try {
            $groups = GroupTable::getList([
                'filter' => ['%STRING_ID' => 'GREBION_TABLE_' . $tableId . '_']
            ]);
            
            $users = [];
            while ($group = $groups->fetch()) {
                $groupUsers = \CGroup::GetGroupUser($group['ID']);
                $parts = explode('_', $group['STRING_ID']);
                $role = strtolower(end($parts));
                
                foreach ($groupUsers as $userId) {
                    $user = UserTable::getById($userId)->fetch();
                    if ($user) {
                        $users[] = [
                            'ID' => $userId,
                            'LOGIN' => $user['LOGIN'],
                            'NAME' => $user['NAME'],
                            'LAST_NAME' => $user['LAST_NAME'],
                            'ROLE' => $role,
                        ];
                    }
                }
            }
            
            return $users;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Получить список таблиц доступных пользователю
     *
     * @param int $userId ID пользователя
     * @param string|null $action Фильтр по действию
     * @return array
     */
    public function getUserTables(int $userId, ?string $action = null): array
    {
        try {
            $tables = [];
            
            // Если пользователь администратор - возвращаем все таблицы
            if ($this->isAdmin($userId)) {
                $tablesResult = $this->tableRepository->getList();
                while ($table = $tablesResult->fetch()) {
                    $tables[] = [
                        'TABLE_ID' => $table['ID'],
                        'TABLE_NAME' => $table['NAME'],
                        'ROLE' => self::ROLE_ADMIN,
                        'ACCESS' => true,
                    ];
                }
                return $tables;
            }
            
            // Получаем таблицы где пользователь владелец
            $ownTablesResult = $this->tableRepository->getList(['OWNER_ID' => $userId]);
            while ($table = $ownTablesResult->fetch()) {
                $access = $action ? $this->checkRolePermission(self::ROLE_OWNER, $action) : true;
                $tables[] = [
                    'TABLE_ID' => $table['ID'],
                    'TABLE_NAME' => $table['NAME'],
                    'ROLE' => self::ROLE_OWNER,
                    'ACCESS' => $access,
                ];
            }
            
            // Получаем таблицы с назначенными правами через группы
            $userGroups = \CUser::GetUserGroup($userId);
            
            foreach ($userGroups as $groupId) {
                $group = GroupTable::getById($groupId)->fetch();
                if ($group && strpos($group['STRING_ID'], 'GREBION_TABLE_') === 0) {
                    $parts = explode('_', $group['STRING_ID']);
                    if (count($parts) >= 4) {
                        $tableId = (int)$parts[2];
                        $role = strtolower($parts[3]);
                        
                        $table = $this->tableRepository->getById($tableId);
                        if ($table) {
                            $access = $action ? $this->checkRolePermission($role, $action) : true;
                            $tables[] = [
                                'TABLE_ID' => $table['ID'],
                                'TABLE_NAME' => $table['NAME'],
                                'ROLE' => $role,
                                'ACCESS' => $access,
                            ];
                        }
                    }
                }
            }
            
            return $tables;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Проверить является ли пользователь администратором
     *
     * @param int $userId ID пользователя
     * @return bool
     */
    public function isAdmin(int $userId): bool
    {
        try {
            $user = \Bitrix\Main\UserTable::getById($userId)->fetch();
            if (!$user) {
                return false;
            }
            
            // Проверяем группы пользователя
            $userGroups = \CUser::GetUserGroup($userId);
            
            // ID группы администраторов обычно 1
            return in_array(1, $userGroups);
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Проверить права роли на выполнение действия
     *
     * @param string|null $role Роль
     * @param string $action Действие
     * @return bool
     */
    private function checkRolePermission(?string $role, string $action): bool
    {
        if (!$role) {
            return false;
        }
        
        $permissions = [
            self::ROLE_ADMIN => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE, self::ACTION_ADMIN],
            self::ROLE_OWNER => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE, self::ACTION_ADMIN],
            self::ROLE_EDITOR => [self::ACTION_READ, self::ACTION_WRITE],
            self::ROLE_VIEWER => [self::ACTION_READ],
        ];
        
        return isset($permissions[$role]) && in_array($action, $permissions[$role]);
    }
    
    /**
     * Копировать права доступа с одной таблицы на другую
     *
     * @param int $sourceTableId ID исходной таблицы
     * @param int $targetTableId ID целевой таблицы
     * @return Result
     */
    public function copyPermissions(int $sourceTableId, int $targetTableId): Result
    {
        $result = new Result();
        
        try {
            $sourceGroups = GroupTable::getList([
                'filter' => ['%STRING_ID' => 'GREBION_TABLE_' . $sourceTableId . '_']
            ]);
            
            $copiedCount = 0;
            while ($sourceGroup = $sourceGroups->fetch()) {
                $parts = explode('_', $sourceGroup['STRING_ID']);
                $role = end($parts);
                
                $targetGroupCode = 'GREBION_TABLE_' . $targetTableId . '_' . $role;
                
                // Создаем группу для целевой таблицы
                $this->createGroupIfNotExists($targetGroupCode, $targetTableId, strtolower($role));
                
                // Копируем пользователей из исходной группы в целевую
                $sourceUsers = \CGroup::GetGroupUser($sourceGroup['ID']);
                $targetGroupId = $this->getGroupIdByCode($targetGroupCode);
                
                if ($targetGroupId) {
                    foreach ($sourceUsers as $userId) {
                        $userGroups = \CUser::GetUserGroup($userId);
                        if (!in_array($targetGroupId, $userGroups)) {
                            \CUser::SetUserGroup($userId, array_merge($userGroups, [$targetGroupId]));
                            $copiedCount++;
                        }
                    }
                }
            }
            
            $result->setData(['copied_count' => $copiedCount]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка копирования прав: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Удалить все права доступа к таблице
     *
     * @param int $tableId ID таблицы
     * @return Result
     */
    public function clearTablePermissions(int $tableId): Result
    {
        $result = new Result();
        
        try {
            $groups = GroupTable::getList([
                'filter' => ['%STRING_ID' => 'GREBION_TABLE_' . $tableId . '_']
            ]);
            
            $deletedCount = 0;
            while ($group = $groups->fetch()) {
                // Удаляем всех пользователей из группы
                $groupUsers = \CGroup::GetGroupUser($group['ID']);
                foreach ($groupUsers as $userId) {
                    $userGroups = \CUser::GetUserGroup($userId);
                    $newGroups = array_diff($userGroups, [$group['ID']]);
                    \CUser::SetUserGroup($userId, $newGroups);
                    $deletedCount++;
                }
                
                // Удаляем саму группу
                $groupObj = new \CGroup();
                $groupObj->Delete($group['ID']);
            }
            
            $result->setData(['deleted_count' => $deletedCount]);
            
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка очистки прав: ' . $e->getMessage()));
        }
        
        return $result;
    }
    
    /**
     * Проверить права на чтение таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return bool
     */
    public function canRead(int $userId, int $tableId): bool
    {
        return $this->checkAccess($userId, $tableId, self::ACTION_READ);
    }
    
    /**
     * Проверить права на запись в таблицу
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return bool
     */
    public function canWrite(int $userId, int $tableId): bool
    {
        return $this->checkAccess($userId, $tableId, self::ACTION_WRITE);
    }
    
    /**
     * Проверить права на удаление из таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return bool
     */
    public function canDelete(int $userId, int $tableId): bool
    {
        return $this->checkAccess($userId, $tableId, self::ACTION_DELETE);
    }
    
    /**
     * Проверить права администратора таблицы
     *
     * @param int $userId ID пользователя
     * @param int $tableId ID таблицы
     * @return bool
     */
    public function canAdmin(int $userId, int $tableId): bool
    {
        return $this->checkAccess($userId, $tableId, self::ACTION_ADMIN);
    }
}