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

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as LaravelUserProvider;
use Illuminate\Contracts\Cache\Factory as CacheContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Support\Str;
use FourCms\Auth\Repositories\RemoteUserRepository;

class RemoteUserProvider implements LaravelUserProvider
{

    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The cache implementation.
     *
     * @var \Illuminate\Contracts\Cache\Factory
     */
    protected $cache;

    /**
     * The Eloquent user model.
     *
     * @var string
     */
    protected $model;

    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Custom User Repository.
     *
     * @var \Longman\Platfourm\Auth\UserRepository
     */
    protected $repository;

    /**
     * Create a new database user provider.
     *
     * @param  \Illuminate\Contracts\Hashing\Hasher   $hasher
     * @param  \Longman\Platfourm\Auth\UserRepository $repository
     * @param  array                                  $config
     * @return void
     */
    public function __construct(
        HasherContract $hasher,
        CacheContract $cache,
        RemoteUserRepository $repository,
        array $config
    ) {
        $this->hasher     = $hasher;
        $this->cache      = $cache;
        $this->config     = $config;
        $this->repository = $repository;

        $this->setModel($config['model']);
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel()
    {
        $class = '\\' . ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        if (empty($this->config['auto_save'])) {
            if (!$this->cache->has('remoteuser_' . $identifier)) {
                return null;
            }
            $attributes = $this->cache->get('remoteuser_' . $identifier);

            $user = $this->createModel()->fill($attributes);
        } else {
            $user = $this->createModel()->newQuery()->find($identifier);
        }

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        throw new \Exception('Not implemented');
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string                                     $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);

        $user->save();
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials)) {
            return;
        }

        $username = $this->itdcMailInCredentials($credentials);

        if ($username) {
            $password = $credentials['password'];
            $user     = $this->retrieveFromService($username, $password);
        } else {
            $user = $this->retrieveFromEloquent($credentials);
        }

        return $user;
    }

    protected function itdcMailInCredentials(array $credentials)
    {
        $username = null;
        foreach ($credentials as $key => $value) {
            if (!Str::contains($key, 'password') && Str::contains($value, '@itdc.ge')) {
                $username = $value;
                break;
            }
        }
        return $username;
    }

    protected function retrieveFromEloquent(array $credentials)
    {
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (!Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function retrieveFromService($username, $password)
    {
        if (empty($username) || empty($password)) {
            return;
        }

        $user = $this->repository->getRemoteUser($username, $password);
        if (empty($user)) {
            return;
        }

        $model = $this->createModel();
        $model->loadRemoteUser($user);

        return $model;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  array                                      $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];

        if ($this->itdcMailInCredentials($credentials)) {
            return sha1(md5($plain)) === $user->getAuthPassword();
        }

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Gets the hasher implementation.
     *
     * @return \Illuminate\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        return $this->hasher;
    }

    /**
     * Sets the hasher implementation.
     *
     * @param  \Illuminate\Contracts\Hashing\Hasher $hasher
     * @return $this
     */
    public function setHasher(HasherContract $hasher)
    {
        $this->hasher = $hasher;

        return $this;
    }

    /**
     * Gets the name of the Eloquent user model.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model.
     *
     * @param  string $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

}
