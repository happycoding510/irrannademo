@extends('user.layout.base')

@section('title', 'Ride Confirmation ')

@section('content')
<div class="col-md-9">
    <div class="dash-content">
        <div class="row no-margin">
            <div class="col-md-12">
                <h4 class="page-title">@lang('messages.ride_now')</h4>
            </div>
        </div>

        <div class="row no-margin">
            <div class="col-md-6">
                <form action="user-waiting-provider.html">
                    <dl class="dl-horizontal left-right">
                        <dt>Type</dt>
                        <dd>{{$service->name}}</dd>
                        <dt>Total Distance</dt>
                        <dd>{{$fare->distance}} Kms</dd>
                        <dt>ETA</dt>
                        <dd>{{$fare->time}}</dd>
                        <dt>Estimate Amount</dt>
                        <dd>{{$fare->estimated_fare}}</dd>
                    </dl>

                    <input type="hidden" name="s_address" name="{{Request::get('s_address')}}">
                    <input type="hidden" name="d_address" name="{{Request::get('d_address')}}">
                    <input type="hidden" name="s_latitude" name="{{Request::get('s_latitude')}}">
                    <input type="hidden" name="s_longitude" name="{{Request::get('s_longitude')}}">
                    <input type="hidden" name="d_latitude" name="{{Request::get('d_latitude')}}">
                    <input type="hidden" name="d_longitude" name="{{Request::get('d_longitude')}}">
                    <input type="hidden" name="service_type" name="{{Request::get('service_type')}}">

                    <select class="form-control" name="payment_mode" id="payment_mode" onchange="card(this.value);">
                      <option value="CASH">CASH</option>
                      @if($cards->count() > 0)
                        <option value="CARD">CARD</option>
                      @endif
                    </select>

                    <select class="form-control" name="card_id" style="display: none;" id="card_id">
                      <option value="">Select Card</option>
                      @foreach($cards as $card)
                        <option value="{{$card->card_id}}">{{$card->brand}} ***{{$card->last_four}}</option>
                      @endforeach
                    </select>

                    <button type="submit" class="full-primary-btn fare-btn">@lang('messages.ride_now')</button>

                </form>
            </div>

            <div class="col-md-6">
                <div class="user-request-map">
                    <?php 
                    $map_icon = asset('asset/marker.png');
                    $static_map = "https://maps.googleapis.com/maps/api/staticmap?autoscale=1&size=600x450&maptype=roadmap&format=png&visual_refresh=true&markers=icon:".$map_icon."%7C".$request->s_latitude.",".$request->s_longitude."&markers=icon:".$map_icon."%7C".$request->d_latitude.",".$request->d_longitude."&path=color:0x191919|weight:8|".$request->s_latitude.",".$request->s_longitude."|".$request->d_latitude.",".$request->d_longitude."&key=".env('GOOGLE_API_KEY'); ?>
                    <div class="map-static" style="background-image: url({{$static_map}});">
                    </div>
                    <div class="from-to row no-margin">
                        <div class="from">
                            <h5>FROM</h5>
                            <p>{{$request->s_address}}</p>
                        </div>
                        <div class="to">
                            <h5>TO</h5>
                            <p>{{$request->d_address}}</p>
                        </div>
                    </div>
                </div> 
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript">
        function card(value){
            if(value == 'CARD'){
                $('#card_id').fadeIn(300);
            }else{
                $('#card_id').fadeOut(300);
            }
        }
    </script>
@endsection