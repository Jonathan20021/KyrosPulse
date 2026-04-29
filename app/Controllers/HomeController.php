<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Plan;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        // La landing debe ser resiliente a un BD aun no instalada.
        $plans = [];
        try {
            $plans = Plan::listActive();
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Landing sin planes (BD no disponible): ' . $e->getMessage());
        }

        $this->view('landing.index', [
            'plans' => $plans,
            'page'  => 'home',
        ], 'layouts.landing');
    }
}
