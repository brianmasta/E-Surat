<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Support\LetterNumbering;
use Illuminate\Http\Request;

class NumberingController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'numbering' => LetterNumbering::monitor(
                $request->query('unit_code'),
                $request->integer('year') ?: null,
                $request->integer('check_sequence') ?: null,
            ),
        ]);
    }
}
