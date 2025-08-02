# Freemius Plugin Licensing

This is a lite version of the main Freemius SDK, specifically developed for use in Duck Dev WordPress plugins. This
library focuses exclusively on managing plugin license activation, deactivation, and updates. It does not provide any
user interface, so your plugin will need to create its own UI and use this library to handle the logic.

## Requirements

* PHP version 7.4 or higher.
* WordPress 5.0+

## Installation

This library should be installed and included in your WordPress plugin using Composer.

```console
composer require duckdev/freemius-licensing-sdk
```

## Usage

### Initialization

Initialize the Freemius SDK by calling the static `DuckDev\Freemius\Freemius::get_instance()` method with your plugin's
details.

```php
// Assuming Composer's autoload.php has been included.
$freemius = DuckDev\Freemius\Freemius::get_instance(
	12345, // Your Freemius product ID.
	array(
		'slug' => 'loggedin', // Your plugin's unique Freemius slug.
		'main_file'  => LOGGEDIN_FILE, // The path to your plugin's main file.
		'public_key' => 'pk_XXXXXXXXXXXXXXXXX', // Your plugin's public key.
	)
);
```

### License Activation

To activate a license, call the `activate()` method on the `license()` object with the user's license key.

```php
$freemius->license()->activate( 'XXXX-XXXX-XXXX' );
```

### License Deactivation

To deactivate a license, simply call the `deactivate()` method.

```php
$freemius->license()->deactivate();
```

### Updates

The library will automatically handle plugin updates as long as a valid license is active. No additional code is
required to check for and apply updates.