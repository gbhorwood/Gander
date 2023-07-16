<?php
namespace Gbhorwood\Gander\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gbhorwood\Gander\Models\GanderStack
 *
 */
class GanderStack extends Model
{
    use HasFactory;

    protected $table = 'gander_stack';

    public $timestamps = true;

    /**
     * Serialization
     *
     * @return Array
     */
    public function jsonSerialize():Array
    {
        return [
            'request_id' => $this->request_id,
            'sequence' => $this->sequence,
            'user_id' => $this->user_id,
            'file' => $this->file,
            'function' => $this->function,
            'line' => $this->line,
            'elapsed_seconds' => $this->elapsed_seconds,
            'message' => $this->message,
            'created_at' => date('Y-m-d H:i:s', strtotime($this->created_at)),
        ];
    } // jsonSerialize
}
