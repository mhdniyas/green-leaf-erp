<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class BaseApiController extends Controller
{
    protected string $resource = '';

    protected int $defaultPerPage = 15;

    protected array $allowedSorts = [];

    protected array $allowedFilters = [];
}
