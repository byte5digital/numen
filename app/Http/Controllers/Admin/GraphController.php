<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GraphController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        /** @var Space|null $firstSpace */
        $firstSpace = $request->space();
        $spaceId = $firstSpace !== null ? $firstSpace->id : '';

        return Inertia::render('Graph/Index', [
            'spaceId' => $spaceId,
        ]);
    }
}
