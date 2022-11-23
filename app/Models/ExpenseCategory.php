<?php

namespace App\Models;

use App\Support\HasAdvancedFilter;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
   use HasAdvancedFilter;

    public $orderable = [
        'id',
        'name',
        'description',
        'created_at',
        'updated_at',
    ];

    public $filterable = [
        'id',
        'name',
        'description',
        'created_at',
        'updated_at',
    ];

    protected $guarded = [];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id', 'id');
    }
}
