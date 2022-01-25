# Loader
Simple loader to send request and read response from address.
Uses cURL extension.
Composer package.

## Install
```
composer require greezlu/ws-loader
```

## Basic usage
Create Loader instance with request params in the constructor.
Use **load** method to get Response object.
Get data from Response object.

### Example

```php
$loader = new \WebServer\Core\Loader('https://raw.githubusercontent.com/greezlu/ws-loader/master/composer.json');
$response = $loader->load();
$response->toString();
```

### Loader object
Main class to send response. [cURL Settings](https://www.php.net/manual/ru/function.curl-setopt/).
```php
WebServer\Core\Loader
```

```php
/* Default script pause time after successful request. */
private Loader::DEFAULT_LOAD_TIMEOUT = 500000;
```

```php
public Loader::__construct(
    string $address,
    string $method = 'GET',
    array $requestParams = [],
    int $requestTimeout = self::DEFAULT_LOAD_TIMEOUT
)
```

```php
/* Example */
$requestParams = [
    'headers'       => ['Header-Name'   => 'Header Value'],
    'cookie'        => ['Cookie-Name'   => 'Cookie Value'],
    'curlSettings'  => ['Setting-Name'  => 'Setting Value'],
    'postParams'    => ['Param-Name'    => 'Param Value'],
    'getParams'     => ['Param-Name'    => 'Param Value']
];
```

```php
/* Send request and return response object or null. */
public Loader::load(): ?Response
```

### LoaderResponse object
Can be converted to **string** using magic method.
```php
WebServer\Core\LoaderResponse
```

```php
/* Get raw response as string data. */
public LoaderResponse::toString(): string
```

```php
/* Attempt to decode response. Return result or empty array. */
public LoaderResponse::toArray(): array
```

```php
/* Get response status code. */
public LoaderResponse::getResponseStatusCode(): array
```

```php
/* Get list of response headers. */
public LoaderResponse::getResponseHeaders(): array
```
