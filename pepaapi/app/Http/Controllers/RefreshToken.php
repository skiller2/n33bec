<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;


use Illuminate\Http\Response;
use JWTAuth;

/**
 * Description of RefreshToken
 *
 * @author fpl
 */
class RefreshToken extends Controller {
    
    public function refresh()
    {
        return response(['ok'=> __("Refresh ok")], Response::HTTP_OK)->header('Access-Control-Expose-Headers', 'Authorization');
    }
}
