# Gander
[![License](http://poser.pugx.org/gbhorwood/gander/license)](https://packagist.org/packages/gbhorwood/gander)
[![License](http://poser.pugx.org/gbhorwood/gander/require/php)](https://packagist.org/packages/gbhorwood/gander)

Gander is an inspector and logger for restful Laravel apis. Gander allows logging of request/responses, as well as optional logging of events in your code for each request. Gander reports are viewed with javascript client which is exported and can be hosted anywhere.

## Install
Gander is installed via composer:

```shell
composer require gbhorwood/gander
php artisan vendor:publish --provider="Gbhorwood\Gander\GanderServiceProvider" --tag="config"
php artisan migrate
```

Installing Gander will add a middleware to your api middleware group, create tables to store Gander events and access keys, expose the `gbhorwood:gander` artisan command, and write the gander config file to your `config` directory.

## Quickstart
The shortest path to getting Gander working after install is to create a client in your api's root directory with:

```shell
php artisan gbhorwood:gander --create-client
```

Then load the html file in a browser, either by hosting it on any http server or by opening it as a file.

## Configuration
All Gander features are turned on by default after installation. To adjust this, add the following lines to the `.env` file of your Laravel api.

```
GANDER_ENABLE=true
GANDER_ENABLE_STACK_TIMERS=true
```

Setting `GANDER_ENABLE` to `false` will turn off Gander completely.

Setting `GANDER_ENABLE_STACK_TIMERS` to false will disable the elapsed time value on all log writes you have added to your code using `Gander::track()`.

### A short note on timer configuration
Timing of the elapsed seconds of a request is done internally using php's [`hrtime()`](https://www.php.net/manual/en/function.hrtime.php) for performance reasons. However, `hrtime()` is unreliable when called across function calls. For this reason, calculating the elapsed time when writing log calls is done using [`microtime()`](https://www.php.net/manual/en/function.microtime.php). However, `microtime()` may be slow in some virtual environments who's [vDSO](https://man7.org/linux/man-pages/man7/vdso.7.html) does not allow access to the clock. For this reason, Gander allows you to turn off the use of `microtime()` by setting `GANDER_ENABLE_STACK_TIMERS` to `false`.

## Writing logs
Gander logs requests and responses automatically. In addition, you can also add log writes for a given request in your endpoint's source code with:

```php
Gander::track()
```

This method takes a loggable message as an optional string argument, and is found in `Gbhorwood\Gander\Gander`.

The track method can be called anywhere in your executed code:

```php
<?php
namespace App\Http\Controllers\api;

use Gbhorwood\Gander\Gander;

class MyController extends BaseController
{
    public function getNumber(Request $request, Int $max):JsonResponse
    {
        $number = $this->generateNumber($max);

        Gander::track("number is $number");

        return response()->json($number, 200);
    }

    private function generateNumber(Int $max):Int
    {
        Gander::track("max is $max");

        return rand(1, $max);
    }
}
```

If `GANDER_ENABLE_STACK_TIMERS` is set to `true`, each call to `Gander::track()` will log the elapsed time, in seconds, since the last track call.

## Generating a client
Data logged by Gander can be viewed with a javascript client created by calling the gander artisan command in the root directory of your api.

```shell
php artisan gbhorwood:gander --create-client
```

The client file will be saved in your api's root directory and will be named using the format:

```
gander_<domain of api>_<name of access key>.html
```

This client will work only for the api in which it was generated. 

The client file can be hosted anywhere that has network access to your api. You can even load it into your browser as an opened file.

The client file will access your api using a unique access key. If you wish to revoke access to a client, you can do so by removing its api key.

If you wish to specify the file name and location of the the client at creation, you can do so with the `--outfile=` option:

```shell
php artisan gbhorwood:gander --create-client --outfile=/path/to/my/file
```

Gander will not overwrite client files that already exist.

## Using the client
A Gander client can be hosted on any http server or opened in a web browser as a file, so long as it has network access to the api it is reporting on.

The client will only work for the api where it was generated. Each client uses a unique api key to get reports on the api (see 'Managing client keys').

Gander reports on activity that ocurred during a time frame defined as a certain point in the past, ie. '2 hours ago', until now.

There are two types of reports:

 * **Stats:** Aggregate statistics on all the activity in the time period
 * **Logs:** A list of all the api requests during the time period

### The 'stats' tab
The Stats tab will show all of the routes called during the time period, along with the total number of requests for each route and their success rate. Clicking on a stats line will show a breakdown of the number of requests to that route by returned status code.

![Gander stats page](https://gander.fruitbat.studio/images/readme_stats_page.png)

Note that the number of time units is an editable input box and the units are buttons.

The domain of the api being reported on and the name of the api key are shown in the top-right corner.

### The 'logs' tab
The logs tab shows all request activity during the time period. The logs are shown from most recent to least recent.

![Gander logs page](https://gander.fruitbat.studio/images/readme_logs_page.png)

Clicking on one log entry will display details about that request.

### Seeing log details
Log details are shown by clicking on a log line. Basic information about the request and response is displayed:

![Gander logs details page](https://gander.fruitbat.studio/images/readme_logs_details_page.png)

The 'permalink' button and url at the bottom can be used to refer back to the log details at any time.

## Managing client keys
The Gander client gets statistics and logs about your api by calling it's own api endoints. These endpoints require api keys to access. 

Gander api keys are identified by their name; a unique, somewhat-readable string.

To get a list of the Gander api keys your api has, run

```shell
php artisan gbhorwood:gander --create-client
```

This will display names and creation times of all keys. 

To delete a given key

```shell
php artisan gbhorwood:gander --delete-key=<key name>
```

When creating a client, you can assign it a custom key name to make key management easier:

```shell
php artisan gbhorwood:gander --create-client --key-name=<custom key name>
```

## Writing your own client
Gander's primary functionality is storing data about requests and providing an api to report on that data; the client is a convenience tool.

If you wish to create your own client, OpenApi documentation on the Gander api is located in the `swagger/` directory of the package.
