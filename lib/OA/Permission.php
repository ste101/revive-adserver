<?php

/*
+---------------------------------------------------------------------------+
| Openads v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| Copyright (c) 2000-2003 the phpAdsNew developers                          |
| For contact details, see: http://www.phpadsnew.com/                       |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

// Following files were included by lib-permissions
require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/www/admin/lib-gui.inc.php';
require_once MAX_PATH . '/www/admin/lib-sessions.inc.php';
require_once MAX_PATH . '/lib/max/other/common.php';
require_once MAX_PATH . '/lib/OA/Upgrade/EnvironmentManager.php';

/**
 * Account types
 */
define('OA_ACCOUNT_ADMIN',      'ADMIN');
define('OA_ACCOUNT_MANAGER',    'MANAGER');
define('OA_ACCOUNT_ADVERTISER', 'ADVERTISER');
define('OA_ACCOUNT_TRAFFICKER', 'TRAFFICKER');

/**
 * Account types constants defined as integers
 * so they could be saved in preferences
 */
define ("OA_ACCOUNT_ADMIN_ID", 1);
define ("OA_ACCOUNT_ADVERTISER_ID", 2);
define ("OA_ACCOUNT_TRAFFICKER_ID", 4);
define ("OA_ACCOUNT_MANAGER_ID", 8);

/**
 * Per-account permissions
 *
 * Warning: Do not change the values of following rights - they are used
 * as a IDs in database and should be the same as in "rights" table.
 */
define('OA_PERM_BANNER_ACTIVATE',   1);
define('OA_PERM_BANNER_DEACTIVATE', 2);
define('OA_PERM_BANNER_ADD',        3);
define('OA_PERM_BANNER_EDIT',       4);

define('OA_PERM_ZONE_ADD',          5);
define('OA_PERM_ZONE_DELETE',       6);
define('OA_PERM_ZONE_EDIT',         7);
define('OA_PERM_ZONE_INVOCATION',   8);
define('OA_PERM_ZONE_LINK',         9);

define('OA_PERM_SUPER_ACCOUNT',    10);


/**
 * A generic class which provides permissions related methods.
 *
 * @static
 * @package    OpenadsPermission
 */
class OA_Permission
{

    /**
     * Helper method which checks whether $condition is true, if it is not true
     * it prints to the end user error message
     *
     * @static
     * @param boolean $condition  Condition to check
     */
    function enforceTrue($condition)
    {
        if (!$condition) {
            phpAds_PageHeader(0);
            phpAds_Die($GLOBALS['strAccessDenied'], $GLOBALS['strNotAdmin']);
        }
    }

    /**
     * A method to show an error if the currently active account of an user
     * doesn't match
     *
     * This function takes either an array as the first parameter or
     * a variable number of parameters
     *
     * @static
     * @param string $accountType user type
     */
    function enforceAccount($accountType)
    {
        $aArgs = is_array($accountType) ? $accountType : func_get_args();
        $isAccount = OA_Permission::isAccount($aArgs);
        if (!$isAccount) {
            $isAccount = OA_Permission::attemptToSwitchToAccount($aArgs);
        }
        OA_Permission::enforceTrue($isAccount);
    }

    /**
     * A method to show an error if the user doesn't have access to a specific
     * account
     *
     * @static
     * @param int $accountId
     * @param int $userId  Get current user if null
     */
    function enforceAccess($accountId, $userId = null)
    {
        OA_Permission::enforceTrue(OA_Permission::hasAccess($accountId, $userId));
    }

    /**
     * A method to show an error if the user doesn't have specific permissions to
     * perform an action on an account
     *
     * @static
     * @param string $permission  See OA_PERM_* constants
     * @param int $accountId Defaults to the current active account
     */
    function enforceAllowed($permission, $accountId = null)
    {
        OA_Permission::enforceTrue(OA_Permission::hasPermission($permission, $accountId));
    }

    /**
     * A method to show an error if the user doesn't have specific permissions to
     * perform an action on an account
     *
     * @static
     * @param string $permission  See OA_PERM_* constants
     * @param int $accountId Defaults to the current active account
     */
    function enforceAccountPermission($accountType, $permission)
    {
        if (OA_Permission::isAccount($accountType)) {
            OA_Permission::enforceTrue(OA_Permission::hasPermission($permission, $accountId));
        }
        return true;
    }

    /**
     * A method to show an error is the current user/account doesn't have access
     * to DataObject (defined by it's table name)
     *
     * @static
     * @param string $entityTable  Table name
     * @param int $entityId  Entity ID (or empty if a new one is being created)
     * @param bool $allowNewEntity  Allow creation of a new entity, defaults to false
     */
    function enforceAccessToObject($entityTable, $entityId, $allowNewEntity = false)
    {
        if (!$allowNewEntity) {
            OA_Permission::enforceTrue(!empty($entityId));
        }
        OA_Permission::enforceTrue(
            OA_Permission::hasAccessToObject($entityTable, $entityId, $accountId)
            || OA_Permission::isUserLinkedToAdmin());
    }

    /**
     * Check if logged user has access to DataObject (defined by it's table name)
     *
     * @static
     * @param string $entityTable  Table name
     * @param int $entityId  Id (or empty if new is created)
     * @param int $accountId  Account Id (if null account from session is taken)
     * @return boolean  True if has access
     */
    function hasAccessToObject($entityTable, $entityId, $accountId = null, $accountType = null)
    {
        if (empty($entityId)) {
            // when a new object is created
            return true;
        }
        $do = OA_Dal::factoryDO($entityTable);
        if (!$do) {
            return false;
        }
        $key = $do->getFirstPrimaryKey();
        if (!$key) {
            return false;
        }
        $do->$key = $entityId;
        $accountTable = OA_Permission::getAccountTable($accountType);
        if (!$accountTable) {
            return false;
        }
        if ($entityTable == $accountTable) {
            // user has access to itself
            if ($accountId === null) {
                return ($entityId == OA_Permission::getEntityId());
            } else {
                $do->account_id = OA_Permission::getAccountId();
                return (bool)$do->count();
            }
        }
        if ($accountId === null) {
            $accountId = OA_Permission::getAccountId();
        }
        return $do->belongsToAccount($accountTable, $accountId);
    }

    /**
     * A method to switch the active account to a different one
     *
     * @static
     * @param int $accountId
     * @param boolean $hasAccess  Can be used for optimization - if we know that user
     *                            has access to the account he is switching to there is
     *                            no need to check it again
     */
    function switchAccount($accountId, $hasAccess = false)
    {
        if ($hasAccess || OA_Permission::hasAccess($accountId)) {
            $oUser = &OA_Permission::getCurrentUser();
            $oUser->switchAccount($accountId);
        }

        // Force session save
        phpAds_SessionDataRegister('user', $oUser);
    }

    /**
     * A method to check if the currently active account of an user
     * matches
     *
     * This function takes either an array as the first parameter or
     * a variable number of parameters
     *
     * @todo Better docblock
     *
     * @static
     * @param mixed $accountType
     * @return bool
     */
    function isAccount($accountType)
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            $aArgs = is_array($accountType) ? $accountType : func_get_args();
            return in_array($oUser->aAccount['account_type'], $aArgs);
        }
        return false;
    }


    function attemptToSwitchToAccount($accountType)
    {
        $oUser = OA_Permission::getCurrentUser();
        if (!$oUser) {
            return false;
        }
        $aAccountTypes = is_array($accountType) ? $accountType : func_get_args();
        $aAccountIds = OA_Permission::getLinkedAccounts(true);
        $defaultUserAccountId = $oUser->aUser['default_account_id'];
        foreach ($aAccountTypes as $accountType) {
            if (isset($aAccountIds[$accountType])) {
                if (isset($aAccountIds[$accountType][$defaultUserAccountId])) {
                    $accountId = $defaultUserAccountId;
                } else {
                    $accountId = array_shift(array_keys($aAccountIds[$accountType]));
                }
                OA_Permission::switchAccount($accountId, $hasAccess = true);
                return true;
            }
        }
        return false;
    }

    /**
     * A (backward compatibility) method to check if the currently active account
     * of an user matches. The difference between this method and isAccount()
     * is that this method gets integers as ACCOUNT TYPES
     *
     * This function takes either an array as the first parameter or
     * a variable number of parameters
     *
     * @todo Better docblock
     *
     * @static
     * @param mixed $accountType
     * @return bool
     */
    function isAccountTypeId($accountTypeId)
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            $userAccountTypeId = OA_Permission::convertAccountTypeToId($oUser->aAccount['account_type']);
            return $userAccountTypeId & $accountTypeId;
        }
        return false;
    }

    /**
     * Returns integer equivalent (ID) of account type
     *
     * @static
     * @param unknown_type $acountType
     * @return unknown
     */
    function convertAccountTypeToId($accountType)
    {
        $accountTypeIdConstant = 'OA_ACCOUNT_' . $accountType . '_ID';
        if (!defined($accountTypeIdConstant)) {
            MAX::raiseError('No such account type ID: ' . $accountType);
            return false;
        }
        return constant($accountTypeIdConstant);
    }

    /**
     * A method to check if the user has access to a specific account
     *
     * User cuold either has direct access to account or indirect.
     * Indirect access could be in case if user has access to one of the parent
     * entities.
     *
     * TODOPERM - should we implement indirect access here?
     *
     * @static
     * @param int $accountId
     * @return boolean
     */
    function hasAccess($accountId, $userId = null)
    {
        if (empty($userId)) {
            $userId = OA_Permission::getUserId();
        }
        return OA_Permission::isUserLinkedToAccount($accountId, $userId)
            || (OA_Permission::getAccountTypeByAccountId($accountId) == OA_ACCOUNT_MANAGER 
                && OA_Permission::isUserLinkedToAdmin());
    }
    
    /**
     * Returns account type for specific accountId
     *
     * @param integer $accountId
     * @return string  Account type
     */
    function getAccountTypeByAccountId($accountId)
    {
        $doAccounts = OA_Dal::factoryDO('accounts');
        $doAccounts->account_id = $accountId;
        if ($doAccounts->find(true)) {
            return $doAccounts->account_type;
        }
        return false;
    }

    /**
     * A method to check if the user is linked to an account
     *
     * @static
     * @param int $accountId
     * @param int $userId
     * @return boolean
     */
    function isUserLinkedToAccount($accountId, $userId)
    {
        $doAccount_user_Assoc = OA_Dal::factoryDO('account_user_assoc');
        $doAccount_user_Assoc->user_id = $userId;
        $doAccount_user_Assoc->account_id = $accountId;
        return (bool) $doAccount_user_Assoc->count();
    }

    /**
     * Set user access to account
     *
     * @param integer $accountId  account ID
     * @param boolean $setAccess  defines whether user should or shouldn't have an access to account
     * @param integer $userId  User ID (if null a logged user id is used)
     * @return boolean  True on success else false
     */
    function setAccountAccess($accountId, $userId, $setAccess = true)
    {
        $doAccount_user_Assoc = OA_Dal::factoryDO('account_user_assoc');
        $doAccount_user_Assoc->account_id = $accountId;
        $doAccount_user_Assoc->user_id = $userId;
        $isExists = (bool) $doAccount_user_Assoc->count();
        if ($isExists && !$setAccess) {
            return $doAccount_user_Assoc->delete();
        }
        if (!$isExists) {
            return $doAccount_user_Assoc->insert();
        }
        return true;
    }

    /**
     * A method to check if the user has specific permissions to perform
     * an action on an account
     *
     * TODOPERM - consider caching permissions in user session so they could
     *            be reused across many user requests
     *
     * @static
     * @param integer $permissionId
     * @param int $accountId
     * @return boolean
     */
    function hasPermission($permissionId, $accountId = null, $userId = null)
    {
        static $permissions;

        if (is_null($permissions)) {
            $permissions = array();
        }

        if ($accountId === null) {
            $accountId = OA_Permission::getAccountId();
        }
        if ($userId === null) {
            $userId = OA_Permission::getUserId();
        }
        if (empty($accountId) || empty($userId)) {
            return false;
        }
        if (!isset($permissions[$userId][$accountId])) {
            $doAccount_user_permission_assoc = OA_Dal::factoryDO('account_user_permission_assoc');
            $doAccount_user_permission_assoc->user_id = $userId;
            $doAccount_user_permission_assoc->account_id = $accountId;
            $doAccount_user_permission_assoc->find();
            while ($doAccount_user_permission_assoc->fetch()) {
                $permissions[$userId][$accountId][$doAccount_user_permission_assoc->permission_id] =
                    $doAccount_user_permission_assoc->is_allowed;
            }
        }
        return isset($permissions[$userId][$accountId][$permissionId]) ?
            $permissions[$userId][$accountId][$permissionId] : false;
    }

    /**
     * Check if a user is linked to the ADMIN account
     *
     * @static
     * @return boolean True if the currently logged in user is linked to the ADMIN account, false otherwise.
     */
    function isUserLinkedToAdmin()
    {
        if (OA_Permission::getCurrentUser()) {
            $doAccount = OA_Dal::factoryDO('accounts');
            $doAccount->account_type = OA_ACCOUNT_ADMIN;
            $doAccount_user_assoc = OA_Dal::factoryDO('account_user_assoc');
            $doAccount_user_assoc->user_id = OA_Permission::getUserId();
            $doAccount->joinAdd($doAccount_user_assoc);
            return (bool) $doAccount->count();
        }
        return false;
    }

    /**
     * A method which returns all the accounts linked to the user
     *
     * Returns array of:
     *   accountId => 'account name'
     * 
     * If $groupByType is equal true returns:
     *   accountType => accountId => 'account name'
     * 
     * where accountType is one of: OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER, etc.
     * 
     * @param boolean $groupByType
     * @return array
     */
    function getLinkedAccounts($groupByType = false)
    {
        $doAccount_user_Assoc = OA_Dal::factoryDO('account_user_assoc');
        $doAccount_user_Assoc->user_id = OA_Permission::getUserId();
        $doAccounts = OA_Dal::factoryDO('accounts');
        $doAccounts->orderBy('account_type, account_name');
        $doAccount_user_Assoc->joinAdd($doAccounts);
        $doAccount_user_Assoc->find();

        $aAccountsByType = array();
        while($doAccount_user_Assoc->fetch()) {
            $aAccountsByType[$doAccount_user_Assoc->account_type][$doAccount_user_Assoc->account_id] =
                $doAccount_user_Assoc->account_name;
        }
        if (isset($aAccountsByType[OA_ACCOUNT_ADMIN])) {
            $aAccountsByType = OA_Permission::mergeAdminAccounts($aAccountsByType);
        }
        if (!$groupByType) {
            $aAccounts = array();
            foreach ($aAccountsByType as $accountType => $aAccount) {
                foreach ($aAccount as $id => $name) {
                    $aAccounts[$id] = $name;
                }
            }
            return $aAccounts;
        }
        return $aAccountsByType;
    }

    function mergeAdminAccounts($aAccountsByType)
    {
        $doAccounts = OA_Dal::factoryDO('accounts');
        $doAccounts->account_type = OA_ACCOUNT_MANAGER;
        $doAccounts->find();
        while ($doAccounts->fetch()) {
            $aAccountsByType[$doAccounts->account_type][$doAccounts->account_id] =
                $doAccounts->account_name;
        }
        return $aAccountsByType;
    }

    /**
     * A method to retrieve the current user object from a session
     *
     * @static
     * @return OA_Permission_User on success or false otherwise
     */
    function &getCurrentUser()
    {
        global $session;
        if (isset($session['user'])) {
            return $session['user'];
        }
        $false = false;
        return $false;
    }

    /**
     * A method to retrieve the user ID of the currently logged in user
     *
     * @static
     * @return int
     */
    function getUserId()
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            return $oUser->aUser['user_id'];
        }
    }

    /**
     * A method to retrieve the username of the currently logged in user
     *
     * @static
     * @return string
     */
    function getUsername()
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            return $oUser->aUser['username'];
        }
    }

    /**
     * A method to retrieve the agency ID
     *
     * @static
     * @return int
     */
    function getAgencyId()
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            return (int) $oUser->aAccount['agency_id'];
        }
        return 0;
    }

    /**
     * A method to get the currently selected account user type
     *
     * @static
     * @param boolean $returnAsString
     * @return string
     */
    function getAccountTable($type = null)
    {
        if (!$type) {
            if (!($oUser = OA_Permission::getCurrentUser())) {
                return false;
            }
            $type = $oUser->aAccount['account_type'];
        }
        $aTypes = array(
           OA_ACCOUNT_ADMIN      => 'users',
           OA_ACCOUNT_ADVERTISER => 'clients',
           OA_ACCOUNT_TRAFFICKER => 'affiliates',
           OA_ACCOUNT_MANAGER    => 'agency'
        );

        return isset($aTypes[$type]) ? $aTypes[$type] : false;
    }

    /**
     * A method to get the currently selected account user type
     *
     * @static
     * @param boolean $returnAsString Return the account type as a string
     *                                conversion of the account type, rather
     *                                than the defined string. Optional,
     *                                default is "false".
     *
     * @return mixed If the a user is logged in, returns one of the constants:
     *                  - OA_ACCOUNT_ADMIN;
     *                  - OA_ACCOUNT_MANAGER;
     *                  - OA_ACCOUNT_ADVERTISER;
     *                  - OA_ACCOUNT_TRAFFICKER,
     *               or, if $returnAsString was true, the value of the constants
     *               converted to "String" format,
     *               of, if no user is logged in, null, or the empty string.
     */
    function getAccountType($returnAsString = false)
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            $type = $oUser->aAccount['account_type'];
            if ($returnAsString) {
                return ucfirst(strtolower($type));
            }
            return $type;
        }
        return $returnAsString ? '' : null;
    }

    /**
     * A method to return the entity id associated with the account
     *
     * @static
     * @return int
     */
    function getEntityId()
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            return (int) $oUser->aAccount['entity_id'];
        }

        return 0;
    }

    /**
     * A method to get the currently selected account ID
     *
     * @static
     * @return integer
     */
    function getAccountId()
    {
        if ($oUser = OA_Permission::getCurrentUser()) {
            return $oUser->aAccount['account_id'];
        }
        return 0;
    }

    /**
     * Returns accountId for entity
     *
     * @param string $entity  Entity name (clients, advertiser, agency etc)
     * @param integer $entityId  Entity ID (client id, advertiser id, etc)
     * @return integer  Account ID or false on error
     */
    function getAccountIdForEntity($entity, $entityId)
    {
        $doEntity = OA_Dal::staticGetDO($entity, $entityId);
        if (!$doEntity) {
            return false;
        }
        return $doEntity->account_id;
    }

    /**
     * Checks if username is still available and if
     * it is allowed to use.
     *
     * @static
     * @param string $oldName  Old username which we want to change
     * @param string $newName  New user name to change to
     * @return boolean  True if allowed
     */
    function isUsernameAllowed($oldName, $newName)
    {
        if (!empty($oldName) && $oldName == $newName) {
            return true;
        }
        $doUser = OA_Dal::factoryDO('users');
        if (!PEAR::isError($doUser) && $doUser->userExists($newName)) {
            return false;
        }
        return true;
    }

    /**
     * Gets a list of unique usernames.
     *
     * @static
     * @param unknown_type $removeName
     * @return array
     *
     * @TODO Remove this method once its use will be removed from UI
     */
    function getUniqueUserNames($removeName = null)
    {
        $uniqueUsers = array();
        $doUser = OA_Dal::factoryDO('users');
        if (PEAR::isError($doUser)) {
            return false;
        }
        $newUniqueNames = $doUser->getUniqueUsers();
        $uniqueUsers = array_merge($uniqueUsers, $newUniqueNames);

        ArrayUtils::unsetIfKeyNumeric($uniqueUsers, $removeName);
        return $uniqueUsers;
    }

	/**
	 * Store user rights per account
	 *
	 * @param array $aPermissions  Array of permission IDs
	 * @param integer $accountId  account ID
	 * @param integer $userId  user ID
	 * @param array $aAllowedPermissions  Array of allowed permissions - keys of array are permissions IDs
	 * @return true on success else false
	 */
	function storeUserAccountsPermissions($aPermissions, $accountId = null, $userId = null,
	   $aAllowedPermissions = null)
	{
	    if (empty($userId)) {
	        $userId = OA_Permission::getUserId();
	    }
	    if (empty($accountId)) {
	        $accountId = OA_Permission::getAccountId();
	    }
	    OA_Permission::deleteExistingPermissions($accountId, $userId, $aAllowedPermissions);

	    // add new permissions
	    foreach ($aPermissions as $permissionId) {
	        if (!is_null($aAllowedPermissions) && !isset($aAllowedPermissions[$permissionId])) {
	            // check if permission is on the list of allowed permissions
	            continue;
	        }
    	    $doAccount_user_permission_assoc = OA_Dal::factoryDO('account_user_permission_assoc');
    	    $doAccount_user_permission_assoc->account_id = $accountId;
    	    $doAccount_user_permission_assoc->user_id = $userId;
    	    $doAccount_user_permission_assoc->permission_id = $permissionId;
    	    $doAccount_user_permission_assoc->is_allowed = 1;
    	    if (!$doAccount_user_permission_assoc->insert()) {
    	        return false;
    	    }
	    }
	    return true;
	}
	
	/**
	 * Deletes existing users permissions. If list of permissions is provided it
	 * only clean up permissions from that list
	 *
	 * @param int $accountId
	 * @param int $userId
	 * @param array $allowedPermissions
	 */
	function deleteExistingPermissions($accountId, $userId, $allowedPermissions)
	{
	    if (is_array($allowedPermissions)) {
	        foreach ($allowedPermissions as $permissionId => $perm) {
        	    $doAccount_user_permission_assoc = OA_Dal::factoryDO('account_user_permission_assoc');
        	    $doAccount_user_permission_assoc->permission_id = $permissionId;
        	    $doAccount_user_permission_assoc->account_id = $accountId;
        	    $doAccount_user_permission_assoc->user_id = $userId;
        	    $doAccount_user_permission_assoc->delete();
	        }
	        
	    } else {
    	    $doAccount_user_permission_assoc = OA_Dal::factoryDO('account_user_permission_assoc');
    	    $doAccount_user_permission_assoc->account_id = $accountId;
    	    $doAccount_user_permission_assoc->user_id = $userId;
    	    $doAccount_user_permission_assoc->delete();
	    }
	}

}

?>