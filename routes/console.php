<?php

use App\Console\Commands\CancelOverduePurchaserWorkCommand;
use App\Console\Commands\RunAutoLoadAllCommand;
use App\Console\Commands\SeedDailyPriceMatrixNextDayCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SeedDailyPriceMatrixNextDayCommand::class)
    ->dailyAt('00:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

Schedule::command(RunAutoLoadAllCommand::class)
    ->everyMinute()
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

Schedule::command(CancelOverduePurchaserWorkCommand::class)
    ->everyFiveMinutes()
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->onFailure(fn () => Log::error('purchaser.cleanup.failed'));
