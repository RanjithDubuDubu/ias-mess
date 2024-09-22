<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Base\Services\OTP\Handler\OTPHandlerContract;
use DB;
use Twilio;
use App\Models\User;
use App\Base\Libraries\SMS\SMSContract;
use App\Base\Services\ImageUploader\ImageUploaderContract;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Helpers\Exception\ExceptionHelpers;
use App\Base\Constants\Auth\Role;
use App\Jobs\Notifications\Auth\Registration\UserNotification;
use Mail;
use Log;
use App\Models\MobileOtp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Session;
use App\Models\MembershipTariff;

class HomeController extends LoginController
{
    use ExceptionHelpers;
    /**
     * The OTP handler instance.
     *
     * @var \App\Base\Services\OTP\Handler\OTPHandlerContract
     */
    protected $otpHandler;

    /**
     * The user model instance.
     *
     * @var \App\Models\User
     */
    protected $user;

    protected $smsContract;

    protected $imageUploader;

     
    public function __construct(User $user, OTPHandlerContract $otpHandler, SMSContract $smsContract,ImageUploaderContract $imageUploader)
    {
        $this->user = $user;
        $this->otpHandler = $otpHandler; 
        $this->smsContract = $smsContract;
        $this->imageUploader = $imageUploader;

    }
    public function index()
    { 
        // $sender_id = 'KTSHSC';
        // $template_id = '1707168862643740857';
        // $phone = 9566754418;
        // $msg = "Payment Done Successfully. Your UserId is test and Password is testttt";
        // $username = 'IndiaklabssOTP';
        // $apikey = '4DE5A-8C990';
        // $uri = 'https://powerstext.in/sms-panel/api/http/index.php';
        // // dd($phone);
        // // Construct the URL with query parameters
        // $url = $uri . '?' . http_build_query(array(
        //     'username' => $username,
        //     'apikey' => $apikey,
        //     'apirequest' => 'Text',
        //     'sender' => $sender_id,
        //     'route' => 'OTP',
        //     'format' => 'JSON',
        //     'message' => $msg,
        //     'mobile' => $phone,
        //     'TemplateID' => $template_id,
        // ));
           
        // $ch = curl_init();
            
        // // Set the URL
        // curl_setopt($ch, CURLOPT_URL, $url);
        
        // // Set the HTTP method to GET (since we're sending data in the URL)
        // curl_setopt($ch, CURLOPT_HTTPGET, true);
        
        // // Set CURLOPT_RETURNTRANSFER so that curl_exec returns the response
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // $response = curl_exec($ch);
        
        // // Check for errors
        // if(curl_errno($ch)) {
        //     // echo 'Curl error: ' . curl_error($ch);
        //     $response = [
        //         'status' => true,
        //         'message' => curl_error($ch)
        //     ];
        //     return response()->json($response);
        // } else {
        //     // Process the response
        //     echo $response;
        // }
        
        // // Close the cURL handle
        // curl_close($ch);
        // exit;
        return view('index'); 
    }
    public function register()
    {
        $membership_tariff = MembershipTariff::get(); 
        return view('admin.register',compact('membership_tariff')); 
    }
    public function forget_user()
    {
        return view('admin.forget-userid'); 
    }
    public function forget_password()
    {
        return view('admin.forget-password'); 
    }
    public function register_confirmation()
    {
        return view('admin.register-confirmation'); 

    }
    public function reset_password($token)
    {
        $check_data_expires = new \stdClass();
        $check_data_expires = User::where('email_confirmation_token',$token) ->first();  
       
        if(!$check_data_expires)
        {
            redirect('/login');
        }
        else{
            if(!Session::has('resend_user_id'))
            { 
                Session::put('resend_user_id',$check_data_expires->id);
            }  
            User::where('id',$check_data_expires->id)->update(['email_confirmation_token'=>NULL]);
        }
        return view('admin.verify-otp',compact('check_data_expires')); 
    }

    public function test()
    {
        return view('admin.test');
    }
    public function register_user(Request $request)
    { 
        $email = $request->email;
        $validate_exists_email = $this->user->belongsTorole(Role::USER)->where('email', $email)->exists();

        if ($validate_exists_email) {  
                $user = $this->user->belongsTorole(Role::USER)->where('email', $email)->first(); 
                return $this->authenticateAndRespond($user, $request, $needsToken=true); 
                $this->throwCustomException('Provided mobile has already been taken');
        }
        $profile_picture = null;
        $proof = null;

        if ($uploadedFile = $this->getValidatedUpload('imageUpload', $request)) {
            $profile_picture = $this->imageUploader->file($uploadedFile)
                ->saveProfilePicture();
        }
        if ($uploadedFile = $this->getValidatedUpload('proof', $request)) {
            $proof = $this->imageUploader->file($uploadedFile)
                ->saveProfilePicture();
        }
        $userid = User::orderBy('created_at', 'DESC')->pluck('userid')->first();

        if ($userid) {
            // Extract the numeric part from the userid
            preg_match('/(\d+)$/', $userid, $matches);
            $numberPart = isset($matches[1]) ? intval($matches[1]) + 1 : 1001; // Increment or default to 1001 if not found
            $userid = "TNIOM" . str_pad($numberPart, 4, '0', STR_PAD_LEFT); // Ensure the number part is at least 4 digits
        } else {
            $userid = "TNIOM1001"; // Default userid
        } 
        $password = bcrypt($request->input('phone'));
        $user_params = [ 
            'salutation' => $request->input('salutation'),
            'name' => $request->input('full_name'), 
            'batch' =>  $request->input('batch'),
            'email' =>  $request->input('email'),
            'mobile' =>  $request->input('phone'),
            'address' =>  $request->input('address'),
            'date_joining'=>$request->input('date_of_join'),
            'dob'=>$request->input('dob'),
            'retired_date'=>$request->input('date_of_retire'), 
            'membership_type'=> $request->input('membership_type'),
            // 'payment_mode'=> $request->input('mode_of_payment'),
            'profile_picture'=> $profile_picture,
            'password'=> $password,
            'proof'=> $proof,
            'userid'=>$userid
        ]; 
        $user = $this->user->create($user_params); 
        $user->attachRole(Role::USER);
        return redirect('/register-confirmation'); 
        
    }
    public function send_email(Request $request)
    {
        // dd($request->all());
        $email = $request->email;
        $validate_exists_email = $this->user->belongsTorole(Role::USER)->where('email', $email)->first();

        if ($validate_exists_email) { 
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
            $details = [
                'title' => 'Mail from Laravel App',
                'body' => 'This is a test email sent from a Laravel application.'
            ];
    
            Mail::to('ranjith@dubudubu.in')->send(new UserNotification($details));
            // dd($validate_exists_email);
            // dispatch(new UserNotification($validate_exists_email));
            $response = [
                "status"=>true,
                "message"=>"Email exists"
            ];
        }
        else{
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
        }
        return response()->json($response);
       
    } 
    public function resend_forget_email(Request $request)
    {  
        $id = $request->id;
        $validate_exists_email = $this->user->where('id', $id)->first(); 
        // dd($validate_exists_email);
        if ($validate_exists_email) { 
            $email = $validate_exists_email->email;
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
            $details = [
                'title' => 'Mail from Laravel App',
                'body' => 'This is a test email sent from a Laravel application.'
            ]; 

            $mobile = $validate_exists_email->mobile;
            
            $mobile_otp = MobileOtp::where('mobile', $mobile)->first();
    
            if (!$mobile_otp) {
                $otp = mt_rand(100000, 999999);
                if ($mobile == 9639639639 || $mobile == 9876543210) {
                    $otp = 123456; // Consider removing fixed OTPs in production.
                }
                Log::info($otp);
    
                $mobile_otp_table = new MobileOtp();
                $mobile_otp_table->mobile = $mobile; 
            
            } else {
                $otp = mt_rand(100000, 999999);
                if ($mobile == 9639639639 || $mobile == 9876543210) {
                    $otp = 123456; // Consider removing fixed OTPs in production.
                }
                Log::info($otp);
    
                $mobile_otp_table = MobileOtp::find($mobile_otp->id);
            }
            $mobile_otp_table->otp = $otp; 
            $mobile_otp_table->verified = false; 
            $mobile_otp_table->save();
            
            // Send SMS
            if ($mobile_otp_table) {
                // dd($mobile);
                $apiKey = "Pte4eXLw90iLEGNTQqoPLQ";
                $msisdn = '91' . $mobile; // Replace with the recipient's phone number
                $sid = "DUBUAT";
                $msg = "Dear User, Your IAS Mess account OTP is $otp.";
                $fl = '0';
                $gwid = '2'; 
                    // $sender_id = 'your sender';
                    // $template_id = 'DLT template id';
                    // $phone = 'phone 1, phone 2';
                    // $msg = "sms content here";
                    // $username = 'sms panel username';
                    // $apikey = 'sms panel api key';
                    // $uri = 'http://domain/sms-panel/api/http/index.php';
                    // $data = array(
                    // 'username'=> $username,
                    // 'apikey'=> $apikey,
                    // 'apirequest'=>'Text/Unicode',
                    // 'sender'=> $sender_id,
                    // 'route'=>'route name',
                    // 'format'=>'JSON',
                    // 'message'=> $msg,
                    // 'mobile'=> $phone,
                    // 'TemplateID' => $template_id,
                    // );

                    // $ch = curl_init($uri);
                    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    // curl_setopt($ch, CURLOPT_POST, 1);
                    // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    // curl_setopt($ch, CURLOPT_FAILONERROR, true);
                    // curl_setopt($ch, CURLOPT_TIMEOUT, 0);
                    // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                    // curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                    // $resp = curl_exec($ch);
                    // $error = curl_error($ch);
                    // curl_close ($ch);
                    // echo json_encode(compact('resp', 'error'));
    
                // $response = Http::get('http://cloud.smsindiahub.in/vendorsms/pushsms.aspx', [
                //     'APIKey' => $apiKey,
                //     'msisdn' => $msisdn,
                //     'sid' => $sid,
                //     'msg' => $msg,
                //     'DLTTemplateId'=>"1007141376294299625",
                //     'fl' => $fl,
                //     'gwid' => $gwid,
                // ]);
                
                // Log the response for debugging purposes
                // \Log::info('SMS API Response', ['response' => $response->body(), 'status' => $response->status(), 'headers' => $response->headers()]);
                // // dd("test");
                // // Dump the response to see the details
                // dd($response->json());
                // Handle response and potential errors from SMS API here.
            }
            $token = Str::random(40);
            $this->user->where('email', $email)->update(['email_confirmation_token'=>$token]);
            // dd($token);
            // Mail::to('ranjith@dubudubu.in')->send(new UserNotification($details));
            // dd($validate_exists_email);
            // dispatch(new UserNotification($validate_exists_email));
            $response = [
                "status"=>true,
                "message"=>"Email exists",
                "token"=>$token
            ];
        }
        else{
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
        }
        return response()->json($response);
    }
    public function send_forget_email(Request $request)
    { 
        $email = $request->email;
        $validate_exists_email = $this->user->belongsTorole(Role::USER)->where('email', $email)->Orwhere('mobile', $email)->first();
        
        // dd($validate_exists_email);
        if ($validate_exists_email) { 
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
            $details = [
                'title' => 'Mail from Laravel App',
                'body' => 'This is a test email sent from a Laravel application.'
            ];
    
           

            $mobile = $validate_exists_email->mobile;
            
            $mobile_otp = MobileOtp::where('mobile', $mobile)->first();
    
            if (!$mobile_otp) {
                $otp = mt_rand(100000, 999999);
                if ($mobile == 9639639639 || $mobile == 9876543210) {
                    $otp = 123456; // Consider removing fixed OTPs in production.
                }
                Log::info($otp);
    
                $mobile_otp_table = new MobileOtp();
                $mobile_otp_table->mobile = $mobile; 
            
            } else {
                $otp = mt_rand(100000, 999999);
                if ($mobile == 9639639639 || $mobile == 9876543210) {
                    $otp = 123456; // Consider removing fixed OTPs in production.
                }
                Log::info($otp);
    
                $mobile_otp_table = MobileOtp::find($mobile_otp->id);
            }
            $mobile_otp_table->otp = $otp; 
            $mobile_otp_table->verified = false; 
            $mobile_otp_table->save();
            
            // Send SMS
            if ($mobile_otp_table) {
                // dd($mobile);
                $apiKey = "Pte4eXLw90iLEGNTQqoPLQ";
                $msisdn = '91' . $mobile; // Replace with the recipient's phone number
                $sid = "DUBUAT";
                $msg = "Dear User, Your IAS Mess account OTP is $otp.";
                $fl = '0';
                $gwid = '2';
    
                // $response = Http::get('http://cloud.smsindiahub.in/vendorsms/pushsms.aspx', [
                //     'APIKey' => $apiKey,
                //     'msisdn' => $msisdn,
                //     'sid' => $sid,
                //     'msg' => $msg,
                //     'DLTTemplateId'=>"1007141376294299625",
                //     'fl' => $fl,
                //     'gwid' => $gwid,
                // ]);
                
                // Log the response for debugging purposes
                // \Log::info('SMS API Response', ['response' => $response->body(), 'status' => $response->status(), 'headers' => $response->headers()]);
                // // dd("test");
                // // Dump the response to see the details
                // dd($response->json());
                // Handle response and potential errors from SMS API here.
            }
            $token = Str::random(40);
            $this->user->where('email', $email)->update(['email_confirmation_token'=>$token]);
            // dd($token);
            // Mail::to('ranjith@dubudubu.in')->send(new UserNotification($details));
            // dd($validate_exists_email);
            // dispatch(new UserNotification($validate_exists_email));
            $response = [
                "status"=>true,
                "message"=>"Email exists",
                "token"=>$token
            ];
        }
        else{
            $response = [
                "status"=>false,
                "message"=>"Email does not exists"
            ];
        }
        return response()->json($response);
    }
     /**
     * Validate the mobile number verification OTP during registration.
     * @bodyParam otp string required Provided otp
     * @bodyParam uuid uuid required uuid comes from sen otp api response
     *
     * @param \App\Http\Requests\Auth\Registration\ValidateRegistrationOTPRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @response {"success":true,"message":"success"}
     */
    public function validateMobileOTP(Request $request)
    {
        // dd($request->all());
        $inputValuesArray = array_filter(explode(',', $request->inputValues), fn($value) => $value !== '');
        // dd($inputValuesArray);
        $otp = "";
        foreach($inputValuesArray as $k=>$v)
        {
            $otp.=$v;
        }
        // dd($otp); 
        $user = User::find($request->id);
        $mobile = $user->mobile;
        $email = $user->email;

        $verify_otp = MobileOtp::where('mobile' ,$mobile)->where('otp', $otp)->first();
        // dd($verify_otp);
            // Log::info($otp);
            // Log::info($mobile);
            Log::info($verify_otp);

        if (!$verify_otp) 
        {
            Log::info($otp);
            Log::info($mobile);

            $response = [
                "status"=>false,
                "message"=>"OTP is Invalid"
            ];
            return response()->json($response);

        }

        MobileOtp::where('mobile' ,$mobile)->where('otp', $otp)->update(['verified' => true]);
        
            $token = Str::random(40);
            $this->user->where('email', $email)->update(['reset_token'=>$token]);
            $response = [
                "status"=>true,
                "message"=>"OTP is Invalid",
                "token"=>$token,
            ];
        return response()->json($response);
    }

    public function check_user_exists(Request $request)
    {
        // dd($request->all()); 
        $email = $request->email;
        $mobile = $request->mobile;
        $validate_exists_mobile = $this->user->belongsTorole(Role::USER)->where('mobile', $mobile)->orwhere('email', $email)->exists(); 
        if($validate_exists_mobile) { 
            $message = [
            "status"=>false,
            "message"=>"Email address or Mobile No. already Exists"
            ];
        } 
        else{
            $message = [
                "status"=>true
              ];
        }
        return response()->json($message);
    }
    public function get_user_details(Request $request)
    {
       $get_user_data = User::where('id',$request->data)->first();
       $message = [
        "status"=>true,
        "user"=>$get_user_data
        ];
        return response()->json($message);

    }
} 