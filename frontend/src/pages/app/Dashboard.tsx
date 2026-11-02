import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ShieldIcon } from '../../components/icons'
import { api } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type { Paginated, Trip } from '../../types'

export function Dashboard() {
  const user = useAuthStore((s) => s.user)
  const firstName = user?.name?.split(' ')[0] ?? 'there'

  const tripsQuery = useQuery({
    queryKey: ['trips'],
    queryFn: async () => (await api.get<Paginated<Trip>>('/trips')).data,
  })

  const upcomingTrips = (tripsQuery.data?.data ?? [])
    .filter((trip) => ['forming', 'ready', 'in_progress'].includes(trip.status))
    .sort((a, b) => {
      if (!a.depart_at) return 1
      if (!b.depart_at) return -1
      return a.depart_at.localeCompare(b.depart_at)
    })
    .slice(0, 3)

  return (
    <div className="animate-rise space-y-5">
      <section className="rounded-3xl bg-gradient-to-br from-brand-700 via-brand-600 to-emerald-500 p-5 text-white shadow-xl shadow-brand-700/20">
        <p className="text-sm font-medium text-brand-100">Assalamu alaikum, {firstName}</p>
        <h1 className="mt-1 text-2xl font-bold tracking-tight">Where are you heading today?</h1>
        <p className="mt-2 max-w-sm text-sm leading-relaxed text-brand-50/90">
          Match with riders on your route and settle the real cost before you leave.
        </p>

        <div className="mt-5 grid grid-cols-2 gap-3">
          <Link
            to="/app/request"
            className="rounded-2xl bg-white p-3.5 text-left text-ink-900 shadow-sm transition hover:-translate-y-0.5"
          >
            <span className="text-lg">🪑</span>
            <p className="mt-2 text-sm font-bold">Request a seat</p>
            <p className="mt-0.5 text-xs text-ink-500">I need a ride</p>
          </Link>
          <Link
            to="/app/offer"
            className="rounded-2xl bg-white/15 p-3.5 text-left text-white ring-1 ring-inset ring-white/25 transition hover:bg-white/20"
          >
            <span className="text-lg">🏍️</span>
            <p className="mt-2 text-sm font-bold">Offer a ride</p>
            <p className="mt-0.5 text-xs text-brand-50/80">I have space</p>
          </Link>
        </div>
      </section>

      {!user?.identity_verified && (
        <Link
          to="/app/profile"
          className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5"
        >
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700">
            <ShieldIcon />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-bold text-amber-900">Finish your verification</span>
            <span className="block text-xs text-amber-700">
              Verify your NID to offer rides and build trust.
            </span>
          </span>
          <span className="text-amber-600">›</span>
        </Link>
      )}

      <section className="rounded-2xl border border-ink-200 bg-white p-4">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-ink-900">Upcoming trips</h2>
          <Link to="/app/trips" className="text-xs font-semibold text-brand-700">
            View all
          </Link>
        </div>
        {tripsQuery.isLoading && (
          <p className="mt-4 py-6 text-center text-xs text-ink-400">Loading your trips…</p>
        )}

        {!tripsQuery.isLoading && upcomingTrips.length === 0 && (
          <div className="mt-4 rounded-xl border border-dashed border-ink-200 px-4 py-8 text-center">
            <p className="text-sm font-semibold text-ink-700">No trips yet</p>
            <p className="mx-auto mt-1 max-w-xs text-xs leading-relaxed text-ink-400">
              Request a seat or offer a ride. Once a match is accepted, your trip appears here.
            </p>
          </div>
        )}

        {upcomingTrips.length > 0 && (
          <div className="mt-3 space-y-2">
            {upcomingTrips.map((trip) => (
              <Link
                key={trip.id}
                to="/app/trips"
                className="block rounded-xl border border-ink-200 px-3 py-2.5 transition hover:bg-ink-50"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-ink-900">
                      {trip.origin?.label ?? 'Pickup pending'} →{' '}
                      {trip.destination?.label ?? 'Destination pending'}
                    </p>
                    <p className="mt-0.5 text-xs text-ink-500">
                      {trip.depart_at ? formatDateTime(trip.depart_at) : 'Departure pending'}
                    </p>
                  </div>
                  <span className="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold capitalize text-brand-700">
                    {trip.status.replace('_', ' ')}
                  </span>
                </div>
              </Link>
            ))}
          </div>
        )}
      </section>

      <section className="grid grid-cols-3 gap-2">
        {[
          { value: user?.rating.trips_completed ?? 0, label: 'Trips' },
          { value: user?.rating.average ? user.rating.average.toFixed(1) : '—', label: 'Rating' },
          { value: user?.rating.no_shows ?? 0, label: 'No-shows' },
        ].map((stat) => (
          <div key={stat.label} className="rounded-2xl border border-ink-200 bg-white px-3 py-3 text-center">
            <p className="text-lg font-bold text-ink-900">{stat.value}</p>
            <p className="text-[11px] font-medium uppercase tracking-wide text-ink-400">{stat.label}</p>
          </div>
        ))}
      </section>

      <section className="rounded-2xl bg-ink-900 p-4 text-white">
        <h2 className="text-sm font-bold">Cost-sharing, not a taxi</h2>
        <p className="mt-1 text-xs leading-relaxed text-ink-200">
          Everyone pays a fair share of fuel, tolls and extras. WayMate never takes a
          commission on your trip and does not process payments — settle in cash.
        </p>
      </section>
    </div>
  )
}
