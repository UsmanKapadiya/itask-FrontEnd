<?php

namespace App\Http\Controllers\Api;

use App\Models\InvitationDetail;
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

class UserController extends Controller
{
    /**
     *  Method to register user
     *
     */
    public function registration(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email|unique:user_details,email,NULL,id,deleted_at,NULL",
            ], [
                "email.required" => "Please enter email",
                "email.unique" => "User with this email id is exist",
            ]);

            if ($validator->fails()) {
                return $this->sendResultJSON("2", $validator->errors()->first());
            }

            $user_detail = new UserDetail();
            $user_detail->name = ucfirst($request->input("name"));
            $user_detail->email = $request->input("email");
            $user_detail->password = md5($request->input("password"));
            $user_detail->isVerified = 0;
            $user_detail->verificationCode = rand(1111, 9999);
            $verificationcode = $user_detail->verificationCode;
            $user_detail->type = $request->input("type");
            $user_detail->deviceType = $request->input("deviceType");
            $user_detail->deviceId = $request->input("deviceId");
            $user_detail->allowNotification = 0;
            $user_detail->save();

            $file = NULL;
            if (count($_FILES) > 0) {
                $file = $_FILES["file"];
            }
            if ($file != NULL) {
                $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
                $user_detail->avatar = $user_detail->id . "." . $ext;
                $destination_path = public_path("uploads/avatar") . "/" . $user_detail->avatar;
                move_uploaded_file($file["tmp_name"], $destination_path);
                $user_detail->save();
            }
            Notification::send($user_detail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with iTask', 'otp' => "Verification code : " . $verificationcode)));
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
            $user_detail = UserDetail::where("verificationCode", $verification_code)->where("email", $request->input("email"))->where("isVerified", 0)->first();
            if (empty($user_detail)) {
                return $this->sendResultJSON("2", "Invalid verification code");
            }
            $user_detail->isVerified = 1;
            $user_detail->verificationCode = $verification_code;
            $user_detail->deviceToken = $request->input("device_token");
            if ($user_detail->timezone == NULL && $request->input("timezone")) {
                $user_detail->timezone = $request->input("timezone");
            }
            $user_detail->save();
            $user_token = generate_access_token($user_detail->id);
            $this->afterVerification($user_detail);
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
            $user_detail = UserDetail::where("email", $request->input("email"))->first();
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

                Notification::send($user_detail, new VerificationCode(array('text' => 'Hi,', 'subtext' => 'Please use the verification code below to verify your account : ', 'subject' => 'Sign up with iTask', 'otp' => "Verification code : " . $verification_code)));
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
            $user = UserDetail::where("email", $email)->where("password", $password)->first();

            if (!$user) {
                return $this->sendResultJSON("2", "User not Found");
            } else {
                if ($user->isVerified == 1) {
                    $user_token = generate_access_token($user->id);
                    $user->deviceToken = $request->input("device_token");
                    $user->type = $request->input("type");
                    $user->deviceType = $request->input("deviceType");
                    if ($user->timezone == NULL && $request->input("timezone")) {
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
                "email" => "required|email|unique:user_details,email,$user->id,id,deleted_at,NULL",
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
                $destination_path = public_path("uploads/avatar") . "/" . $user->avatar;
                move_uploaded_file($file["tmp_name"], $destination_path);
                $user->save();
            }
            if ($request->input("isAvatarDeleted")) {
                if ($user->avatar != NULL) {
                    unlink(public_path("uploads/avatar/" . $user->avatar));
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
}
