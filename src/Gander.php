<?php

namespace Gbhorwood\Gander;

use Closure;

use DB;
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
     * String uuid of the request
     */
    protected static String $requestId;

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
        self::$requestId = self::randUniqueId();

        /**
         * User id, ie from passport, if any
         */
        $userId = null;
        try {
            $userId = @auth()->guard('api')->user()->id ?? null;
        } catch(\Exception $e) {
        }

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
         * Get headers to log in request
         */
        $headersToLog = array_map(fn ($p) => trim($p), explode(',', config('gander.headers_to_log')));
        $requestHeadersJson = @json_encode(
            array_combine(array_values($headersToLog), array_map(fn ($h) => $request->header($h), $headersToLog))
        );

        /**
         * Function to validate if a string is json
         * @param  String $value
         * @return bool
         */
        $validateJson = function (String $value): bool {
            try {
                json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        };

        /**
         * Insert request
         */
        $requestInsert = [
            'request_id' => self::$requestId,
            'method' => $request->method(),
            'endpoint' => $request->route()->uri,
            'response_status' => $response->status(),
            'response_status_text' => $response->statusText(),
            'url' => substr(str_replace($request->root(), '', $request->fullUrl()), -254),
            'request_headers_json' => $requestHeadersJson,
            'request_body_json' => $request->isJson() ? $this->redactPasswords($request->getContent()) : null,
            'response_body_json' => $validateJson($responseBodyJson) ? $responseBodyJson : "[\"".addslashes($responseBodyJson)."\"]",
            'user_id' => $userId,
            'user_ip' => $request->ip(),
            'curl' => $this->getCurl($request),
            'elapsed_seconds' => number_format(($endHrTime - $startHrTime) / 1000000000, 5),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        GanderRequest::insert($requestInsert);

        /**
         * Insert stack, if any
         */
        if(count(self::$stack) > 0) {
            $stackInserts = array_map(function ($s) use ($userId) {
                return [
                    'request_id' => self::$requestId,
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
     * Get the id of the request
     *
     * @return String
     */
    public static function requestId(): String
    {
        return self::$requestId;
    }

    /**
     * Add a tracking call to the stack for this request
     *
     * @param  String $message   Optional custom message to add to the tracking call
     * @param  String $requestId The optional id of the request to append this track to
     * @return bool
     */
    public static function track(String $message = null, String $requestId = null): bool
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
        $file = $t[0]['file'];
        $function = $t[1]['function'];
        $line = $t[0]['line'];

        /**
         * Handle requestId not null, ie. if called from a queued job
         * Insert directly.
         */
        if(!is_null($requestId)) {
            $sql =<<<SQL
            INSERT
            INTO        gander_stack
            VALUES     (null,
                        '$requestId',
                        (SELECT CASE
                                WHEN COUNT(*) > 0
                                THEN MAX(sequence) + 1
                                ELSE 1
                                END
                         FROM   gander_stack gs
                         WHERE  gs.request_id = '$requestId'),
                        null,
                        '$file',
                        '$function',
                        $line,
                        0.0,
                        "$message",
                        NOW(),
                        NOW())
            SQL;
            DB::insert($sql);
            return true;
        }

        /**
         * Calculate time in seconds since last call to this function for this request
         */
        $nowMicroseconds = config('gander.stack_timers_enabled') ? microtime(true) : null;
        $elapsedSeconds = null;
        if(config('gander.stack_timers_enabled') && count(self::$stack) > 0) {
            $elapsedSeconds = $nowMicroseconds - self::$stack[count(self::$stack) - 1]['now_microseconds'];
        }

        /**
         * Add to stack to insert at end of request
         */
        self::$stack[] = [
            'sequence' => count(self::$stack) > 0 ? count(self::$stack) + 1 : 1,
            'file' => $file,
            'function' => $function, 
            'line' => $line,
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
     * @return ?String The new json body with passwords redacted
     */
    private function redactPasswords(String $json): ?String
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
                // handle string of json
                if(is_string($v) && is_array(json_decode($v, true))) {
                    $j[$k] = json_encode($r(json_decode($j[$k], true)));
                }
                // handle any other string or number or null
                elseif(is_scalar($v) || is_null($v)) {
                    $j[$k] = $f($k, $j[$k]);
                }
                // handle any array or object
                else {
                    $j[$k] = $r($j[$k]);
                }
            }
            return $j;
        };

        /**
         * Return json body as string with password values redacted
         */
        return $v($json) ? json_encode($r(json_decode($json, true))) : null;
    }

    /**
     * Get a command-line curl as a string from the request
     *
     * @param  Request $request
     * @return String
     */
    private function getCurl(Request $request): String
    {
        $curl[] = "curl -s -X ".strtoupper($request->method());
        foreach($request->headers->all() as $k => $v) {
            foreach($v as $v1) {
                if(strlen($v1) > 0) {
                    $curl[] = "-H \"$k: ".addcslashes($v1, "\"\\")."\"";
                }
            }
        }
        $curl[] = "\"".$request->fullUrl()."\"";
        $curl[] = $request->isJson() ? "-d '".json_encode(json_decode($this->redactPasswords($request->getContent())), JSON_UNESCAPED_SLASHES)."'" : null;
        return join(" \\".PHP_EOL, $curl)." --compressed";
    }
}
