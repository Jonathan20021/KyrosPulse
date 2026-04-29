<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Changelog;

final class ChangelogController extends Controller
{
    /** Pagina publica /changelog */
    public function publicIndex(Request $request): void
    {
        $entries = Changelog::listPublished(80);

        // Agrupar por mes para mejor escaneo visual
        $grouped = [];
        foreach ($entries as $e) {
            $month = date('Y-m', strtotime((string) ($e['published_at'] ?? $e['created_at'])));
            $grouped[$month][] = $e;
        }

        $this->view('changelog.index', [
            'entries'    => $entries,
            'grouped'    => $grouped,
            'categories' => Changelog::CATEGORIES,
        ], 'layouts.landing');
    }
}
