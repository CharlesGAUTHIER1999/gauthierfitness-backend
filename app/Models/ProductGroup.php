<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    // Products belonging to this group, ordered by color
    public function products()
    {
        return $this->hasMany(Product::class, 'group_id')->orderBy('color_code');
    }
}
