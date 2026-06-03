<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BaseApiController
 *
 * All API controllers extend this class.
 * Provides shared helper methods for JSON responses and auth resolution.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Return a successful JSON response.
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $status
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string $message
     * @param  int    $status
     * @param  mixed  $errors
     */
    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Get the authenticated shop (User) from the current request.
     * Returns null if no authenticated user is found.
     */
    protected function getShop(Request $request): ?\App\Models\User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $shop = $request->query('shop') ?: $request->header('x-shopify-shop-domain');

        if ($shop) {
            return User::query()
                ->where('name', $shop)
                ->orWhere('shopify_domain', $shop)
                ->first();
        }

        return null;
    }
}
