<?php namespace Nabeghe\Configurator;

/**
 * Abstract Configurator class for managing configuration data with file persistence.
 * Supports ArrayAccess, dot notation, caching and dynamic proxy creation.
 */
#[\AllowDynamicProperties]
abstract class Configurator implements \ArrayAccess
{
    /**
     * Default configuration values for all sections.
     */
    const array DEFAULTS = [];

    /**
     * The base configuration provided during instantiation.
     *
     * @var array|null
     */
    public readonly ?array $baseConfig;

    /**
     * Array of Proxy instances for each configuration section.
     *
     * @var Proxy[]
     */
    protected ?array $proxies = null;

    /**
     * List of available configuration files in the path.
     *
     * @var array
     */
    protected array $availableFiles;

    /**
     * Cache identifier for storing file metadata.
     *
     * @var string|null
     */
    protected ?string $cacheName = null;

    /**
     * Constructs a new Configurator instance.
     *
     * @param  string|null  $path  Directory path for configuration files.
     * @param  array  $config  Initial configuration data.
     */
    public function __construct(
        protected ?string $path = null,
        protected array $config = [],
        protected bool $apcuAllowed = false,
    ) {
        if ($this->isLoadable() && $this->config) {
            $this->baseConfig = $this->config;
        } else {
            $this->baseConfig = null;
        }

        if ($this->isLoadable() && file_exists($this->path)) {
            $mtime = filemtime($this->path);

            $cache = $this->getCache();

            if (is_array($cache) && isset($cache['available_files']) && is_array($cache['available_files']) && (!isset($cache['time']) || $cache['time'] == $mtime)) {
                $this->availableFiles = $cache['available_files'];
            } else {
                $this->updateCache();
            }
        } else {
            $this->availableFiles = [];
        }
    }

    /**
     * Magic getter for retrieving a Proxy for a configuration section.
     *
     * @param  string  $name  The section name.
     * @return Proxy|null The Proxy instance or null.
     */
    public function __get(string $name)
    {
        if (!isset($this->proxies[$name])) {
            $this->proxies[$name] = new Proxy($this, $name);

            if ($this->isLoadable() && !isset($this->config[$name])) {
                $this->config[$name] = $this->load($name) ?? [];
            }
        }

        return $this->proxies[$name] ?? null;
    }

    /**
     * Magic setter for assigning a Proxy or creating a new section.
     *
     * @param  string  $name  The section name.
     * @param  mixed  $value  A Proxy instance or configuration data.
     * @return void
     */
    public function __set(string $name, $value): void
    {
        $this->proxies[$name] = $value instanceof Proxy ? $value : new Proxy($this, $name);

        // load config from the file?
        if ($this->isLoadable() && !isset($this->config[$name])) {
            $this->config[$name] = $this->load($name) ?? [];
        }
    }

    /**
     * Returns the configuration directory path.
     *
     * @return string|null The path or null.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Sets the configuration directory path.
     *
     * @param  string  $value  The new path.
     * @return void
     */
    public function setPath(string $value): void
    {
        $this->path = $value;
    }

    /**
     * Checks if the Configurator can load/save files.
     *
     * @return bool True if a path is set.
     */
    public function isLoadable(): bool
    {
        return !is_null($this->path);
    }

    /**
     * Checks if a default value exists for a key in a section.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @return bool True if default exists.
     */
    public function hasDefault(string $name, string $key): bool
    {
        return isset(static::DEFAULTS[$name][$key]);
    }

    /**
     * Retrieves a default value for a key in a section.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @return mixed The default value or null.
     */
    public function getDefault(string $name, string $key): mixed
    {
        return static::DEFAULTS[$name][$key] ?? null;
    }

    /**
     * Retrieves all default values for a section.
     *
     * @param  string  $name  The section name.
     * @return array Array of default values.
     */
    public function getDefaults(string $name): array
    {
        return static::DEFAULTS[$name] ?? [];
    }

    /**
     * Checks if a key exists in a configuration section.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @return bool True if the key exists.
     */
    public function has(string $name, string $key): bool
    {
        return array_key_exists($key, $this->config[$name]);
    }

    /**
     * Retrieves a configuration value by section and key.
     * Returns default value if key does not exist.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @return mixed The configuration value or default.
     */
    public function get(string $name, string $key): mixed
    {
        if (!isset($this->proxies[$name])) {
            $this->__get($name);
        }

        return is_array($this->config[$name] ?? null) && array_key_exists($key, $this->config[$name])
            ? $this->config[$name][$key]
            : $this->getDefault($name, $key);
    }

    /**
     * Sets a configuration value for a key in a section.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @param  mixed  $value  The value to set.
     * @return void
     */
    public function set(string $name, string $key, mixed $value): void
    {
        if (!isset($this->config[$name])) {
            $this->config[$name] = [];
        }

        $this->config[$name][$key] = $value;
    }

    /**
     * Sets a configuration value only if the key does not already exist.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The configuration key.
     * @param  mixed  $value  The value to set.
     * @return void
     */
    public function setOnce(string $name, string $key, mixed $value): void
    {
        if (!isset($this->config[$name])) {
            $this->config[$name] = [$key => $value];
        } elseif (!isset($this->config[$name][$key])) {
            $this->config[$name][$key] = $value;
        }
    }

    /**
     * Gets or sets a configuration value using dot notation.
     *
     * @param  string  $path  The dot notation path (e.g., "section.key.subkey").
     * @param  mixed|null  $value  The value to set (if provided).
     * @return mixed The retrieved value or $this for chaining.
     */
    public function dot(string $path, mixed $value = null): mixed
    {
        $keys = explode('.', $path);
        $keysCount = count($keys);

        if (func_num_args() < 2) {
            if ($keysCount == 1) {
                return $this->{$keys[0]};
            }

            if ($keysCount == 2) {
                return $this->{$keys[0]}->{$keys[1]};
            }

            $config = $this->{$keys[0]}->{$keys[1]};
            if (!is_array($config)) {
                return null;
            }

            for ($i = 2; $i < $keysCount; $i++) {
                if (is_array($config) && isset($config[$keys[$i]])) {
                    $config = $config[$keys[$i]];
                } else {
                    return null;
                }
            }

            return $config;
        }

        if ($keysCount == 1) {
            $this->{$keys[0]} = $value;
            return $this;
        }

        $config = $this->{$keys[0]};
        $target = &$config;
        for ($i = 1; $i < $keysCount - 1; $i++) {
            $key = $keys[$i];
            if (!isset($target[$key]) || !is_array($target[$key])) {
                $target[$key] = [];
            }
            $target = &$target[$key];
        }
        $target[$keys[$keysCount - 1]] = $value;
        $this->{$keys[0]} = $config;

        return $this;
    }

    /**
     * Deletes a configuration key from a section.
     *
     * @param  string  $name  The section name.
     * @param  string  $key  The key to delete.
     * @return bool True if the key was removed.
     */
    public function del(string $name, string $key): bool
    {
        if ($this->has($name, $key)) {
            unset($this->config[$name][$key]);
            return true;
        }

        return false;
    }

    /**
     * Retrieves all configuration data for a section or the entire config.
     *
     * @param  string|bool|null  $name  The section name, true for all with defaults, or null for all.
     * @param  bool  $addDefaults  Whether to include default values.
     * @return array The configuration data.
     */
    public function getAll(string|bool|null $name = null, bool $addDefaults = false): array
    {
        if (func_num_args() === 0 || !is_string($name)) {
            $config = $this->config;
            $addDefaults = (bool) $name;
        } else {
            $config = $this->config[$name] ?? [];
        }

        if ($addDefaults && $defaults = $this->getDefaults($name)) {
            foreach ($defaults as $key => $value) {
                if (!isset($config[$key]) && !is_null($value)) {
                    $config[$key] = $value;
                }
            }
        }

        return $config;
    }

    /**
     * Replaces configuration data for a section or the entire config.
     *
     * @param  string|array  $name  The section name or full config array.
     * @param  array  $config  The new configuration data.
     * @return void
     */
    public function setAll(string|array $name, array $config = []): void
    {
        if (is_array($name)) {
            $this->config = $name;
        } else {
            $this->config[$name] = $config;
        }
    }

    /**
     * Retrieves all keys for a configuration section.
     *
     * @param  string  $name  The section name.
     * @param  bool  $addDefaults  Whether to include default keys.
     * @return array|null Array of keys or null.
     */
    public function getKeys(string $name, bool $addDefaults = false): ?array
    {
        if (isset($this->config[$name]) && $this->config[$name]) {
            $keys = array_keys($this->config[$name]);
        }

        if ($addDefaults && $defaults = $this->getDefaults($name)) {
            if (!isset($keys)) {
                return array_keys($defaults);
            }

            $keys = array_merge($keys, $defaults);
        }

        return $keys ?? null;
    }

    /**
     * Checks if a section or the entire configuration is empty.
     *
     * @param  string|null  $name  The section name or null for entire config.
     * @return bool True if empty.
     */
    public function isEmpty(?string $name = null): bool
    {
        if ($name === null) {
            return empty($this->config);
        }

        return empty($this->config[$name]);
    }

    /**
     * Generates the file path for a configuration section.
     *
     * @param  string  $name  The section name.
     * @return string|null The file path or null.
     */
    public function generatePath(string $name): ?string
    {
        if (is_null($this->path)) {
            return null;
        }

        return "$this->path/$name.php";
    }

    /**
     * Loads configuration data from a file for a section.
     *
     * @param  Proxy|string  $proxy  The Proxy instance or section name.
     * @return array|null The loaded configuration or null.
     */
    public function load(Proxy|string $proxy): ?array
    {
        if (!$this->isLoadable()) {
            return null;
        }

        $name = is_string($proxy) ? $proxy : $proxy->getName();

        return isset($this->availableFiles[$name]) ? include $this->generatePath($name) : null;
    }

    /**
     * Saves configuration data for a section or all sections to files.
     *
     * @param  Proxy|string|null  $proxy  The Proxy instance, section name, or null for all.
     * @return bool True on success.
     */
    public function save(Proxy|string|null $proxy = null): bool
    {
        if (!$this->isLoadable()) {
            return false;
        }

        if ($proxy === null) {
            $success = false;

            foreach ($this->proxies as $itemProxy) {
                if ($itemProxy->save()) {
                    $success = true;
                }
            }

            return $success;
        }

        $name = is_string($proxy) ? $proxy : $proxy->getName();
        $path = $this->generatePath($name);

        if (file_exists($path)) {
            @unlink($path);
        }

        $config = $proxy->getAll();
        $configDefaults = $proxy->getDefaults();

        $config = array_filter($config, fn($v) => !is_null($v));
        foreach ($config as $key => $value) {
            if (isset($configDefaults[$key]) && $configDefaults[$key] === $value) {
                unset($config[$key]);
            }
        }

        if (!$config) {
            return $this->delete($proxy);
        }

        $export = var_export($config, true);
        $contents = '<?php return '.$export.';';

        if (!file_exists($this->path)) {
            static::mkdirs($this->path);
        }

        $success = (bool) file_put_contents($path, $contents);

        $this->onUpdated($name);

        return $success;
    }

    /**
     * Clears all configuration values for a section.
     *
     * @param  Proxy|string  $proxy  The Proxy instance or section name.
     * @return bool True if cleared.
     */
    public function clear(Proxy|string $proxy): bool
    {
        $name = is_string($proxy) ? $proxy : $proxy->getName();

        if (isset($this->config[$name])) {
            $this->config[$name] = [];
            return true;
        }

        return false;
    }

    /**
     * Deletes the configuration file for a section.
     *
     * @param  Proxy|string  $proxy  The Proxy instance or section name.
     * @return bool True if deleted.
     */
    public function delete(Proxy|string $proxy): bool
    {
        if (!$this->isLoadable()) {
            return false;
        }

        $name = is_string($proxy) ? $proxy : $proxy->getName();
        $path = $this->generatePath($name);

        if (is_null($path)) {
            return false;
        }

        @unlink($path);

        $this->onUpdated($name);

        return !file_exists($path);
    }

    /**
     * Ejects a section from memory (removes from config and proxies).
     *
     * @param  Proxy|string|array  $proxy  The Proxy instance, section name, or array of them.
     * @return void
     */
    public function eject(Proxy|string|array $proxy): void
    {
        if (is_array($proxy)) {
            $success = false;
            foreach ($proxy as $p) {
                $this->eject($p);
            }
        }

        $name = is_string($proxy) ? $proxy : $proxy->getName();

        if ($this->config) {
            if (array_key_exists($name, $this->config)) {
                unset($this->config[$name]);
            }
        }

        if ($this->proxies) {
            if (array_key_exists($name, $this->proxies)) {
                unset($this->proxies[$name]);
            }
        }
    }

    /**
     * Ejects all sections from memory except those specified.
     *
     * @param  array|null  $excepts  Array of section names or Proxy instances to keep.
     * @return void
     */
    public function ejectAll(?array $excepts = []): void
    {
        if (!$this->config && !$this->proxies) {
            return;
        }

        if ($excepts) {
            $newConfig = [];
            $newProxies = [];
            /**
             * @var Proxy|string $proxy
             */
            foreach ($excepts as $proxy) {
                $name = is_string($proxy) ? $proxy : $proxy->getName();

                if ($this->proxies && !is_null($this->proxies[$name] ?? null)) {
                    $newProxies[$name] = $this->proxies[$name];
                }

                if ($this->config && !is_null($this->config[$name] ?? null)) {
                    $newConfig[$name] = $this->config[$name];
                }
            }
            $this->proxies = $newProxies;
            $this->config = $newConfig;
        } else {
            $this->proxies = [];
            $this->config = [];
        }
    }

    /**
     * Iterates over each key-value pair in a configuration section.
     *
     * @param  string  $name  The section name.
     * @param  callable  $callback  The callback function (key, value).
     * @param  bool  $addDefaults  Whether to include defaults in iteration.
     * @return void
     */
    public function each(string $name, callable $callback, bool $addDefaults = false): void
    {
        if (!isset($this->proxies[$name])) {
            $this->__get($name);
        }

        if (isset($this->config[$name]) && $this->config[$name]) {
            foreach ($this->config[$name] as $key => $value) {
                if ($callback($key, $value)) {
                    break;
                }
            }
        }

        if ($addDefaults && $defaults = $this->getDefaults($name)) {
            foreach ($defaults as $key => $value) {
                if (!isset($config[$key])) {
                    if ($callback($key, $value) !== false) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Handles post-update operations (OPCache invalidation and cache update).
     *
     * @param  string  $name  The updated section name.
     * @return void
     */
    protected function onUpdated(string $name): void
    {
        $path = $this->generatePath($name);

        if (!is_null($path) && function_exists('opcache_invalidate')) {
            opcache_invalidate($path);
        }

        $this->updateCache();
    }

    /**
     * Recursively creates a directory with specified permissions.
     *
     * @param  string  $path  The directory path.
     * @param  int  $permissions  Directory permissions (default: 0777).
     * @return bool True on success.
     */
    public static function mkdirs(string $path, int $permissions = 0777): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, $permissions, true)) {
            chmod($path, $permissions);
            return true;
        }

        return false;
    }

    #region Cache

    /**
     * Returns the cache identifier name.
     *
     * @return string The cache name.
     */
    public function getCacheName(): string
    {
        if (!$this->cacheName) {
            if ($this->apcuAllowed && function_exists('apcu_store')) {
                $this->cacheName = 'tueen_configurator_'.md5(__FILE__);
            } else {
                $this->cacheName = '_.cache';
            }
        }

        return $this->cacheName;
    }

    /**
     * Checks if a cache entry exists.
     *
     * @return bool True if cache exists.
     */
    public function hasCache(): bool
    {
        $name = $this->getCacheName();

        if ($this->apcuAllowed && function_exists('apcu_store')) {
            return apcu_exists($name);
        }

        return file_exists("$this->path/$name");
    }

    /**
     * Retrieves the cached data.
     *
     * @return array|null The cached data or null.
     */
    public function getCache(): ?array
    {
        if ($this->apcuAllowed && function_exists('apcu_store')) {
            $value = apcu_fetch($this->getCacheName(), $success);

            if ($success && is_array($value)) {
                return $value;
            }

            return null;
        }

        $path = "$this->path/{$this->getCacheName()}";
        if (file_exists($path)) {
            $cache = file_get_contents($path);
            if ($cache) {
                $cache = unserialize($cache);
                if (is_array($cache)) {
                    return $cache;
                }
            }
        }

        return null;
    }

    /**
     * Updates the cache with current file metadata.
     *
     * @return bool True on success.
     */
    public function updateCache(): bool
    {
        $this->availableFiles = [];

        $configPathExists = file_exists($this->path);

        if ($configPathExists) {
            foreach (scandir($this->path) as $file) {
                if (str_ends_with($file, '.php')) {
                    $this->availableFiles[basename($file, '.php')] = true;
                }
            }
        } else {
            $this->availableFiles = [];
        }

        $cache = ['available_files' => $this->availableFiles];

        if ($this->apcuAllowed && function_exists('apcu_store')) {
            return (bool) apcu_store($this->getCacheName(), $cache, 86400);
        }

        $path = "$this->path/{$this->getCacheName()}";
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }

        $cache['time'] = time();
        if ($configPathExists) {
            touch($this->path, $cache['time']);
        }
        $success = (bool) file_put_contents($path, serialize($cache));

        if ($success) {
            chmod($path, 0700);
        }

        return $success;
    }

    /**
     * Deletes the cache entry.
     *
     * @return bool True if deleted.
     */
    public function delCache(): bool
    {
        if ($this->apcuAllowed && function_exists('apcu_store')) {
            return apcu_delete($this->getCacheName());
        }

        $path = "$this->path/{$this->getCacheName()}";
        if (file_exists($path)) {
            @unlink($path);
        }

        return !file_exists($path);
    }

    #endregion

    /**
     * ArrayAccess: Checks if a configuration section exists.
     *
     * @param  mixed  $offset  The section name.
     * @return bool True if the section exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->config[$offset]);
    }

    /**
     * ArrayAccess: Retrieves a Proxy for a configuration section.
     *
     * @param  mixed  $offset  The section name.
     * @return mixed The Proxy instance.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * ArrayAccess: Sets a Proxy or creates a new section.
     *
     * @param  mixed  $offset  The section name.
     * @param  mixed  $value  A Proxy instance or configuration data.
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * ArrayAccess: Removes a configuration section.
     *
     * @param  mixed  $offset  The section name.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->config[$offset])) {
            unset($this->config[$offset]);
        }
    }
}
