<?php
// app/Http/Controllers/CodeHistoryController.php

namespace App\Http\Controllers;

use App\Models\CodeHistory;
use Illuminate\Http\Request;

class CodeHistoryController extends Controller
{
    public function index()
    {
        $history = CodeHistory::orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    public function show($id)
    {
        $history = CodeHistory::find($id);

        if (!$history) {
            return response()->json([
                'success' => false,
                'message' => 'History not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $history->id,
                'prompt' => $history->prompt,
                'code' => $history->code,
                'type' => $history->type,
                'description' => $history->description,
                'libraries' => json_decode($history->libraries, true),
                'created_at' => $history->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function destroy($id)
    {
        $history = CodeHistory::find($id);

        if (!$history) {
            return response()->json([
                'success' => false,
                'message' => 'History not found'
            ], 404);
        }

        $history->delete();

        return response()->json([
            'success' => true,
            'message' => 'History deleted successfully'
        ]);
    }

    public function clear()
    {
        CodeHistory::truncate();

        return response()->json([
            'success' => true,
            'message' => 'All history cleared'
        ]);
    }
}