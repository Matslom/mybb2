<?php
/**
 * @author    MyBB Group
 * @version   2.0.0
 * @package   mybb/core
 * @license   http://www.mybb.com/licenses/bsd3 BSD-3
 */

namespace MyBB\Core\Permissions\Traits;

use MyBB\Core\Services\PermissionChecker;

trait InheritPermissionableTrait
{
    use PermissionableTrait;

    /**
     * Returns an array of permissions where a positive permission in one of the parents overrides negative permissions
     * in its child
     *
     * @return array
     */
    public static function getPositiveParentOverrides() : array
    {
        return [];
    }

    /**
     * Returns an array of permissions where a negative permission in one of the parents overrides positive permissions
     * in its child By default the viewable permission is returned
     *
     * @return array
     */
    public static function getNegativeParentOverrides() : array
    {
        return [
            static::getViewablePermission(),
        ];
    }

    /**
     * @return InheritPermissionableTrait
     */
    public function getParent()
    {
        return $this->parent;
    }
}
