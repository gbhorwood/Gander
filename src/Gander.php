<?php

namespace Gbhorwood\Gander;

use Closure;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Facades
 */
use Illuminate\Support\Facades\Auth;

/**
 * Models
 */
use Gbhorwood\Gander\Models\GanderStack;
use Gbhorwood\Gander\Models\GanderRequest;

/**
 * Gander middleware and static functions
 *
 */
class Gander
{
    /**
     * Array to track all entries made by track()
     */
    protected static array $stack = [];

    /**
     * Middleware to track and write request and stack logs.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * If gander is not enabled, return early
         */
        if(!config('gander.enabled')) {
            return $next($request);
        }

        /**
         * Set id that references stack entries to a request entry
         */
        $requestId = self::randUniqueId();

        /**
         * User id, ie from passport, if any
         */
        $userId = @auth()->guard('api')->user()->id ?? null;

        /**
         * hrtime start for tracking elapsed time
         * @note We use microtime in the stack because hrtime() is unreliable when
         * called across functions, but use it here because it is faster in vms.
         */
        $startHrTime = hrtime(true);

        /**
         * Next middleware
         */
        $response = $next($request);

        /**
         * hr time end for tracking elapsed time
         */
        $endHrTime = hrtime(true);

        /**
         * Get json out of the response body
         */
        switch(get_class($response)) {
            case "Illuminate\Http\JsonResponse":
                $responseBodyJson = $response->content();
                break;
            case "Illuminate\Http\Response":
                @json_decode($response->content());
                $responseBodyJson = json_last_error() === JSON_ERROR_NONE ? $response->content() : null;
                break;
            default:
                $responseBodyJson = null;
        }

        /**
         * Insert request
         */
        $requestInsert = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'endpoint' => $request->route()->uri,
            'response_status' => $response->status(),
            'response_status_text' => $response->statusText(),
            'url' => substr(str_replace($request->root(), '', $request->fullUrl()), -254),
            'request_body_json' => $request->isJson() ? $this->scrubPasswords($request->getContent()) : null,
            'response_body_json' => $responseBodyJson,
            'user_id' => $userId,
            'user_ip' => $request->ip(),
            'elapsed_seconds' => number_format(($endHrTime - $startHrTime) / 1000000000, 5),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        GanderRequest::insert($requestInsert);

        /**
         * Insert stack, if any
         */
        if(count(self::$stack) > 0) {
            $stackInserts = array_map(function ($s) use ($requestId, $userId) {
                return [
                    'request_id' => $requestId,
                    'sequence' => $s['sequence'],
                    'user_id' => $userId,
                    'file' => substr(str_replace(base_path(), '', $s['file']), -254), // truncate from the right to col length
                    'function' => substr($s['function'], -254),
                    'line' => $s['line'],
                    'elapsed_seconds' => is_null($s['elapsed_seconds']) ? null : number_format($s['elapsed_seconds'], 5),
                    'message' => $s['message'],
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }, self::$stack);
            GanderStack::insert($stackInserts);
        }

        /**
         * Clean up
         */
        self::$stack = [];

        /**
         * Return response from closure call
         */
        return $response;
    }

    /**
     * Add a tracking call to the stack for this request
     *
     * @param  String $message Optional custom message to add to the tracking call
     * @return bool
     */
    public static function track(String $message = null): bool
    {
        /**
         * If gander is not enabled, return
         */
        if(!config('gander.enabled')) {
            return false;
        }

        /**
         * Exception used for getting the trace so we can determine
         * the file and method that called this function.
         */
        $e = new \Exception();
        $t = $e->getTrace();

        /**
         * Calculate time in seconds since last call to this function for this request
         */
        $nowMicroseconds = config('gander.stack_timers_enabled') ? microtime(true) : null;
        $elapsedSeconds = null;
        if(config('gander.stack_timers_enabled') && count(self::$stack) > 0) {
            $elapsedSeconds = $nowMicroseconds - self::$stack[count(self::$stack) - 1]['now_microseconds'];
        }

        self::$stack[] = [
            'sequence' => count(self::$stack) > 0 ? count(self::$stack) + 1 : 1,
            'file' => $t[0]['file'],
            'function' => $t[1]['function'],
            'line' => $t[0]['line'],
            'now_microseconds' => $nowMicroseconds,
            'elapsed_seconds' => $elapsedSeconds,
            'message' => $message,
        ];

        return true;
    }

    /**
     * Generate a 'unique' id from rand. uniqid() is timebased to microsecond
     * and has duplication issues sometimes.
     *
     * @return String
     */
    public static function randUniqueId(): String
    {
        return bin2hex(random_bytes(7));
    }

    /**
     * Traverses the json body and replaces all values keyed with one of
     * the values listed in .env value GANDER_PASSWORD_KEYS with asteriskes.
     * This is used to sanitize input since we don't want to write user
     * passwords to the db.
     *
     * @param  String $json The json body
     * @return ?String The new json body with password scrubbed
     */
    private function scrubPasswords(String $json): ?String
    {
        /**
         * Get the keys that contain passwords from the config
         */
        $passwordKeys = array_map(fn ($p) => trim($p), explode(',', config('gander.password_keys')));

        /**
         * Function to validate if json or not
         */
        $v = function ($json) {
            if (!empty($json)) {
                return is_string($json) && is_array(json_decode($json, true)) ? true : false;
            }
            return false;
        };

        /**
         * Recursive function to scrub password values at any depth
         */
        $r = function ($j) use (&$r, $passwordKeys) {
            $f = function ($k, $v) use ($passwordKeys) {
                return in_array($k, $passwordKeys) ? "*******" : $v;
            };
            foreach($j as $k => $v) {
                $j[$k] = is_scalar($v) || is_null($v) ? $f($k, $j[$k]) : $r($j[$k]);
            }
            return $j;
        };

        /**
         * Return json body as string with password values scrubbed
         */
        return $v($json) ? json_encode($r(json_decode($json, true))) : null;
    }
}
