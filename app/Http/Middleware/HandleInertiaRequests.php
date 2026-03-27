<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
                'import_conflict' => fn () => $request->session()->get('import_conflict'),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,

                        'roles' => method_exists($request->user(), 'getRoleNames')
                            ? $request->user()->getRoleNames()
                            : [],

                        'is_admin' => method_exists($request->user(), 'hasRole')
                            ? $request->user()->hasRole('admin')
                            : false,
                    ]
                    : null,
            ],
        ]);
    }
}