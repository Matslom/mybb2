<?php
/**
 * User repository implementation, using Eloquent ORM.
 *
 * @author    MyBB Group
 * @version   2.0.0
 * @package   mybb/core
 * @license   http://www.mybb.com/licenses/bsd3 BSD-3
 */

namespace MyBB\Core\Database\Repositories\Eloquent;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Query\Builder;
use MyBB\Core\Database\Models\User;
use MyBB\Core\Database\Repositories\UserRepositoryInterface;
use MyBB\Core\Permissions\PermissionChecker;
use MyBB\Settings\Models\Setting;

class UserRepository implements UserRepositoryInterface
{
    /**
     * @var User $userModel
     */
    protected $userModel;

    /**
     * @var PermissionChecker
     */
    private $permissionChecker;

    /**
     * @var Guard
     */
    private $guard;

    /**
     * @param User $userModel The model to use for users.
     * @param PermissionChecker $permissionChecker
     * @param Guard $guard
     */
    public function __construct(
        User $userModel,
        PermissionChecker $permissionChecker,
        Guard $guard
    ) {
        $this->userModel = $userModel;
        $this->permissionChecker = $permissionChecker;
        $this->guard = $guard;
    }

    /**
     * Get all users.
     *
     * @param string $sortBy
     * @param string $sortDir
     * @param int $perPage
     *
     * @return mixed
     */
    public function all(string $sortBy = 'created_at', string $sortDir = 'asc', int $perPage = 10)
    {
        return $this->userModel->orderBy($sortBy, $sortDir)->paginate($perPage);
    }
    
    /**
     * Get all users who match a certain set of criteria.
     *
     * @param string username
     * @param string email
     * @param int role_id
     * @param string $sortBy
     * @param string $sortDir
     * @param int $perPage
     *
     * @return mixed
     */
    public function search(string $username = "", string $email = "", int $roleId = 0, string $sortBy = 'created_at', string $sortDir = 'asc', int $perPage = 10)
    {
        if ($roleId == 0) {
            return $this->userModel->where('name', 'like', "%{$username}%")->where('email', 'like', "%{$email}%")->orderBy($sortBy, $sortDir)->paginate($perPage);
        }
        
        // to-do: role search
    }

    /**
     * Get all users active in the last x minutes
     *
     * @param int $minutes The number of minutes which are considered as "online time"
     * @param string $orderBy
     * @param string $orderDir
     * @param int $num The number of users to return. Set to 0 to get all users
     *
     * @return mixed
     */
    public function online(int $minutes = 15, string $orderBy = 'last_visit', string $orderDir = 'desc', int $num = 20)
    {
        // If the user visited the logout page as last he's not online anymore
        /** @var Builder $baseQuery */
        $baseQuery = $this->userModel->where('last_visit', '>=', new \DateTime("{$minutes} minutes ago"))
            ->where('last_page', '!=', 'auth/logout')
            ->orderBy('users.' . $orderBy, $orderDir);

        // No need to add anymore if the user has permission to view anyone
        if (!$this->permissionChecker->hasPermission('user', null, 'canViewAllOnline')) {
            // First get the id of our setting
            $settingId = Setting::where('name', 'user.showonline')->first()->id;

            // Now join the correct setting_values row
            $baseQuery->leftJoin('setting_values', function ($join) use ($settingId) {
                $join->on('setting_values.user_id', '=', 'users.id')->where(
                    'setting_values.setting_id',
                    '=',
                    $settingId
                );
            });

            // Either the setting is true or not set...
            $baseQuery->where(function ($query) {
                $query->where('setting_values.value', true)->orWhereNull('setting_values.value');

                // ... or we're querying our row at the moment
                if ($this->guard->check()) {
                    $query->orWhere('users.id', '=', $this->guard->user()->id);
                }
            });
        }

        if ($num > 0) {
            return $baseQuery->paginate($num, ['users.*']);
        }

        return $baseQuery->get(['users.*']);
    }

    /**
     * Find a single user by ID.
     *
     * @param int $id The ID of the user to find.
     *
     * @return mixed
     */
    public function find(int $id = 0)
    {
        return $this->userModel->find($id);
    }

    /**
     * Find a single user by its username.
     *
     * @param string $username The username of the user. Eg: 'admin'.
     *
     * @return mixed
     */
    public function findByUsername(string $username = '')
    {
        return $this->userModel->whereNname($username)->first();
    }

    /**
     * Create a new user
     *
     * @param array $details Details about the user.
     *
     * @return User
     */
    public function create(array $details = []) : User
    {
        $user = $this->userModel->create($details);
        $user->setCreatedAt($user->freshTimestamp())
            ->setUpdatedAt($user->freshTimestamp())->save();
        return $user;
    }

    /**
     * Update user
     *
     * @param User $user The user to edit
     * @param array $userDetails The details of the user.
     *
     * @return User
     */
    public function update(User $user, array $userDetails = []) : User
    {
        $user->update($userDetails);

        return $user;
    }
    
    /**
     * Delete a user
     *
     * @param int $id The ID of the user you want to delete.
     *
     * @return mixed
     */
    public function delete(int $id = 0)
    {
        $user = $this->userModel->find($id);
        //$user->activity()->detach();
        $user->conversations()->detach();
        $user->topics()->update(['user_id' => null,'username' => ""]);
        $user->posts()->update(['user_id' => null,'username' => ""]);
        $user->roles()->detach();
        return $user->delete();
    }
}
