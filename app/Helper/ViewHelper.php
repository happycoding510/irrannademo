<?php
use App\PromocodeUsage; 

function currency($value = '')
{
	if($value == ""){
		return Setting::get('currency');
	}else{
		return Setting::get('currency').$value;
	}
}

function img($img){
	if($img == ""){
		return asset('main/avatar.jpg');
	}else{
		return asset('storage/'.$img);
	}
}

function image($img){
	if($img == ""){
		return asset('main/avatar.jpg');
	}else{
		return asset($img);
	}
}

function promo_used_count($promo_id)
{
	return PromocodeUsage::where('status','USED')->where('promocode_id',$promo_id)->count();
}

function curl($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    $return = curl_exec($ch);
    curl_close ($ch);
    return $return;
}
