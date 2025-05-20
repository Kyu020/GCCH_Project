<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'job_title',
        'job_description',
        'job_location',
        'job_type',
        'monthly_salary',
        'recommended_course',
        'recommended_course_2',
        'recommended_course_3',
        'date_posted',
        'status',
        'company_id', // foreign key from company that posted the job
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }
}
