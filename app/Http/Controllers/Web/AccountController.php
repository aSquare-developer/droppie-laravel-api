<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing(['profile', 'activeVehicle']);

        return view('account.index', [
            'user' => $user,
            'profile' => $user->profile,
            'vehicle' => $user->activeVehicle,
        ]);
    }
}
