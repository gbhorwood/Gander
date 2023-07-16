<?php

namespace Gbhorwood\Gander\Controllers;

use DB;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;

/**
 * Models
 */
use Gbhorwood\Gander\Models\GanderStack;
use Gbhorwood\Gander\Models\GanderApiKey;
use Gbhorwood\Gander\Models\GanderRequest;
use Gbhorwood\Gander\Models\GanderRequestDigest;

/**
 * Controller methods for gander front end reports
 *
 */
class GanderController extends BaseController
{
    /**
     * Headers for HTTP OPTIONS calls. Avoids needing middleware.
     * Allows X-Gander-Key header
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function options(Request $request):JsonResponse
    {
        return response()->json()
            ->header("Access-Control-Allow-Headers",  "Origin, X-Gander-Key")
            ->header("Access-Control-Allow-Methods",  "GET")
            ->header("Access-Control-Allow-Origin",  "*");
    }

    /**
     * Statistics on requests ocurring in the time range defined by $number (ie. 3) and $unit (ie. hours)
     * ago.
     *
     * @param  Int    $number Any positive integer > 1
     * @param  String $units  One of 'minute', 'hour', 'day', 'week', 'month'
     * @return JsonResponse
     */
    public function getRequestsStats(Request $request, Int $number, String $units):JsonResponse
    {
        /**
         * Validate api key manually
         * HTTP 403
         */
        if(!GanderApiKey::exists($request->header('X-Gander-Key'))) {
            return $this->sendError(403, null);
        }

        /**
         * Validate path parameters; return values.
         * HTTP 400
         */
        try {
            $number = $this->validateTimeNumber($number);
            $units = $this->validateTimeUnits($units);
        }
        catch(\Exception $e) {
            return $this->sendError(400, $e->getMessage());
        }

        /**
         * Select base stats on method/endpoint combinations
         */
        $sql =<<<SQL
        SELECT      grq.method,
                    grq.endpoint,
                    COUNT(*) AS total,
                    CAST((((SELECT      count(*)
                            FROM        gander_requests
                            WHERE       response_status >= 200
                            AND         response_status < 300
                            AND         method=grq.method
                            AND         endpoint=grq.endpoint
                            AND         now() - INTERVAL $number $units < created_at)
                            / count(*)) * 100) AS UNSIGNED)
                    AS      successes_percent,
                    SUM(grq.elapsed_seconds) / COUNT(*) AS average_elapsed_seconds
        FROM        gander_requests grq
        WHERE       NOW() - INTERVAL $number $units < grq.created_at
        GROUP BY    grq.method, grq.endpoint
        ORDER BY    total
        DESC
        SQL;
        $reportRaw = DB::select($sql);

        /**
         * Counts by response status for each method/endpoint
         */
        $report = array_map(function($r) use($number, $units){
            $sql =<<<SQL
            SELECT      response_status,
                        ANY_VALUE(response_status_text) as response_status_text,
                        COUNT(*) AS total
            FROM        gander_requests
            WHERE       method = "{$r->method}"
            AND         endpoint = "{$r->endpoint}"
            AND         now() - INTERVAL $number $units < created_at
            GROUP BY    response_status
            SQL;
            $r->responses = DB::select($sql);
            return $r;
        }, $reportRaw);

        /**
         * HTTP 200
         */
        return $this->sendResponse(200, $report);
    }

    /**
     * Get one page of requests
     *
     * @param  Int    $number Any positive integer > 1
     * @param  String $units  One of 'minute', 'hour', 'day', 'week', 'month'
     * @return JsonResponse
     */
    public function getRequestsLogs(Request $request, Int $number, String $units):JsonResponse
    {
        /**
         * Validate api key manually
         * HTTP 403
         */
        if(!GanderApiKey::exists($request->header('X-Gander-Key'))) {
            return $this->sendError(403, null);
        }

        /**
         * Validate path parameters; return values.
         * HTTP 400
         */
        try {
            $number = $this->validateTimeNumber($number);
            $units = $this->validateTimeUnits($units);
		    $startDate = date('Y-m-d H:i:s', strtotime("-$number $units"));
        }
        catch(\Exception $e) {
            return $this->sendError(400, $e->getMessage());
        }

        /**
         * Get pagination data from request
         */
        $paginationDataResult = $this->__validateAndExtractPaginationData($request);
        if (!is_array($paginationDataResult)) {
            return $paginationDataResult;
        }
        $size = $paginationDataResult['size'];

        /**
         * Select page
         */
		$requestsPage = GanderRequestDigest::where('created_at', '>', $startDate)
            ->orderBy('created_at', 'desc')
			->paginate($size);

        /**
         * Reformat
         */
		$requestsItems = $requestsPage->items();
		$requestsHateoas = $this->createPaginationFromOrm($requestsPage);

        /**
         * HTTP 200
         */
        return $this->sendResponse(200, $requestsItems, $requestsHateoas);
    }

    /**
     * Get one request by request_id
     *
     * @param  String $request_id
     * @return JsonResponse
     */
    public function getRequest(Request $request, String $requestId):JsonResponse
    {
        /**
         * Validate api key manually
         * HTTP 403
         */
        if(!GanderApiKey::exists($request->header('X-Gander-Key'))) {
            return $this->sendError(403, null);
        }

        $request = GanderRequest::where('request_id', '=', $requestId)->first();

        return $this->sendResponse(200, $request ?? []);
    }

    /**
     * Validates the number for mysql INTERVAL is greater than 0.
     *
     * @param  Int $number
     * @return Int
     * @throws Exception
     */
    private function validateTimeNumber(Int $number):Int
    {
        if($number < 1) {
            throw new \Exception("Time number must be at least 1");
        }
        return $number;
    }

    /**
     * Validate unit for mysql INTERVAL is one of the accepted values
     * or close enough.
     *
     * @param  String $unit The unit name to validate
     * @return String The appropriate unit value
     * @throws Exception
     */
    private function validateTimeUnits(String $unit):String
    {
        $timeUnits = [
            'minute' => 'minute',
            'minutes' => 'minute',
            'min' => 'minute',
            'hour' => 'hour',
            'hours' => 'hour',
            'week' => 'week',
            'weeks' => 'week',
            'month' => 'month',
            'months' => 'month',
            'day' => 'day',
            'days' => 'day',
        ];

        if(!isset($timeUnits[strtolower($unit)])) {
            throw new \Exception("Time unit must be one of: ".join(', ', array_unique(array_values($timeUnits))));
        }

        return strtoupper($timeUnits[$unit]);
    }
}