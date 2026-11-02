import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { LocationSearch } from '../../components/LocationSearch'
import { api, errorMessage } from '../../lib/api'
import { defaultDepartInput } from '../../lib/format'
import { useAuthStore } from '../../stores/authStore'
import type { GeoPlace } from '../../types'

const inputClass =
  'w-full rounded-2xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100'
const labelClass = 'mb-1.5 block text-sm font-semibold text-ink-700'

export function RequestRide() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)

  const [origin, setOrigin] = useState<GeoPlace | null>(null)
  const [destination, setDestination] = useState<GeoPlace | null>(null)
  const [departAt, setDepartAt] = useState(defaultDepartInput())
  const [seatsNeeded, setSeatsNeeded] = useState(1)
  const [poolMax, setPoolMax] = useState(1)
  const [accepting, setAccepting] = useState(true)
  const [budget, setBudget] = useState('')
  const [womenOnly, setWomenOnly] = useState(false)
  const [transport, setTransport] = useState('any')
  const [notes, setNotes] = useState('')
  const [error, setError] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        origin_lat: origin!.lat,
        origin_lng: origin!.lng,
        origin_label: origin!.label,
        dest_lat: destination!.lat,
        dest_lng: destination!.lng,
        dest_label: destination!.label,
        depart_at: new Date(departAt).toISOString(),
        seats_needed: seatsNeeded,
        pool_max_seats: accepting ? poolMax : seatsNeeded,
        accepting_copassengers: accepting,
        ...(budget.trim() === ''
          ? {}
          : { budget_per_seat_poisha: Math.round(Number(budget) * 100) }),
        women_only: womenOnly,
        transport_preference: transport,
        notes: notes.trim() || null,
      }

      return api.post('/ride-requests', payload)
    },
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['ride-requests'] })
      const id = (response.data as { data: { id: number } }).data.id
      navigate(`/app/matches/request/${id}`)
    },
    onError: (err) => setError(errorMessage(err, 'Could not create the request.')),
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

    mutation.mutate()
  }

  return (
    <form onSubmit={submit} className="animate-rise space-y-5 pb-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">Request a seat</h1>
        <p className="mt-1 text-sm text-ink-500">
          Tell us your route. We will match you with riders already heading that way.
        </p>
      </div>

      <section className="space-y-4 rounded-2xl border border-ink-200 bg-white p-4">
        <LocationSearch
          label="Pickup"
          tone="origin"
          placeholder="Where should they pick you up?"
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
      </section>

      <section className="space-y-4 rounded-2xl border border-ink-200 bg-white p-4">
        <div>
          <label htmlFor="depart" className={labelClass}>
            Leaving at
          </label>
          <input
            id="depart"
            type="datetime-local"
            value={departAt}
            onChange={(event) => setDepartAt(event.target.value)}
            className={inputClass}
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label htmlFor="seats" className={labelClass}>
              Seats you need
            </label>
            <select
              id="seats"
              value={seatsNeeded}
              onChange={(event) => {
                const next = Number(event.target.value)
                setSeatsNeeded(next)
                setPoolMax((current) => Math.max(current, next))
              }}
              className={inputClass}
            >
              {[1, 2, 3, 4].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="budget" className={labelClass}>
              Budget / seat (৳)
            </label>
            <input
              id="budget"
              type="number"
              min="0"
              step="10"
              inputMode="numeric"
              value={budget}
              onChange={(event) => setBudget(event.target.value)}
              placeholder="Optional"
              className={inputClass}
            />
          </div>
        </div>

        <label className="flex items-start gap-3 rounded-xl bg-ink-50 p-3">
          <input
            type="checkbox"
            checked={accepting}
            onChange={(event) => setAccepting(event.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600"
          />
          <span>
            <span className="block text-sm font-semibold text-ink-800">
              Share the ride with other passengers
            </span>
            <span className="block text-xs text-ink-500">
              We can pool you with others going the same way to lower everyone's share.
            </span>
          </span>
        </label>

        {accepting && (
          <div>
            <label htmlFor="pool" className={labelClass}>
              Maximum seats in the pool
            </label>
            <select
              id="pool"
              value={poolMax}
              onChange={(event) => setPoolMax(Number(event.target.value))}
              className={inputClass}
            >
              {[1, 2, 3, 4]
                .filter((n) => n >= seatsNeeded)
                .map((n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
            </select>
          </div>
        )}

        <div>
          <label htmlFor="transport" className={labelClass}>
            Preferred ride
          </label>
          <select
            id="transport"
            value={transport}
            onChange={(event) => setTransport(event.target.value)}
            className={inputClass}
          >
            <option value="any">Any</option>
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
            Women-only ride
          </label>
        )}

        <div>
          <label htmlFor="notes" className={labelClass}>
            Notes for the provider
          </label>
          <textarea
            id="notes"
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            rows={3}
            maxLength={500}
            placeholder="Landmark, luggage, flexibility…"
            className={inputClass}
          />
        </div>
      </section>

      {error && (
        <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{error}</p>
      )}

      <button
        type="submit"
        disabled={mutation.isPending}
        className="w-full rounded-2xl bg-brand-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 disabled:opacity-60"
      >
        {mutation.isPending ? 'Finding your match…' : 'Request seat'}
      </button>

      <p className="text-center text-[11px] leading-relaxed text-ink-400">
        By requesting, you agree to share the actual trip cost. WayMate does not process
        payments — settle in cash.
      </p>
    </form>
  )
}
