import { useForm } from '@inertiajs/react';

export default function Login() {
  const { data, setData, post, errors, processing } = useForm({
    email: '',
    password: '',
  });

  function submit(e: any) {
    e.preventDefault();
    post('/login');
  }

  return (
    <div className="flex items-center justify-center min-h-screen">
      <form onSubmit={submit} className="p-6 bg-white shadow rounded w-96">
        <h1 className="text-xl mb-4">Login</h1>

        {errors.auth && (<div className="mb-4 text-red-600 text-sm">{errors.auth}</div>)}

        <input
          type="email"
          placeholder="Email"
          className="border p-2 w-full mb-2"
          value={data.email}
          onChange={(e) => setData('email', e.target.value)}
        />
        {errors.email && <div className="text-red-500 text-sm mb-2">{errors.email}</div>}


        <input
          type="password"
          placeholder="Password"
          className="border p-2 w-full mb-2"
          value={data.password}
          onChange={(e) => setData('password', e.target.value)}
        />

        {errors.password && (
            <div className="text-red-500 text-sm mb-2 ">
                {errors.password}
            </div>
            )}

        <button
          disabled={processing}
          className="bg-blue-600 text-white px-4 py-2 w-full"
        >
          Login
        </button>
        
      </form>
    </div>
  );
}