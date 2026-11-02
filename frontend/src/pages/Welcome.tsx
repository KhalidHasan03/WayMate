import { Link } from 'react-router-dom'
import { Logo } from '../components/Logo'
import { useAuthStore } from '../stores/authStore'

const features = [
  {
    title: 'Share the cost, not a fare',
    body: 'Riders and drivers agree on fuel, tolls and extras. No surge, no hidden commission.',
    icon: '৳',
  },
  {
    title: 'Built for real routes',
    body: 'Match along your corridor — Mirpur to Motijheel, Uttara to Gulshan, wherever you go.',
    icon: '↗',
  },
  {
    title: 'People you can trust',
    body: 'Phone verification, NID checks for providers, and a safety trail on every trip.',
    icon: '✓',
  },
]

export function Welcome() {
  const token = useAuthStore((s) => s.token)

  return (
    <main className="mx-auto flex min-h-dvh w-full max-w-6xl flex-col px-5 py-6 sm:px-8">
      <header className="flex items-center justify-between">
        <Logo withWordmark />
        <Link
          to={token ? '/app' : '/login'}
          className="rounded-full border border-ink-200 bg-white px-4 py-2 text-sm font-semibold text-ink-700 transition hover:border-brand-300 hover:text-brand-700"
        >
          {token ? 'Open app' : 'Sign in'}
        </Link>
      </header>

      <section className="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-16">
        <div className="animate-rise">
          <span className="inline-flex items-center gap-2 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-brand-800">
            <span className="h-1.5 w-1.5 rounded-full bg-brand-600" />
            Bangladesh · peer to peer
          </span>
          <h1 className="mt-5 max-w-xl text-4xl font-extrabold leading-[1.08] tracking-tight text-ink-900 sm:text-6xl">
            Your daily ride,
            <span className="block text-brand-600">split fairly.</span>
          </h1>
          <p className="mt-5 max-w-lg text-base leading-relaxed text-ink-500 sm:text-lg">
            WayMate connects riders heading the same way. Pool a seat, share a private
            car, or join an external ride — with costs agreed before anyone moves.
          </p>

          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <Link
              to={token ? '/app' : '/login'}
              className="inline-flex items-center justify-center rounded-2xl bg-brand-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700"
            >
              {token ? 'Go to dashboard' : 'Continue with phone'}
            </Link>
            <Link
              to="/login"
              className="inline-flex items-center justify-center rounded-2xl border border-ink-200 bg-white px-6 py-3.5 text-base font-semibold text-ink-700 transition hover:border-brand-300"
            >
              Offer a ride
            </Link>
          </div>

          <p className="mt-4 max-w-md text-xs leading-relaxed text-ink-400">
            WayMate is a cost-sharing platform, not a commercial transport service.
            Contributions cover actual trip costs only.
          </p>
        </div>

        <div className="animate-rise relative mx-auto w-full max-w-md">
          <div className="absolute -left-6 top-10 h-24 w-24 rounded-full bg-brand-200/60 blur-2xl" />
          <div className="absolute -right-4 bottom-8 h-32 w-32 rounded-full bg-amber-200/50 blur-2xl" />
          <div className="relative rounded-[2rem] border border-ink-200/80 bg-white p-6 shadow-2xl shadow-ink-900/10">
            <div className="flex items-center justify-between text-xs font-semibold text-ink-400">
              <span>Today · 8:10 AM</span>
              <span className="rounded-full bg-brand-50 px-2 py-1 text-brand-700">3 going</span>
            </div>

            <div className="mt-5 space-y-5">
              <div className="flex gap-3">
                <div className="flex flex-col items-center pt-1">
                  <span className="h-3 w-3 rounded-full border-[3px] border-brand-500 bg-white" />
                  <span className="my-1 h-12 w-px border-l border-dashed border-ink-200" />
                  <span className="h-3 w-3 rounded-full bg-ink-900" />
                </div>
                <div className="flex-1 space-y-6">
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-ink-400">Pickup</p>
                    <p className="font-semibold text-ink-900">Mirpur 10 Circle</p>
                  </div>
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-ink-400">Destination</p>
                    <p className="font-semibold text-ink-900">Motijheel, Dainik Bangla</p>
                  </div>
                </div>
              </div>

              <div className="rounded-2xl bg-ink-50 p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs text-ink-500">Estimated cost</p>
                    <p className="text-2xl font-bold text-ink-900">৳ 240</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-ink-500">Your share</p>
                    <p className="text-2xl font-bold text-brand-600">৳ 80</p>
                  </div>
                </div>
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-200">
                  <div className="h-full w-1/3 rounded-full bg-brand-500" />
                </div>
                <p className="mt-2 text-xs text-ink-400">Split equally between 3 riders</p>
              </div>

              <div className="flex items-center justify-between">
                <div className="flex -space-x-2">
                  {['R', 'N', 'S'].map((letter, i) => (
                    <span
                      key={letter}
                      className={`grid h-9 w-9 place-items-center rounded-full border-2 border-white text-xs font-bold text-white ${
                        ['bg-brand-500', 'bg-indigo-500', 'bg-amber-500'][i]
                      }`}
                    >
                      {letter}
                    </span>
                  ))}
                </div>
                <span className="text-xs font-medium text-ink-500">Verified riders</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="grid gap-4 pb-8 sm:grid-cols-3">
        {features.map((feature) => (
          <div key={feature.title} className="rounded-2xl border border-ink-200/70 bg-white p-5">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-lg font-bold text-brand-700">
              {feature.icon}
            </span>
            <h2 className="mt-3 text-sm font-bold text-ink-900">{feature.title}</h2>
            <p className="mt-1 text-sm leading-relaxed text-ink-500">{feature.body}</p>
          </div>
        ))}
      </section>
    </main>
  )
}
