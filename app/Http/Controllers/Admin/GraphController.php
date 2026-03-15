<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Inertia\Inertia;

class GraphController extends Controller
{
    public function index(): \Inertia\Response
    {
        $spaceId = Space::first()?->id ?? '';

        return Inertia::render('Graph/Index', [
            'spaceId' => $spaceId,
        ]);
    }
}
