<?php

namespace phpMyFAQ\Permission;

/**
 * The medium permission class provides group rights.
 *
 * 
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at http://mozilla.org/MPL/2.0/.
 *
 * @category  phpMyFAQ
 * @author    Lars Tiedemann <php@larstiedemann.de>
 * @copyright 2005-2019 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2005-09-17
 */

use phpMyFAQ\Configuration;
use phpMyFAQ\Db;
use phpMyFAQ\User\CurrentUser;

if (!defined('IS_VALID_PHPMYFAQ')) {
    exit();
}

/**
 * PMF_Perm_Medium.
 *
 * @category  phpMyFAQ
 * @author    Lars Tiedemann <php@larstiedemann.de>
 * @copyright 2005-2019 phpMyFAQ Team
 * @license   http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2005-09-17
 */
class MediumPermission extends BasicPermission
{
    /**
     * Default data for new groups.
     *
     * @var array
     */
    public $defaultGroupData = [
        'name' => 'DEFAULT_GROUP',
        'description' => 'Short group description.',
        'auto_join' => false,
    ];

    /**
     * Constructor.
     *
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        parent::__construct($config);
    }

    /**
     * Returns true if the group specified by $groupId owns the
     * right given by $rightId, otherwise false.
     *
     * @param int $groupId Group ID
     * @param int $rightId Right ID
     * 
     * @return bool
     */
    public function checkGroupRight($groupId, $rightId)
    {
        // check input
        if ($rightId <= 0 || $groupId <= 0 || !is_numeric($rightId) || !is_numeric($groupId)) {
            return false;
        }

        // check right
        $select = sprintf('
            SELECT
                fr.right_id AS right_id
            FROM
                %sfaqright fr,
                %sfaqgroup_right fgr,
                %sfaqgroup fg
            WHERE
                fr.right_id = %d AND
                fr.right_id = fgr.right_id AND
                fg.group_id = fgr.group_id AND
                fg.group_id = %d',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $rightId,
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) >= 1) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array that contains the right-IDs of all
     * group-rights the group $groupId owns.
     *
     * @param int $groupId Group ID
     * 
     * @return array
     */
    public function getGroupRights($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return [];
        }
        // check right
        $select = sprintf('
            SELECT
                fr.right_id AS right_id
            FROM
                %sfaqright fr,
                %sfaqgroup_right fgr,
                %sfaqgroup fg
            WHERE
                fg.group_id = %d AND
                fg.group_id = fgr.group_id AND
                fr.right_id = fgr.right_id',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        $result = [];
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $result[] = $row['right_id'];
        }

        return $result;
    }

    /**
     * Returns true, if the user given by $userId owns the right
     * specified by $right. It does not matter if the user owns this
     * right as a user-right or because of a group-membership.
     * The parameter $right may be a right-ID (recommended for
     * performance) or a right-name.
     *
     * @param int   $userId Group ID
     * @param mixed $right  Rights
     *
     * @return bool
     */
    public function checkRight($userId, $right)
    {
        $user = new CurrentUser($this->config);
        $user->getUserById($userId);

        if ($user->isSuperAdmin()) {
            return true;
        }

        // get right id
        if (!is_numeric($right) && is_string($right)) {
            $right = $this->getRightId($right);
        }

        // check user right and group right
        if (
            $this->checkUserGroupRight($userId, $right) ||
            $this->checkUserRight($userId, $right)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Grants the group given by $groupId the right specified by
     * $rightId.
     *
     * @param int $groupId Group ID
     * @param int $rightId Right ID
     * 
     * @return bool
     */
    public function grantGroupRight($groupId, $rightId)
    {
        // check input
        if ($rightId <= 0 || $groupId <= 0 || !is_numeric($rightId) || !is_numeric($groupId)) {
            return false;
        }

        // is right for users?
        $right_data = $this->getRightData($rightId);
        if (!$right_data['for_groups']) {
            return false;
        }

        // grant right
        $insert = sprintf('
            INSERT INTO
                %sfaqgroup_right
            (group_id, right_id)
                VALUES
            (%d, %d)',
            Db::getTablePrefix(),
            $groupId,
            $rightId
        );

        $res = $this->config->getDb()->query($insert);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Refuses the group given by $groupId the right specified by
     * $rightId.
     *
     * @param int $groupId Group ID
     * @param int $rightId Right ID
     *
     * @return bool
     */
    public function refuseGroupRight($groupId, $rightId)
    {
        // check input
        if ($rightId <= 0 || $groupId <= 0 || !is_numeric($rightId) || !is_numeric($groupId)) {
            return false;
        }

        // grant right
        $delete = sprintf('
            DELETE FROM
                %sfaqgroup_right
            WHERE
                group_id = %d AND
                right_id = %d',
            Db::getTablePrefix(),
            $groupId,
            $rightId
        );

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Adds a new group to the database and returns the ID of the
     * new group. The associative array $groupData contains the
     * data for the new group.
     *
     * @param array $groupData Array of group data
     *
     * @return int
     */
    public function addGroup(Array $groupData)
    {
        // check if group already exists
        if ($this->getGroupId($groupData['name']) > 0) {
            return 0;
        }

        $nextId = $this->config->getDb()->nextId(Db::getTablePrefix().'faqgroup', 'group_id');
        $groupData = $this->checkGroupData($groupData);
        $insert = sprintf("
            INSERT INTO
                %sfaqgroup
            (group_id, name, description, auto_join)
                VALUES
            (%d, '%s', '%s', '%s')",
            Db::getTablePrefix(),
            $nextId,
            $groupData['name'],
            $groupData['description'],
            (int) $groupData['auto_join']
        );

        $res = $this->config->getDb()->query($insert);
        if (!$res) {
            return 0;
        }

        return $nextId;
    }

    /**
     * Changes the group data of the given group.
     *
     * @param int   $groupId   Group ID
     * @param array $groupData Array of group data
     *
     * @return bool
     */
    public function changeGroup($groupId, Array $groupData)
    {
        $checkedData = $this->checkGroupData($groupData);
        $set = '';
        $comma = '';

        foreach ($groupData as $key => $val) {
            $set  .= $comma.$key." = '".$this->config->getDb()->escape($checkedData[$key])."'";
            $comma = ",\n                ";
        }

        $update = sprintf('
            UPDATE
                %sfaqgroup
            SET
                %s
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $set,
            $groupId
        );

        $res = $this->config->getDb()->query($update);

        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Removes the group given by $groupId from the database.
     * Returns true on success, otherwise false.
     *
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function deleteGroup($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaqgroup
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaquser_group
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaqgroup_right
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the user given by $userId is a member of
     * the group specified by $groupId, otherwise false.
     *
     * @param int $userId  User ID
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function isGroupMember($userId, $groupId)
    {
        if ($userId <= 0 || $groupId <= 0 || !is_numeric($userId) || !is_numeric($groupId)) {
            return false;
        }

        $select = sprintf('
            SELECT
                fu.user_id AS user_id
            FROM
                %sfaquser fu,
                %sfaquser_group fug,
                %sfaqgroup fg
            WHERE
                fu.user_id  = %d AND
                fu.user_id  = fug.user_id AND
                fg.group_id = fug.group_id AND
                fg.group_id = %d',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $userId,
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) == 1) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array that contains the user-IDs of all members
     * of the group $groupId.
     *
     * @param int $groupId Group ID
     *
     * @return array
     */
    public function getGroupMembers($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return [];
        }

        $select = sprintf('
            SELECT
                fu.user_id AS user_id
            FROM
                %sfaquser fu,
                %sfaquser_group fug,
                %sfaqgroup fg
            WHERE
                fg.group_id = %d AND
                fg.group_id = fug.group_id AND
                fu.user_id  = fug.user_id',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        $result = [];
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $result[] = $row['user_id'];
        }

        return $result;
    }

    /**
     * Adds a new member $userId to the group $groupId.
     * Returns true on success, otherwise false.
     *
     * @param int $userId  User ID
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function addToGroup($userId, $groupId)
    {
        if ($userId <= 0 || $groupId <= 0 || !is_numeric($userId) || !is_numeric($groupId)) {
            return false;
        }

        if (!$this->getGroupData($groupId)) {
            return false;
        }

        // add user to group
        $insert = sprintf('
            INSERT INTO
                %sfaquser_group
            (user_id, group_id)
               VALUES
            (%d, %d)',
            Db::getTablePrefix(),
            $userId,
            $groupId
        );

        $res = $this->config->getDb()->query($insert);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Removes a user $userId from the group $groupId.
     * Returns true on success, otherwise false.
     *
     * @param int $userId  User ID
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function removeFromGroup($userId, $groupId)
    {
        if ($userId <= 0 || $groupId <= 0 || !is_numeric($userId) || !is_numeric($groupId)) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaquser_group
            WHERE
                user_id  = %d AND
                group_id = %d',
            Db::getTablePrefix(),
            $userId,
            $groupId);

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Returns the ID of the group that has the name $name. Returns
     * 0 if the group-name cannot be found.
     *
     * @param string $name Group name
     *
     * @return int
     */
    public function getGroupId($name)
    {
        $select = sprintf("
            SELECT
                group_id
            FROM
                %sfaqgroup
            WHERE
                name = '%s'",
            Db::getTablePrefix(),
            $this->config->getDb()->escape($name)
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) != 1) {
            return 0;
        }
        $row = $this->config->getDb()->fetchArray($res);

        return $row['group_id'];
    }

    /**
     * Returns an associative array with the group-data of the group
     * $groupId.
     *
     * @param int $groupId Group ID
     *
     * @return array
     */
    public function getGroupData($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return [];
        }

        $select = sprintf('
            SELECT
                group_id,
                name,
                description,
                auto_join
            FROM
                %sfaqgroup
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) != 1) {
            return [];
        }

        return $this->config->getDb()->fetchArray($res);
    }

    /**
     * Returns an array that contains the IDs of all groups in which
     * the user $userId is a member.
     *
     * @param int $userId User ID
     *
     * @return array
     */
    public function getUserGroups($userId)
    {
        if ($userId <= 0 || !is_numeric($userId)) {
            return [-1];
        }

        $select = sprintf('
            SELECT
                fg.group_id AS group_id
            FROM
                %sfaquser fu,
                %sfaquser_group fug,
                %sfaqgroup fg
            WHERE
                fu.user_id  = %d AND
                fu.user_id  = fug.user_id AND
                fg.group_id = fug.group_id',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $userId
        );

        $res = $this->config->getDb()->query($select);
        $result = array(-1);
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $result[] = $row['group_id'];
        }

        return $result;
    }

    /**
     * Returns an array with the IDs of all groups stored in the
     * database if no user ID is passed.
     * @param int userId 
     * @return array
     */
    public function getAllGroups($userId = 1)
    {
        $select = sprintf('
            SELECT
                group_id
            FROM
                %sfaqgroup',
            Db::getTablePrefix()
        );
        if ($userId != 1){
            $select = sprintf('
                SELECT
                    fg.group_id
                FROM
                    %sfaqgroup fg
                LEFT JOIN
                    %sfaquser_group fug ON fg.group_id=fug.group_id
                WHERE
                    fug.user_id = %d',
                Db::getTablePrefix(),
                Db::getTablePrefix(),
                $userId
            );
        }

        $res = $this->config->getDb()->query($select);
        $result = [];
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $result[] = $row['group_id'];
        }

        return $result;
    }

    /**
     * Get all groups in <option> tags.
     *
     * @todo Move into the Helper class
     *
     * @param array $groups Selected groups
     * @param integer $userId
     * @return string
     */
    public function getAllGroupsOptions(Array $groups, $userId = 1)
    {
        $options = '';
        $allGroups = $this->getAllGroups($userId);

        foreach ($allGroups as $groupId) {
            if (-1 != $groupId) {
                $options .= sprintf('<option value="%d"%s>%s</option>',
                    $groupId,
                    (in_array($groupId, $groups) ? ' selected' : ''),
                    $this->getGroupName($groupId)
                );
            }
        }

        return $options;
    }

    /**
     * Returns true if the user $userId owns the right $rightId
     * because of a group-membership, otherwise false.
     *
     * @param int $userId  User ID
     * @param int $rightId Right ID
     *
     * @return bool
     */
    public function checkUserGroupRight($userId, $rightId)
    {
        // check input
        if ($rightId <= 0 || $userId <= 0 || !is_numeric($rightId) || !is_numeric($userId)) {
            return false;
        }

        $select = sprintf('
            SELECT
                fr.right_id AS right_id
            FROM
                %sfaqright fr,
                %sfaqgroup_right fgr,
                %sfaqgroup fg,
                %sfaquser_group fug,
                %sfaquser fu
            WHERE
                fr.right_id = %d AND
                fr.right_id = fgr.right_id AND
                fg.group_id = fgr.group_id AND
                fg.group_id = fug.group_id AND
                fu.user_id  = fug.user_id AND
                fu.user_id  = %d',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $rightId,
            $userId
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) == 1) {
            return true;
        }

        return false;
    }

    /**
     * Checks the given associative array $groupData. If a
     * parameter is incorrect or is missing, it will be replaced
     * by the default values in $this->defaultGroupData.
     * Returns the corrected $groupData associative array.
     *
     * @param array $groupData Array of group data
     *
     * @return array
     */
    public function checkGroupData(Array $groupData)
    {
        if (!isset($groupData['name']) || !is_string($groupData['name'])) {
            $groupData['name'] = $this->defaultGroupData['name'];
        }
        if (!isset($groupData['description']) || !is_string($groupData['description'])) {
            $groupData['description'] = $this->defaultGroupData['description'];
        }
        if (!isset($groupData['auto_join'])) {
            $groupData['auto_join'] = $this->defaultGroupData['auto_join'];
        }
        $groupData['auto_join'] = (int) $groupData['auto_join'];

        return $groupData;
    }

    /**
     * Returns an array that contains the right-IDs of all rights
     * the user $userId owns. User-rights and the rights the user
     * owns because of a group-membership are taken into account.
     *
     * @param int $userId User ID
     *
     * @return array
     */
    public function getAllUserRights($userId)
    {
        if ($userId <= 0 || !is_numeric($userId)) {
            return [];
        }
        $userRights = $this->getUserRights($userId);
        $groupRights = $this->getUserGroupRights($userId);

        return array_unique(array_merge($userRights, $groupRights));
    }

    /**
     * Adds the user $userId to all groups with the auto_join
     * option. By using the auto_join option, user administration
     * can be much easier. For example by setting this option only
     * for a single group called 'All Users'. The autoJoin() method
     * then has to be called every time a new user registers.
     * Returns true on success, otherwise false.
     *
     * @param int $userId User ID
     *
     * @return bool
     */
    public function autoJoin($userId)
    {
        if ($userId <= 0 || !is_numeric($userId)) {
            return false;
        }

        $select = sprintf('
            SELECT
                group_id
            FROM
                %sfaqgroup
            WHERE
                auto_join = 1',
            Db::getTablePrefix()
        );

        $res = $this->config->getDb()->query($select);
        if (!$res) {
            return false;
        }

        $auto_join = [];
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $auto_join[] = $row['group_id'];
        }

        // add to groups
        foreach ($auto_join as $groupId) {
            $this->addToGroup($userId, $groupId);
        }

        return true;
    }

    /**
     * Removes the user $userId from all groups.
     * Returns true on success, otherwise false.
     *
     * @param int $userId User ID
     *
     * @return bool
     */
    public function removeFromAllGroups($userId)
    {
        if ($userId <= 0 || !is_numeric($userId)) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaquser_group
            WHERE
                user_id  = %d',
            Db::getTablePrefix(),
            $userId);

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Returns an array that contains the IDs of all rights the user
     * $userId owns because of a group-membership.
     *
     * @param int $userId User ID
     *
     * @return array
     */
    public function getUserGroupRights($userId)
    {
        if ($userId <= 0 || !is_numeric($userId)) {
            return [];
        }

        $select = sprintf('
            SELECT
                fr.right_id AS right_id
            FROM
                %sfaqright fr,
                %sfaqgroup_right fgr,
                %sfaqgroup fg,
                %sfaquser_group fug,
                %sfaquser fu
            WHERE
                fu.user_id  = %d AND
                fu.user_id  = fug.user_id AND
                fg.group_id = fug.group_id AND
                fg.group_id = fgr.group_id AND
                fr.right_id = fgr.right_id',
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            Db::getTablePrefix(),
            $userId);

        $res = $this->config->getDb()->query($select);
        $result = [];
        while ($row = $this->config->getDb()->fetchArray($res)) {
            $result[] = $row['right_id'];
        }

        return $result;
    }

    /**
     * Refuses all group rights.
     * Returns true on success, otherwise false.
     *
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function refuseAllGroupRights($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return false;
        }

        $delete = sprintf('
            DELETE FROM
                %sfaqgroup_right
            WHERE
                group_id  = %d',
            Db::getTablePrefix(),
            $groupId);

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }

    /**
     * Returns the name of the group $groupId.
     *
     * @param int $groupId Group ID
     *
     * @return string
     */
    public function getGroupName($groupId)
    {
        if ($groupId <= 0 || !is_numeric($groupId)) {
            return '-';
        }

        $select = sprintf('
            SELECT
                name
            FROM
                %sfaqgroup
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId
        );

        $res = $this->config->getDb()->query($select);
        if ($this->config->getDb()->numRows($res) != 1) {
            return '-';
        }
        $row = $this->config->getDb()->fetchArray($res);

        return $row['name'];
    }

    /**
     * Removes all users from the group $groupId.
     * Returns true on success, otherwise false.
     *
     * @param int $groupId Group ID
     *
     * @return bool
     */
    public function removeAllUsersFromGroup($groupId)
    {
        if ($groupId <= 0 or !is_numeric($groupId)) {
            return false;
        }

        // remove all user from group
        $delete = sprintf('
            DELETE FROM
                %sfaquser_group
            WHERE
                group_id = %d',
            Db::getTablePrefix(),
            $groupId);

        $res = $this->config->getDb()->query($delete);
        if (!$res) {
            return false;
        }

        return true;
    }
}
