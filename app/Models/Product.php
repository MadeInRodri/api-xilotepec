<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'price', 'category_id', 'url_image', 'max_quantity', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
                    ->withPivot('required_quantity')
                    ->withTimestamps();
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}