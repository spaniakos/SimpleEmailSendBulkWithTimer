<?php

namespace App\Http\Controllers;

use App\Models\EmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $totalJobs = $user->emailJobs()->count();
        $activeJobs = $user->emailJobs()->whereIn('status', ['pending', 'processing'])->count();
        
        $totalEmails = $user->emailJobs()->sum('total_recipients');
        $sentEmails = $user->emailJobs()->sum('sent_count');
        $failedEmails = $user->emailJobs()->sum('failed_count');
        
        $recentJobs = $user->emailJobs()
            ->with('smtpConfiguration')
            ->latest()
            ->take(10)
            ->get();

        return view('dashboard', compact(
            'totalJobs',
            'activeJobs',
            'totalEmails',
            'sentEmails',
            'failedEmails',
            'recentJobs'
        ));
    }
}
