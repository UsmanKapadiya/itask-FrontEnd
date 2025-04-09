<?php

namespace App\Http\Controllers\Api;

use App\Models\InvitationDetail;
use App\Models\LogDataDetail;
use App\Models\LogUserDetail;
use App\Models\MemberDetail;
use App\Models\NotificationDetail;
use App\Models\NotificationSettingDetail;
use App\Models\ProjectTaskDetail;
use App\Models\UserDetail;
use App\Models\UsersTokenDetail;
use App\Notifications\MailNotification;
use App\Notifications\VerificationCode;
use Illuminate\Http\Request;
use Validator;
use Notification;
use Carbon\Carbon;

class LogUserController extends Controller
{
    /**
     *  Method to register user
     *
     */
    public function registration(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email|unique:log_user_details,email,NULL,id,deleted_at,NULL",
            ], [
                "email.required" => "Please enter email",
                "email.unique" => "User with this email id is exist",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $user_detail = new LogUserDetail();
            $user_detail->name = ucfirst($request->input("name"));
            $user_detail->email = $request->input("email");
            $user_detail->password = md5($request->input("password"));
            $user_detail->isVerified = 0;
            $user_detail->verificationCode = rand(1111, 9999);
            $verificationcode = $user_detail->verificationCode;
            $user_detail->type = $request->input("type");
            $user_detail->deviceType = $request->input("deviceType");
            $user_detail->deviceId = $request->input("deviceId");
            $user_detail->save();

            $file = NULL;
            if (count($_FILES) > 0) {
                $file = $_FILES["file"];
            }
            if ($file != NULL) {
                $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
                $user_detail->avatar = $user_detail->id . "." . $ext;
                $destination_path = public_path("uploads/logAvatar") . "/" . $user_detail->avatar;
                move_uploaded_file($file["tmp_name"], $destination_path);
                $user_detail->save();
            }
            Notification::send($user_detail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with Log app', 'otp' => "Verification code : " . $verificationcode)));
            return $this->sendResultJSON("1", "User Successfully Registered", array("avatar_url" => getUserAvatarForEdit($user_detail->avatar)));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to verify verification code
     *
     */
    public function verifyVerificationCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "verificationCode" => "required",
                "email" => "required|email",
            ], [
                "verificationCode.required" => "Please enter verification code",
                "email.required" => "Please enter email",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $verification_code = $request->input("verificationCode");
            $user_detail = LogUserDetail::where("verificationCode", $verification_code)->where("email", $request->input("email"))->where("isVerified", 0)->first();
            if (empty($user_detail)) {
                return $this->sendResultJSON("2", "Invalid verification code");
            }
            $user_detail->isVerified = 1;
            $user_detail->verificationCode = $verification_code;
            $user_detail->deviceToken = $request->input("device_token");
            if ($request->input("timezone")) {
                $user_detail->timezone = $request->input("timezone");
            }
            $user_detail->save();
            $user_token = generate_access_token($user_detail->id);
           // $this->afterVerification($user_detail);
            return $this->sendResultJSON("1", "User verified", array("user_id" => $user_detail->id, "authentication_token" => $user_token, "user_email" => $user_detail->email, "timezone" => $user_detail->timezone));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    private function afterVerification($user_detail)
    {
        $notification_setting = config("constants.notification_setting");
        foreach ($notification_setting as $key => $n) {
            $data = new NotificationSettingDetail();
            $data->userId = $user_detail->id;
            $data->notificationType = $key;
            $data->email = 1;
            $data->pushNotification = 1;
            $data->save();
        }

        $invitation_status = config('constants.invitation_status');
        $invitations = InvitationDetail::where("memberEmailID", $user_detail->email)->where("status", $invitation_status["pending"])->get();
        foreach (count($invitations) > 0 ? $invitations : array() as $i) {
            $i->status = $invitation_status["accepted"];
            $i->memberId = $user_detail->id;
            $i->actionTime = Carbon::now();
            $i->save();

            $project_member = new MemberDetail();
            $project_member->ptId = $i->ptId;
            $project_member->memberId = $user_detail->id;
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
                        $notification_data->sentTo = $user_detail->id;
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
                        $create_project_text = str_replace(array("{members}", "{project_name}"), array($user_detail->name, $project_task_detail->name), $create_project_text);
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
                                    sendNotificationIOS(array($project_task_detail->createdBy => $creator_data->deviceToken), $create_project_text, config("notificationText.create_project"), array("members" => $user_detail->id), "create_project", $pass_parameter, $i->ptId);
                                } else {
                                    sendNotificationAndroid(array($project_task_detail->createdBy => $creator_data->deviceToken), $create_project_text, config("notificationText.create_project"), array("members" => $user_detail->id), "create_project", $pass_parameter, $i->ptId);
                                }
                            } else {
                                $notification_data = new NotificationDetail();
                                $notification_data->notificationType = "create_project";
                                $notification_data->notificationText = config("notificationText.create_project");
                                $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->sentBy = $creator_data->id;
                                $notification_data->sentTo = $creator_data->id;
                                $notification_data->parameters = json_encode(array("members" => $user_detail->id));
                                $notification_data->ptId = $project_task_detail->id;
                                $notification_data->created_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->updated_at = Carbon::now()->format('Y-m-d h:i:s');
                                $notification_data->save();
                            }
                            $pusher = getPusherObject();
                            $data = array("user_id" => $creator_data->id, "text" => $create_project_text, "type" => "add_project", "pt_id" => $project_task_detail->id);
                            $pusher->trigger('user' . env('PUSHER_APP_KEY') . "." . md5($creator_data->id), 'App\\Events\\ProjectEvent', $data);
                        }
                    } else {
                        $notification_data = new NotificationDetail();
                        $notification_data->notificationType = "create_task";
                        $notification_data->notificationText = config("notificationText.create_task");
                        $notification_data->sentTime = Carbon::now()->format('Y-m-d h:i:s');
                        $notification_data->sentBy = $creator_data->id;
                        $notification_data->sentTo = $user_detail->id;
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
     *  Method to resend verification code
     *
     */
    public function ResendVerificationCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email"
            ], [
                "email.required" => "Please enter email"
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $user_detail = LogUserDetail::where("email", $request->input("email"))->first();
            if ($user_detail) {
                if ($user_detail->isVerified == 1) {
                    return $this->sendResultJSON("2", "User is already verified");
                }
                $verification_code = rand(1111, 9999);
                if ($user_detail->verificationCode == $verification_code) {
                    $verification_code = rand(1111, 9999);
                }
                $user_detail->verificationCode = $verification_code;
                $user_detail->save();

                Notification::send($user_detail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with Log app', 'otp' => "Verification code : " . $verification_code)));
                return $this->sendResultJSON("1", "Verification code is resent. Please check your email.");
            }
            return $this->sendResultJSON("0", "User data not found");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for login
     *
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email",
                "password" => "required"
            ], [
                "email.required" => "Please enter Email",
                "password.required" => "Please enter password",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $email = $request->input("email");
            $password = md5($request->input("password"));
            $user = LogUserDetail::where("email", $email)->where("password", $password)->first();

            if (!$user) {
                return $this->sendResultJSON("2", "User not Found");
            } else {
                if ($user->isVerified == 1) {
                    $user_token = generate_access_token($user->id);
                    $user->deviceToken = $request->input("device_token");
                    $user->type = $request->input("type");
                    $user->deviceType = $request->input("deviceType");
                    if ($request->input("timezone")) {
                        $user->timezone = $request->input("timezone");
                    }
                    $user->save();
                    return $this->sendResultJSON("1", "Successfully Login", array("user_id" => $user->id, "authentication_token" => $user_token, "user_email" => $user->email, "timezone" => $user->timezone));
                } else {
                    return $this->sendResultJSON("3", "User not Verified");
                }
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for forgot password
     *
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
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $tokendetail = UsersTokenDetail::where("email", $request->input('email'))->first();
            if (!$tokendetail) {
                $tokendetail = new UsersTokenDetail();
                $tokendetail->email = $request->input('email');
            }
            $otp = rand(1111, 9999);
            $tokendetail->otp = $otp;
            $tokendetail->invitationTime = Carbon::now();
            $tokendetail->save();
            Notification::send($tokendetail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use below mentioned OTP to reset password : ', 'subject' => 'Forgot password', 'otp' => "OTP : " . $otp)));
            return $this->sendResultJSON("1", "OTP is sent. Please check your email.");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for verify OTP
     *
     */
    public function verifyOtp(Request $request)
    {
        try {
            $email = $request->input('email');
            $otp = $request->input('otp');
            $valid = UsersTokenDetail::where("email", $email)->where("otp", $otp)->first();
            if (!$valid) {
                return $this->sendResultJSON("2", "Please check OTP");
            } else {
                $valid->delete();
                return $this->sendResultJSON("1", "User Verified");
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for resend OTP
     *
     */
    public function resendOtp(Request $request)
    {
        try {
            $email = $request->input('email');
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
            return $this->sendResultJSON("1", "OTP is sent again. Please check your email.");
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
            $validator = Validator::make($request->all(), [
                "email" => "exists:user_details,email",
                "password" => "required"
            ], [
                "email.required" => "Email Id is not exist",
                "password.required" => "Please enter password",
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $user = UserDetail::where("email", $request->input("email"))->first();

            if (!$user) {
                return $this->sendResultJSON("2", "User not Found");
            } else {
                $is_verified = $user->isVerified;
                if (!$user->isVerified) {
                    $user->isVerified = 1;
                }
                $user->password = md5($request->input("password"));
                $user->deviceToken = $request->input("device_token");
                if ($user->timezone == NULL && $request->input("timezone")) {
                    $user->timezone = $request->input("timezone");
                }
                $user->save();
                if (!$is_verified) {
                    $this->afterVerification($user);
                }
                $user_token = generate_access_token($user->id);
                return $this->sendResultJSON("1", "Password changed successfully", array("user_id" => $user->id, "authentication_token" => $user_token, "user_email" => $user->email, "timezone" => $user->timezone));
            }
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method for update device token
     *
     */
    public function updateDeviceToken(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user_data = session("user_details");
            if ($request->input("old_device_token") && $request->input("device_token") == "") {
                if ($user_data->deviceToken == $request->input("old_device_token")) {
                    $user_data->deviceToken = NULL;
                }
            } else {
                $user_data->deviceToken = $request->input("device_token");
            }
            $user_data->save();
            return $this->sendResultJSON("1", "Device token updated");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to get user details
     *
     */
    public function getUserDetails()
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user = session("user_details");
            return $this->sendResultJSON("1", "", array("name" => $user->name, "avatar" => getUserAvatarForEdit($user->avatar), "email" => $user->email));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to update user details
     *
     */
    public function updateUserDetails(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user = session("user_details");
            $validator = Validator::make($request->all(), [
                "email" => "required|email|unique:log_user_details,email,$user->id,id,deleted_at,NULL",
            ], [
                "email.required" => "Please enter email",
                "email.unique" => "User with this email id is exist",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            if ($request->input("name")) {
                $user->name = ucfirst($request->input("name"));
            }
            if ($request->input("email")) {
                $user->email = $request->input("email");
            }
            if ($request->input("password")) {
                $user->password = md5($request->input("password"));
            }
            $user->save();
            $file = NULL;
            if (count($_FILES) > 0) {
                $file = $_FILES["file"];
            }
            if ($file != NULL) {
                $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
                $user->avatar = $user->id . "." . $ext;
                $destination_path = public_path("uploads/logAvatar") . "/" . $user->avatar;
                move_uploaded_file($file["tmp_name"], $destination_path);
                $user->save();
            }
            if ($request->input("isAvatarDeleted")) {
                if ($user->avatar != NULL) {
                    unlink(public_path("uploads/logAvatar/" . $user->avatar));
                }
                $user->avatar = NULL;
                $user->save();
            }
            return $this->sendResultJSON("1", "User updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Update notification setting
     *
     */
    public function updateNotificationSetting(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "settingType" => "required",
                "notificationType" => "required",
                "value" => "required"
            ], [
                "settingType.required" => "Please enter setting type",
                "notificationType.required" => "Please enter notification type",
                "value.required" => "Please enter value"
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $user_id = session("user_details")->id;
            $type = $request->input("settingType");
            $notificationType = $request->input("notificationType");
            $data = NotificationSettingDetail::where("userId", $user_id)->where("notificationType", $type)->first();
            if (!$data) {
                $data = new NotificationSettingDetail();
                $data->userId = $user_id;
                $data->notificationType = $type;
            }
            $data->{$notificationType} = intval($request->input("value"));
            $data->save();
            return $this->sendResultJSON("1", "Setting updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to update user timezone
     *
     */
    public function updateTimezone(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user_data = session("user_details");
            $user_data->timezone = $request->input("timezone");
            $user_data->save();
            return $this->sendResultJSON("1", "Timezone updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to update automatic reminder setting
     *
     */
    public function updateDefaultReminder(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user_data = session("user_details");
            $user_data->automatic_reminder = $request->input("defaultReminder");
            $user_data->save();
            return $this->sendResultJSON("1", "Automatic reminder option updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    /**
     *  Method to update remind via option setting
     *
     */
    public function updateRemindVia(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $validator = Validator::make($request->all(), [
                "type" => "required",
                "value" => "required"
            ], [
                "type.required" => "Please enter type",
                "value.required" => "Please enter value"
            ]);
            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }
            $user_data = session("user_details");
            $type = $request->input("type");
            $field = ($type == "email" ? "remind_via_email" : ($type == "mobile_notification" ? "remind_via_mobile_notification" : "remind_via_desktop_notification"));
            $user_data->$field = $request->input("value");
            $user_data->save();
            return $this->sendResultJSON("1", "Remind via option updated successfully");
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
    
   public function addLogData(Request $request)
     {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }

            $user_data = session("user_details");
            $user_id = $request->input('user_id') ? $request->input('user_id') : $user_data->id;
            $user_timezone = $user_data->timezone;
            $user_name = $user_data->name;
            if ($request->input('user_id')) {
                $user_data_new = LogUserDetail::where("id", $request->input('user_id'))->first();
                if ($user_data_new) {
                    $user_timezone = $user_data_new->timezone;
                    $user_name = $user_data_new->name;
                }
            }

            $original_date = Carbon::parse($request->input('chat_date'));
            $chat_date = Carbon::parse($request->input('chat_date') . " " . $user_timezone)->tz(config('app.timezone'));

            $log_data = new LogDataDetail();
            $log_data->user_id = $user_id;
            $log_data->chatDate = $chat_date->format("Y-m-d");
            $log_data->msg = $request->input("msg");
            $log_data->chatTime = $chat_date->format("H:i:s");
            $log_data->save();

            $device_tokens = array();
            $member_chatDate = array();
            $all_members = LogUserDetail::where("id", "!=", $user_id)->where("type", intval(config('constants.device_type.ios')))->where("isVerified", 1)->whereRaw('deviceToken != ""')->get();

            foreach (count($all_members) > 0 ? $all_members : array() as $am) {
                $device_tokens[$am->id] = $am->deviceToken;
                $timezone = $am->timezone != "" ? $am->timezone : config('app.timezone');
                $member_chatDate[$am->id] = Carbon::parse($log_data->chatDate . " " . $log_data->chatTime . " " . config('app.timezone'))->tz($timezone)->format("Y-m-d");
            }

            if (count($device_tokens) > 0) {
                $pass_parameter["chat_id"] = $log_data->id;
                $pass_parameter["chat_date"] = $log_data->chatDate;
                $pass_parameter["custom_text"] = $user_name . "\n" . $log_data->msg;
                $pass_parameter["type"] = "chat";
                sendLogSlientNotificationIOS($device_tokens, $pass_parameter, $member_chatDate);
            }

            $concat = "DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . Carbon::parse(config('app.timezone'))->timezone->toOffsetName()  . "','" . Carbon::parse($user_timezone)->timezone->toOffsetName() . "'),'%Y-%m-%d')";

            $chat_data = LogDataDetail::selectRaw("*,DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . Carbon::parse(config('app.timezone'))->timezone->toOffsetName() . "','" . Carbon::parse($user_timezone)->timezone->toOffsetName() . "'),'%Y-%m-%d %H:%i:%s') as converted_date")->whereRaw($concat." = '". $chat_date->format("Y-m-d")."'")->orderBy("converted_date", "asc")->get();

            $chat_data_array = array();
            $current_timezone = $user_timezone != '' ? $user_timezone : config('app.timezone');
            foreach (count($chat_data) > 0 ? $chat_data : array() as $key => $c) {
                $converted_date = Carbon::parse($c->converted_date);
                if (!isset($chat_data_array[$converted_date->format("Y-m-d")])) {
                    $chat_data_array[$converted_date->format("Y-m-d")] = array("date" => $converted_date->format("Y-m-d"), "chat" => array());
                }
                array_push($chat_data_array[$converted_date->format("Y-m-d")]["chat"], array("id" => $c->id, "user_id" => $c->user_id, "user_name" => (isset($c->userData) ? $c->userData->name : ""), "msg" => $c->msg, "chat_time" => $converted_date->format("g:i A")));
            }
            return $this->sendResultJSON("1", "Chat added successfully", array("chat_data" => array_values($chat_data_array)));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }

    public function logDataList(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user_data = session("user_details");
            $chat_date = $request->input('first_chat_date') ? Carbon::parse($request->input('first_chat_date')) : Carbon::parse($request->input('chat_date'));
            $end_date = Carbon::parse($chat_date)->format("Y-m-d");
            $start_date = Carbon::parse($end_date)->subDays(7)->format("Y-m-d");

            $current_timezone = $user_data->timezone != '' ? $user_data->timezone : config('app.timezone');
            $converted_user_timezone = Carbon::parse($current_timezone)->timezone->toOffsetName();
            $converted_utc_timezone = Carbon::parse(config('app.timezone'))->timezone->toOffsetName();
            $chat_data_array = array();
            $total_chat_count = LogDataDetail::count();
            $first_start_data = LogDataDetail::selectRaw("DATE_FORMAT(CONVERT_TZ(chatDate,'" . $converted_utc_timezone . "','" . $converted_user_timezone . "'),'%Y-%m-%d') as chatDate")->orderBy("chatDate", "asc")->first();

            if ($first_start_data) {
                $first_start_data = $first_start_data->chatDate;
            }

            $total_msg = 0;
            if ($request->input('notification_first_date')) {
                $notification_first_date = Carbon::parse($request->input('notification_first_date'))->format("Y-m-d");
                $concat = "DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . $converted_utc_timezone . "','" . $converted_user_timezone . "'),'%Y-%m-%d')";
                $chat_data = LogDataDetail::selectRaw("*,DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . $converted_utc_timezone . "','" . $converted_user_timezone . "'),'%Y-%m-%d %H:%i:%s') as converted_date")->whereRaw($concat." >= '" . $notification_first_date . "'")->whereRaw($concat." <= '" . Carbon::now($current_timezone)->format("Y-m-d") . "'")->orderBy("chatDate", "asc")->orderBy("chatTime", "asc")->get();
                foreach (count($chat_data) > 0 ? $chat_data : array() as $key => $c) {
                    $converted_date = Carbon::parse($c->converted_date);
                    if (!isset($chat_data_array[$converted_date->format("Y-m-d")])) {
                        $chat_data_array[$converted_date->format("Y-m-d")] = array("date" => $converted_date->format("Y-m-d"), "chat" => array());
                    }
                    array_push($chat_data_array[$converted_date->format("Y-m-d")]["chat"], array("id" => $c->id, "user_id" => $c->user_id, "user_name" => (isset($c->userData) ? $c->userData->name : ""), "msg" => $c->msg, "chat_time" => $converted_date->format("g:i A")));
                    $total_msg += 1;
                }
                $end_date = $notification_first_date;
            } else {
                $concat = "DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . $converted_utc_timezone . "','" . $converted_user_timezone . "'),'%Y-%m-%d')";
                while ($total_msg >= 0 && $total_msg < 10) {
                    $chat_data = LogDataDetail::selectRaw("*,DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . $converted_utc_timezone . "','" . $converted_user_timezone . "'),'%Y-%m-%d %H:%i:%s') as converted_date")->whereRaw($concat." >= '" . $start_date . "'")->whereRaw($concat. " <= '" . Carbon::parse($end_date)->format("Y-m-d") . "'")->orderBy("chatDate", "asc")->orderBy("chatTime", "asc")->get();

                    foreach (count($chat_data) > 0 ? $chat_data : array() as $key => $c) {
                        $converted_date = Carbon::parse($c->converted_date);
                        //$converted_date = Carbon::parse($c->chatDate . " " . $c->chatTime . " " . config('app.timezone'))->tz($current_timezone);
                        if (!isset($chat_data_array[$converted_date->format("Y-m-d")])) {
                            $chat_data_array[$converted_date->format("Y-m-d")] = array("date" => $converted_date->format("Y-m-d"), "chat" => array());
                        }
                        array_push($chat_data_array[$converted_date->format("Y-m-d")]["chat"], array("id" => $c->id, "user_id" => $c->user_id, "user_name" => (isset($c->userData) ? $c->userData->name : ""), "msg" => $c->msg, "chat_time" => $converted_date->format("g:i A")));
                        $total_msg += 1;
                    }
                    if ($total_msg >= $total_chat_count || $start_date <= $first_start_data) {
                        $end_date = $start_date;
                        break;
                    } else {
                        $end_date = $start_date;
                        $start_date = Carbon::parse($end_date)->subDays(7)->format("Y-m-d");
                    }
                }
            }
            asort($chat_data_array);
            
            $all_users_data = LogUserDetail::select('id','name')->where("isVerified", 1)->get();
            $users_array = array();
            foreach (count($all_users_data) > 0 ? $all_users_data : array() as $u) {
                array_push($users_array, array("id" => $u->id, "name" => $u->name));
            }

            return $this->sendResultJSON("1", "", array("chat_data" => array_values($chat_data_array), "total_count" => $total_chat_count, "chat_count" => $total_msg, "start_date" => ($total_msg == 0 ? Carbon::parse($end_date)->format("Y-m-d") : Carbon::parse($end_date)->subDays(1)->format("Y-m-d")),"users_data" => $users_array));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
    
    public function addLogDataTemp(Request $request)
    {
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }
            $user_data = session("user_details");
            $user_id = $request->input('user_id') ? $request->input('user_id') : $user_data->id;
            $chat_date = Carbon::parse($request->input('chat_date'), config('app.timezone'));
            $log_data = new LogDataDetail();
            $log_data->user_id = $user_id;
            $log_data->chatDate = $chat_date->format("Y-m-d");
            $log_data->msg = $request->input("msg");
            $log_data->chatTime = $chat_date->format("H:i:s");
            $log_data->save();

            $device_tokens = array();
            $all_members = LogUserDetail::where("type", intval(config('constants.device_type.ios')))->where("isVerified", 1)->whereRaw('deviceToken != ""')->get();

            foreach (count($all_members) > 0 ? $all_members : array() as $am) {
                $device_tokens[$am->id] = $am->deviceToken;
            }

            if (count($device_tokens) > 0) {
                $pass_parameter["text"] =  $user_data->name."\n".$log_data->msg;
                $pass_parameter["type"] = "chat";
                sendLogSlientNotificationIOS($device_tokens, $pass_parameter);
            }

            $chat_data = LogDataDetail::where("chatDate", "=", $chat_date->format("Y-m-d"))->where("user_id", "=", $user_id)->orderBy("chatTime", "asc")->get();
            $chat_data_array = array();
            $current_timezone = $user_data->timezone != '' ? $user_data->timezone : config('app.timezone');
            foreach (count($chat_data) > 0 ? $chat_data : array() as $key => $c) {
                if (!isset($chat_data_array[$c->chatDate])) {
                    $chat_data_array[$c->chatDate] = array("date" => $c->chatDate, "chat" => array());
                }
                array_push($chat_data_array[$c->chatDate]["chat"], array("id" => $c->id, "user_id" => $c->user_id, "user_name" => (isset($c->userData) ? $c->userData->name : ""), "msg" => $c->msg, "chat_time" => Carbon::parse($c->chatTime)->tz($current_timezone)->format("g:i A")));
            }
            return $this->sendResultJSON("1", "Chat added successfully",array("chat_data" => array_values($chat_data_array)));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
    
    public function getLogDataByDate(Request $request){
        try {
            if (!session("user_details")) {
                return $this->sendResultJSON("11", "Unauthorised");
            }

            $user_data = session("user_details");
            $user_timezone = $user_data->timezone;
            //$chat_date = Carbon::parse($request->input('chat_date') . " " . $user_timezone)->tz(config('app.timezone'));
            $chat_date = Carbon::parse($request->input('chat_date'));
            $concat = "DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . Carbon::parse(config('app.timezone'))->timezone->toOffsetName()  . "','" . Carbon::parse($user_timezone)->timezone->toOffsetName() . "'),'%Y-%m-%d')";
            $chat_data = LogDataDetail::selectRaw("*,DATE_FORMAT(CONVERT_TZ(CONCAT(chatDate,' ',chatTime),'" . Carbon::parse(config('app.timezone'))->timezone->toOffsetName() . "','" . Carbon::parse($user_timezone)->timezone->toOffsetName() . "'),'%Y-%m-%d %H:%i:%s') as converted_date")->whereRaw($concat." = '" . $chat_date->format("Y-m-d") . "'")->orderBy("converted_date", "asc")->get();

            $chat_data_array = array();
            $current_timezone = $user_timezone != '' ? $user_timezone : config('app.timezone');

            foreach (count($chat_data) > 0 ? $chat_data : array() as $key => $c) {
                $converted_date = Carbon::parse($c->converted_date);
                if (!isset($chat_data_array[$converted_date->format("Y-m-d")])) {
                    $chat_data_array[$converted_date->format("Y-m-d")] = array("date" => $converted_date->format("Y-m-d"), "chat" => array());
                }
                array_push($chat_data_array[$converted_date->format("Y-m-d")]["chat"], array("id" => $c->id, "user_id" => $c->user_id, "user_name" => (isset($c->userData) ? $c->userData->name : ""), "msg" => $c->msg, "chat_time" => $converted_date->format("g:i A")));
            }
            return $this->sendResultJSON("1", "", array("chat_data" => array_values($chat_data_array)));
        } catch (\Exception $e) {
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
}
