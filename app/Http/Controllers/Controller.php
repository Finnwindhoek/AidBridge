<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Gives every controller $this->authorize(), so Policies can be enforced as a
    // second layer behind the route-level role middleware.
    use AuthorizesRequests, ValidatesRequests;
}
