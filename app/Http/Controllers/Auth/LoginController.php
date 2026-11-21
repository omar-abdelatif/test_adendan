<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, mixed $user)
    {
        $notification = [
            'message' => 'تم تسجيل الدخول',
            'alert-type' => 'success',
        ];

        return redirect()->route('home')->with($notification);
    }

    protected function getTranslatedLoginDescription(string $eventName): string
    {
        $descriptions = [
            'login' => 'قام المستخدم ":name" بتسجيل الدخول بنجاح.',
            'logout' => 'قام المستخدم ":name" بتسجيل الخروج بنجاح.',
        ];
        $user = Auth::user();
        $arDescription = $descriptions[$eventName] ?? 'حدث تسجيل دخول غير معروف.';

        return strtr($arDescription, [':name' => $user->name]);
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        // This override replaced Laravel's login(), which also enforces the
        // failed-attempt lockout; without these lines passwords could be guessed endlessly.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            $this->clearLoginAttempts($request);
            $user = $this->guard()->user();
            if ($user) {
                activity('auth')->performedOn($user)->causedBy($user)->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])->event('loggedin')->log($this->getTranslatedLoginDescription('login'));
            }
            $request->session()->regenerate();

            return $this->authenticated($request, $user) ?: redirect()->intended($this->redirectPath());
        }
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    public function logout(Request $request)
    {
        $user = $this->guard()->user();
        if ($user) {
            activity('auth')->performedOn($user)->causedBy($user)->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])->event('loggedout')->log($this->getTranslatedLoginDescription('logout'));
        }
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson() ? new JsonResponse([], 204) : redirect('/');
    }
}
