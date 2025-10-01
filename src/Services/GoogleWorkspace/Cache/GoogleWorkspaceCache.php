<?php

namespace Bu\Gws\Services\GoogleWorkspace\Cache;

use Illuminate\Support\Facades\Redis;
use Google\Service\Directory\User;

class GoogleWorkspaceCache
{
    protected $redis;
    protected $prefix;
    protected $ttl;

    /**
     * Create a new cache instance.
     *
     * @param \Illuminate\Redis\Connections\Connection $redis
     */
    public function __construct($redis)
    {
        $this->redis = $redis;
        $this->prefix = config('google-workspace.cache.prefix');
        $this->ttl = config('google-workspace.cache.ttl');
    }

    /**
     * Get or cache a user by email.
     *
     * @param string $email
     * @param callable $callback
     * @return mixed
     */
    public function rememberUser($email, $callback)
    {
        $key = "{$this->prefix}user:{$email}";

        if ($this->redis->exists($key)) {
            $cached = unserialize($this->redis->get($key));
            if ($cached instanceof User) {
                return $cached;
            }
        }

        $result = $callback();
        $this->redis->setex($key, $this->ttl['user'], serialize($result));
        return $result;
    }

    /**
     * Cache user list results.
     *
     * @param string $domain
     * @param array $options
     * @param mixed $results
     * @return void
     */
    public function cacheUserList($domain, array $options, $results)
    {
        $key = $this->generateListKey($domain, $options);
        $this->redis->setex($key, $this->ttl['default'], serialize($results));
    }

    /**
     * Get cached user list.
     *
     * @param string $domain
     * @param array $options
     * @return mixed|null
     */
    public function getCachedUserList($domain, array $options)
    {
        $key = $this->generateListKey($domain, $options);
        if ($this->redis->exists($key)) {
            return unserialize($this->redis->get($key));
        }
        return null;
    }

    /**
     * Invalidate user cache.
     *
     * @param string $email
     * @return void
     */
    public function invalidateUser($email)
    {
        $key = "{$this->prefix}user:{$email}";
        $this->redis->del($key);
    }

    /**
     * Invalidate domain cache.
     *
     * @param string $domain
     * @return void
     */
    public function invalidateDomain($domain)
    {
        $pattern = "{$this->prefix}domain:{$domain}:*";
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del(...$keys);
        }
    }

    /**
     * Generate a cache key for user lists.
     *
     * @param string $domain
     * @param array $options
     * @return string
     */
    protected function generateListKey($domain, array $options): string
    {
        $optionsHash = md5(serialize($options));
        return "{$this->prefix}domain:{$domain}:list:{$optionsHash}";
    }

    /**
     * Clear all Google Workspace related cache.
     *
     * @return void
     */
    public function flushAll()
    {
        $pattern = "{$this->prefix}*";
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del(...$keys);
        }
    }
}
