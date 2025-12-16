# Nabeghe Configurator for PHP

> A PHP configuration management library that supports file-based storage and loads each section lazily.

---

## Features

- **Section-Based Configuration**: Organize your config into named sections for better structure and readability.
- **ArrayAccess & Magic Methods**: Access configuration sections and keys using array syntax or object properties.
- **Lazy Loading of Sections**: Configuration sections are loaded only when accessed, reducing memory usage and
  improving performance.
- **Type-Safe Access**: Supports IDE autocomplete and type hinting via Proxy classes and PHPDoc annotations.
- **Dot Notation Support**: Easily get or set nested configuration values with intuitive dot paths (e.g., `database.connections.mysql.host`).
- **Defaults Handling**: Define default values for keys, ensuring fallback values when a key is missing.
- **File-Based Persistence**: Save and load configuration sections to individual PHP files.
- **Dynamic Proxy System**: Each configuration section is accessible via a Proxy class, providing seamless interaction
  and encapsulation.
- **Caching**: Optional APCu or file-based caching to improve performance by storing available file metadata.
- **Utility Methods**: Easily manage keys, delete sections, iterate over values, or eject sections from memory.

---

### Installation

Install via Composer:

```bash
composer require nabeghe/configurator
```
## Usage

1. Create a configuration class and extend it from `Configurator`.
2. Define the `DEFAULTS` constant in this class. This constant is an array that can contain arbitrary values. Each key represents a section, and its value is an array of that section’s configuration values (for example: `db`, `log`, etc.).
3. It is recommended to create a separate class for each section. This class is used only for IDE type hinting and has no other functional purpose. In this class, you can define the keys that the section may contain, along with their types, using `@property` annotations in the docblock comments. This class must extend the `Proxy` class, because each section is a `Proxy`.
5. Instantiate your configuration class.

### Configurator Constructor

The constructor accepts three parameters:

- First parameter: The path to a directory where each section can be stored as a separate PHP file. Each file must return an array. For example, a section named `db.php` can be used for database configuration. The default value is `null`, meaning no directory is used.
- Second parameter: Configuration data. You may inject configuration data for each section at instantiation time. In this case, the data will not be loaded from the directory. The default value is an empty array.
- Third parameter: Specifies whether APCu should be used. It is used only to cache the list of available files and has no other purpose. If not set, a file named `_.cache` will be used in the specified directory instead.

**Note 1:**  
By default, the directory path is `null`. If it is not set, loading configuration from files and persisting configurations is not possible. Therefore, if your configuration is file-based, you should set this path.

**Note 2:**  
Injecting data together with a directory path is allowed. In this case, the injected sections will no longer be loaded from their corresponding files.

```php
use Nabeghe\Configurator\Configurator;
use Nabeghe\Configurator\Proxy;

class MyConfig extends Configurator
{
    const DEFAULTS = [
        'db' => [
            'type' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ],
        'debug' => [
            'enabled' => true,
        ],
    ];
}

$config = new MyConfig(path: __DIR__ . '/config');

// Accessing sections via Proxy
$db = $config->db;
echo $db->host; // localhost

// Using dot notation
$config->dot('db.host', '127.0.0.1');
echo $config->dot('db.host'); // 127.0.0.1

// Adding new keys or editing
$config->app->version = '1.0.0';

// Save a section
$config->db->save();

// Delete a key
$config->db->del('port');

// Iterate through keys
$config->database->each(function($key, $value) {
    echo "$key => $value\n";
});

// Get all configuration data including defaults
$allConfig = $config->getAll(addDefaults: true);
```

## Cache file

A file named `_.cache` is reserved. If APCu is not available, the list of existing configuration files is stored in this file. Although the system automatically updates the list, it is recommended to delete the `_.cache` file whenever you add a new file or remove an existing one.

## کلاس Configurator

| Method                                          | Description                                                        |
|-------------------------------------------------|--------------------------------------------------------------------|
| __construct($path, $config, $apcuAllowed)       | Initialize configurator with path, initial config and cache option |
| __get($name)                                    | Get a Proxy instance for a config section                          |
| __set($name, $value)                            | Set or create a config section (via Proxy)                         |
| getPath()                                       | Get configuration directory path                                   |
| setPath($value)                                 | Set configuration directory path                                   |
| isLoadable()                                    | Check if file loading/saving is enabled                            |
| hasDefault($section, $key)                      | Check if a default value exists                                    |
| getDefault($section, $key)                      | Get default value for a key                                        |
| getDefaults($section)                           | Get all default values of a section                                |
| has($section, $key)                             | Check if a key exists in a section                                 |
| get($section, $key)                             | Get a config value (falls back to default)                         |
| set($section, $key, $value)                     | Set a config value                                                 |
| setOnce($section, $key, $value)                 | Set a value only if it does not exist                              |
| dot($path, $value = null)                       | Get/set config using dot-notation                                  |
| del($section, $key)                             | Delete a key from a section                                        |
| getAll($section = null, $addDefaults = false)   | Get all config data                                                |
| setAll($sectionOrArray, $config = [])           | Replace section or full config                                     |
| getKeys($section, $addDefaults = false)         | Get keys of a section                                              |
| isEmpty($section = null)                        | Check if section or config is empty                                |
| generatePath($section)                          | Generate file path for a section                                   |
| load($proxyOrName)                              | Load section config from file                                      |
| save($proxyOrName = null)                       | Save section(s) to file                                            |
| clear($proxyOrName)                             | Clear config values of a section                                   |
| delete($proxyOrName)                            | Delete config file of a section                                    |
| eject($proxyOrName)                             | Remove section from memory                                         |
| ejectAll($excepts = [])                         | Remove all sections except given ones                              |
| each($section, $callback, $addDefaults = false) | Iterate over section values                                        |
| mkdirs($path, $permissions)                     | Recursively create directories                                     |
| getCacheName()                                  | Get cache identifier name                                          |
| hasCache()                                      | Check if cache exists                                              |
| getCache()                                      | Retrieve cached metadata                                           |
| updateCache()                                   | Update cache with file metadata                                    |
| delCache()                                      | Delete cache entry                                                 |
| offsetExists($offset)                           | ArrayAccess: check section exists                                  |
| offsetGet($offset)                              | ArrayAccess: get section Proxy                                     |
| offsetSet($offset, $value)                      | ArrayAccess: set section                                           |
| offsetUnset($offset)                            | ArrayAccess: remove section                                        |

## کلاس Proxy

This class is used for each configuration section.

| Method                                | Description                                |
|---------------------------------------|--------------------------------------------|
| __construct($configurator, $name)     | Create a proxy for a configuration section |
| offsetExists($key)                    | ArrayAccess: check if a key exists         |
| offsetGet($key)                       | ArrayAccess: get a value by key            |
| offsetSet($key, $value)               | ArrayAccess: set a value by key            |
| offsetUnset($key)                     | ArrayAccess: remove a key                  |
| __get($key)                           | Get a config value via property access     |
| __set($key, $value)                   | Set a config value via property access     |
| has($key)                             | Check if a key exists in the section       |
| getName()                             | Get section name                           |
| getDefaults()                         | Get default values of the section          |
| getKeys()                             | Get all keys in the section                |
| isEmpty()                             | Check if section is empty                  |
| getAll($addDefaults = false)          | Get all config values                      |
| setAll($config)                       | Replace all section values                 |
| hasDefault($key)                      | Check if a default exists for key          |
| getDefault($key)                      | Get default value for key                  |
| load()                                | Load section config from file              |
| save()                                | Save section config to file                |
| clear()                               | Clear all values in section                |
| delete()                              | Delete section config file                 |
| eject()                               | Remove section from memory                 |
| each($callback, $addDefaults = false) | Iterate over key-value pairs               |

## 📖 License

Licensed under the MIT license, see [LICENSE.md](LICENSE.md) for details.
