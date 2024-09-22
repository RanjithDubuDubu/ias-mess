<?php

namespace App\Http\Controllers\Web\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\SportsBooking;
use App\Models\SportsTariff;
use App\Models\SportsTariffBooking; 
use Illuminate\Http\Request;
use App\Models\Admin\VehicleType;
use App\Models\Admin\ServiceLocation;
use App\Http\Controllers\ApiController;
use App\Base\Constants\Auth\Role as RoleSlug;
use App\Base\Filters\Master\CommonMasterFilter;
use App\Http\Controllers\Api\V1\BaseController;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Base\Services\ImageUploader\ImageUploaderContract;
use App\Http\Requests\Admin\VehicleTypes\CreateVehicleTypeRequest;
use App\Http\Requests\Admin\VehicleTypes\UpdateVehicleTypeRequest;
use App\Base\Constants\Auth\Role;

/**
 * @resource Vechicle-Types
 *
 * vechicle types Apis
 */
class SportsController extends BaseController
{
    /**
     * The VehicleType model instance.
     *
     * @var \App\Models\Admin\VehicleType
     */
    protected $vehicle_type;

    /**
     * VehicleTypeController constructor.
     *
     * @param \App\Models\Admin\VehicleType $vehicle_type
     */
    public function __construct(VehicleType $vehicle_type, ImageUploaderContract $imageUploader)
    {
        $this->vehicle_type = $vehicle_type;
        $this->imageUploader = $imageUploader;
    }

    /**
    * Get all vehicle types
    * @return \Illuminate\Http\JsonResponse
    */
    public function index()
    {
        // dd(request()->url());
        $page = trans('pages_names.types');
        $main_menu = 'master';
        $sub_menu = 'sports';
        $sub_menu_1 = '';
        $sports_tariff = SportsTariff::get();

        return view('admin.sports.index', compact('page', 'main_menu', 'sub_menu','sub_menu_1','sports_tariff'))->render();
    }
    public function view(Request $request,QueryFilterContract $queryFilter,SportsBooking $booking)
    { 
         
        $page = trans('pages_names.types');
        $main_menu = 'master';
        $sub_menu = 'sports';
        $sub_menu_1 = ''; 
        $checkinDate = Carbon::parse($booking->checkin_date); 
        // Get the current date
        $currentDate = Carbon::now('Asia/kolkata'); 
        // Calculate the difference in days between the current date and the check-in date
        $daysDifference = $currentDate->diffInDays($checkinDate);
       $date_diff = $checkinDate->day - $currentDate->day;
        // dd($booking->details[0]->tariff); 
        return view('admin.sports.view', compact('page', 'main_menu', 'sub_menu','sub_menu_1','booking','date_diff'))->render();
    }

    public function book_now(Request $request)
    {
        //    dd($request->all());
           $checkinDatetime = Carbon::createFromFormat('Y-m-d', $request->from_date, 'Asia/Kolkata')->startOfDay()->format('Y-m-d H:i:s');
           $checkoutDatetime = Carbon::createFromFormat('Y-m-d', $request->to_date, 'Asia/Kolkata')->endOfDay()->format('Y-m-d H:i:s');
           $bookings =  new SportsBooking(); 
           $bookings->booking_id = time();
           $bookings->checkin_date = $checkinDatetime;
           $bookings->checkout_date = $checkoutDatetime; 
           $bookings->guest_type = $request->guest_type;     
           $bookings->subscription_type = $request->subscription_type;     
           $bookings->tariff = $request->total_amount;
           $bookings->user_id = auth()->user()->id; 
           $bookings->save();
            // dd($request->name);            
           foreach($request->name as $key=>$value)
           {
            $sports_tariff_booking =  new SportsTariffBooking(); 
            $sports_tariff_booking->booking_id = $bookings->id;
            $sports_tariff_booking->tariff_id = $value; 
            $sports_tariff_booking->save();
           }
           $response = [
               "status" => true,
               "message" => "Booking Confirmed Successfully"
           ];
           return response()->json($response);
    }
    

    public function getAllTypes(QueryFilterContract $queryFilter)
    {
        $query = SportsBooking::where('user_id',auth()->user()->id)->orderBy('created_at','desc');
        $results = $queryFilter->builder($query)->customFilter(new CommonMasterFilter)->paginate();
        return view('admin.sports._types', compact('results'))->render();
    }

    /**
    * Get Types by admin for ajax
    *
    */
    public function byAdmin(Request $request)
    {
        $types = VehicleType::where('admin_id', $request->admin_id)->get();

        return $this->respondSuccess($types);
    }

    /**
    * Create Vehicle type
    *
    */
    public function create()
    {
        $page = trans('pages_names.add_type');
        // $services = ServiceLocation::whereActive(true)->get();
        $main_menu = 'master';
        $sub_menu = 'sports';
        $sub_menu_1 = '';
        return view('admin.sports.create', compact('page', 'main_menu', 'sub_menu','sub_menu_1'));
    }


    /**
     * Store Vehicle type.
     *
     * @param \App\Http\Requests\Admin\VehicleTypes\CreateVehicleTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @response
     * {
     *"success": true,
     *"message": "success"
     *}
     */
    public function store(CreateVehicleTypeRequest $request)
    {
    if (auth()->user()->hasRole(!(Role::ADMIN))) {

         if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

            return redirect('sports')->with('warning', $message);
        }
    }
        // dd($request->transport_type);
        $created_params = $request->only(['name', 'capacity','is_accept_share_ride','description','supported_vehicles','short_description', 'transport_type','icon_types_for']);

        if ($uploadedFile = $this->getValidatedUpload('icon', $request)) {
            $created_params['icon'] = $this->imageUploader->file($uploadedFile)
                ->saveVehicleTypeImage();
        }
        $created_params['active'] = true;
        $created_params['created_by'] = auth()->user()->id;


        $created_params['is_taxi'] = $request->transport_type;


        $this->vehicle_type->create($created_params);

        $message = trans('succes_messages.type_added_succesfully');

        return redirect('sports')->with('success', $message);
    }

    /**
    * Edit Vehicle type view
    *
    */
    public function edit($id)
    {   
        $page = trans('pages_names.edit_type');
        $type = $this->vehicle_type->where('id', $id)->first();
        // dd($type->is_taxi);
        // $admins = User::doesNotBelongToRole(RoleSlug::SUPER_ADMIN)->get();
        // $services = ServiceLocation::whereActive(true)->get();
        $main_menu = 'master';
        $sub_menu = 'sports';
        $sub_menu_1 = '';
        return view('admin.sports.update', compact('page', 'type', 'main_menu', 'sub_menu','sub_menu_1'));
    }


    /**
     * Update Vehicle type.
     *
     * @param \App\Http\Requests\Admin\VehicleTypes\CreateVehicleTypeRequest $request
     * @param App\Models\Admin\VehicleType $vehicle_type
     * @return \Illuminate\Http\JsonResponse
     * @response
     * {
     *"success": true,
     *"message": "success"
     *}
     */
    public function update(UpdateVehicleTypeRequest $request, VehicleType $vehicle_type)
    {
    if (auth()->user()->hasRole(!(Role::ADMIN))) {

        if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

            return redirect('sports')->with('warning', $message);
        }
    }
        // dd($request->all());
        $this->validateAdmin();
        $created_params = $request->only(['name', 'capacity','is_accept_share_ride','description','supported_vehicles','short_description','transport_type','icon_types_for']);

        if ($uploadedFile = $this->getValidatedUpload('icon', $request)) {
            $created_params['icon'] = $this->imageUploader->file($uploadedFile)
                ->saveVehicleTypeImage();
        }

        $created_params['is_taxi'] = $request->transport_type;
       
        $created_params['updated_by'] = auth()->user()->id;

        $vehicle_type->update($created_params);

        $message = trans('succes_messages.type_updated_succesfully');
        // clear the cache
        cache()->tags('vehilce_types')->flush();
        return redirect('sports')->with('success', $message);
    }
    public function toggleStatus(VehicleType $vehicle_type)
    {
        if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

            return redirect('sports')->with('warning', $message);
        }
        
        $status = $vehicle_type->active == 1 ? 0 : 1;
        $vehicle_type->update([
            'active' => $status
        ]);

        $message = trans('succes_messages.type_status_changed_succesfully');
        return redirect('sports')->with('success', $message);
    }
    /**
     * Delete Vehicle type.
     *
     * @param App\Models\Admin\VehicleType $vehicle_type
     * @return \Illuminate\Http\JsonResponse
     * @response
     * {
     *"success": true,
     *"message": "success"
     *}
     */

    public function delete(VehicleType $vehicle_type)
    {
        if (env('APP_FOR')=='demo') {
            $message = trans('succes_messages.you_are_not_authorised');

            return redirect('sports')->with('warning', $message);
        }

        $vehicle_type->delete();

        $message = trans('succes_messages.vehicle_type_deleted_succesfully');
        return $message;
    }
}
