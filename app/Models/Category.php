<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'parent_id'];

    /** Parent category of this category. */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Child categories of this category. */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** Products belonging to this category. */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_category');
    }
}
