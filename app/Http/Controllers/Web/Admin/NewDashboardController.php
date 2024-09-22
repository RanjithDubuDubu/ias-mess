<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Web\BaseController;
use App\Models\Admin\Driver;
use App\Models\Request\Request;
use App\Models\Request\RequestBill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use App\Base\Constants\Setting\Settings;
use App\Models\Admin\DriverAvailability;
use App\Models\Admin\Complaint;
use DB;
use App\Models\Request\EmployeeStatus;
use App\Models\Admin\RegisteredDriver;
use App\Base\Constants\Auth\Role;
use Kreait\Firebase\Contract\Database;
use App\Models\Request\RentalRequest;
use  App\Models\Admin\DriverInvoice;
use App\Models\Payment\DriverSubscription;

class NewDashboardController extends BaseController
{

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }
    public function dashboard()
    {

        if(!Session::get('applocale')){
            Session::put('applocale', 'en');
        }

 // Session::put('applocale', 'en');

        $ownerId = null;
        // if (auth()->user()->hasRole('owner')) {
        //     $ownerId = auth()->user()->owner->id;
        // }
        // dd("SDfdfsfdf");

        $page = trans('pages_names.dashboard');
        $main_menu = 'dashboard';
        $sub_menu = null;
        $sub_menu_1 = '';

        $today = date('Y-m-d'); //get Today
        $month = date('m'); // get the current month
        $year = date('Y'); // get the current year
        $lastMonth = Carbon::now()->subMonth(); // get the Last month


        $complaint = Complaint::all();

    // Completed and on-trip requests
    $requestStatus = Request::selectRaw('
            SUM(CASE WHEN is_completed = true THEN 1 ELSE 0 END) AS completedTrips,
            SUM(CASE WHEN is_completed = false AND is_cancelled = false THEN 1 ELSE 0 END) AS onTrips
        ')->first();

        // Driver online/offline
        $drivers = Driver::where('approve', true)->get();
        $drivers_online = (env('APP_FOR') == 'live') ? count($this->database->getReference('drivers')->orderByChild('is_active')->equalTo(1)->getValue()) : 0;
        $drivers_offline = $drivers->count() - $drivers_online;    


//registered Drivers && Drivers Stats

        $regitered_drivers =  RegisteredDriver::get()->count();                               

        $driver_statistics =  $this->getDriverStatistics();

//Driver stats End

// //User status starts Here

    $total_users = $this->getUserStatistics();

        $current_month_users = User::whereMonth('created_at', $month)
                  ->whereYear('created_at', $year)
                  ->count();

        $last_month_users = User::whereMonth('created_at', $lastMonth->month)
                  ->whereYear('created_at', $lastMonth->year)
                  ->count();
       
        $today_users = User::whereDate('created_at', $today)->count();

        $users_count = User::whereActive(true)->count();

//User status Ends Here



//today trips status starts Here

$today_trip_time = Carbon::now('UTC')->toDateString();        

            $trips = Request::companyKey()->selectRaw('
                IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END),0) AS today_completed,
                IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END),0) AS today_cancelled,
                IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END),0) AS today_scheduled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) AS auto_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) AS user_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0) AS driver_cancelled,
                (IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0)) AS total_cancelled
            ')
            ->whereDate('trip_start_time',$today_trip_time)
            ->get();

// Today Earnings

        $upiEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=4,request_bills.total_amount,0)),0)";        
        $cardEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=0,request_bills.total_amount,0)),0)";
        $cashEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=1,request_bills.total_amount,0)),0)";
        $walletEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=2,request_bills.total_amount,0)),0)";
        $adminCommissionQuery = "IFNULL(SUM(request_bills.admin_commision_with_tax),0)";
        $driverCommissionQuery = "IFNULL(SUM(request_bills.driver_commision),0)";
        $totalEarningsQuery = "$cardEarningsQuery + $cashEarningsQuery + $walletEarningsQuery + $upiEarningsQuery";


        $earningsQuery = "
            IFNULL(SUM(IF(requests.payment_opt=4,request_bills.total_amount,0)),0) AS upi,
            IFNULL(SUM(IF(requests.payment_opt=0,request_bills.total_amount,0)),0) AS card,
            IFNULL(SUM(IF(requests.payment_opt=1,request_bills.total_amount,0)),0) AS cash,
            IFNULL(SUM(IF(requests.payment_opt=2,request_bills.total_amount,0)),0) AS wallet,
            IFNULL(SUM(IF(requests.payment_opt=0,request_bills.total_amount,0)) +
                   SUM(IF(requests.payment_opt=1,request_bills.total_amount,0)) +
                   SUM(IF(requests.payment_opt=2,request_bills.total_amount,0)) +
                   SUM(IF(requests.payment_opt=4,request_bills.total_amount,0)),0) AS total,
            IFNULL(SUM(request_bills.promo_discount),0) AS promo_discount,
            IFNULL(SUM(request_bills.total_amount),0) AS total_amount
        ";

       $todayEarnings = $this->getEarnings($earningsQuery, 'requests.is_completed', true, 'requests.trip_start_time', $today);


//today earnings Completed

//Overall Earnings  and over all trips Completed 
            $overallEarnings = $this->getEarnings($earningsQuery, 'requests.is_completed', true);

            $over_all_trips = $this->getTripStatus();

        //Overall Earnings  and over all trips Completed End
        //cancelled_trips

            // Cancellation chart
            $cancelled_trips = $this->getCancelledTrips();

     $startDate = Carbon::now()->startOfMonth()->subMonths(6);
     $endDate = Carbon::now();
     $data=[];
    while ($startDate->lte($endDate))
        {

            $from = Carbon::parse($startDate)->startOfMonth();
            $to = Carbon::parse($startDate)->endOfMonth();
            $shortName = $startDate->shortEnglishMonth;
            $monthName = $startDate->monthName;
            $data['cancel'][] = [
                'y' => $shortName,
                'a' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','0')->whereIsCancelled(true)->count(),
                'u' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','1')->whereIsCancelled(true)->count(),
                'd' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','2')->whereIsCancelled(true)->count()
                    ];
                    $data['earnings']['months'][] = $monthName;
                    $data['earnings']['values'][] = RequestBill::whereHas('requestDetail', function ($query) use ($from,$to) {
                                                                $query->companyKey()->whereBetween('trip_start_time', [$from,$to])->whereIsCompleted(true);
                                                            })->sum('total_amount');
              $startDate->addMonth();
          } 
//currency symbol
        if (auth()->user()->countryDetail) {
            $currency = auth()->user()->countryDetail->currency_symbol;
        } else {
            $currency = get_settings(Settings::CURRENCY_SYMBOL);
        }
        $currency = get_settings('currency_symbol');
//Cancellation Ends Here

        $rentalData = [];
        $startDate = Carbon::now()->startOfMonth()->subMonths(6);
        $endDate = Carbon::now();

        while ($startDate->lte($endDate)) {
            $from = Carbon::parse($startDate)->startOfMonth();
            $to = Carbon::parse($startDate)->endOfMonth();
            $monthYearLabel = $startDate->format('M Y');

            $rentalData[] = [
                'y' => $monthYearLabel,
                'booked' => RentalRequest::where('city', 'goa')->whereBetween('created_at', [$from, $to])->where('is_completed', false)->where('is_confirmed', false)->where('is_cancelled', false)->count(),
                'cancelled' => RentalRequest::where('city', 'goa')->whereBetween('created_at', [$from, $to])->where('is_cancelled', true)->count(),
                'completed' => RentalRequest::where('city', 'goa')->whereBetween('completed_at', [$from, $to])->where('is_completed', true)->count(),
            ];

            $startDate->addMonth();
        }

        $data['rental-goa'] = $rentalData;


        // dd($data['rental-goa']);

        $goa_data = RentalRequest::where('city', 'goa')->selectRaw("
            SUM(CASE WHEN is_completed = false AND is_cancelled = false AND is_confirmed = false THEN 1 ELSE 0 END) as booked,
            SUM(CASE WHEN is_completed = true  THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_cancelled = true THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN is_completed = false AND is_cancelled = false AND is_confirmed = true THEN 1 ELSE 0 END) as confirmed
        ")->first();


        //pondy

        $rentalData = [];
        $startDate = Carbon::now()->startOfMonth()->subMonths(6);
        $endDate = Carbon::now();

        while ($startDate->lte($endDate)) {
            $from = Carbon::parse($startDate)->startOfMonth();
            $to = Carbon::parse($startDate)->endOfMonth();
            $monthYearLabel = $startDate->format('M Y');

            $rentalData[] = [
                'y' => $monthYearLabel,
                'booked' => RentalRequest::where('city', 'pondicherry')->whereBetween('created_at', [$from, $to])->where('is_completed', false)->where('is_confirmed', false)->where('is_cancelled', false)->count(),
                'cancelled' => RentalRequest::where('city', 'pondicherry')->whereBetween('created_at', [$from, $to])->where('is_cancelled', true)->count(),
                'completed' => RentalRequest::where('city', 'pondicherry')->whereBetween('completed_at', [$from, $to])->where('is_completed', true)->count(),
            ];

            $startDate->addMonth();
        }

        $data['rental-pondy'] = $rentalData;

        $pondy_data = RentalRequest::where('city', 'pondicherry')->selectRaw("
            SUM(CASE WHEN is_completed = false AND is_cancelled = false AND is_confirmed = false THEN 1 ELSE 0 END) as booked,
            SUM(CASE WHEN is_completed = true  THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_cancelled = true THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN is_completed = false AND is_cancelled = false AND is_confirmed = true THEN 1 ELSE 0 END) as confirmed
        ")->first();
        // dd($pondy_data);


//rental charts end here        

        return view('admin.new_dashboard', compact('page', 'main_menu','currency', 'sub_menu','driver_statistics','total_users','trips','todayEarnings','overallEarnings','over_all_trips','data','current_month_users','last_month_users','today_users', 'complaint','cancelled_trips','sub_menu_1','goa_data','pondy_data','requestStatus','drivers_online','drivers_offline','users_count','regitered_drivers'));
   }
    private function getDriverStatistics()
    {
        $today = date('Y-m-d'); //get Today
        $month = date('m'); // get the current month
        $year = date('Y'); // get the current year
        $lastMonth = Carbon::now()->subMonth(); // get the Last month

        $driver = Driver::selectRaw('
                IFNULL(SUM(CASE WHEN approve = 1 THEN 1 ELSE 0 END), 0) AS approved,
                IFNULL((SUM(CASE WHEN approve = 1 THEN 1 ELSE 0 END) / count(*)), 0) * 100 AS approve_percentage,
                IFNULL((SUM(CASE WHEN approve = 0 THEN 1 ELSE 0 END) / count(*)), 0) * 100 AS decline_percentage,
                IFNULL(SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END), 0) AS deleted,
                IFNULL(SUM(CASE WHEN approve = 0 AND is_deleted = 0 AND reason IS NULL THEN 1 ELSE 0 END), 0) AS pending,
                IFNULL(SUM(CASE WHEN approve = 0 AND is_deleted = 0 AND reason IS NOT NULL THEN 1 ELSE 0 END), 0) AS declined,
                count(*) AS total,
                SUM(IF(MONTH(created_at) = :month AND YEAR(created_at) = :year, 1, 0)) AS current_month_drivers,
                SUM(IF(MONTH(created_at) = :last_month AND YEAR(created_at) = :last_year, 1, 0)) AS last_month_drivers,
                SUM(IF(DATE(created_at) = :today, 1, 0)) AS today_drivers
            ', [
                'month' => $month,
                'year' => $year,
                'last_month' => $lastMonth->month,
                'last_year' => $lastMonth->year,
                'today' => Carbon::now()->toDateString(),
            ])
            ->whereHas('user', function ($query) {
                $query->companyKey();
            })
            ->get();


        return $driver;            
    }


    private function getUserStatistics()
    {
        $today = date('Y-m-d'); //get Today
        $month = date('m'); // get the current month
        $year = date('Y'); // get the current year
        $lastMonth = Carbon::now()->subMonth(); // get the Last month

        $user = User::selectRaw('
                COUNT(*) AS total_users,
                SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) AS deleted_users,
                SUM(CASE WHEN is_deleted = 0 AND active = 1 THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN is_deleted = 0 AND active = 0 THEN 1 ELSE 0 END) AS inactive_users
            ')
            ->belongsToRole('user')
            ->companyKey()
            ->get();
        return $user;
    }

    private function getEarnings($earningsQuery, $whereColumn, $whereValue, $whereDateColumn = null, $whereDateValue = null)
    {
        $query = Request::leftJoin('request_bills', 'requests.id', 'request_bills.request_id')
            ->selectRaw($earningsQuery)
            ->companyKey();

        if ($whereDateColumn && $whereDateValue) {
            $query->where($whereDateColumn, $whereDateValue);
        }

        return $query->where($whereColumn, $whereValue)->get();
    }
    private function getCancelledTrips()
    {
        return Request::companyKey()->selectRaw('
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 0 THEN 1 END) AS auto_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 1 THEN 1 END) AS user_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 2 THEN 1 END) AS driver_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 AND cancel_method = 3 THEN 1 END) AS dispatcher_cancelled,
            COUNT(CASE WHEN is_cancelled = 1 THEN 1 END) AS total_cancelled
        ')->first();
    }
    private function getTripStatus()
    {
        return Request::companyKey()->selectRaw('
                IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END),0) AS completed_trips,
                IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END),0) AS cancelled_trips,
                IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END),0) AS scheduled_trips,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) AS auto_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) AS user_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0) AS driver_cancelled,
                (IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0)) AS total_cancelled
            ')
            ->get();
    }

    public function stats()
    {
        $page = trans('pages_names.driver_stats');
        $main_menu = 'settings';
        $sub_menu = 'driver_stats';
        $sub_menu_1 = '';

        $active_subscription_count = DriverSubscription::where('active', true)->count();

        $drivers_count = Driver::where('approve', true)->where('is_free_trial', false)->count();

        $in_active_subscription_count = abs($active_subscription_count - $drivers_count);


        $today = Carbon::now()->format('Y-m-d');
        $firstDayOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
        $lastDayOfLastMonth = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');


        $subscriptionCounts = DriverSubscription::selectRaw('
                SUM(CASE WHEN created_at >= ? THEN paid_amount ELSE 0 END) AS today_subscribed,
                SUM(CASE WHEN created_at >= ? THEN paid_amount ELSE 0 END) AS this_month_subscribed,
                SUM(CASE WHEN created_at >= ? AND created_at < ? THEN paid_amount ELSE 0 END) AS last_month_subscribed
            ', [$today, $firstDayOfMonth, $lastDayOfLastMonth, $firstDayOfMonth])
            ->first();

        $paidInvoicesCounts = DriverInvoice::selectRaw('
                SUM(CASE WHEN is_paid = true AND created_at >= ? THEN amount ELSE 0 END) AS today_paid_invoices,
                SUM(CASE WHEN is_paid = true AND created_at >= ? THEN amount ELSE 0 END) AS this_month_paid_invoices,
                SUM(CASE WHEN is_paid = true AND created_at >= ? AND created_at < ? THEN amount ELSE 0 END) AS last_month_paid_invoices,
                SUM(CASE WHEN is_paid = true THEN 1 ELSE 0 END) AS received_invoices_count,
                SUM(CASE WHEN is_paid = false THEN 1 ELSE 0 END) AS pending_invoices_count,
                COUNT(*) AS total_invoices_count
            ', [$today, $firstDayOfMonth, $lastDayOfLastMonth, $firstDayOfMonth])
            ->where('is_subscription_invoice', false)
            ->first();



            

        return view('admin.driver_stats', compact('page', 'main_menu', 'sub_menu', 'sub_menu_1', 'active_subscription_count', 'in_active_subscription_count','subscriptionCounts','paidInvoicesCounts'));
    }
}

