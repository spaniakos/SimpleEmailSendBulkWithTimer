<?php

namespace App\Http\Controllers;

use App\Models\EmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
        
        // Queue status
        $queueJobs = DB::table('jobs')->where('queue', 'default')->count();
        $delayedJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('available_at', '>', now()->timestamp)
            ->count();
        $readyJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('available_at', '<=', now()->timestamp)
            ->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        // Check for recipients that might be stuck (pending but no jobs in queue)
        $pendingRecipients = $user->emailJobs()
            ->withCount(['recipients as pending_recipients_count' => function($query) {
                $query->where('status', 'pending');
            }])
            ->get()
            ->sum('pending_recipients_count');

        return view('dashboard', compact(
            'totalJobs',
            'activeJobs',
            'totalEmails',
            'sentEmails',
            'failedEmails',
            'recentJobs',
            'queueJobs',
            'delayedJobs',
            'readyJobs',
            'failedJobs',
            'pendingRecipients'
        ));
    }
}
