<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\V1\Auth\UserAuth;
use App\Requests\V1\LoginUserRequest;
use App\Requests\V1\RegisterUserRequest;
use App\Requests\V1\ForgotPasswordRequest;
use App\Requests\V1\PasswordResetRequest;
use App\Requests\V1\ChangePasswordRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use \Exception;

class AuthController extends Controller
{
    use ApiResponses;

    protected UserAuth $userAuth;

    public function __construct(UserAuth $userAuth)
    {
        $this->userAuth = $userAuth;
    }

    public function register(RegisterUserRequest $request)
    {
        try {
            return $this->userAuth->register($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function login(LoginUserRequest $request)
    {
        try {
            return $this->userAuth->login($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            return $this->userAuth->logout($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            return $this->userAuth->changePassword($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            return $this->userAuth->forgotPassword($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function passwordReset(PasswordResetRequest $request)
    {
        try {
            return $this->userAuth->passwordReset($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getSecurityInfo(Request $request)
    {
        try {
            return $this->userAuth->getSecurityInfo($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
