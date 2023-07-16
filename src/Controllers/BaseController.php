<?php
namespace Gbhorwood\Gander\Controllers;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller as Controller;

/**
 * Controller superclass for fruitbat/cloverhitch/kludgetastic projects
 *
 */
class BaseController extends Controller
{
    /**
     * Return success respones
     *
     * @param   Int   $status  The HTTP status code of the response, ie 200
     * @param   Mixed $data    The data object to return under 'data'
     * @param   Array $hateoas An array of HATEOAS data, optional
     * @return  JsonResponse
     */
    public function sendResponse(int $status, $data, Array $hateoas = []):JsonResponse
    {
        $response = [
            'data' => $data,
        ];

        if (count($hateoas) > 0) {
            $response['links'] = $hateoas;
        }

        return response()
            ->json($response, $status)
            ->header("Access-Control-Allow-Headers",  "Origin, X-Gander-Key")
            ->header("Access-Control-Allow-Methods",  "GET")
            ->header("Access-Control-Allow-Origin",  "*");

    } // sendResponse

    /**
     * Return error response.
     *
     * @param  Int   $status  The HTTP status code of the response
     * @param  Mixed $error   The error message
     * @param  Mixed $details Details of the error message, if any
     * @return JsonResponse
     */
    public function sendError(int $status, $error, $details=null):JsonResponse
    {
        $response = [
            'error' => $error,
            'details' => $details,
        ];

        return response()->json($response, $status)->header("Access-Control-Allow-Origin",  "*");
    } // sendError

    /**
     * Validates a json string against a set of Laravel validation rules.
     *
     * @note Only flat json at this point
     *
     * @param Array $rules The Laravel validator ruleset to validate against
     * @param Request $request The request object laravel injects into the controller
     * @return mixed `boolean` true on success, `Array` on failure
     */
    protected function __validateJson(array $rules, Request $request)
    {
        $requestContent = $request->getContent();
        $data = json_decode($requestContent, true);
        $data = $data ? $data : [];
        $validator = Validator::make($data, $rules);

        if ($validator->passes()) {
            return true;
        } else {
            $errors = $validator->errors();
            $errorArray = [];
            foreach ($errors->getMessages() as $key => $message) {
                $errorArray[$key] = $message;
            }
            return $errorArray;
        }
    } //validateJson

    /**
     * Makes a nice hateoas array from LengthAwarePaginator collection
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginationCollection The return collection from Model::paginate()
     * @return Array
     */
    protected function createPaginationFromOrm(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginationCollection)
    {
        $hateoas = [];
        if ($paginationCollection->hasMorePages()) {
            $hateoas['next_page'] = $paginationCollection->nextPageUrl().
                                    "&size=".
                                    $paginationCollection->count();
        }
        if ($paginationCollection->previousPageUrl()) {
            $hateoas['previous_page'] = $paginationCollection->previousPageUrl().
                                    "&size=".
                                    $paginationCollection->count();
        }
        $hateoas['has_more'] = $paginationCollection->hasMorePages();
        $hateoas['current_page'] = $paginationCollection->currentPage();
        $hateoas['last_page'] = $paginationCollection->lastPage();
        $hateoas['current_size'] = $paginationCollection->perPage();

        return $hateoas;
    } // createPaginationFromOrm

    /**
     * Get an array of all dates as YYYY-MM-DD between start date and end date
     *
     * @param   String  $startdate
     * @param   String  $enddate
     * @return  Array
     */
    protected function __getAllDates(String $startdate, String $enddate):array
    {
        $begin      = new \DateTime($startdate);
        $end        = new \DateTime($enddate);
        $end        = $end->modify('+1 day');
        $interval   = new \DateInterval('P1D');
        $daterange  = new \DatePeriod($begin, $interval, $end);

        $returnableDateRange = [];
        foreach ($daterange as $d) {
            $returnableDateRange[] = $d->format("Y-m-d");
        }

        return $returnableDateRange;
    } // __getAllDates

    /**
     * Validates that a string is in YYYY-MM-DD format
     *
     * @param String $date The string to validate
     * @return boolean
     */
    public function __validYYYYMMDDFormat($date):bool
    {
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    } // __validYYYYMMDDFormat


   /**
    * Validates that a string is in YYYY-MM-DD h:i:s format
    */
    public function __validateDateTime(String $date, String $format = 'Y-m-d H:i:s'):bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    } // __validateDateTime


   /**
    * Validate pagination query string in a request.
    * This will return the object [ 'page' => A, 'size' => B ] on a valid request,
    * or an error response on failure.
    *
    * @param  Request $request
    * @return Array
    */
    protected function __validateAndExtractPaginationData(Request $request):array
    {
        $defaultPage = config('app.pagination.default_page_number') ?? 1;
        $defaultSize = config('app.pagination.default_page_size') ?? 10;
        $page = $request->query('page') !== null ? (int)$request->query('page') : $defaultPage;
        $size = $request->query('size') !== null ? (int)$request->query('size') : $defaultSize;

        if ($page < 1) {
            $page = 1;
        }

        if ($size < 1) {
            $size = 1;
        }

        $request->page = $page;

        return [
            'page' => $page,
            'size' => $size
        ];
    } // __validateAndExtractPaginationData
} // BaseController
