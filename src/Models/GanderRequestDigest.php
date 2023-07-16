<?php
namespace Gbhorwood\Gander\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gbhorwood\Gander\Models\GanderRequest
 *
 */
class GanderRequestDigest extends GanderRequest
{

    /**
     * Serialization
     *
     * @return Array
     */
    public function jsonSerialize():Array
    {
        return [
            'request_id' => $this->request_id,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'response_status' => $this->response_status,
            'response_status_text' => $this->response_status_text,
            'elapsed_seconds' => $this->elapsed_seconds,
            'user_id' => $this->user_id,
            'user_ip' => $this->user_ip,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at)),
        ];
    } // jsonSerialize
}
