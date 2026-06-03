<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AddressLookupException;
use App\Exceptions\InvalidAddressException;
use App\Http\Controllers\Controller;
use App\Services\GoogleAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function autocomplete(Request $request, GoogleAddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'min:3', 'max:255'],
            'session_token' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            return response()->json([
                'data' => $addresses->autocomplete(
                    $validated['input'],
                    $validated['session_token'] ?? null
                ),
            ]);
        } catch (AddressLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function validateAddress(Request $request, GoogleAddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
            'session_token' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            return response()->json([
                'data' => $addresses->validatePlace(
                    $validated['place_id'],
                    $validated['session_token'] ?? null
                ),
            ]);
        } catch (InvalidAddressException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'place_id' => [$exception->getMessage()],
                ],
            ], 422);
        } catch (AddressLookupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
