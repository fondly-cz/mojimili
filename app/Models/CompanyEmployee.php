<?php

namespace App\Models;

use Database\Factories\CompanyEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyEmployee extends Model
{
    /** @use HasFactory<CompanyEmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'email',
        'phone',
        'position',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
