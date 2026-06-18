import { Link, router } from '@inertiajs/react';

type Profile = {
    id: number;
    username: string;
    status: 'pending' | 'fetching' | 'fetched' | 'failed';

    // profile_picture_url: string | null;
    bio: string | null;

    followers_count: number | null;
    following_count: number | null;
    post_count: number | null;

    last_refreshed_at: string | null;
    created_at: string;
};

type Props = {
    profiles: { data: Profile[]; links: { url: string | null; label: string }[] };
    filters: { q?: string; status?: string };
};

const statusColors: Record<string, string> = {
    pending:  'bg-yellow-100 text-yellow-800',
    fetching: 'bg-blue-100 text-blue-800',
    fetched:  'bg-green-100 text-green-800',
    failed:   'bg-red-100 text-red-800',
};
function clearFilters() {
    router.get('/watchlist', {}, {
        preserveState: false,
        replace: true,
    });
}
export default function Index({ profiles, filters }: Props) {
    function search(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        router.get('/watchlist', Object.fromEntries(form), { preserveState: true });
    }

    return (
        <div className="max-w-6xl mx-auto p-6">
            <div className="flex justify-between mb-4">
                <h1 className="text-2xl font-semibold">Watchlist</h1>

                <div className="flex gap-2">
                    <Link
                        href="/watchlist/create"
                        className="bg-blue-600 text-white px-4 py-2 rounded"
                    >
                        + Add handle
                    </Link>

                    <button
                        onClick={() => router.post('/logout')}
                        className="bg-red-600 text-white px-4 py-2 rounded"
                    >
                        Logout
                    </button>
                </div>
            </div>

            {/* Search + filter */}
            <form onSubmit={search} className="flex gap-2 mb-4">
                <input name="q" defaultValue={filters.q} placeholder="Search username..." className="border rounded px-2 py-2 flex-1" />
                <select name="status" defaultValue={filters.status} className="border rounded px-3 py-2">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="fetching">Fetching</option>
                    <option value="fetched">Fetched</option>
                    <option value="failed">Failed</option>
                </select>
                <div className="flex gap-2">
                    <button
                        type="submit"
                        className="bg-gray-800 text-white px-4 py-2 rounded"
                    >
                        Apply filters
                    </button>

                    <button
                        type="button"
                        onClick={clearFilters}
                        className="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300"
                    >
                        Clear
                    </button>
                </div>
            </form>

            {/* Table */}
            <table className="w-full border-collapse">
                <thead>
                    <tr className="bg-gray-50 text-left text-sm text-gray-600">
                        <th className="p-3 border-b">Profile</th>
                        <th className="p-3 border-b">Bio</th>
                        <th className="p-3 border-b">Followers</th>
                        <th className="p-3 border-b">Following</th>
                        <th className="p-3 border-b">Posts</th>
                        <th className="p-3 border-b">Status</th>
                        <th className="p-3 border-b">Last checked</th>
                    </tr>
                </thead>
                <tbody>
                    {profiles.data.map(p => (
                    <tr key={p.id} className="hover:bg-gray-50">

                        <td className="p-3 border-b">
                            <Link href={`/watchlist/${p.id}`} className="text-blue-600">
                                @{p.username}
                            </Link>
                        </td>

                        <td className="p-3 border-b text-sm text-gray-500">
                            {p.bio ?? '—'}
                        </td>

                        <td className="p-3 border-b">
                            {p.followers_count?.toLocaleString() ?? '—'}
                        </td>

                        <td className="p-3 border-b">
                            {p.following_count?.toLocaleString() ?? '—'}
                        </td>

                        <td className="p-3 border-b">
                            {p.post_count?.toLocaleString() ?? '—'}
                        </td>

                        <td className="p-3 border-b">
                            <span className={`text-xs px-2 py-1 rounded-full ${statusColors[p.status]}`}>
                                {p.status}
                            </span>
                        </td>

                       <td className="p-3 border-b text-sm text-gray-500">
                            {p.last_refreshed_at
                                ? new Date(p.last_refreshed_at).toLocaleString('en-IN', {
                                    day: '2-digit',
                                    month: 'short',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })
                                : '—'}
                        </td>
                    </tr>
                    ))}
                </tbody>
            </table>

            {/* Pagination */}
            <div className="flex gap-1 mt-4">
                {profiles.links.map((link, i) => (
                <Link key={i} href={link.url ?? '#'}
                    className={`px-3 py-1 border rounded text-sm
                    ${!link.url ? 'text-gray-400' : 'hover:bg-gray-100'}`}
                    dangerouslySetInnerHTML={{ __html: link.label }} />
                ))}
            </div>
        </div>
    );
}