<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Changelog;
use App\Models\Plan;
use App\Models\SaasSetting;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        // La landing debe ser resiliente a una BD aun no instalada.
        $plans     = [];
        $changelog = [];
        $branding  = [];

        try { $plans = Plan::listActive(); } catch (\Throwable) {}
        try { $changelog = Changelog::latest(3); } catch (\Throwable) {}
        try { $branding = SaasSetting::all(); } catch (\Throwable) {}

        $this->view('landing.index', [
            'plans'     => $plans,
            'changelog' => $changelog,
            'branding'  => $branding,
            'page'      => 'home',
        ], 'layouts.landing');
    }
}
