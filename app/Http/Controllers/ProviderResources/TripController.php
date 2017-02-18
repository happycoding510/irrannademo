<?php

namespace App\Http\Controllers\ProviderResources;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Auth;
use Setting;
use Carbon\Carbon;

use App\User;
use App\Helpers\Helper;
use App\RequestFilter;
use App\UserRequests;
use App\UserRequestRating;
use App\UserRequestPayment;

class TripController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try{
            $IncomingRequests = RequestFilter::IncomingRequest(Auth::user()->id)->get();

            $Timeout = Setting::get('provider_select_timeout', 180);
                if(!empty($IncomingRequests)){
                    for ($i=0; $i < sizeof($IncomingRequests); $i++) {
                        $IncomingRequests[$i]->time_left_to_respond = $Timeout - (time() - strtotime($IncomingRequests[$i]->request->assigned_at));
                        if($IncomingRequests[$i]->request->status == 'SEARCHING' && $IncomingRequests[$i]->time_left_to_respond < 0) {
                            $this->assign_next_provider($IncomingRequests[$i]->id);
                            return $this->index();
                        }
                    }
                }

            $Response = [
                    'account_status' => Auth::user()->status,
                    'service_status' => Auth::user()->service ? Auth::user()->service->status : 'offline',
                    'requests' => $IncomingRequests,
                ];

            return $Response;
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Something went wrong']);
        }
    }

    /**
     * Cancel given request.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $Cancellable = ['SEARCHING', 'ACCEPTED', 'ARRIVED', 'STARTED', 'CREATED'];

        if(!in_array($UserRequest->status, $Cancellable)) {
            return response()->json(['error' => 'Cannot cancel request at this stage!']);
        }

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function rate(Request $request, $id)
    {

        $this->validate($request, [
                'rating' => 'required|integer|in:1,2,3,4,5',
                'comment' => 'max:255',
            ]);
    
        try {

            $UserRequest = UserRequests::where('id', $id)
                ->where('status', 'COMPLETED')
                ->firstOrFail();

            $rating = new UserRequestRating();
            $rating->provider_id = $UserRequest->provider_id;
            $rating->user_id = $UserRequest->user_id;
            $rating->request_id = $UserRequest->id;
            $rating->provider_rating = $request->rating;
            $rating->provider_comment = $request->comment;
            $rating->save();

            // Delete from filter so that it doesn't show up in status checks.
            RequestFilter::where('request_id', $id)->delete();

            // Send Push Notification to Provider 
            $average = UserRequestRating::where('provider_id', $UserRequest->provider_id)->avg('provider_rating');

            try {
                User::findOrFail($UserRequest->user_id)->update(['rating' => $average]);
                return response()->json(['message' => 'Request Completed!']);
            } catch (ModelNotFoundException $e) {
                return response()->json(['error' => 'Something went wrong'], 500);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Request not yet completed!'], 500);
        }
    }

    /**
     * Get the trip history of the provider
     *
     * @return \Illuminate\Http\Response
     */
    public function history(Request $request)
    {
        if($request->ajax()) {
            $Jobs = UserRequests::where('provider_id', Auth::user()->id)->with('payment')->get();
            if(!empty($Jobs)){
                $map_icon = asset('asset/marker.png');
                foreach ($Jobs as $key => $value) {
                    $Jobs[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?autoscale=1&size=320x130&maptype=terrian&format=png&visual_refresh=true&markers=icon:".$map_icon."%7C".$value->s_latitude.",".$value->s_longitude."&markers=icon:".$map_icon."%7C".$value->d_latitude.",".$value->d_longitude."&path=color:0x000000|weight:3|".$value->s_latitude.",".$value->s_longitude."|".$value->d_latitude.",".$value->d_longitude."&key=".env('GOOGLE_API_KEY');
                }
            }
            return $Jobs;
        }
        $Jobs = UserRequests::where('provider_id', Auth::guard('provider')->user()->id)->with('user', 'service_type', 'payment', 'rating')->get();
        return view('provider.trip.index', compact('Jobs'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function accept($id)
    {
        try {

            $UserRequest = UserRequests::findOrFail($id);

            if($UserRequest->status != "SEARCHING") {
                return response()->json(['error' => 'Request already under progress!']);
            }

            $UserRequest->provider_id = Auth::user()->id;
            $UserRequest->status = "STARTED";
            // dd($UserRequest->toArray());
            $UserRequest->save();

            $Filters = RequestFilter::where('request_id', $UserRequest->id)->where('provider_id', '!=', Auth::user()->id)->get();
            // dd($Filters->toArray());
            foreach ($Filters as $Filter) {
                $Filter->delete();
            }

            // Send Push Notification to User

            return $UserRequest->with('user')->get();

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Unable to accept, Please try again later']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Connection Error']);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
              'status' => 'required|in:ACCEPTED,STARTED,ARRIVED,PICKEDUP,DROPPED,PAYMENT,COMPLETED',
           ]);

        try{

            $UserRequest = UserRequests::findOrFail($id);

            if($request->status == 'DROPPED' && $UserRequest->payment_mode != 'CASH') {
                $UserRequest->status = 'COMPLETED';
            } else if ($request->status == 'COMPLETED' && $UserRequest->payment_mode == 'CASH') {
                $UserRequest->status = $request->status;
                $UserRequest->paid = 1;
            } else {
                $UserRequest->status = $request->status;
            }
            $UserRequest->save();

            if($request->status == 'DROPPED') {
                $UserRequest->with('user')->findOrFail($id);
                $UserRequest->invoice = $this->invoice($id);
                return $UserRequest;
            }

            // Send Push Notification to User
       
            return $UserRequest->with('user')->get();

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Unable to update, Please try again later']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Connection Error']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $UserRequest = UserRequests::find($id);

        try {

            // Send Push Notification to User
            RequestFilter::where('request_id', $UserRequest->id)->where('provider_id', Auth::user()->id)->delete();
            return $UserRequest->with('user')->get();

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Unable to reject, Please try again later']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Connection Error']);
        }
    }

    public function assign_next_provider($request_id) {

        try {
            $UserRequest = UserRequests::findOrFail($request_id);
        } catch (ModelNotFoundException $e) {
            // Cancelled between update.
            return false;
        }

        RequestFilter::where('provider_id', Auth::user()->id)
            ->where('request_id', $UserRequest->id)
            ->delete();

        try {

            $next_provider = RequestFilter::where('request_id', $UserRequest->id)
                ->orderBy('id')
                ->firstOrFail();

            $UserRequest->current_provider_id = $next_provider->provider_id;
            $UserRequest->assigned_at = Carbon::now();
            $UserRequest->save();
            
        } catch (ModelNotFoundException $e) {
            UserRequests::where('id', $UserRequest->id)->update(['status' => 'CANCELLED']);

            // No longer need request specific rows from RequestMeta
            RequestFilter::where('request_id', $UserRequest->id)->delete();
        }
    }

    public function invoice($request_id)
    {
        try {
            $UserRequest = UserRequests::findOrFail($request_id);
            
            $Fixed = $UserRequest->service_type->fixed ? : 0;
            $Distance = ceil($UserRequest->distance) * $UserRequest->service_type->distance;
            $Discount = 0; // Promo Code discounts should be added here.
            $Wallet = 0;

            $Commision = ( $Fixed + $Distance - $Discount ) * (Setting::get('payment_commision', 10) / 100);

            $Tax = $Fixed + $Distance - $Discount + $Commision * (Setting::get('payment_tax', 10) / 100);
            $Total = $Fixed + $Distance - $Discount + $Commision + $Tax;



            $Payment = new UserRequestPayment;
            $Payment->request_id = $UserRequest->id;
            $Payment->fixed = $Fixed;
            $Payment->distance = $Distance;
            $Payment->commision = $Commision;
            $Payment->discount = $Discount;

            if($UserRequest->use_wallet == 1){

                $User = User::find(Auth::user()->id);

                $Wallet = $User->wallet_balance;

                if($Wallet != 0){

                    if($Total > $Wallet){

                        $Payment->wallet = $Wallet;
                        $Payable = $Total - $Wallet;
                        User::where('id',Auth::user()->id)->update(['wallet_balance' => 0 ]);
                        $Payment->total = abs($Payable);

                    }else{

                        $Payment->total = 0;
                        $WalletBalance = $Wallet - $Total;
                        User::where('id',Auth::user()->id)->update(['wallet_balance' => $WalletBalance]);
                        $Payment->wallet = $Total;
                    }

                }

            }else{
                $Payment->total = abs($Total);
            }

            $Payment->tax = $Tax;
            $Payment->save();

            return $Payment;

        } catch (ModelNotFoundException $e) {
            return false;
        }
    }

    /**
     * Get the trip history details of the provider
     *
     * @return \Illuminate\Http\Response
     */
    public function history_details(Request $request)
    {
        $this->validate($request, [
                'request_id' => 'required|integer|exists:user_requests,id',
            ]);

        if($request->ajax()) {
            
            $Jobs = UserRequests::where('id',$request->request_id)
                                ->where('provider_id', Auth::user()->id)
                                ->with('payment','service_type','user','rating')
                                ->get();
            if(!empty($Jobs)){
                $map_icon = asset('asset/marker.png');
                foreach ($Jobs as $key => $value) {
                    $Jobs[$key]->static_map = "https://maps.googleapis.com/maps/api/staticmap?autoscale=1&size=320x130&maptype=terrian&format=png&visual_refresh=true&markers=icon:".$map_icon."%7C".$value->s_latitude.",".$value->s_longitude."&markers=icon:".$map_icon."%7C".$value->d_latitude.",".$value->d_longitude."&path=color:0x000000|weight:3|".$value->s_latitude.",".$value->s_longitude."|".$value->d_latitude.",".$value->d_longitude."&key=".env('GOOGLE_API_KEY');
                }
            }

            return $Jobs;
        }

    }

}
