import { useForm, Link } from '@inertiajs/react';

export default function Create() {
  const { data, setData, post, processing, errors } = useForm({ username: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/watchlist');
  }

  return (
    <div className="max-w-md mx-auto p-6">

      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold">Add Instagram handle</h1>

        <Link
          href="/watchlist"
          className="text-blue-600 hover:underline text-sm"
        >
          ← Back
        </Link>
      </div>

      <form onSubmit={submit} className="flex flex-col gap-4">
        <div>
          <input
            value={data.username}
            onChange={e => {
              const value = e.target.value
                .trim()
                .replace(/^@/, '')
                .toLowerCase();

              setData('username', value);
            }}
            placeholder="@cristiano"
            className="border rounded px-3 py-2 w-full"
          />

          {errors.username && (
            <p className="text-red-500 text-sm mt-1">
              {errors.username}
            </p>
          )}
        </div>

        <button
          type="submit"
          disabled={processing}
          className="bg-blue-600 text-white px-4 py-2 rounded disabled:opacity-50"
        >
          {processing ? 'Adding...' : 'Add handle'}
        </button>
      </form>
    </div>
  );
}