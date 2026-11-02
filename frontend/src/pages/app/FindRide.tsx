import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { LocationSearch } from '../../components/LocationSearch'
import { api, errorMessage } from '../../lib/api'
import { defaultDepartInput, durationLabel, formatDateTime, taka } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type { GeoPlace, RideSearchResult } from '../../types'

const inputClass =
  'w-full rounded-2xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100'
const labelClass = 'mb-1.5 block text-sm font-semibold text-ink-700'

export function FindRide() {
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const [origin, setOrigin] = useState<GeoPlace | null>(null)
  const [destination, setDestination] = useState<GeoPlace | null>(null)
  const [departAt, setDepartAt] = useState(defaultDepartInput(2))
  const [seatsNeeded, setSeatsNeeded] = useState(1)
  const [womenOnly, setWomenOnly] = useState(false)
  const [transport, setTransport] = useState('any')
  const [joinedIds, setJoinedIds] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)

  const searchMutation = useMutation({
    mutationFn: async () => {
      const { data } = await api.get<{ data: RideSearchResult[] }>('/ride-offers/search', {
        params: {
          origin_lat: origin!.lat,
          origin_lng: origin!.lng,
          dest_lat: destination!.lat,
          dest_lng: destination!.lng,
          depart_at: new Date(departAt).toISOString(),
          seats_needed: seatsNeeded,
          women_only: womenOnly,
          transport_preference: transport,
          limit: 30,
        },
      })

      return data.data
    },
    onSuccess: () => setError(null),
    onError: (err) => setError(errorMessage(err, 'Could not search rides right now.')),
  })

  const joinMutation = useMutation({
    mutationFn: async (offerId: number) => api.post(`/ride-offers/${offerId}/join`, { seats: seatsNeeded }),
    onSuccess: (_, offerId) => {
      setError(null)
      setJoinedIds((current) => [...current, offerId])
      queryClient.invalidateQueries({ queryKey: ['joins'] })
    },
    onError: (err) => setError(errorMessage(err, 'Could not request a seat.')),
  })

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)

    if (!origin || !destination) {
      setError('Pick both a pickup point and a destination.')
      return
    }

    if (origin.lat === destination.lat && origin.lng === destination.lng) {
      setError('Pickup and destination must be different.')
      return
    }

    searchMutation.mutate()
  }

  const results = searchMutation.data ?? []

  return (
    <div className="animate-rise space-y-5 pb-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Find a ride</h1>
        <p className="mt-1 text-sm text-ink-500">
          Search open rides along your route before creating a request.
        </p>
      </div>

      <form onSubmit={submit} className="space-y-4 rounded-2xl border border-ink-200 bg-white p-4">
        <LocationSearch
          label="Pickup"
          tone="origin"
          placeholder="Where are you starting from?"
          value={origin}
          onChange={setOrigin}
        />

        <div className="relative pl-1">
          <div className="absolute -top-2 left-[5px] h-3 w-px bg-ink-200" />
        </div>

        <LocationSearch
          label="Destination"
          tone="destination"
          placeholder="Where are you going?"
          value={destination}
          onChange={setDestination}
        />

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label htmlFor="find-depart" className={labelClass}>
              Leaving after
            </label>
            <input
              id="find-depart"
              type="datetime-local"
              value={departAt}
              onChange={(event) => setDepartAt(event.target.value)}
              className={inputClass}
              required
            />
          </div>
          <div>
            <label htmlFor="find-seats" className={labelClass}>
              Seats
            </label>
            <select
              id="find-seats"
              value={seatsNeeded}
              onChange={(event) => setSeatsNeeded(Number(event.target.value))}
              className={inputClass}
            >
              {[1, 2, 3, 4].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label htmlFor="find-transport" className={labelClass}>
            Ride type
          </label>
          <select
            id="find-transport"
            value={transport}
            onChange={(event) => setTransport(event.target.value)}
            className={inputClass}
          >
            <option value="any">Any ride</option>
            <option value="private">Private car</option>
            <option value="external">Bike / other</option>
          </select>
        </div>

        {user?.gender === 'female' && (
          <label className="flex items-center gap-3 text-sm font-medium text-ink-700">
            <input
              type="checkbox"
              checked={womenOnly}
              onChange={(event) => setWomenOnly(event.target.checked)}
              className="h-4 w-4 rounded border-ink-300 text-brand-600"
            />
            Women-only rides
          </label>
        )}

        <button
          type="submit"
          disabled={searchMutation.isPending}
          className="w-full rounded-2xl bg-brand-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-60"
        >
          {searchMutation.isPending ? 'Searching…' : 'Search rides'}
        </button>
      </form>

      {error && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{error}</p>
      )}

      {!searchMutation.isPending && searchMutation.isSuccess && results.length === 0 && (
        <div className="rounded-2xl border border-dashed border-ink-200 bg-white px-5 py-10 text-center">
          <h2 className="text-base font-bold text-ink-900">No open rides matched</h2>
          <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-ink-500">
            Try a slightly different time or nearby landmark. You can also post a request and let
            matching riders find you.
          </p>
          <Link
            to="/app/request"
            className="mt-4 inline-block rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white"
          >
            Request a seat instead
          </Link>
        </div>
      )}

      {results.length > 0 && (
        <section className="space-y-3">
          <h2 className="px-1 text-xs font-bold uppercase tracking-wide text-ink-400">
            {results.length} ride{results.length === 1 ? '' : 's'} found
          </h2>

          {results.map((row) => {
            const provider = row.offer.provider
            const joined = joinedIds.includes(row.offer.id)

            return (
              <article
                key={row.offer.id}
                className="rounded-2xl border border-ink-200 bg-white p-4"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="flex min-w-0 items-center gap-2.5">
                    {provider?.avatar_url ? (
                      <img
                        src={provider.avatar_url}
                        alt=""
                        className="h-10 w-10 rounded-full object-cover"
                      />
                    ) : (
                      <span className="grid h-10 w-10 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">
                        {(provider?.name ?? 'W')
                          .split(' ')
                          .map((part) => part[0])
                          .join('')
                          .slice(0, 2)
                          .toUpperCase()}
                      </span>
                    )}
                    <div className="min-w-0">
                      <p className="truncate text-sm font-bold text-ink-900">
                        {provider?.name ?? 'WayMate rider'}
                      </p>
                      <p className="text-[11px] text-ink-500">
                        ★ {(provider?.rating ?? 0).toFixed(1)} · {provider?.trips_completed ?? 0}{' '}
                        trips
                        {provider?.identity_verified ? ' · ID verified' : ''}
                      </p>
                    </div>
                  </div>
                  <span className="shrink-0 rounded-full bg-brand-600 px-2.5 py-1 text-[11px] font-bold text-white">
                    {row.match.score}% match
                  </span>
                </div>

                <div className="mt-3 space-y-1.5">
                  <div className="flex items-start gap-2">
                    <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" />
                    <p className="truncate text-sm font-semibold text-ink-900">
                      {row.offer.origin.label}
                    </p>
                  </div>
                  <div className="ml-[3px] h-2.5 w-px bg-ink-200" />
                  <div className="flex items-start gap-2">
                    <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-ink-900" />
                    <p className="truncate text-sm font-semibold text-ink-900">
                      {row.offer.destination.label}
                    </p>
                  </div>
                </div>

                <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-500">
                  <span>{formatDateTime(row.offer.depart_at)}</span>
                  <span>{durationLabel(row.offer.duration_s)}</span>
                  <span>
                    {row.offer.seats_available} of {row.offer.seats_total} seat
                    {row.offer.seats_total > 1 ? 's' : ''} free
                  </span>
                  {row.offer.women_only && <span className="text-pink-600">Women only</span>}
                </div>

                {(row.match.reasons ?? []).length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {(row.match.reasons ?? []).map((reason) => (
                      <span
                        key={reason}
                        className="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700"
                      >
                        {reason}
                      </span>
                    ))}
                  </div>
                )}

                <div className="mt-3 flex items-center justify-between rounded-xl bg-brand-50 px-3 py-2.5">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-brand-700/70">
                      Estimated share
                    </p>
                    <p className="text-sm font-bold text-brand-800">
                      {taka(row.estimated_share_poisha)}
                      <span className="text-xs font-medium text-brand-700/70"> / seat</span>
                    </p>
                  </div>
                  <p className="text-xs font-semibold text-brand-800">
                    {taka(row.estimated_total_poisha)} total
                  </p>
                </div>

                {joined ? (
                  <p className="mt-3 rounded-xl bg-ink-50 px-3 py-2.5 text-center text-xs font-semibold text-ink-600">
                    Seat request sent. The provider will respond soon.
                  </p>
                ) : (
                  <button
                    type="button"
                    onClick={() => joinMutation.mutate(row.offer.id)}
                    disabled={joinMutation.isPending}
                    className="mt-3 w-full rounded-xl bg-brand-600 px-3 py-2.5 text-xs font-bold text-white disabled:opacity-60"
                  >
                    Request {seatsNeeded} seat{seatsNeeded > 1 ? 's' : ''}
                  </button>
                )}
              </article>
            )
          })}
        </section>
      )}
    </div>
  )
}
