<?php

namespace Act\Relatable\Test\TestModels;

use Illuminate\Database\Eloquent\Model;
use Act\Relatable\HasRelatedContent;

class HasFruitAsRelatedContent extends Model
{
    use HasRelatedContent;

    /** @var array */
    protected $guarded = [];

    /** @var bool */
    public $timestamps = false;
}
