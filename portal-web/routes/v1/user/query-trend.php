<?php

use App\Http\Controllers\Api\V1\User\QueryTrendController;
use Illuminate\Support\Facades\Route;

// 查询趋势数据（会员首页 7 天图表）
Route::get('query-trend', [QueryTrendController::class, 'index']);