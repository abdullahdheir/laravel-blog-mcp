<?php

namespace AbdullahDheir\BlogMcp\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';

    public $timestamps = false;

    protected $guarded = [];
}
