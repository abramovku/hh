<?php

namespace App\Http\Controllers;

class CallbackController extends Controller
{
    public function HHCallback()
    {
        $hh = app('hh');
        $hh->baseInstall($_GET['code']);

        return 'Success!';
    }
}
