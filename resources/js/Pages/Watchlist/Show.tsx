import { router, Link } from '@inertiajs/react';

type Snapshot = {
  id: number;
  followers_count: number;
  captured_at: string;
  following_count: number;
  post_count: number;
};

type Profile = {
  id: number;
  username: string;
  status: string;
  followers_count: number | null;
  following_count: number | null;
  post_count: number | null;
  bio: string | null;
};

type Props = { profile: Profile; snapshots: Snapshot[] };

function formatDate(date: string) {
  return new Date(date).toLocaleString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function Show({ profile, snapshots }: Props) {

  function refetch() {
    router.post(`/watchlist/${profile.id}/refetch`);
  }

  function goBack() {
    router.get('/watchlist');
  }

  const withDelta = snapshots.map((s, i) => {
    const prev = snapshots[i + 1];
    const delta = prev ? s.followers_count - prev.followers_count : null;
    return { ...s, delta };
  });

  return (
    <div className="max-w-4xl mx-auto p-6">

      {/* Header */}
      <div className="flex justify-between items-start mb-6">

        <div>
          <div className="flex items-center gap-3">
            <button
              onClick={goBack}
              className="text-sm text-gray-600 hover:underline"
            >
              ← Back
            </button>

            <h1 className="text-2xl font-semibold">
              @{profile.username}
            </h1>
          </div>

          <p className="text-gray-500 text-sm mt-1">
            {profile.bio ?? 'No bio available'}
          </p>
        </div>

        <button
          onClick={refetch}
          className="bg-blue-600 text-white px-4 py-2 rounded"
        >
          Re-fetch
        </button>
      </div>

      <div className="grid grid-cols-3 gap-4 mb-8">

        <div className="border rounded p-4 text-center">
          <div className="text-2xl font-bold">
            {profile.followers_count?.toLocaleString() ?? '—'}
          </div>
          <div className="text-sm text-gray-500">Followers</div>
        </div>

        <div className="border rounded p-4 text-center">
          <div className="text-2xl font-bold">
            {profile.following_count?.toLocaleString() ?? '—'}
          </div>
          <div className="text-sm text-gray-500">Following</div>
        </div>

        <div className="border rounded p-4 text-center">
          <div className="text-2xl font-bold">
            {profile.post_count?.toLocaleString() ?? '—'}
          </div>
          <div className="text-sm text-gray-500">Posts</div>
        </div>

      </div>

      {/* History */}
      <h2 className="text-lg font-medium mb-3">History</h2>

      <table className="w-full border-collapse">
        <thead>
            <tr className="bg-gray-50 text-sm text-gray-600 text-left">
            <th className="p-3 border-b">Date</th>
            <th className="p-3 border-b">Followers</th>
            <th className="p-3 border-b">Following</th>
            <th className="p-3 border-b">Posts</th>
            </tr>
        </thead>

        <tbody>
            {withDelta.map(s => (
            <tr key={s.id}>

                {/* Date */}
                <td className="p-3 border-b text-sm text-gray-600">
                {formatDate(s.captured_at)}
                </td>

                <td className="p-3 border-b">
                <div className="flex flex-col">
                    <span>
                    {s.followers_count.toLocaleString()}
                    </span>

                    {s.delta !== null && (
                    <span className={`text-xs ${
                        s.delta >= 0 ? 'text-green-600' : 'text-red-500'
                    }`}>
                        {s.delta >= 0 ? '+' : ''}
                        {s.delta.toLocaleString()}
                    </span>
                    )}
                </div>
                </td>

                {/* Following */}
                <td className="p-3 border-b">
                {s.following_count?.toLocaleString() ?? '—'}
                </td>

                {/* Posts */}
                <td className="p-3 border-b">
                {s.post_count?.toLocaleString() ?? '—'}
                </td>

            </tr>
            ))}
        </tbody>
    </table>
    </div>
  );
}