<?php

namespace App\Http\Controllers;

use App\Models\InvitationDetail;
use App\Models\MemberDetail;
use App\Models\NotificationSettingDetail;
use App\Models\NotificationDetail;
use App\Models\UsersTokenDetail;
use App\Notifications\MailNotification;
use App\Notifications\VerificationCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\UserDetail;
use App\Models\ProjectTaskDetail;
use Validator;
use Auth;
use Notification;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Show the application dashboard.
     */

    public function index()
    {
        return view('home');
    }

    /**
     * Method to register user
     */

    public function register(Request $request)
    {
        try {
            $element_array = array(
                'name' => 'required',
                'email' => ['required', 'email', 'unique:user_details,email,NULL,id,deleted_at,NULL'],
                'password' => ['required', 'min:6', 'confirmed'],
            );
            $validator = Validator::make($request->all(), $element_array, [
                'name.required' => "Please enter name",
                'email.required' => "Please enter unique email address",
                'password.required' => "Please enter password"
            ]);
            if ($validator->fails()) {
                return json_encode(array("response" => "", "error_msg" => $validator->errors()));
            }
            $user = new UserDetail();
            $user->name = ucfirst($request->input('name'));
            $user->email = $request->input('email');
            $user->password = md5($request->input('password'));
            $user->isVerified = 0;
            $user->verificationCode = rand(1111, 9999);
            $verificationcode = $user->verificationCode;
            $user->type = 0;
            $user->deviceType = 0;
            $user->save();

            $image = $request->file("avatar");
            if (isset($image)) {
                $image_name = $user->id . "." . $image->getClientOriginalExtension();
                $destination_path = public_path("uploads/avatar");
                $image->move($destination_path, $image_name);
                $user->avatar = $image_name;
                $user->save();
            }
            Notification::send($user, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with iTask', 'otp' => "Verification code : " . $verificationcode)));
            return json_encode(array("response" => "Registered successfully", "error_msg" => ""));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to load verification code view
     */

    public function loadVerifyCode($email)
    {
        $email = base64_decode($email);
        if (trim($email) == "") {
            return view('verifycode')->withErrors("Email Id not found");
        }
        $data = UserDetail::where("email", $email)->where("isVerified", 0)->first();
        if (!$data) {
            return view('verifycode')->withErrors("Incorrect data");
        } else {
            return view('verifycode', compact("email"));
        }
    }

    /**
     * Method to verify user
     */

    public function verifyCode(Request $request)
    {
        try {
            $element_array = array(
                'code' => 'required|size:4',
            );
            $validator = Validator::make($request->all(), $element_array, [
                'code.required' => "Please enter verification code"
            ]);

            if ($validator->fails()) {
                return json_encode(array("response" => "", "error_msg" => $validator->errors()));
            }
            $code = $request->input("code");
            $email = $request->input("email");
            $data = UserDetail::where("email", $email)->where("verificationCode", $code)->where("isVerified", 0)->first();
            if (!$data) {
                return json_encode(array("response" => "", "error_msg" => array("vcode" => "Invalid Verification code")));
            }
            $data->isVerified = 1;
            $location_data = geoip()->getLocation();
            $data->timezone = $location_data->timezone;
            $data->save();

            $this->afterVerified($data);
            Auth::loginUsingId($data->id);
            return json_encode(array("response" => "Login successfully", "error_msg" => ""));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to send notification and set notification setting after verified
     */
    private function afterVerified($data)
    {
        $notification_setting = config("constants.notification_setting");
        foreach ($notification_setting as $key => $n) {
            $notification = new NotificationSettingDetail();
            $notification->userId = $data->id;
            $notification->notificationType = $key;
            $notification->email = 1;
            $notification->pushNotification = 1;
            $notification->save();
        }
        $invitation_status = config('constants.invitation_status');
        $invitations = InvitationDetail::where("memberEmailID", $data->email)->where("status", $invitation_status["pending"])->get();
        foreach (count($invitations) > 0 ? $invitations : array() as $i) {
            $i->status = $invitation_status["accepted"];
            $i->memberId = $data->id;
            $i->actionTime = Carbon::now();
            $i->save();

            $project_member = new MemberDetail();
            $project_member->ptId = $i->ptId;
            $project_member->memberId = $data->id;
            $project_member->save();

            $project_task_detail = ProjectTaskDetail::where("id", $i->ptId)->first();
            if ($project_task_detail) {
                if (isset($project_task_detail->creatorData)) {
                    $creator_data = $project_task_detail->creatorData;
                    if ($project_task_detail->type == config("constants.type.project")) {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "member_invitation_create_project";
                        $notification_data->notificationText = config("notificationText.member_invitation_create_project");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = $creator_data->id;
                        $notification_data->sentTo = $data->id;
                        $notification_data->parameters = json_encode(array());
                        $notification_data->ptId = $project_task_detail->id;
                        $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->save();

                        $permission = array("add_member" => array("email" => 1, "push_notification" => 1));
                        $user_permission = NotificationSettingDetail::where("userId", $creator_data->id)->where("notificationType", "add_member")->first();
                        if ($user_permission) {
                            $permission[$user_permission->notificationType]["email"] = $user_permission->email;
                            $permission[$user_permission->notificationType]["push_notification"] = $user_permission->pushNotification;
                        }
                        $create_project_text = config("notificationText.create_project");
                        $create_project_text = str_replace(array("{members}", "{project_name}"), array($data->name, $project_task_detail->name), $create_project_text);
                        if ($permission["add_member"]["email"]) {
                            Notification::send($creator_data, new MailNotification(array('text' => $create_project_text, 'subtext' => '', 'btntext' => 'View this project', 'subject' => ($project_task_detail->name . " | Invitation accepted"))));
                        }

                        $pass_parameter["type"] = "assign";
                        $pass_parameter["subtype"] = "project";
                        $pass_parameter["project_id"] = $project_task_detail->id;
                        $pass_parameter["project_name"] = $project_task_detail->name;
                        $pass_parameter["project_status"] = $project_task_detail->status;
                        $pass_parameter["parent_id"] = intval($project_task_detail->parentId);
                        $pass_parameter["all_parent_ids"] = getBaseParentId($project_task_detail, array(), 1);
                        $pass_parameter["all_child_ids"] = getChildIds($project_task_detail->id, array(), 1);

                        if ($permission["add_member"]["push_notification"]) {
                            if ($creator_data->deviceToken != "") {
                                if ($creator_data->type == intval(config('constants.device_type.ios'))) {
                                    sendNotificationIOS(array($project_task_detail->createdBy => $creator_data->deviceToken), $create_project_text, config("notificationText.create_project"), array("members" => $data->id), "create_project", $pass_parameter, $i->ptId);
                                } else {
                                    sendNotificationAndroid(array($project_task_detail->createdBy => $creator_data->deviceToken), $create_project_text, config("notificationText.create_project"), array("members" => $data->id), "create_project", $pass_parameter, $i->ptId);
                                }
                            } else {
                                $notification_data = new NotificationDetail();
                                $notification_data->notificationType = "create_project";
                                $notification_data->notificationText = config("notificationText.create_project");
                                $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->sentBy = $creator_data->id;
                                $notification_data->sentTo = $creator_data->id;
                                $notification_data->parameters = json_encode(array("members" => $data->id));
                                $notification_data->ptId = $project_task_detail->id;
                                $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->save();
                            }
                            $pusher = getPusherObject();
                            $n_data = array("user_id" => $creator_data->id, "text" => $create_project_text, "type" => "add_project", "pt_id" => $project_task_detail->id);
                            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($creator_data->id), 'App\\Events\\ProjectEvent', $n_data);
                        }
                    } else {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "create_task";
                        $notification_data->notificationText = config("notificationText.create_task");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = $creator_data->id;
                        $notification_data->sentTo = $data->id;
                        $notification_data->parameters = json_encode(array());
                        $notification_data->ptId = $project_task_detail->id;
                        $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->save();
                    }
                }
            }
        }
    }

    /**
     * Method to regenerate verification code
     */

    public function resendCode(Request $request)
    {
        try {
            $element_array = array(
                'email' => 'required|email',
            );
            $validator = Validator::make($request->all(), $element_array, [
                'email.required' => "Please enter email"
            ]);

            if ($validator->fails()) {
                return json_encode(array("response" => "", "error_msg" => $validator->errors()));
            }
            $email = $request->input("email");
            $data = UserDetail::where("email", $email)->where("isVerified", 0)->first();
            if (!$data) {
                return json_encode(array("response" => "", "error_msg" => array("user" => "User not found")));
            }
            $verificationcode = rand(1111, 9999);
            if ($data->verificationCode == $verificationcode) {
                $verificationcode = rand(1111, 9999);
            }
            $data->verificationCode = $verificationcode;
            $data->save();

            Notification::send($data, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with iTask', 'otp' => "Verification code : " . $verificationcode)));
            return json_encode(array("response" => "Verification code is resent. Please check your email.", "error_msg" => ""));
        } catch (\Exception $e) {

        }
    }

    /**
     * Method to check user credentials
     */

    public function vefiryLogin(Request $request)
    {
        try {
            $element_array = array(
                'email' => 'required',
                'password' => 'required|min:3'
            );
            $validator = Validator::make($request->all(), $element_array, [
                'email.required' => "Please enter user name",
                'password.required' => "Please enter password"
            ]);
            if ($validator->fails()) {
                return json_encode(array("response" => "", "error_msg" => $validator->errors()));
            }
            $email = $request->input("email");
            $password = $request->input("password");
            $data = UserDetail::where("email", $email)->where("password", md5($password))->first();
            if (!$data) {
                return json_encode(array("response" => "", "error_msg" => array("not_found" => "User not found")));
            }
            if ($data->isVerified == 0) {
                return json_encode(array("response" => "", "error_msg" => array("not_found" => "User not Verified")));
            }
            $location_data = geoip()->getLocation();
            $data->timezone = $location_data->timezone;
            $data->save();
            Auth::loginUsingId($data->id);
            return json_encode(array("response" => "Login successfully", "error_msg" => ""));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to logged in user detail
     */

    public function memberInformation()
    {
        try {
            $userId = Auth::user()->id;
            $member = UserDetail::where('id', $userId)->first();
            $avatar = $member->avatar;
            $file = getUserAvatarForEdit($avatar);
            $usertimezone = ($member->timezone == NULL ? config('app.timezone') : $member->timezone);
            $timezonelist = \DateTimeZone::listIdentifiers();
            return json_encode(array('id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'avatar' => $file, 'usertimezone' => $usertimezone, 'timezonelist' => $timezonelist));
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * Method to update user data
     */

    public function userUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            $element_array = array(
                'name' => 'required',
                'email' => ['required', 'email', 'unique:user_details,email,' . $user->id . ',id,deleted_at,NULL']
            );
            if ($request->input("password")) {
                $element_array['password'] = ['confirmed'];
            }
            $validator = Validator::make($request->all(), $element_array, [
                'name.required' => "Please enter name",
                'email.required' => "Please enter unique email address",
                'password.confirmed' => "Please enter confirm password"
            ]);
            if ($validator->fails()) {
                return json_encode(array("response" => "", "error_msg" => $validator->errors()));
            }
            $user->name = ucfirst($request->input('name'));
            $user->email = $request->input('email');
            if ($request->input("password")) {
                $user->password = md5($request->input("password"));
            }
            $user->save();
            $image = $request->file("avatar");
            if (isset($image)) {
                $image_name = $user->id . "." . $image->getClientOriginalExtension();
                $destination_path = public_path("uploads/avatar");
                $image->move($destination_path, $image_name);
                $user->avatar = $image_name;
                $user->save();
            } else {
                $avtar_name = $user->avatar;
                unlink(public_path('uploads/' . 'avatar/' . $avtar_name));
                $user->avatar = NULL;
                $user->save();
            }
            return json_encode(array("response" => "User updated successfully", "error_msg" => ""));
        } catch (\Exception$e) {
            return $e->getMessage();
        }

    }

    /**
     * Method to update user timezone
     */

    public function timezoneUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            $user->timezone = $request->input('name');
            $user->save();
            return json_encode(array("response" => "Timezone updated successfully", "error_msg" => ""));
        } catch (\Exception$e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to get reminder setting details
     */

    public function memberNotificationInformation()
    {
        try {
            $userId = Auth::user()->id;
            $member = UserDetail::select('automatic_reminder', 'remind_via_email', 'remind_via_mobile_notification', 'remind_via_desktop_notification')->where('id', $userId)->first();
            return json_encode(array('automatic_reminder' => $member->automatic_reminder, 'remind_via_email' => $member->remind_via_email, 'remind_via_mobile' => $member->remind_via_mobile_notification, 'remind_via_desktop' => $member->remind_via_desktop_notification));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function vianotificationUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            $user->remind_via_email = ($request->input('email') == "true" ? 1 : 0);
            $user->remind_via_mobile_notification = ($request->input('mobile') == "true" ? 1 : 0);
            $user->remind_via_desktop_notification = ($request->input('desktop') == "true" ? 1 : 0);
            $user->save();
            return json_encode(array("response" => "Reminder Notification updated successfully", "error_msg" => ""));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to get auto reminder setting details
     */

    public function autoreminderUpdate(Request $request)
    {
        try {
            $user = Auth::user();
            $user->automatic_reminder = $request->input('name');
            $user->save();
            return json_encode(array("response" => "Auto reminder updated successfully", "error_msg" => ""));
        } catch (\Exception$e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to get notification setting details
     */

    public function notificationInformation()
    {
        try {
            $userId = Auth::user()->id;
            $user_notification_setting = NotificationSettingDetail::where('userId', $userId)->get();
            $notification_settings = config('constants.notification_setting');
            $notification_setting_array = array();
            foreach ($notification_settings as $key => $n) {
                $notification_setting_array[$key] = array(
                    'email' => 1,
                    'push_notification' => 1,
                    'key_val' => $key,
                    'title' => $n
                );
            }
            foreach (
                count($user_notification_setting) > 0
                    ? $user_notification_setting
                    : array()
                as $un
            ) {
                $notification_setting_array[$un->notificationType]['email'] =
                    $un->email;
                $notification_setting_array[$un->notificationType]['push_notification'] = $un->pushNotification;
            }
            return json_encode(array('notification_settings' => array_values($notification_setting_array)));
        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * Method to update notification setting details
     */

    public function notificationsettingUpdate(Request $request)
    {
        try {
            $user_id = Auth::user()->id;
            $setting = $request->all();
            foreach (count($setting) > 0 ? $setting : array() as $key => $s) {
                $notificationType = $key;
                $data = NotificationSettingDetail::where("userId", $user_id)->where("notificationType", $notificationType)->first();
                if (!$data) {
                    $data = new NotificationSettingDetail();
                    $data->userId = $user_id;
                    $data->notificationType = $notificationType;
                }
                $value = json_decode($s);
                $data->email = $value->email;
                $data->pushNotification = $value->push_notification;

                $data->save();

            }

            return json_encode(array("response" => "Notification setting updated successfully", "error_msg" => ""));
        } catch (\Exception$e) {
            return $e->getMessage();
        }
    }

    /**
     * Method to mark notification as read
     */

    public function readNotification(Request $request)
    {
        try {
            $id = $request->input('id');
            $notification = NotificationDetail:: where('id', $id)->first();
            $notification->isRead = 1;
            $notification->save();
            return json_encode(array("response" => "Notification updated successfully", "error_msg" => ""));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "exists:user_details,email,deleted_at,NULL",
            ], [
                "email.exists" => "User with this email id is not exist",
            ]);

            if ($validator->fails()) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => $validator->errors()->first()]);
            }
            $email = $request->input('email');
            $tokendetail = UsersTokenDetail::where("email", $email)->first();
            if (!$tokendetail) {
                $tokendetail = new UsersTokenDetail();
                $tokendetail->email = $email;
            }
            $otp = rand(1111, 9999);
            $tokendetail->otp = $otp;
            $tokendetail->invitationTime = Carbon::now();
            $tokendetail->save();
            Notification::send($tokendetail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use below mentioned OTP to reset password : ', 'subject' => 'Forgot password', 'otp' => "OTP : " . $otp)));
            return redirect('password-reset/' . base64_encode(base64_encode($email)));
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /*
     * Load enter OTP view for forgot password
     */
    public function loadOTPView($email)
    {
        $email = base64_decode(base64_decode($email));
        if (trim($email) == "") {
            return view('auth.passwords.otp')->withErrors("Email Id not found");
        }
        $data = UserDetail::where("email", $email)->first();
        if (!$data) {
            return view('auth.passwords.otp')->withErrors("Incorrect data");
        } else {
            return view('auth.passwords.otp', compact("email"));
        }
    }

    /**
     *  Method for verify OTP
     *
     */
    public function verifyOtp(Request $request)
    {
        try {
            $email = base64_decode(base64_decode($request->input('email')));
            $otp = $request->input('otp');
            $valid = UsersTokenDetail::where("email", $email)->where("otp", implode($otp))->first();
            if (!$valid) {
                return back()->withInput($request->all())->with(['status' => "Please check OTP"]);
            } else {
                $valid->delete();
                return redirect('change-password/' . base64_encode(base64_encode($email)));
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /*
    * Load change password view
    */
    public function loadChangePassword($email)
    {
        $email = base64_decode(base64_decode($email));
        if (trim($email) == "") {
            return view('auth.passwords.changePassword')->withErrors("Email Id not found");
        }
        $data = UserDetail::where("email", $email)->first();
        if (!$data) {
            return view('auth.passwords.changePassword')->withErrors("Incorrect data");
        } else {
            return view('auth.passwords.changePassword', compact("email"));
        }
    }

    /**
     *  Method for resend OTP
     *
     */
    public function resendOtp($email)
    {
        try {
            $email = base64_decode(base64_decode($email));
            $tokendetail = UsersTokenDetail::where("email", $email)->first();
            if (!$tokendetail) {
                $tokendetail = new UsersTokenDetail();
                $tokendetail->email = $email;
            }
            $otp = rand(1111, 9999);
            if (isset($tokendetail->otp) && $tokendetail->otp == $otp) {
                $otp = rand(1111, 9999);
            }
            $tokendetail->otp = $otp;
            $tokendetail->invitationTime = Carbon::now();
            $tokendetail->save();
            Notification::send($tokendetail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use below mentioned OTP to reset password : ', 'subject' => 'Forgot password', 'otp' => "OTP : " . $otp)));
            return back()->with(['status' => "OTP is send again. Please check your email."]);
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for change password
     *
     */
    public function changePassword(Request $request)
    {
        try {
            $element_array = array(
                'password' => ['required', 'confirmed'],
            );
            $validator = Validator::make($request->all(), $element_array, [
                'password.required' => "Please enter password"
            ]);
            if ($validator->fails()) {
                return back()->withInput($request->all())->with(['status' => $validator->errors()->first()]);
            }
            $user = UserDetail::where("email", base64_decode(base64_decode($request->input("email"))))->first();
            if (!$user) {
                return back()->withInput($request->all())->with(['status' => "User not Found"]);
            } else {
                $is_verified = $user->isVerified;
                if (!$user->isVerified) {
                    $user->isVerified = 1;
                }
                $user->password = md5($request->input("password"));
                $location_data = geoip()->getLocation();
                $user->timezone = $location_data->timezone;
                $user->save();

                if (!$is_verified) {
                    $this->afterVerified($user);
                }
                Auth::loginUsingId($user->id);
                return redirect('/dashboard');
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

}


