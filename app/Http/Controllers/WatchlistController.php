<?php
namespace App\Http\Controllers;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WatchlistController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->q
        ? strtolower(trim(ltrim($request->q, '@')))
        : null;

        $profiles = Profile::query()
            ->select([
                'id',
                'username',
                'status',
                // 'profile_picture_url',
                'bio',
                'followers_count',
                'following_count',
                'post_count',
                'last_refreshed_at',
                'created_at'
            ])
            ->when($search, function ($q) use ($search) {
                $q->where('username', 'ilike', "%{$search}%");
            })
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status))
            ->with('latestSnapshot')  
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Watchlist/Index', [
            'profiles' => $profiles,
            'filters'  => $request->only(['q', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Watchlist/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9._@]+$/'
            ],
        ]);

        // normalize safely on backend (source of truth)
        $username = strtolower(trim(ltrim($validated['username'], '@')));

        $profile = Profile::firstOrCreate(
            ['username' => $username],
            ['status' => 'pending']
        );

        FetchProfileJob::dispatch($profile->id);

        return redirect()->route('watchlist.index');
    }

    public function show(Profile $profile)
    {
        $snapshots = $profile->snapshots()->get();

        return Inertia::render('Watchlist/Show', [
            'profile'   => $profile,
            'snapshots' => $snapshots,
        ]);
    }

    public function refetch(Profile $profile)
    {
        $profile->update(['status' => 'pending']);
        FetchProfileJob::dispatch($profile->id);

        return back();
    }
}