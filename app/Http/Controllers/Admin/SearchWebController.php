<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SearchWebController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Search/Health');
    }

    public function synonyms(): Response
    {
        return Inertia::render('Admin/Search/Synonyms/Index');
    }

    public function promoted(): Response
    {
        return Inertia::render('Admin/Search/Promoted/Index');
    }

    public function analytics(): Response
    {
        return Inertia::render('Admin/Search/Analytics');
    }
}
