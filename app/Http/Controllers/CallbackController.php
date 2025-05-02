<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function HHCallback()
    {
        $hh = app('hh');
        $hh->baseInstall($_GET['code']);
        return "Success!";
    }
}
