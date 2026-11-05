<?php

namespace App\Http\Controllers;

use App\Support\AiStats;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(AiStats $stats): Response
    {
        return Inertia::render('Home', [
            'aiStats' => Inertia::defer(fn () => $stats->summary()),
        ]);
    }
}
