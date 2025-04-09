<?php

namespace App\Http\Middleware;

use App\Models\LogUserDetail;
use Closure;

class LogAPIToken
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //First it will check header data.
        if ($request->header('Authorization')) {
            //It will decode token and then parse to check valid request or not.
            $token = $request->header('Authorization');
            $token = explode(" ", $token);
            if (is_array($token) && count($token) == 2 && in_array("Bearer", $token)) {
                $token = base64_decode(base64_decode($token[1]));
                if ($token != "") {
                    $token_parts = json_decode($token, true);
                    if (is_array($token_parts) && count($token_parts) == 2) {
                        $user_detail = LogUserDetail::where("id", $token_parts["user_id"])->first();
                        if (!$user_detail) {
                            return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
                        } else {
                            session(['user_details' => $user_detail]);
                            return $next($request);
                        }
                    }
                }
            }
        }
        return response()->json(['ResponseCode' => "11", 'ResponseText' => "Unauthorised"], 200);
    }
}
