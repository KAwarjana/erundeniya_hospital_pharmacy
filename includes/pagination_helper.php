<?php
// pagination_helper.php - Add this file to your project

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML for pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation" class="mt-4">';
    $html .= '<ul class="pagination pagination-primary justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">';
        $html .= '<i class="material-symbols-rounded">chevron_left</i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link"><i class="material-symbols-rounded">chevron_left</i></span>';
        $html .= '</li>';
    }
    
    // Page numbers with smart ellipsis
    $range = 2; // Number of pages to show before and after current page
    
    // Always show first page
    if ($currentPage > $range + 2) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '&page=1">1</a>';
        $html .= '</li>';
        
        if ($currentPage > $range + 3) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Pages around current page
    $start = max(1, $currentPage - $range);
    $end = min($totalPages, $currentPage + $range);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active">';
            $html .= '<span class="page-link">' . $i . '</span>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>';
            $html .= '</li>';
        }
    }
    
    // Always show last page
    if ($currentPage < $totalPages - $range - 1) {
        if ($currentPage < $totalPages - $range - 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '&page=' . $totalPages . '">' . $totalPages . '</a>';
        $html .= '</li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">';
        $html .= '<i class="material-symbols-rounded">chevron_right</i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link"><i class="material-symbols-rounded">chevron_right</i></span>';
        $html .= '</li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Build pagination URL preserving all query parameters
 * @param array $params Current GET parameters
 * @return string Base URL with all parameters except 'page'
 */
function buildPaginationUrl($params) {
    $filteredParams = array_filter($params, function($key) {
        return $key !== 'page';
    }, ARRAY_FILTER_USE_KEY);
    
    $queryString = http_build_query($filteredParams);
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
    
    return $baseUrl . '?' . $queryString;
}

/**
 * Calculate pagination values
 * @param int $totalRecords Total number of records
 * @param int $recordsPerPage Records to show per page
 * @param int $currentPage Current page number
 * @return array Array with offset, limit, totalPages, currentPage
 */
function calculatePagination($totalRecords, $recordsPerPage = 20, $currentPage = 1) {
    $totalPages = max(1, ceil($totalRecords / $recordsPerPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'offset' => $offset,
        'limit' => $recordsPerPage,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
        'totalRecords' => $totalRecords,
        'recordsPerPage' => $recordsPerPage
    ];
}

/**
 * Generate pagination info text
 * @param array $pagination Pagination data from calculatePagination
 * @param int $currentCount Current number of records on page
 * @return string Info text like "Showing 1-20 of 150 records"
 */
function getPaginationInfo($pagination, $currentCount) {
    if ($pagination['totalRecords'] == 0) {
        return 'No records found';
    }
    
    $start = $pagination['offset'] + 1;
    $end = min($pagination['offset'] + $currentCount, $pagination['totalRecords']);
    
    return "Showing {$start}-{$end} of {$pagination['totalRecords']} records";
}
?>

<!-- Pagination CSS Styles - Add to your main stylesheet -->
<style>
.pagination {
    display: flex;
    padding-left: 0;
    list-style: none;
    border-radius: 0.5rem;
    gap: 0.25rem;
}

.page-item {
    margin: 0;
}

.page-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
    margin: 0;
    font-size: 0.875rem;
    color: #344767;
    text-decoration: none;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    transition: all 0.15s ease-in;
    min-width: 40px;
    height: 40px;
}

.page-link:hover {
    z-index: 2;
    color: #fff;
    background-color: #42424a;
    border-color: #42424a;
    transform: translateY(-2px);
}

.page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background: linear-gradient(195deg, #42424a 0%, #191919 100%);
    border-color: #42424a;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    background-color: #fff;
    border-color: #dee2e6;
    opacity: 0.5;
}

.page-link .material-symbols-rounded {
    font-size: 20px;
    line-height: 1;
}

/* Responsive pagination */
@media (max-width: 576px) {
    .pagination {
        font-size: 0.75rem;
        gap: 0.125rem;
    }
    
    .page-link {
        padding: 0.375rem 0.5rem;
        min-width: 32px;
        height: 32px;
        font-size: 0.75rem;
    }
    
    .page-link .material-symbols-rounded {
        font-size: 16px;
    }
}

/* Pagination info text */
.pagination-info {
    text-align: center;
    margin-top: 1rem;
    color: #67748e;
    font-size: 0.875rem;
}

.pagination-info strong {
    color: #344767;
    font-weight: 600;
}
</style>