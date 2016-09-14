<?php
/*
 * This file is part of the FourCms Auth package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FourCms\Auth;

use Cache;
use DB;

trait RemoteUserTrait
{

    protected $remote_user;

    public function loadRemoteUser(array $attributes)
    {
        $this->fill($attributes);
        $this->remote_user = true;
        $this->setRemoteId($attributes);

        if (config('auth.providers.itdc.auto_save')) {
            $user = $this->find(['id' => $this->id])->first();
            if (config('auth.providers.itdc.attach_role')) {
                $this->attachAdminRoleForRemoteUser($this->id);
            }
            if (!$user) {
                $this->password     = bcrypt(uniqid());
                $this->is_developer = 1;
                $this->save();
            }
        } else {
            Cache::forever('remoteuser_' . $this->id, $this->getAttributes());
        }

        // Set password from service for validation with input
        $this->password = $attributes['password'];

        return $this;
    }

    protected function setRemoteId(array $attributes)
    {
        if ($this->incrementing) {
            $this->id = $attributes['id'];
        } else {
            $this->id = $attributes['uuid'];
        }
    }

    protected function attachAdminRoleForRemoteUser($id)
    {
        $role = DB::table('roles')
            ->select()
            ->where('name', '=', config('auth.providers.itdc.attach-role', 'super_admin'))
            ->first();
        if (!$role) {
            return false;
        }

        $this->role_id = $role->id;

        return true;
    }
}
