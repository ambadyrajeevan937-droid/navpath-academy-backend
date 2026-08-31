<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'learnyst_course_id', 'slug', 'title', 'category', 'description',
        'price', 'mrp', 'validity_days', 'is_published', 'synced_at',
    ];
    protected $casts = ['is_published' => 'boolean', 'synced_at' => 'datetime'];

    public function lessons(): HasMany    { return $this->hasMany(Lesson::class)->orderBy('position'); }
    public function materials(): HasMany  { return $this->hasMany(Material::class); }
    public function tests(): HasMany      { return $this->hasMany(Test::class); }
    public function enrolments(): HasMany { return $this->hasMany(Enrolment::class); }

    public function getRouteKeyName(): string { return 'slug'; }
}
