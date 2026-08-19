<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait RespondTrait
{
    protected function paginationMeta($paginated): array {
        return [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ];
    }

    protected function respondWithJson($content, $status = 200, array $headers = [], $options = 0) {
        if (is_array($content) && ($content['items'] ?? null) instanceof LengthAwarePaginator) {
            $paginated = $content['items'];
            $content['items'] = [
                'data' => $paginated->items(),
                'pagination' => $this->paginationMeta($paginated),
            ];
        }

        $response = [
            'data' => $content,
            'status' => $status,
        ];

        return response()->json($response, $status, $headers, $options);
    }

    protected function respondWithError($message = 'An error occurred', $code = 400, $status = 400, array $headers = [], $options = 0, array $extra = []) {
        $content = array_merge([
            'status' => $code,
            'errors' => $message,
        ], $extra);

        return response()->json($content, $code, $headers, $options);
    }
}
