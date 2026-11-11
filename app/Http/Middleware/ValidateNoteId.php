<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateNoteId
{
    public function handle(Request $request, Closure $next)
    {
        Validator::make(['id' => $request->route('id')], [
            'id' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        return $next($request);
    }
}
