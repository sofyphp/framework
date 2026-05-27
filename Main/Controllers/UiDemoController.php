<?php

declare(strict_types=1);

namespace Main\Controllers;

use Sofy\Http\Response;

class UiDemoController extends Controller
{
    public function index(): Response
    {
        return $this->view('ui-demo');
    }
}
