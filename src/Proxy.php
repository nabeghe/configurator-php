<?php namespace Nabeghe\Configurator;

use Override;

/**
 * Proxy class for accessing configuration data through ArrayAccess and magic methods.
 * This class acts as a bridge between the Configurator and specific configuration sections.
 */
#[\AllowDynamicProperties]
class Proxy implements \ArrayAccess
{
    /**
     * Constructs a new Proxy instance for a specific configuration section.
     *
     * @param  Configurator  $configurator  The parent Configurator instance.
     * @param  string  $name  The name of the configuration section.
     */
    public function __construct(
        protected readonly Configurator $configurator,
        protected readonly string $name,
    ) {
    }

    /**
     * Checks if a configuration key exists in the current section.
     *
     * @param  mixed  $offset  The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->configurator->has($this->getName(), $offset);
    }

    /**
     * Retrieves a configuration value by key using array access.
     *
     * @param  mixed  $offset  The key to retrieve.
     * @return mixed The configuration value.
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->{$offset};
    }

    /**
     * Sets a configuration value by key using array access.
     *
     * @param  mixed  $offset  The key to set.
     * @param  mixed  $value  The value to assign.
     * @return void
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->{$offset} = $value;
    }

    /**
     * Removes a configuration key from the current section.
     *
     * @param  mixed  $offset  The key to remove.
     * @return void
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->configurator->del($this->getName(), $offset);
    }

    /**
     * Magic getter for accessing configuration values via property syntax.
     *
     * @param  string  $name  The property name (configuration key).
     * @return mixed The configuration value.
     */
    public function __get(string $name)
    {
        return $this->configurator->get($this->getName(), $name);
    }

    /**
     * Magic setter for setting configuration values via property syntax.
     *
     * @param  string  $name  The property name (configuration key).
     * @param  mixed  $value  The value to assign.
     * @return void
     */
    public function __set(string $name, $value): void
    {
        $this->configurator->set($this->getName(), $name, $value);
    }

    /**
     * Checks if a specific key exists in the current configuration section.
     *
     * @param  string  $key  The key to check.
     * @return bool True if the key exists.
     */
    public function has(string $key)
    {
        return $this->configurator->has($this->getName(), $key);
    }

    /**
     * Returns the name of the current configuration section.
     *
     * @return string The section name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retrieves all default values for the current configuration section.
     *
     * @return array An array of default values.
     */
    public function getDefaults(): array
    {
        return $this->configurator->getDefaults($this->getName());
    }

    /**
     * Retrieves all keys in the current configuration section.
     *
     * @param  bool  $addDefaults  Whether to include default keys.
     * @return array|null Array of keys or null if empty.
     */
    public function getKeys(): ?array
    {
        return $this->configurator->getKeys($this->getName());
    }

    /**
     * Checks if the current configuration section is empty.
     *
     * @return bool True if the section has no values.
     */
    public function isEmpty(): bool
    {
        return $this->configurator->isEmpty($this->getName());
    }

    /**
     * Retrieves all configuration values for the current section.
     *
     * @param  bool  $addDefaults  Whether to include default values.
     * @return array The configuration data.
     */
    public function getAll(bool $addDefaults = false): array
    {
        return $this->configurator->getAll($this->getName(), $addDefaults);
    }

    /**
     * Replaces all configuration values for the current section.
     *
     * @param  array  $config  The new configuration data.
     * @return void
     */
    public function setAll(array $config): void
    {
        $this->configurator->setAll($this->getName(), $config);
    }

    /**
     * Checks if a default value exists for a specific key.
     *
     * @param  string  $key  The key to check.
     * @return bool True if a default exists.
     */
    public function hasDefault(string $key): bool
    {
        return $this->configurator->hasDefault($this->getName(), $key);
    }

    /**
     * Retrieves the default value for a specific key.
     *
     * @param  string  $key  The key to retrieve.
     * @return mixed The default value or null.
     */
    public function getDefault(string $key): mixed
    {
        return $this->configurator->getDefault($this->getName(), $key);
    }

    /**
     * Loads configuration data from the associated file.
     *
     * @return array|null The loaded configuration or null.
     */
    public function load(): ?array
    {
        return $this->configurator->load($this);
    }

    /**
     * Saves the current configuration to its file.
     *
     * @return bool True on success, false on failure.
     */
    public function save(): bool
    {
        return $this->configurator->save($this);
    }

    /**
     * Clears all configuration values in the current section.
     *
     * @return bool True if cleared, false otherwise.
     */
    public function clear(): bool
    {
        return $this->configurator->clear($this);
    }

    /**
     * Deletes the configuration file for the current section.
     *
     * @return bool True if deleted, false otherwise.
     */
    public function delete(): bool
    {
        return $this->configurator->delete($this);
    }

    /**
     * Ejects the current section from the Configurator (removes from memory).
     *
     * @return void
     */
    public function eject(): void
    {
        $this->configurator->eject($this);
    }

    /**
     * Iterates over each key-value pair in the configuration section.
     *
     * @param  callable  $callback  The callback function (key, value).
     * @param  bool  $addDefaults  Whether to include defaults in iteration.
     * @return void
     */
    public function each(callable $callback, bool $addDefaults = false): void
    {
        $this->configurator->each($this->getName(), $callback, $addDefaults);
    }
}
