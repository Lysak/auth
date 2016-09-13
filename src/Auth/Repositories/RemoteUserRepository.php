<?php
/*
 * This file is part of the FourCms Auth package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FourCms\Auth\Repositories;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Logging\Log as LogContract;
use InvalidArgumentException;
use Longman\IPTools\Ip;

class RemoteUserRepository
{

    /**
     * HTTP Client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Application config repository.
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;

    /**
     * Application cache repository.
     *
     * @var \Illuminate\Cache\Repository
     */
    protected $cache;

    /**
     * Application logger.
     *
     * @var \Illuminate\Logging\Writer
     */
    protected $log;

    public function __construct(Client $client, ConfigContract $config, CacheContract $cache, LogContract $log)
    {
        $this->client = $client;
        $this->config = $config;
        $this->cache  = $cache;
        $this->log    = $log;
    }

    public function getRemoteUser($username, $password)
    {
        if (empty($username)) {
            throw new InvalidArgumentException('Username is empty!');
        }

        $timeout    = 1440;
        $cache_hash = 'login_superuser_' . md5($username . '_' . $password);

        if (!$this->cache->has($cache_hash)) {
            try {
                $res = $this->client->get(
                    $this->config->get('auth.providers.itdc.endpoint') . '/user/' . rawurlencode($username),
                    [
                        'query'           => [
                            'token' => $this->config->get('app.key'),
                        ],
                        'verify'          => true,
                        'connect_timeout' => 30,
                        //'debug' => $this->config->get('app.debug'),
                    ]
                );
                $data = $res->getBody()->getContents();
                if (empty($data)) {
                    return false;
                }
                $data = json_decode($data, true);
                if (empty($data)) {
                    return false;
                }
                $this->cache->put($cache_hash, $data, $timeout);
            } catch (Exception $e) {
                $this->log->warning('Login failed (ITDC driver): ' . $e->getMessage());
                return false;
            }
        } else {
            $data = $this->cache->get($cache_hash);
        }

        if ($data['status'] != 'success') {
            $this->log->warning('Login failed (ITDC driver): Can not retrieve data from service', $data);
            return false;
        }

        if (empty($data['data'])) {
            $this->log->warning('Login failed (ITDC driver): data is empty', $data);
            return false;
        }

        if ($this->config->get('auth.providers.itdc.check_ip', true)) {
            if (!empty($data['data']['ip'])) {
                if (strpos($data['data']['ip'], ',') !== false) {
                    $ips = explode(',', $data['data']['ip']);
                } elseif (strpos($data['data']['ip'], '|') !== false) {
                    $ips = explode('|', $data['data']['ip']);
                } else {
                    $ips = [$data['data']['ip']];
                }

                if (!empty($ips)) {
                    $ips = array_map('trim', $ips);
                    $ip  = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : false;

                    if ($ip) {
                        try {
                            $match = Ip::match($ip, $ips);
                            if (!$match) {
                                $this->log->warning('Login failed (ITDC driver): client ip "' . $ip . '" not match with ip ranges: ' . implode(', ',
                                                                                                                                               $ips));
                                return false;
                            }
                        } catch (InvalidArgumentException $e) {
                            $this->log->warning('Login failed (ITDC driver): ' . $e->getMessage());
                            return false;
                        }
                    }
                }
            }
        }
        return $data['data'];
    }
}
