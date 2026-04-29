<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Paginador simple para queries SQL.
 *
 *   $page = Paginator::fromRequest($request);
 *   $rows = Database::fetchAll("SELECT ... LIMIT $page->limit OFFSET $page->offset", $params);
 *   $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM ...", $params);
 *   $page->setTotal($total);
 */
final class Paginator
{
    public int $page;
    public int $perPage;
    public int $offset;
    public int $limit;
    public int $total = 0;
    public int $lastPage = 1;

    public function __construct(int $page = 1, int $perPage = 25)
    {
        $this->page    = max(1, $page);
        $this->perPage = min(200, max(5, $perPage));
        $this->offset  = ($this->page - 1) * $this->perPage;
        $this->limit   = $this->perPage;
    }

    public static function fromRequest(Request $request, int $perPage = 25): self
    {
        return new self(
            (int) ($request->query('page') ?? 1),
            (int) ($request->query('per_page') ?? $perPage)
        );
    }

    public function setTotal(int $total): self
    {
        $this->total    = $total;
        $this->lastPage = max(1, (int) ceil($total / $this->perPage));
        return $this;
    }

    public function links(string $baseUrl, array $extraQuery = []): string
    {
        if ($this->lastPage <= 1) return '';
        $html = '<div class="flex items-center gap-1 text-sm">';
        $start = max(1, $this->page - 2);
        $end   = min($this->lastPage, $this->page + 2);

        $build = function (int $p) use ($baseUrl, $extraQuery): string {
            $q = array_merge($extraQuery, ['page' => $p]);
            return $baseUrl . '?' . http_build_query($q);
        };

        if ($this->page > 1) {
            $html .= '<a href="' . e($build($this->page - 1)) . '" class="px-3 py-1.5 rounded-lg dark:hover:bg-white/5 hover:bg-slate-100 dark:text-slate-300 text-slate-700">&lsaquo;</a>';
        }
        for ($p = $start; $p <= $end; $p++) {
            $cls = $p === $this->page
                ? 'bg-primary text-white'
                : 'dark:hover:bg-white/5 hover:bg-slate-100 dark:text-slate-300 text-slate-700';
            $html .= '<a href="' . e($build($p)) . '" class="px-3 py-1.5 rounded-lg ' . $cls . '">' . $p . '</a>';
        }
        if ($this->page < $this->lastPage) {
            $html .= '<a href="' . e($build($this->page + 1)) . '" class="px-3 py-1.5 rounded-lg dark:hover:bg-white/5 hover:bg-slate-100 dark:text-slate-300 text-slate-700">&rsaquo;</a>';
        }

        $html .= '</div>';
        return $html;
    }
}
