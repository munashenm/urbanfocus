<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function suggest(Request $request, SearchService $search): JsonResponse
    {
        return response()->json([
            'results' => $search->suggest($request->get('q', '')),
        ]);
    }
}
