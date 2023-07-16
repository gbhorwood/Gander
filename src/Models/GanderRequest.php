<?php
namespace Gbhorwood\Gander\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gbhorwood\Gander\Models\GanderRequest
 *
 */
class GanderRequest extends Model
{
    use HasFactory;

    protected $table = 'gander_requests';

    public $timestamps = true;

   /**
    * All stack records for this request
    * 
    * @return hasMany
    */
    public function stack():hasMany 
    {
      return $this->hasMany(GanderStack::class, 'request_id', 'request_id');
    }

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
            'url' => $this->url,
            'response_status' => $this->response_status,
            'response_status_text' => $this->response_status_text,
            'elapsed_seconds' => $this->elapsed_seconds,
            'user_id' => $this->user_id,
            'user_ip' => $this->user_ip,
            'request_body_json' => $this->request_body_json,
            'response_body_json' => $this->response_body_json,
            'stack' => $this->stack,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at)),
        ];
    } // jsonSerialize
}
