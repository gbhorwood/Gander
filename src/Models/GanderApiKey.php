<?php
namespace Gbhorwood\Gander\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gbhorwood\Gander\Models\GanderApiKey
 *
 */
class GanderApiKey extends Model
{
    use HasFactory;

    protected $table = 'gander_api_keys';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'key',
    ];

    /**
     * Where key exists
     * 
     * @return bool True if key exists
     */
    public function scopeExists(Builder $query, String $key):bool
    {
        return (bool)$query->where('key', '=', $key)->count();
    }
}
