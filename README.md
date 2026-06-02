# Freemius Plugin Licensing

A lite, UI-free Freemius SDK for Duck Dev WordPress plugins. The library handles license activation, deactivation,
update delivery, and addon listing by talking to the Freemius API directly. It deliberately ships no admin screens —
host plugins build their own UI and call into this library for the underlying logic.

## Requirements

* PHP 7.4 or higher
* WordPress 5.0+
* Composer

## Installation

```console
composer require duckdev/freemius-plugin-licensing
```

The library autoloads under the `DuckDev\Freemius\` namespace via PSR-4.

## Architecture

The library is organised as a small dependency-injection container wired up by the entry class
`DuckDev\Freemius\Freemius`. The folder layout mirrors the namespace:

```
src/
├── Freemius.php              # Container + entry point
├── Api/
│   ├── Client.php            # Unsigned HTTP client over wp_remote_request
│   ├── SignedClient.php      # Adds FS / FSP signed auth headers
│   ├── RequestSigner.php     # Pure header-signing logic
│   └── ApiFactory.php        # Builds fresh clients per call
├── Contracts/
│   ├── ServiceInterface.php
│   ├── ApiClientInterface.php
│   └── CacheInterface.php
├── Data/
│   ├── Plugin.php            # Immutable host plugin info
│   ├── Activation.php        # Value object around the persisted activation
│   └── ApiKeys.php           # Public / secret key pair
├── Storage/
│   ├── ActivationRepository.php   # Reads / writes the activation option
│   └── TransientCache.php         # Per-plugin transient cache + throttle
├── Services/
│   ├── AbstractService.php
│   ├── License.php           # activate() / deactivate()
│   ├── Update.php            # WP update hooks
│   └── Addon.php             # Addon listing
├── Support/
│   └── SiteIdentity.php      # Deterministic site UID
└── Exceptions/
    └── FreemiusException.php
```

Each service receives its collaborators by constructor injection, so they can be unit-tested without WordPress in the
loop. Hook registration happens inside `boot()` (called once by the container), so simply instantiating the container
has no side effects.

## Usage

### Initialization

Initialise the container by calling `Freemius::get_instance()` with your Freemius product ID and an arguments array:

```php
// Assuming Composer's autoload.php has been included.
$freemius = \DuckDev\Freemius\Freemius::get_instance(
    12345, // Your Freemius product ID.
    array(
        'slug'       => 'loggedin',              // Your plugin's unique Freemius slug.
        'main_file'  => LOGGEDIN_FILE,           // Absolute path to the plugin's main file.
        'public_key' => 'pk_XXXXXXXXXXXXXXXXX',  // Plugin public key.
        'is_premium' => true,                    // Whether this build is the premium edition.
        'has_addons' => false,                   // Whether the product has addons to list.
    )
);
```

The supported arguments are:

| Key           | Type     | Description                                                                                       |
|---------------|----------|---------------------------------------------------------------------------------------------------|
| `slug`        | `string` | Unique Freemius slug for the plugin.                                                              |
| `main_file`   | `string` | Absolute path to the plugin's main file (used for `plugin_basename()` and `get_plugin_data()`).   |
| `public_key`  | `string` | Freemius public key (`pk_…`). Required for plugin-scoped endpoints (addons, info).                |
| `is_premium`  | `bool`   | Whether this build is the premium edition. Update hooks only register when `true`. Default false. |
| `has_addons`  | `bool`   | Whether the product has addons to list. Default false.                                            |

The first call to `get_instance()` creates the container and registers WordPress hooks. Subsequent calls for the same
plugin ID return the existing instance (the second argument is ignored after the first call).

### License Activation

```php
$result = $freemius->license()->activate( 'XXXX-XXXX-XXXX' );

if ( is_wp_error( $result ) ) {
    // $result->get_error_message() — show to the user.
}
```

`activate()` returns `true` / `false` from the option update on success, or a `WP_Error` when the key is empty, the
plugin is not the premium build, the API call fails, or the response does not include an install ID.

### License Deactivation

```php
$result = $freemius->license()->deactivate();
```

`deactivate()` refuses to proceed when the stored UID does not match the current site — that means the activation was
moved to another host, and we let the new host appear unlicensed rather than silently freeing the original seat.

### Reading the Current Activation

```php
$activation = $freemius->license()->get_activation();

if ( $activation->is_active() ) {
    // $activation->license_key(), $activation->install_id(), …
}
```

`get_activation()` always returns an `Activation` value object — use `is_empty()` to detect the no-activation case.

### Updates

Update hooks are registered automatically during `boot()` for premium builds. There is no manual integration needed —
WordPress will check for, display, and apply updates through its standard pipeline.

To force a refresh from the host plugin's UI:

```php
$freemius->update()->get_update_data( true );
```

### Addons

```php
$addons = $freemius->addon()->get_addons();              // Cached for 24h.
$addons = $freemius->addon()->get_addons( true );        // Force refresh.
```

Each entry is enriched with a `link` field (Freemius checkout URL) and an `is_premium` boolean. Use the
`duckdev_freemius_format_addon_data` filter to add or rewrite fields per addon.

## Hooks

### Actions

| Hook                                  | Arguments                | When                                  |
|---------------------------------------|--------------------------|---------------------------------------|
| `duckdev_freemius_license_activated`  | `array $activation, bool $success`   | After a successful activation.        |
| `duckdev_freemius_license_deactivated`| `array $activation, bool $success`   | After a successful deactivation.      |

### Filters

| Hook                                       | Arguments                                       | Use                                                              |
|--------------------------------------------|-------------------------------------------------|------------------------------------------------------------------|
| `duckdev_freemius_api_request_args`        | `array $args, string $method, string $url, array $data, array $headers` | Tweak the request arguments before they reach `wp_remote_request()`. |
| `duckdev_freemius_api_request_verify_ssl`  | `bool $verify, Client $client`                  | Disable SSL verification (typically only in local dev).          |
| `duckdev_freemius_format_addon_data`       | `array $addon, Addon $service`                  | Rewrite or augment each addon entry before it is returned.       |

## Security Notes

* The library does **not** verify nonces or capabilities. Host plugins MUST do that before forwarding form input to
  `License::activate()` / `License::deactivate()`.
* The license key is stored in the `duckdev_freemius_activation_data` option (an autoload-safe option keyed by plugin
  ID). It is blanked from storage on deactivation.

## License

GPL-2.0+
