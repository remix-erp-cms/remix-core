<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ApiAuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'username' => 'required|string|min:3|max:255|unique:users',
                'password' => 'required|string|min:3|confirmed',
            ]);
            if ($validator->fails()) {
                return response(['errors' => $validator->errors()->all()], 422);
            }
            $request['password'] = Hash::make($request['password']);
            $request['remember_token'] = Str::random(10);
            $user = User::create($request->toArray());
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;
            $response = ['token' => $token];
            return response($response, 200);
        } catch (\Exception $err) {
            return $this->respondWithError($err->getMessage(), [], 403);
        }
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|min:3',
                'password' => 'required|string|min:3',
            ]);

            if ($validator->fails()) {
                $message = "Vui lòng nhập đủ thông tin";
                $errors = $validator->errors()->all();
                return $this->respondWithError($message, $errors, 422);
            }

            $user = User::where("status", "active")
                ->where("username", $request->username)
                ->orWhere("email", $request->username)
                ->first();

            if ($user) {
                if (Hash::check($request->password, $user->password)) {
                    $token = $user->createToken('Laravel Password Grant Client')->accessToken;

                    $dataUser = User::with([
                        'roles',
                    ])
                        ->find($user->id);

                    $response = [
                        'user' => $dataUser,
                        'token' => $token
                    ];
                    return $this->respondSuccess($response);
                } else {
                    $message = "Thông tin đăng nhập chưa chính xác";
                    return $this->respondWithError($message, [], 422);
                }
            } else {
                $message = "Người dùng không tồn tại";
                return $this->respondWithError($message, [], 422);
            }

        } catch (\Exception $err) {
            return $this->respondWithError($err->getMessage(), [], 403);
        }
    }

    public function logout(Request $request)
    {
        try {
            if (Auth::guard('api')->user()) {
                $token = Auth::guard('api')->user()->token();
                $token->revoke();
            }

            $response = "Bạn đã đăng xuất thành công!";

            return $this->respondSuccess($response);
        } catch (\Exception $err) {
            return $this->respondWithError($err->getMessage(), [], 403);
        }
    }
}
